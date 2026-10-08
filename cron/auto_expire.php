<?php
// cron/auto_expire.php - Run every hour via cron
// Expires offers older than 48h, penalizes no-rating after 48h from delivery

require_once __DIR__ . '/../config/db.php';

echo "Starting auto-expire job at " . date('Y-m-d H:i:s') . "\n";

try {
    $pdo->beginTransaction();
    
    // 1. Expire offers older than 48h (status active -> expired)
    $stmt = $pdo->prepare("UPDATE offers SET status = 'expired' WHERE status = 'active' AND expires_at < NOW()");
    $stmt->execute();
    $expiredCount = $stmt->rowCount();
    echo "Expired $expiredCount offers\n";
    
    // 2. Mark offers with 0 portions as inactive (active -> inactive, but not expired yet)
    // These will show greyed out in feed but still accessible
    $stmt = $pdo->prepare("UPDATE offers SET status = 'inactive' WHERE status = 'active' AND portions <= 0 AND expires_at > NOW()");
    $stmt->execute();
    $inactiveCount = $stmt->rowCount();
    echo "Marked $inactiveCount offers as inactive (0 portions)\n";
    
    // 3. Penalize consumers who didn't rate within 48h of delivery
    // Find delivered requests older than 48h without rating
    $stmt = $pdo->query("
        SELECT r.consumer_id, r.id as request_id
        FROM requests r
        LEFT JOIN ratings rt ON rt.offer_id = r.offer_id AND rt.consumer_id = r.consumer_id
        WHERE r.status = 'delivered' 
        AND r.updated_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)
        AND rt.id IS NULL
    ");
    $unratedDeliveries = $stmt->fetchAll();
    
    foreach ($unratedDeliveries as $delivery) {
        $pdo->prepare("UPDATE users SET points = points - 1 WHERE id = ?")->execute([$delivery['consumer_id']]);
        // Optionally log this penalty
        echo "Penalty -1 point for user {$delivery['consumer_id']} (no rating within 48h)\n";
    }
    $penaltyCount = count($unratedDeliveries);
    echo "Applied $penaltyCount rating penalties\n";
    
    // 4. Mark offers that were inactive but now expired (inactive -> expired)
    $stmt = $pdo->prepare("UPDATE offers SET status = 'expired' WHERE status = 'inactive' AND expires_at < NOW()");
    $stmt->execute();
    $inactiveExpiredCount = $stmt->rowCount();
    echo "Expired $inactiveExpiredCount inactive offers\n";
    
    $pdo->commit();
    echo "Auto-expire job completed successfully\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}