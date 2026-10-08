<?php
// Admin/api/get_all_offers.php
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

$stmt = $pdo->query("
    SELECT o.*, u.username as cook_name 
    FROM offers o 
    JOIN users u ON o.cook_id = u.id 
    ORDER BY o.created_at DESC
");
$offers = $stmt->fetchAll();

echo json_encode(['success' => true, 'offers' => $offers]);