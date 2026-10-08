<?php
// Consumer/api/get_my_requests.php
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

$consumer_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT 
        r.id,
        r.offer_id,
        r.consumer_id,
        r.status,
        r.created_at,
        r.updated_at,
        o.title,
        o.pickup_location,
        o.pickup_time,
        o.cook_id,
        u.username as cook_name,
        TIMESTAMPDIFF(HOUR, r.updated_at, NOW()) AS hours_since_delivery,
        CASE WHEN rt.id IS NOT NULL THEN 1 ELSE 0 END AS is_rated,
        (SELECT COUNT(*) FROM ratings rt2 WHERE rt2.offer_id = o.id AND rt2.consumer_id = r.consumer_id) as rated
    FROM requests r 
    JOIN offers o ON r.offer_id = o.id 
    JOIN users u ON o.cook_id = u.id 
    LEFT JOIN ratings rt ON rt.offer_id = o.id AND rt.consumer_id = r.consumer_id
    WHERE r.consumer_id = ? 
    ORDER BY r.created_at DESC
");
$stmt->execute([$consumer_id]);
$requests = $stmt->fetchAll();

echo json_encode(['success' => true, 'requests' => $requests]);