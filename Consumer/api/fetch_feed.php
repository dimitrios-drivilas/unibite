<?php
// Consumer/api/fetch_feed.php
require_once '../../config/db.php';

$silent_cron = true;
require_once '../../cron/auto_expire.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Απαιτείται σύνδεση.']);
    exit;
}

// Get query parameters for filtering
$lat = isset($_GET['lat']) && $_GET['lat'] !== '' ? floatval($_GET['lat']) : null;
$lon = isset($_GET['lon']) && $_GET['lon'] !== '' ? floatval($_GET['lon']) : null;
$maxDistance = isset($_GET['max_distance']) ? floatval($_GET['max_distance']) : 10; // km
$sortBy = $_GET['sort'] ?? 'created_desc'; // created_desc, distance_asc, portions_desc
$limit = isset($_GET['limit']) ? max(1, min(200, intval($_GET['limit']))) : 50;
$locationType = isset($_GET['location_type']) ? $_GET['location_type'] : ''; // campus, dorm, home

// Base query: get active or inactive (0 portions) offers that have not expired and are not cancelled/expired
$whereConditions = [
    "o.expires_at > NOW()",
    "o.status IN ('active', 'inactive')"
];
$params = [];

// Filter by location type
if ($locationType === 'dorm') {
    $whereConditions[] = "o.pickup_location LIKE '🏢 Εστίες:%'";
} elseif ($locationType === 'home') {
    $whereConditions[] = "o.pickup_location LIKE '🏠 Σπίτι:%'";
} elseif ($locationType === 'campus') {
    $whereConditions[] = "o.pickup_location NOT LIKE '🏢 Εστίες:%' AND o.pickup_location NOT LIKE '🏠 Σπίτι:%'";
}

$haversineExpr = "(6371 * ACOS(LEAST(1.0, GREATEST(-1.0, COS(RADIANS(?)) * COS(RADIANS(o.latitude)) * COS(RADIANS(o.longitude) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(o.latitude))))))";

if ($lat !== null && $lon !== null) {
    $whereConditions[] = "$haversineExpr <= ?";
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
            $orderBy = "distance_km ASC";
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

// Default University of Patras coordinates when user hasn't filtered by location
$refLat = $lat !== null ? $lat : 38.2918;
$refLon = $lon !== null ? $lon : 21.7855;

$sql = "
    SELECT o.*, u.username as cook_name,
        (SELECT COALESCE(AVG(r.rating), 0) FROM ratings r WHERE r.offer_id = o.id) as avg_rating,
        (SELECT COUNT(*) FROM ratings r WHERE r.offer_id = o.id) as rating_count,
        $haversineExpr as distance_km,
        CASE 
            WHEN o.portions > 0 AND o.status = 'active' THEN 'active'
            ELSE 'inactive'
        END as display_status,
        (SELECT COUNT(*) FROM requests rq 
         WHERE rq.offer_id = o.id 
         AND rq.consumer_id = ? 
         AND rq.status = 'delivered') as can_rate,
        (SELECT COUNT(*) FROM ratings rt 
         WHERE rt.offer_id = o.id 
         AND rt.consumer_id = ?) as already_rated
    FROM offers o 
    JOIN users u ON o.cook_id = u.id 
    WHERE $whereClause
    ORDER BY $orderBy
    LIMIT $limit
";

$stmt = $pdo->prepare($sql);

$selectParams = [
    $refLat,
    $refLon,
    $refLat,
    $_SESSION['user_id'],
    $_SESSION['user_id']
];

$selectParams = array_merge($selectParams, $params);

$stmt->execute($selectParams);
$offers = $stmt->fetchAll();

foreach ($offers as &$offer) {
    if (isset($offer['distance_km'])) {
        $offer['distance_km'] = round((float)$offer['distance_km'], 2);
    }
}

echo json_encode(['success' => true, 'offers' => $offers]);
