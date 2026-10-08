<?php
// Admin/api/get_leaderboard.php
require_once '../../config/db.php';

$donorStmt = $pdo->query("SELECT u.username, SUM(o.portions) as total_portions_offered, COUNT(o.id) as offer_count 
    FROM users u JOIN offers o ON u.id = o.cook_id 
    WHERE JSON_CONTAINS(u.roles, '\"cook\"') GROUP BY u.id ORDER BY total_portions_offered DESC LIMIT 5");
$topDonors = $donorStmt->fetchAll();

$ratedStmt = $pdo->query("SELECT o.title, u.username as cook_name, AVG(r.rating) as avg_rating, COUNT(r.id) as rating_count
    FROM ratings r JOIN offers o ON r.offer_id = o.id JOIN users u ON o.cook_id = u.id
    GROUP BY o.id ORDER BY avg_rating DESC, rating_count DESC LIMIT 5");
$topRated = $ratedStmt->fetchAll();

echo json_encode([
    'success' => true,
    'top_donors' => $topDonors,
    'top_rated_meals' => $topRated
]);
