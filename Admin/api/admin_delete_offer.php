<?php
// Admin/api/admin_delete_offer.php
header('Content-Type: application/json; charset=UTF-8');

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

$data = json_decode(file_get_contents('php://input'), true);
$offer_id = intval($data['offer_id'] ?? 0);

if ($offer_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Μη έγκυρο ID αγγελίας.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Soft delete: mark as cancelled (not expired) for consistency
    $stmt = $pdo->prepare("UPDATE offers SET status = 'cancelled', expires_at = NOW() WHERE id = ?");
    $stmt->execute([$offer_id]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Δεν βρέθηκε η αγγελία.']);
        exit;
    }

    // Cancel any pending requests for this offer
    $pdo->prepare("UPDATE requests SET status = 'cancelled' WHERE offer_id = ? AND status = 'pending'")->execute([$offer_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Η αγγελία ακυρώθηκε από διαχειριστή και τα εκκρεμή αιτήματα ανнулиώθηκε.']);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}