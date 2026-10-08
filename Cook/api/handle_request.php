<?php
// Cook/api/handle_request.php
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
$request_id = intval($data['request_id'] ?? 0);
$action = $data['action'] ?? ''; 
$cook_id = $_SESSION['user_id'];

$allowed_actions = ['approve', 'reject', 'delivered', 'absent'];
if (!in_array($action, $allowed_actions)) {
    echo json_encode(['success' => false, 'error' => 'Μη έγκυρη ενέργεια.']);
    exit;
}

$stmt = $pdo->prepare("SELECT r.*, o.portions, o.cook_id FROM requests r JOIN offers o ON r.offer_id = o.id WHERE r.id = ? AND o.cook_id = ?");
$stmt->execute([$request_id, $cook_id]);
$request = $stmt->fetch();

if (!$request) {
    echo json_encode(['success' => false, 'error' => 'Δεν βρέθηκε το αίτημα ή δεν έχετε δικαίωμα.']);
    exit;
}

// Prevent changes if request is already in final state (delivered or absent)
if (in_array($request['status'], ['delivered', 'absent'])) {
    echo json_encode(['success' => false, 'error' => 'Το αίτημα έχει ολοκληρωθεί και δεν μπορεί να αλλάξει.']);
    exit;
}

$status_map = [
    'approve' => 'approved',
    'reject' => 'rejected',
    'delivered' => 'delivered',
    'absent' => 'absent'
];
$new_status = $status_map[$action];

$pdo->beginTransaction();

try {
    // Update request status
    $upd = $pdo->prepare("UPDATE requests SET status = ? WHERE id = ?");
    $upd->execute([$new_status, $request_id]);
    
    $consumer_id = $request['consumer_id'];
    
    if ($action === 'approve') {
        // Spend 1 point from consumer
        $pdo->prepare("UPDATE users SET points = points - 1 WHERE id = ?")->execute([$consumer_id]);
        // Decrement offer portions
        $pdo->prepare("UPDATE offers SET portions = portions - 1 WHERE id = ?")->execute([$request['offer_id']]);
        // If portions depleted, mark offer inactive so it doesn't appear in feed
        $pdo->prepare("UPDATE offers SET status = 'inactive' WHERE id = ? AND portions <= 0")->execute([$request['offer_id']]);
        $message = 'Το αίτημα εγκρίθηκε. Μερίδα δεσμεύτηκε (-1 ποντός από καταναλωτή).';
    } elseif ($action === 'reject') {
        $message = 'Το αίτημα απορρίφθηκε.';
    } elseif ($action === 'delivered') {
        // Points for cook will be awarded when rating is submitted
        $message = 'Η παραλαβή επιβεβαιώθηκε. Οι πόντοι θα ανατεθούν μετά την αξιολόγηση.';
        // Check if offer should be completed or inactive
        $stmtCheck = $pdo->prepare("
            SELECT o.portions, 
                   COUNT(CASE WHEN r.status IN ('pending', 'approved') THEN 1 END) as pending_requests
            FROM offers o
            LEFT JOIN requests r ON r.offer_id = o.id
            WHERE o.id = ?
            GROUP BY o.id
        ");
        $stmtCheck->execute([$request['offer_id']]);
        $offerStatus = $stmtCheck->fetch();

        if ($offerStatus && $offerStatus['portions'] <= 0 && $offerStatus['pending_requests'] == 0) {
            $pdo->prepare("UPDATE offers SET status = 'completed' WHERE id = ?")->execute([$request['offer_id']]);
        } elseif ($offerStatus && $offerStatus['portions'] <= 0) {
            $pdo->prepare("UPDATE offers SET status = 'inactive' WHERE id = ?")->execute([$request['offer_id']]);
        }
    } elseif ($action === 'absent') {
        // Penalty: -1 point for no-show
        $pdo->prepare("UPDATE users SET points = points - 1 WHERE id = ?")->execute([$consumer_id]);
        $message = 'Καταχωρήθηκε απουσία. -1 ποντός πρόστιμο.';
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => $message]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Αποτυχία ενημέρωσης.']);
}