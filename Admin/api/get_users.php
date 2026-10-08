<?php
// Admin/api/get_users.php
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

$stmt = $pdo->query("SELECT id, username, email, roles AS role, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

echo json_encode(['success' => true, 'users' => $users]);