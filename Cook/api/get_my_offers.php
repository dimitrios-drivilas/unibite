<?php
// Cook/api/get_my_offers.php
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

$cook_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT o.*, 
    (SELECT COUNT(*) FROM requests r WHERE r.offer_id = o.id) as total_requests
    FROM offers o WHERE o.cook_id = ? ORDER BY o.created_at DESC");
$stmt->execute([$cook_id]);
$offers = $stmt->fetchAll();

foreach ($offers as &$offer) {
    $reqStmt = $pdo->prepare("SELECT r.*, u.username as consumer_name FROM requests r JOIN users u ON r.consumer_id = u.id WHERE r.offer_id = ?");
    $reqStmt->execute([$offer['id']]);
    $offer['requests'] = $reqStmt->fetchAll();
}

echo json_encode(['success' => true, 'offers' => $offers]);