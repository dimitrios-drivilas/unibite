<?php
// Consumer/api/cancel_request.php
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
$request_id = intval($data['request_id'] ?? 0);
$consumer_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT r.*, o.status as offer_status FROM requests r JOIN offers o ON r.offer_id = o.id WHERE r.id = ? AND r.consumer_id = ?");
$stmt->execute([$request_id, $consumer_id]);
$request = $stmt->fetch();

if (!$request) {
    echo json_encode(['success' => false, 'error' => 'Δεν βρέθηκε το αίτημα.']);
    exit;
}

if ($request['status'] !== 'pending') {
    echo json_encode(['success' => false, 'error' => 'Μπορείτε να ακυρώσετε μόνο αιτήματα σε αναμονή.']);
    exit;
}

$upd = $pdo->prepare("UPDATE requests SET status = 'cancelled' WHERE id = ?");
if ($upd->execute([$request_id])) {
    echo json_encode(['success' => true, 'message' => 'Το αίτημα ακυρώθηκε.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Αποτυχία ακύρωσης.']);
}