<?php
// Consumer/api/make_request.php
header('Content-Type: application/json; charset=UTF-8');

require_once '../../config/db.php';

try {
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

    $data = json_decode(file_get_contents('php://input'), true);
    $offer_id = intval($data['offer_id'] ?? 0);

    if ($offer_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Μη έγκυρο ID αγγελίας.']);
        exit;
    }

    // Check if user is trying to request their own offer
    $offerStmt = $pdo->prepare("SELECT cook_id, portions, status, expires_at FROM offers WHERE id = ?");
    $offerStmt->execute([$offer_id]);
    $offer = $offerStmt->fetch();

    if (!$offer) {
        echo json_encode(['success' => false, 'error' => 'Δεν βρέθηκε η αγγελία.']);
        exit;
    }

    if ($offer['cook_id'] == $consumer_id) {
        echo json_encode(['success' => false, 'error' => 'Δεν μπορείτε να κάνετε αίτημα στην δική σας αγγελία.']);
        exit;
    }

    // Check consumer has at least 1 point
    $userStmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
    $userStmt->execute([$consumer_id]);
    $user = $userStmt->fetch();

    if (!$user || $user['points'] < 1) {
        echo json_encode(['success' => false, 'error' => 'Δεν έχετε επαρκείς πόντους για να κάνετε αίτημα (απαιτείται τουλάχιστον 1 πόντος).']);
        exit;
    }

    // Check if offer is active and has portions
    if ($offer['portions'] <= 0 || $offer['status'] !== 'active' || $offer['expires_at'] <= date('Y-m-d H:i:s')) {
        echo json_encode(['success' => false, 'error' => 'Η αγγελία δεν είναι πλέον διαθέσιμη.']);
        exit;
    }

    // Check for existing request by this consumer for this offer
    $existingStmt = $pdo->prepare("SELECT id, status FROM requests WHERE offer_id = ? AND consumer_id = ?");
    $existingStmt->execute([$offer_id, $consumer_id]);
    $existing = $existingStmt->fetch();

    $pdo->beginTransaction();

    if ($existing) {
        $existingStatus = $existing['status'];
        if (in_array($existingStatus, ['pending', 'approved', 'delivered'], true)) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Έχετε ήδη ενεργό αίτημα για αυτή την αγγελία.']);
            exit;
        }
        // If cancelled or rejected, update to pending
        $upd = $pdo->prepare("UPDATE requests SET status = 'pending', updated_at = NOW() WHERE id = ?");
        $upd->execute([$existing['id']]);
    } else {
        // Create new request
        $ins = $pdo->prepare("INSERT INTO requests (offer_id, consumer_id, status) VALUES (?, ?, 'pending')");
        $ins->execute([$offer_id, $consumer_id]);
    }

    // NO point deduction here. Point is deducted only when cook approves the request.
    
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Το αίτημά σας καταχωρήθηκε και αναμένει έγκριση.']);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}