<?php
// cron/auto_expire.php - Run every hour via cron
// Expires offers older than 48h, penalizes no-rating after 48h from delivery
// When included via $silent_cron = true, suppresses echo output for JSON API compatibility

require_once __DIR__ . '/../config/db.php';

// Support silent mode when included from API
$silent_cron = $silent_cron ?? false;

function cronLog($msg) {
    global $silent_cron;
    if (!$silent_cron) {
        echo $msg . "\n";
    }
}

// Check and add penalized column BEFORE starting transaction (ALTER TABLE causes implicit commit in MySQL)
$cols = $pdo->query("SHOW COLUMNS FROM requests LIKE 'penalized'")->fetchAll();
if (empty($cols)) {
    $pdo->exec("ALTER TABLE requests ADD COLUMN penalized TINYINT(1) NOT NULL DEFAULT 0");
    cronLog("Added penalized column to requests table");
}

cronLog("Starting auto-expire job at " . date('Y-m-d H:i:s'));

try {
    $pdo->beginTransaction();
    
    // 1. Expire offers older than 48h (status active -> expired)
    $stmt = $pdo->prepare("UPDATE offers SET status = 'expired' WHERE status = 'active' AND expires_at < NOW()");
    $stmt->execute();
    $expiredCount = $stmt->rowCount();
    cronLog("Expired $expiredCount offers");
    
    // 2. Mark offers with 0 portions as inactive (active -> inactive, but not expired yet)
    // These will show greyed out in feed but still accessible
    $stmt = $pdo->prepare("UPDATE offers SET status = 'inactive' WHERE status = 'active' AND portions <= 0 AND expires_at > NOW()");
    $stmt->execute();
    $inactiveCount = $stmt->rowCount();
    cronLog("Marked $inactiveCount offers as inactive (0 portions)");
    
    // 3. Penalize consumers who didn't rate within 48h of delivery
    // Find delivered requests older than 48h without rating AND not yet penalized
    $stmt = $pdo->query("
        SELECT r.consumer_id, r.id as request_id
        FROM requests r
        LEFT JOIN ratings rt ON rt.offer_id = r.offer_id AND rt.consumer_id = r.consumer_id
        WHERE r.status = 'delivered' 
        AND r.updated_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)
        AND rt.id IS NULL
        AND r.penalized = 0
    ");
    $unratedDeliveries = $stmt->fetchAll();
    
    foreach ($unratedDeliveries as $delivery) {
        $pdo->prepare("UPDATE users SET points = points - 1 WHERE id = ?")->execute([$delivery['consumer_id']]);
        // Mark as penalized so we don't apply penalty again
        $pdo->prepare("UPDATE requests SET penalized = 1 WHERE id = ?")->execute([$delivery['request_id']]);
        cronLog("Penalty -1 point for user {$delivery['consumer_id']} (no rating within 48h)");
    }
    $penaltyCount = count($unratedDeliveries);
    cronLog("Applied $penaltyCount rating penalties");
    
    // 4. Mark offers that were inactive but now expired (inactive -> expired)
    $stmt = $pdo->prepare("UPDATE offers SET status = 'expired' WHERE status = 'inactive' AND expires_at < NOW()");
    $stmt->execute();
    $inactiveExpiredCount = $stmt->rowCount();
    cronLog("Expired $inactiveExpiredCount inactive offers");
    
    $pdo->commit();
    cronLog("Auto-expire job completed successfully");
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    cronLog("Error: " . $e->getMessage());
    if (!$silent_cron) exit(1);
    throw $e;
}