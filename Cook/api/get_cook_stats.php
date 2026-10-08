<?php
// Cook/api/get_cook_stats.php
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

$cook_id = $_SESSION['user_id'];

try {
    // Total points
    $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
    $stmt->execute([$cook_id]);
    $user = $stmt->fetch();
    $totalPoints = $user['points'] ?? 0;

    // Total offers created
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM offers WHERE cook_id = ?");
    $stmt->execute([$cook_id]);
    $totalOffers = $stmt->fetchColumn();

    // Total portions offered (portions_at_creation sum)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(portions_at_creation), 0) as total FROM offers WHERE cook_id = ?");
    $stmt->execute([$cook_id]);
    $totalPortions = $stmt->fetchColumn();

    // Average rating across all cook's offers
    $stmt = $pdo->prepare("
        SELECT AVG(r.rating) as avg_rating, COUNT(r.id) as rating_count
        FROM ratings r
        JOIN offers o ON r.offer_id = o.id
        WHERE o.cook_id = ?
    ");
    $stmt->execute([$cook_id]);
    $ratingData = $stmt->fetch();
    $ratingCount = $ratingData['rating_count'] ?? 0;
    $avgRating = $ratingCount > 0 ? round($ratingData['avg_rating'], 1) : null;

    // Offers with ratings detail
    $stmt = $pdo->prepare("
        SELECT 
            o.id,
            o.title,
            o.portions_at_creation,
            o.created_at,
            o.status,
            COUNT(DISTINCT rq.id) as total_requests,
            COUNT(DISTINCT CASE WHEN rq.status = 'delivered' THEN rq.id END) as delivered_requests,
            COALESCE(AVG(rt.rating), 0) as avg_rating,
            COUNT(rt.id) as rating_count,
            GROUP_CONCAT(DISTINCT rt.rating ORDER BY rt.rating DESC) as all_ratings,
            GROUP_CONCAT(DISTINCT rt.comment ORDER BY rt.created_at DESC) as all_comments
        FROM offers o
        LEFT JOIN requests rq ON rq.offer_id = o.id
        LEFT JOIN ratings rt ON rt.offer_id = o.id
        WHERE o.cook_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$cook_id]);
    $offers = $stmt->fetchAll();

    // Format offers for display
    $formattedOffers = [];
    foreach ($offers as $offer) {
        $ratings = [];
        if ($offer['rating_count'] > 0) {
            $ratingValues = explode(',', $offer['all_ratings']);
            $comments = $offer['all_comments'] ? explode(',', $offer['all_comments']) : [];
            for ($i = 0; $i < count($ratingValues); $i++) {
                $ratings[] = [
                    'rating' => (int)$ratingValues[$i],
                    'comment' => $comments[$i] ?? ''
                ];
            }
        }
        $formattedOffers[] = [
            'id' => $offer['id'],
            'title' => $offer['title'],
            'portions' => $offer['portions_at_creation'],
            'created_at' => $offer['created_at'],
            'status' => $offer['status'],
            'total_requests' => (int)$offer['total_requests'],
            'delivered_requests' => (int)$offer['delivered_requests'],
            'avg_rating' => round($offer['avg_rating'], 1),
            'rating_count' => (int)$offer['rating_count'],
            'ratings' => $ratings
        ];
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_points' => $totalPoints,
            'total_offers' => $totalOffers,
            'total_portions' => $totalPortions,
            'avg_rating' => $avgRating,
            'rating_count' => $ratingCount
        ],
        'offers' => $formattedOffers
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Σφάλμα φόρτωσης στατιστικών: ' . $e->getMessage()]);
}