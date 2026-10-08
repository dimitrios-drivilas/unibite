<?php
// Cook/api/edit_offer.php
require_once '../../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userRoles = $_SESSION['roles'] ?? [];
if (!is_array($userRoles)) {
    $userRoles = json_decode($userRoles, true) ?: [];
}
if (!isset($_SESSION['user_id']) || !in_array('cook', $userRoles, true)) {
    echo json_encode(['success' => false, 'error' => 'Μη εξουσιοδοτημένη πρόσβαση.']);
    exit;
}

// Configuration
$uploadDir = __DIR__ . '/../uploads/';
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
$maxFileSize = 2 * 1024 * 1024; // 2MB

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Form data
$offer_id = intval($_POST['offer_id'] ?? 0);
$cook_id = $_SESSION['user_id'];
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$allergens = trim($_POST['allergens'] ?? '');
$portions = intval($_POST['portions'] ?? 0);
$pickup_location = trim($_POST['pickup_location'] ?? '');
$pickup_time = $_POST['pickup_time'] ?? '';
$status = $_POST['status'] ?? 'active';
$latitude = floatval($_POST['latitude'] ?? 38.2942);
$longitude = floatval($_POST['longitude'] ?? 21.7887);

// Validation
if ($offer_id <= 0 || empty($title) || $portions <= 0 || empty($pickup_location) || empty($pickup_time)) {
    echo json_encode(['success' => false, 'error' => 'Συμπληρώστε όλα τα απαραίτητα πεδία σωστά.']);
    exit;
}

$pickup_timestamp = strtotime($pickup_time);
$now_timestamp = time();
$max_timestamp = strtotime('+48 hours');
if (!$pickup_timestamp || $pickup_timestamp < ($now_timestamp - 300) || $pickup_timestamp > $max_timestamp) {
    echo json_encode([
        'success' => false,
        'error' => 'Η ώρα παραλαβής πρέπει να είναι μελλοντική και εντός των επόμενων 48 ωρών.'
    ]);
    exit;
}

// Handle file upload (optional - keep existing if not provided)
$photoPath = null;
$updatePhoto = false;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['photo'];

    if ($file['size'] > $maxFileSize) {
        echo json_encode(['success' => false, 'error' => 'Το αρχείο υπερβαίνει το μέγιστο επιτρεπτό μέγεθος (2MB).']);
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedTypes, true)) {
        echo json_encode(['success' => false, 'error' => 'Μη επιτρεπτός τύπος αρχείου. Επιτρεπόμενοι: JPG, PNG, WebP.']);
        exit;
    }

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'jpg'
    };
    $filename = sprintf('%d_%d_%s.%s', $cook_id, time(), bin2hex(random_bytes(8)), $ext);
    $destPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        echo json_encode(['success' => false, 'error' => 'Αποτυχία αποθήκευσης αρχείου.']);
        exit;
    }

    $photoPath = 'Cook/uploads/' . $filename;
    $updatePhoto = true;
}

// Get current photo path for cleanup if needed
$currentPhotoStmt = $pdo->prepare("SELECT photo FROM offers WHERE id = ? AND cook_id = ?");
$currentPhotoStmt->execute([$offer_id, $cook_id]);
$currentOffer = $currentPhotoStmt->fetch();
$oldPhotoPath = $currentOffer['photo'] ?? null;

// Build UPDATE query
if ($updatePhoto) {
    $stmt = $pdo->prepare("UPDATE offers SET title = ?, description = ?, photo = ?, allergens = ?, portions = ?, pickup_location = ?, pickup_time = ?, status = ?, latitude = ?, longitude = ? WHERE id = ? AND cook_id = ?");
    $params = [$title, $description, $photoPath, $allergens, $portions, $pickup_location, $pickup_time, $status, $latitude, $longitude, $offer_id, $cook_id];
} else {
    $stmt = $pdo->prepare("UPDATE offers SET title = ?, description = ?, allergens = ?, portions = ?, pickup_location = ?, pickup_time = ?, status = ?, latitude = ?, longitude = ? WHERE id = ? AND cook_id = ?");
    $params = [$title, $description, $allergens, $portions, $pickup_location, $pickup_time, $status, $latitude, $longitude, $offer_id, $cook_id];
}

$success = $stmt->execute($params);

if ($success) {
    // Delete old photo file if replaced
    if ($updatePhoto && $oldPhotoPath && file_exists(__DIR__ . '/../' . $oldPhotoPath)) {
        @unlink(__DIR__ . '/../' . $oldPhotoPath);
    }
    echo json_encode(['success' => true, 'message' => 'Η αγγελία ενημερώθηκε επιτυχώς!']);
} else {
    // Cleanup on DB failure
    if ($updatePhoto && file_exists($uploadDir . $filename)) {
        @unlink($uploadDir . $filename);
    }
    echo json_encode(['success' => false, 'error' => 'Αποτυχία ενημέρωσης αγγελίας.']);
}