<?php
// Cook/api/delete_offer.php
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

$data = json_decode(file_get_contents('php://input'), true);
$offer_id = intval($data['offer_id'] ?? 0);
$cook_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("DELETE FROM offers WHERE id = ? AND cook_id = ?");
if ($stmt->execute([$offer_id, $cook_id])) {
    echo json_encode(['success' => true, 'message' => 'Η αγγελία διαγράφηκε!']);
} else {
    echo json_encode(['success' => false, 'error' => 'Αποτυχία διαγραφής.']);
}