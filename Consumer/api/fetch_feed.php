<?php
// Consumer/api/fetch_feed.php
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Απαιτείται σύνδεση.']);
    exit;
}

// Get query parameters for filtering
$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lon = isset($_GET['lon']) ? floatval($_GET['lon']) : null;
$maxDistance = isset($_GET['max_distance']) ? floatval($_GET['max_distance']) : 10; // km
$sortBy = $_GET['sort'] ?? 'created_desc'; // created_desc, distance_asc, portions_desc
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

// Base query: get active offers (not expired) with portions >= 0
// Status logic:
// - active: expires_at > NOW() AND portions > 0
// - inactive: expires_at > NOW() AND portions = 0 (show greyed out)
// - expired: expires_at <= NOW() (hidden from feed)
$whereConditions = ["o.expires_at > NOW()"];
$params = [];

if ($lat !== null && $lon !== null) {
    // Haversine formula for distance in km
    $whereConditions[] = "(6371 * ACOS(COS(RADIANS(?)) * COS(RADIANS(o.latitude)) * COS(RADIANS(o.longitude) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(o.latitude)))) <= ?";
    $params[] = $lat;
    $params[] = $lon;
    $params[] = $lat;
    $params[] = $maxDistance;
}

$whereClause = implode(' AND ', $whereConditions);

// Determine ORDER BY
$orderBy = 'o.created_at DESC';
switch ($sortBy) {
    case 'distance_asc':
        if ($lat !== null && $lon !== null) {
            $orderBy = "(6371 * ACOS(COS(RADIANS($lat)) * COS(RADIANS(o.latitude)) * COS(RADIANS(o.longitude) - RADIANS($lon)) + SIN(RADIANS($lat)) * SIN(RADIANS(o.latitude)))) ASC";
        }
        break;
    case 'portions_desc':
        $orderBy = 'o.portions DESC, o.created_at DESC';
        break;
    case 'pickup_asc':
        $orderBy = 'o.pickup_time ASC';
        break;
    default:
        $orderBy = 'o.created_at DESC';
}

$stmt = $pdo->prepare("
    SELECT o.*, u.username as cook_name,
        (SELECT COALESCE(AVG(r.rating), 0) FROM ratings r WHERE r.offer_id = o.id) as avg_rating,
        (SELECT COUNT(*) FROM ratings r WHERE r.offer_id = o.id) as rating_count,
        (6371 * ACOS(COS(RADIANS(?)) * COS(RADIANS(o.latitude)) * COS(RADIANS(o.longitude) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(o.latitude)))) as distance_km,
        CASE 
            WHEN o.portions > 0 THEN 'active'
            ELSE 'inactive'
        END as display_status,
        -- Check if current user has a delivered request for this offer
        (SELECT COUNT(*) FROM requests rq 
         WHERE rq.offer_id = o.id 
         AND rq.consumer_id = ? 
         AND rq.status = 'delivered') as can_rate,
        -- Check if current user already rated this offer
        (SELECT COUNT(*) FROM ratings rt 
         WHERE rt.offer_id = o.id 
         AND rt.consumer_id = ?) as already_rated
    FROM offers o 
    JOIN users u ON o.cook_id = u.id 
    WHERE $whereClause
    ORDER BY $orderBy
    LIMIT ?
");

// Add lat/lon params for distance calculation in SELECT (if provided)
$selectParams = [];
if ($lat !== null && $lon !== null) {
    $selectParams[] = $lat;
    $selectParams[] = $lon;
    $selectParams[] = $lat;
} else {
    // Dummy values when no location provided
    $selectParams[] = 37.983810;
    $selectParams[] = 23.727539;
    $selectParams[] = 37.983810;
}

// Add consumer_id for can_rate check
$selectParams[] = $_SESSION['user_id'];

// Add consumer_id for already_rated check
$selectParams[] = $_SESSION['user_id'];

// Add WHERE params
$selectParams = array_merge($selectParams, $params);
$selectParams[] = $limit;

$stmt->execute($selectParams);
$offers = $stmt->fetchAll();

// Round distance for display
foreach ($offers as &$offer) {
    if (isset($offer['distance_km'])) {
        $offer['distance_km'] = round($offer['distance_km'], 2);
    }
}

echo json_encode(['success' => true, 'offers' => $offers]);