<?php
// Admin/api/get_statistics.php
require_once '../../config/db.php';

// Total portions shared (successfully delivered) in the last month
$stmt = $pdo->query("
    SELECT SUM(o.portions_at_creation) as total_portions_shared,
           COUNT(DISTINCT o.id) as total_offers
    FROM offers o
    WHERE o.created_at >= NOW() - INTERVAL 1 MONTH
");
$stats = $stmt->fetch();

// But we need portions that were actually delivered (approved requests that became delivered)
// Let's count approved requests that turned into delivered
$reqStmt = $pdo->query("
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN r.status = 'delivered' THEN 1 ELSE 0 END) as delivered_requests,
        SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved_requests
    FROM requests r
    JOIN offers o ON r.offer_id = o.id
    WHERE o.created_at >= NOW() - INTERVAL 1 MONTH
");
$reqStats = $reqStmt->fetch();

// Calculate portions actually delivered (each delivered request = 1 portion)
$portionsDelivered = intval($reqStats['delivered_requests'] ?? 0);

echo json_encode([
    'success' => true,
    'total_portions' => $portionsDelivered,
    'total_offers' => intval($stats['total_offers'] ?? 0),
    'total_requests' => intval($reqStats['total_requests'] ?? 0),
    'delivered_requests' => $portionsDelivered
]);