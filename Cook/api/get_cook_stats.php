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

    // Total portions offered: count only delivered requests (portions actually delivered)
    $stmt = $pdo->prepare("
        SELECT COUNT(r.id) as total
        FROM requests r
        JOIN offers o ON r.offer_id = o.id
        WHERE o.cook_id = ? AND r.status = 'delivered'
    ");
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

    // Points earned from offers (ratings): 4-5 stars = +2, 1-3 stars = +1
    $stmt = $pdo->prepare("
        SELECT SUM(CASE WHEN rt.rating > 3 THEN 2 ELSE 1 END) as points_from_offers
        FROM ratings rt
        JOIN offers o ON rt.offer_id = o.id
        WHERE o.cook_id = ?
    ");
    $stmt->execute([$cook_id]);
    $pointsFromOffers = (int)($stmt->fetchColumn() ?? 0);

    // Penalties received (as consumer): rating penalties (penalized=1) + absence penalties (status='absent')
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM requests WHERE consumer_id = ? AND penalized = 1) as rating_penalties,
            (SELECT COUNT(*) FROM requests WHERE consumer_id = ? AND status = 'absent') as absence_penalties
    ");
    $stmt->execute([$cook_id, $cook_id]);
    $penaltyData = $stmt->fetch();
    $penaltiesReceived = (int)($penaltyData['rating_penalties'] ?? 0) + (int)($penaltyData['absence_penalties'] ?? 0);

    // 1. Get all offers for this cook (without ratings to avoid cartesian product)
    $stmt = $pdo->prepare("
        SELECT 
            o.id,
            o.title,
            o.portions_at_creation,
            o.created_at,
            o.status,
            COUNT(DISTINCT rq.id) as total_requests,
            COUNT(DISTINCT CASE WHEN rq.status = 'delivered' THEN rq.id END) as delivered_requests
        FROM offers o
        LEFT JOIN requests rq ON rq.offer_id = o.id
        WHERE o.cook_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$cook_id]);
    $offers = $stmt->fetchAll();

    // 2. Get ALL ratings for these offers in ONE separate query
    $offerIds = array_column($offers, 'id');
    $ratingsByOffer = [];
    if (!empty($offerIds)) {
        $placeholders = str_repeat('?,', count($offerIds) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT 
                rt.offer_id,
                rt.rating,
                rt.comment,
                rt.created_at,
                u.username as consumer_name
            FROM ratings rt
            LEFT JOIN users u ON u.id = rt.consumer_id
            WHERE rt.offer_id IN ($placeholders)
            ORDER BY rt.offer_id, rt.created_at DESC
        ");
        $stmt->execute($offerIds);
        $allRatings = $stmt->fetchAll();
        
        foreach ($allRatings as $r) {
            $ratingsByOffer[$r['offer_id']][] = [
                'rating' => (int)$r['rating'],
                'comment' => $r['comment'] ?? '',
                'consumer_name' => $r['consumer_name'] ?? 'Άγνωστος'
            ];
        }
    }

    // 3. Format offers for display with their ratings
    $formattedOffers = [];
    foreach ($offers as $offer) {
        $offerRatings = $ratingsByOffer[$offer['id']] ?? [];
        $offerRatingCount = count($offerRatings);
        $offerAvgRating = $offerRatingCount > 0 ? round(array_sum(array_column($offerRatings, 'rating')) / $offerRatingCount, 1) : 0;

        $formattedOffers[] = [
            'id' => $offer['id'],
            'title' => $offer['title'],
            'portions' => $offer['portions_at_creation'],
            'created_at' => $offer['created_at'],
            'status' => $offer['status'],
            'total_requests' => (int)$offer['total_requests'],
            'delivered_requests' => (int)$offer['delivered_requests'],
            'avg_rating' => $offerAvgRating,
            'rating_count' => $offerRatingCount,
            'ratings' => $offerRatings
        ];
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_points' => $totalPoints,
            'total_offers' => $totalOffers,
            'total_portions' => $totalPortions,
            'avg_rating' => $avgRating,
            'rating_count' => $ratingCount,
            'points_from_offers' => $pointsFromOffers,
            'penalties_received' => $penaltiesReceived
        ],
        'offers' => $formattedOffers
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Σφάλμα φόρτωσης στατιστικών: ' . $e->getMessage()]);
}