<?php
// Admin/api/delete_user.php
require_once '../../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userRoles = $_SESSION['roles'] ?? [];
if (!is_array($userRoles)) {
    $userRoles = json_decode($userRoles, true) ?: [];
}
if (!isset($_SESSION['user_id']) || !in_array('admin', $userRoles, true)) {
    echo json_encode(['success' => false, 'error' => 'Μη εξουσιοδοτημένη πρόσβαση.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = intval($data['user_id'] ?? 0);

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Μη έγκυρο ID χρήστη.']);
    exit;
}

// Prevent admin from deleting themselves
if ($user_id === $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'error' => 'Δεν μπορείτε να διαγράψετε τον εαυτό σας.']);
    exit;
}

$stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
if ($stmt->execute([$user_id])) {
    echo json_encode(['success' => true, 'message' => 'Ο χρήστης διαγράφηκε.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Αποτυχία διαγραφής.']);
}