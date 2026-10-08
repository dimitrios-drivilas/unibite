<?php
// Consumer/api/make_request.php
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
$consumer_id = $_SESSION['user_id'];

// Security check: prevent cook from requesting their own offer
$offerOwner = $pdo->prepare("SELECT cook_id FROM offers WHERE id = ?");
$offerOwner->execute([$offer_id]);
$offerData = $offerOwner->fetch();
if ($offerData && $offerData['cook_id'] == $consumer_id) {
    echo json_encode(['success' => false, 'error' => 'Δεν μπορείτε να δεσμεύσετε τη δική σας αγγελία.']);
    exit;
}

// Check if consumer has at least 1 point
$pts = $pdo->prepare("SELECT points FROM users WHERE id = ?");
$pts->execute([$consumer_id]);
$user = $pts->fetch();
if (!$user || $user['points'] < 1) {
    echo json_encode(['success' => false, 'error' => 'Δεν έχετε αρκετά πόντα για να κάνετε αίτημα (απαραίτητα: 1 πόντους).']);
    exit;
}

$chk = $pdo->prepare("SELECT id FROM requests WHERE offer_id = ? AND consumer_id = ?");
$chk->execute([$offer_id, $consumer_id]);
if ($chk->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Έχετε ήδη κάνει αίτημα για αυτή τη μερίδα.']);
    exit;
}

// Check offer has portions and is not expired
$offerChk = $pdo->prepare("SELECT portions FROM offers WHERE id = ? AND status = 'active' AND expires_at > NOW()");
$offerChk->execute([$offer_id]);
$offer = $offerChk->fetch();
if (!$offer || $offer['portions'] <= 0) {
    echo json_encode(['success' => false, 'error' => 'Δεν υπάρχουν διαθέσιμες μερίδες για αυτή την αγγελία.']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO requests (offer_id, consumer_id, status) VALUES (?, ?, 'pending')");
if ($stmt->execute([$offer_id, $consumer_id])) {
    echo json_encode(['success' => true, 'message' => 'Το αίτημα στάλθηκε επιτυχώς στον μάγειρα!']);
} else {
    echo json_encode(['success' => false, 'error' => 'Αποτυχία αποστολής αίτησης.']);
}