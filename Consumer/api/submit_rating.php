<?php
// Consumer/api/submit_rating.php
require_once '../../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userRoles = $_SESSION['roles'] ?? [];
if (!is_array($userRoles)) {
    $userRoles = json_decode($userRoles, true) ?: [];
}
if (!isset($_SESSION['user_id']) || !in_array('consumer', $userRoles, true)) {
    echo json_encode(['success' => false, 'error' => 'Μη εξουσιοδοτημένη πρόσβαση.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$offer_id = intval($data['offer_id'] ?? 0);
$rating = intval($data['rating'] ?? 5);
$comment = trim($data['comment'] ?? '');
$consumer_id = $_SESSION['user_id'];

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'error' => 'Η βαθμολογία πρέπει να είναι από 1 έως 5.']);
    exit;
}

// 1. Έλεγχος αν υπάρχει παραδοτέο αίτημα (status = 'delivered')
$chk = $pdo->prepare("SELECT r.id FROM requests r WHERE r.offer_id = ? AND r.consumer_id = ? AND r.status = 'delivered'");
$chk->execute([$offer_id, $consumer_id]);
if (!$chk->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Μπορείτε να αξιολογήσετε μόνο γεύματα που έχετε παραλάβει.']);
    exit;
}

// 2. Έλεγχος αποτροπής διπλής αξιολόγησης
$alreadyRated = $pdo->prepare("SELECT id FROM ratings WHERE offer_id = ? AND consumer_id = ?");
$alreadyRated->execute([$offer_id, $consumer_id]);
if ($alreadyRated->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Έχετε ήδη αξιολογήσει αυτό το γεύμα.']);
    exit;
}

// 3. Εύρεση του cook_id
$cookStmt = $pdo->prepare("SELECT cook_id FROM offers WHERE id = ?");
$cookStmt->execute([$offer_id]);
$offer = $cookStmt->fetch();
if (!$offer) {
    echo json_encode(['success' => false, 'error' => 'Δεν βρέθηκε η αγγελία.']);
    exit;
}
$cook_id = $offer['cook_id'];

$pdo->beginTransaction();

try {
    // Καταχώρηση αξιολόγησης
    $stmt = $pdo->prepare("INSERT INTO ratings (offer_id, consumer_id, rating, comment) VALUES (?, ?, ?, ?)");
    $stmt->execute([$offer_id, $consumer_id, $rating, $comment]);
    
    // Πόντοι στον μάγειρα: 1-3 αστέρια = +1, 4-5 αστέρια = +2
    $points = ($rating >= 4) ? 2 : 1;
    $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$points, $cook_id]);
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "Η αξιολόγηση καταχωρήθηκε! +{$points} πόντοι στον μάγειρα."]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Σφάλμα καταχώρησης αξιολόγησης.']);
}
