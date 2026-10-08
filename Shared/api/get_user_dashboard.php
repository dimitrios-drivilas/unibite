<?php
// Shared/api/get_user_dashboard.php
// Returns dashboard stats for any authenticated user (cook, consumer, or both)
require_once '../../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userRoles = $_SESSION['roles'] ?? [];
if (!is_array($userRoles)) {
    $userRoles = json_decode($userRoles, true) ?: [];
}
if (!isset($_SESSION['user_id']) || empty($userRoles)) {
    echo json_encode(['success' => false, 'error' => 'Μη εξουσιοδοτημένη πρόσβαση.']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // 1. Total points
    $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $totalPoints = $user['points'] ?? 0;

    // 2. Stats as Cook
    $totalOffers = 0;
    $totalPortions = 0;
    $avgRating = 0.0;
    $ratingCount = 0;
    if (in_array('cook', $userRoles, true)) {
        // Total offers created
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM offers WHERE cook_id = ?");
        $stmt->execute([$user_id]);
        $totalOffers = (int)$stmt->fetchColumn();

        // Total portions offered (portions_at_creation sum)
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(portions_at_creation), 0) as total FROM offers WHERE cook_id = ?");
        $stmt->execute([$user_id]);
        $totalPortions = (int)$stmt->fetchColumn();

        // Average rating across all cook's offers
        $stmt = $pdo->prepare("
            SELECT AVG(r.rating) as avg_rating, COUNT(r.id) as rating_count
            FROM ratings r
            JOIN offers o ON r.offer_id = o.id
            WHERE o.cook_id = ?
        ");
        $stmt->execute([$user_id]);
        $ratingData = $stmt->fetch();
        $avgRating = $ratingData['avg_rating'] ? round($ratingData['avg_rating'], 1) : 0.0;
        $ratingCount = (int)($ratingData['rating_count'] ?? 0);
    }

    // 3. Stats as Consumer
    $totalMealsReceived = 0;
    if (in_array('consumer', $userRoles, true)) {
        // Count delivered requests (meals actually received)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM requests r
            JOIN offers o ON r.offer_id = o.id
            WHERE r.consumer_id = ? AND r.status = 'delivered'
        ");
        $stmt->execute([$user_id]);
        $totalMealsReceived = (int)$stmt->fetchColumn();
    }

    // 4. Roles for nav
    $hasCook = in_array('cook', $userRoles, true);
    $hasConsumer = in_array('consumer', $userRoles, true);
    $hasAdmin = in_array('admin', $userRoles, true);

    echo json_encode([
        'success' => true,
        'username' => $_SESSION['username'] ?? '',
        'roles' => $userRoles,
        'stats' => [
            'points' => $totalPoints,
            'offers_created' => $totalOffers,
            'portions_offered' => $totalPortions,
            'avg_rating' => $avgRating,
            'rating_count' => $ratingCount,
            'meals_received' => $totalMealsReceived
        ],
        'capabilities' => [
            'can_cook' => $hasCook,
            'can_consume' => $hasConsumer,
            'can_admin' => $hasAdmin
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Σφάλμα φόρτωσης dashboard: ' . $e->getMessage()]);
}