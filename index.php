<?php
// index.php
$silent_cron = true;
require_once __DIR__ . '/cron/auto_expire.php';

// Try to get current user without redirecting
require_once __DIR__ . '/config/db.php';

// Session cookie config for cross-page sessions (only if session not active)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

$user = null;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['user_id']) && isset($_SESSION['roles'])) {
    $userRoles = $_SESSION['roles'];
    if (!is_array($userRoles)) {
        $userRoles = json_decode($userRoles, true) ?: [];
    }
    $user = [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'roles' => $userRoles
    ];
}
$isLoggedIn = $user !== null;
$username = $user['username'] ?? '';
$userRoles = $user['roles'] ?? [];
$hasCook = in_array('cook', $userRoles, true);
$hasConsumer = in_array('consumer', $userRoles, true);
$hasAdmin = in_array('admin', $userRoles, true);

// Redirect admin users to their dedicated dashboard
if ($hasAdmin && !$hasCook && !$hasConsumer) {
    header('Location: Admin/admin.php');
    exit;
}

// ===== SERVER-SIDE DATA FETCHING FOR DASHBOARD =====
$current_points = 0;
$cook_offers_count = 0;
$cook_portions_count = 0;
$avg_rating = '-';
$consumer_delivered_count = 0;
$my_requests = [];

if ($isLoggedIn) {
    $user_id = $_SESSION['user_id'];

    // 1. User points
    $stmtPts = $pdo->prepare("SELECT points FROM users WHERE id = ?");
    $stmtPts->execute([$user_id]);
    $current_points = (int)($stmtPts->fetchColumn() ?: 0);

    // 2. Cook statistics
    if ($hasCook) {
        $stmtCookOffers = $pdo->prepare("SELECT COUNT(*) FROM offers WHERE cook_id = ?");
        $stmtCookOffers->execute([$user_id]);
        $cook_offers_count = (int)$stmtCookOffers->fetchColumn();

        $stmtCookPortions = $pdo->prepare("SELECT COALESCE(SUM(portions_at_creation), 0) FROM offers WHERE cook_id = ?");
        $stmtCookPortions->execute([$user_id]);
        $cook_portions_count = (int)$stmtCookPortions->fetchColumn();

        $stmtCookRating = $pdo->prepare("
            SELECT AVG(rt.rating) 
            FROM ratings rt 
            JOIN offers o ON rt.offer_id = o.id 
            JOIN requests rq ON rq.offer_id = o.id AND rq.consumer_id = rt.consumer_id AND rq.status = 'delivered'
            WHERE o.cook_id = ?
        ");
        $stmtCookRating->execute([$user_id]);
        $avg_rating_raw = $stmtCookRating->fetchColumn();
        $avg_rating = $avg_rating_raw ? number_format((float)$avg_rating_raw, 1) : '-';
    }

    // 3. Consumer statistics
    if ($hasConsumer) {
        $stmtDelivered = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE consumer_id = ? AND status = 'delivered'");
        $stmtDelivered->execute([$user_id]);
        $consumer_delivered_count = (int)$stmtDelivered->fetchColumn();

        // 4. Commitments (Requests) & Pending Ratings
        $stmtReqs = $pdo->prepare("
            SELECT 
                r.id as request_id,
                r.status as request_status,
                r.created_at,
                r.updated_at,
                r.penalized,
                o.id as offer_id,
                o.title,
                o.pickup_location,
                o.pickup_time,
                u.username as cook_name,
                (SELECT COUNT(*) FROM ratings rt WHERE rt.offer_id = o.id AND rt.consumer_id = r.consumer_id) as is_rated
            FROM requests r
            JOIN offers o ON r.offer_id = o.id
            JOIN users u ON o.cook_id = u.id
            WHERE r.consumer_id = ?
            ORDER BY r.created_at DESC
            LIMIT 10
        ");
        $stmtReqs->execute([$user_id]);
        $my_requests = $stmtReqs->fetchAll(PDO::FETCH_ASSOC);
    }
}
?><!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniBite - Φοιτητική Πλατφόρμα Διαμοιρασμού Φαγητού</title>
    <link rel="icon" type="image/svg+xml" href="/public/assets/favicon.svg">
    <link rel="apple-touch-icon" href="/public/assets/favicon.svg">
    <!-- Google Fonts: Host Grotesk (display) + Instrument Sans (body) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Host+Grotesk:wght@400;500;600;700;800&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Shared/css/common.css?v=<?= time() ?>">
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar" role="navigation" aria-label="Main navigation">
        <a href="index.php" class="navbar-brand" aria-label="UniBite Home">
            <span class="logo-uni">Uni</span><span class="logo-bite">Bite</span>
            <span class="navbar-badge">CAMPUS FOOD</span>
        </a>
        <?php if ($isLoggedIn): ?>
            <span class="navbar-user" style="display:flex; align-items:center; gap:12px; color:var(--dp-color-on-field-secondary); font-size:var(--dp-typography-interface); font-weight:500;">
                <span style="color:var(--dp-color-accent);">●</span> Φοιτητής: <strong><?= htmlspecialchars($username) ?></strong>
            </span>
            <ul class="navbar-links">
                <li><a href="index.php" class="active">🏠 Αρχική</a></li>
                <?php if ($hasCook): ?>
                <li><a href="Cook/cook.php">🍳 Μάγειρας</a></li>
                <?php endif; ?>
                <?php if ($hasConsumer): ?>
                <li><a href="Consumer/consumer.php">🎒 Καταναλωτής</a></li>
                <?php endif; ?>
                <?php if ($hasAdmin): ?>
                <li><a href="Admin/admin.php">⚙️ Διαχείριση</a></li>
                <?php endif; ?>
            </ul>
            <button class="navbar-logout" onclick="logout()">Αποσύνδεση</button>
        <?php else: ?>
            <ul class="navbar-links">
                <li><a href="#">Πώς λειτουργεί</a></li>
                <li><a href="#">Κανόνες Πόντων</a></li>
            </ul>
            <a href="#" class="navbar-cta" onclick="openAuthModal()">Εγγραφή</a>
        <?php endif; ?>
    </nav>

    <!-- HERO SPLIT SCREEN / DASHBOARD -->
    <main role="main">
        <?php if ($isLoggedIn): ?>
            <!-- LOGGED IN: USER DASHBOARD -->
            <section class="dashboard" style="max-width: 1200px; margin: 0 auto; padding: var(--dp-spacing-xl) var(--dp-spacing-lg);">
                <!-- Points Card -->
                <div class="card points-card" style="margin-bottom: var(--dp-spacing-xl); text-align: center; border-left: 5px solid var(--dp-color-accent); background: var(--dp-color-ground-muted);">
                    <h2 style="margin: 0 0 var(--dp-spacing-sm); font-size: 1.25rem; color: var(--dp-color-ink);">💎 Οι Πόντοι σου</h2>
                    <div id="dashboard-points" style="font-family: var(--dp-font-display); font-size: clamp(2.5rem, 5vw, 4rem); font-weight: 800; line-height: var(--dp-typography-tight-leading); letter-spacing: -0.025em; color: var(--dp-color-accent);"><?= $current_points ?></div>
                    <p style="margin: var(--dp-spacing-md) 0 0; color: var(--dp-color-ink-secondary); font-size: var(--dp-typography-small);">Χρειάζεσαι τουλάχιστον <strong>1 πόντου</strong> για δέσμευση μερίδας</p>
                </div>

                <!-- Stats Grid + Action Cards -->
                <div class="dashboard-layout" style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--dp-spacing-xl);">
                    <!-- Stats Card -->
                    <div class="card" style="padding: var(--dp-spacing-lg);">
                        <h3 style="margin: 0 0 var(--dp-spacing-lg); font-size: 1.1rem; color: var(--dp-color-ink); font-weight: 700; display:flex; align-items:center; gap:var(--dp-spacing-sm);">
                            <span style="font-size:1.25rem;">📊</span> Συνολικά Στατιστικά
                        </h3>
                        <div class="dashboard-stats-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--dp-spacing-md);">
                            <?php if ($hasCook): ?>
                            <div class="stat-item" style="background:var(--dp-color-ground-muted); border-radius:var(--dp-radius-panel); padding:var(--dp-spacing-md); border:var(--dp-border-hairline) solid var(--dp-color-rule);">
                                <div style="font-family: var(--dp-font-display); font-size: 1.75rem; font-weight: 700; color: var(--dp-color-accent);" id="stat-offers"><?= $cook_offers_count ?></div>
                                <div style="font-size: var(--dp-typography-small); color: var(--dp-color-ink-secondary); margin-top: var(--dp-spacing-sm);">Αγγελίες που Δημιούργησα</div>
                            </div>
                            <div class="stat-item" style="background:var(--dp-color-ground-muted); border-radius:var(--dp-radius-panel); padding:var(--dp-spacing-md); border:var(--dp-border-hairline) solid var(--dp-color-rule);">
                                <div style="font-family: var(--dp-font-display); font-size: 1.75rem; font-weight: 700; color: var(--dp-color-success);" id="stat-portions"><?= $cook_portions_count ?></div>
                                <div style="font-size: var(--dp-typography-small); color: var(--dp-color-ink-secondary); margin-top: var(--dp-spacing-sm);">Μερίδες που Προσέφερα</div>
                            </div>
                            <div class="stat-item" style="background:var(--dp-color-ground-muted); border-radius:var(--dp-radius-panel); padding:var(--dp-spacing-md); border:var(--dp-border-hairline) solid var(--dp-color-rule);">
                                <div style="font-family: var(--dp-font-display); font-size: 1.75rem; font-weight: 700; color: var(--dp-color-warning);" id="stat-rating"><?= $avg_rating ?></div>
                                <div style="font-size: var(--dp-typography-small); color: var(--dp-color-ink-secondary); margin-top: var(--dp-spacing-sm);">Μέση Βαθμολογία (⭐)</div>
                            </div>
                            <?php endif; ?>
                            <?php if ($hasConsumer): ?>
                            <div class="stat-item" style="background:var(--dp-color-ground-muted); border-radius:var(--dp-radius-panel); padding:var(--dp-spacing-md); border:var(--dp-border-hairline) solid var(--dp-color-rule);">
                                <div style="font-family: var(--dp-font-display); font-size: 1.75rem; font-weight: 700; color: var(--dp-color-info);" id="stat-meals"><?= $consumer_delivered_count ?></div>
                                <div style="font-size: var(--dp-typography-small); color: var(--dp-color-ink-secondary); margin-top: var(--dp-spacing-sm);">Γεύματα που Παρέλαβα</div>
                            </div>
                            <?php endif; ?>
                            <?php if (!$hasCook && !$hasConsumer): ?>
                            <div style="grid-column:1/-1; text-align:center; color:var(--dp-color-ink-secondary); padding:var(--dp-spacing-xl);">
                                Δεν υπάρχουν διαθέσιμα στατιστικά για τον ρόλο σας.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Action Cards -->
                    <div class="dashboard-actions-grid" style="display: flex; flex-direction: column; gap: var(--dp-spacing-md);">
                        <?php if ($hasCook): ?>
                        <a href="Cook/cook.php" class="action-card" style="display:flex; align-items:center; gap:var(--dp-spacing-lg); padding:var(--dp-spacing-lg); background:var(--dp-color-panel); border:var(--dp-border-hairline) solid var(--dp-color-rule); border-radius:var(--dp-radius-panel); text-decoration:none; color:inherit; transition:box-shadow var(--dp-motion-interaction) var(--dp-motion-easing), transform var(--dp-motion-interaction) var(--dp-motion-easing), border-color var(--dp-motion-interaction) var(--dp-motion-easing);">
                            <div style="flex-shrink:0; width:64px; height:64px; border-radius:var(--dp-radius-panel); background:linear-gradient(135deg,var(--dp-color-accent),var(--dp-color-accent-hover)); display:flex; align-items:center; justify-content:center; font-size:1.75rem;">🍳</div>
                            <div>
                                <h4 style="margin:0 0 var(--dp-spacing-sm); font-family: var(--dp-font-display); font-size:1.1rem; font-weight:700; color:var(--dp-color-ink);">Προσφορά Φαγητού</h4>
                                <p style="margin:0; font-size:var(--dp-typography-small); color:var(--dp-color-ink-secondary);">Δημιούργησε νέα αγγελία, διαχειρίσετε τις δικές σας</p>
                            </div>
                        </a>
                        <?php endif; ?>
                        <?php if ($hasConsumer): ?>
                        <a href="Consumer/consumer.php" class="action-card" style="display:flex; align-items:center; gap:var(--dp-spacing-lg); padding:var(--dp-spacing-lg); background:var(--dp-color-panel); border:var(--dp-border-hairline) solid var(--dp-color-rule); border-radius:var(--dp-radius-panel); text-decoration:none; color:inherit; transition:box-shadow var(--dp-motion-interaction) var(--dp-motion-easing), transform var(--dp-motion-interaction) var(--dp-motion-easing), border-color var(--dp-motion-interaction) var(--dp-motion-easing);">
                            <div style="flex-shrink:0; width:64px; height:64px; border-radius:var(--dp-radius-panel); background:linear-gradient(135deg,var(--dp-color-info),var(--dp-color-accent)); display:flex; align-items:center; justify-content:center; font-size:1.75rem;">🔍</div>
                            <div>
                                <h4 style="margin:0 0 var(--dp-spacing-sm); font-family: var(--dp-font-display); font-size:1.1rem; font-weight:700; color:var(--dp-color-ink);">Εξερεύνηση Γευμάτων</h4>
                                <p style="margin:0; font-size:var(--dp-typography-small); color:var(--dp-color-ink-secondary);">Διαδραστικός χάρτης, live feed, αιτήματα</p>
                            </div>
                        </a>
                        <?php endif; ?>
                        <?php if (!$hasCook && !$hasConsumer): ?>
                        <div class="card" style="text-align:center; padding:var(--dp-spacing-2xl); color:var(--dp-color-ink-secondary);">
                            Ο λογαριασμός σας δεν έχει ενεργούς ρόλους. Επικοινωνήστε με τη διαχείριση.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- My Commitments & Pending Ratings Section (Consumer only) -->
                <?php if ($hasConsumer): ?>
                <section class="card commitments-section" style="margin-top: var(--dp-spacing-xl); padding: var(--dp-spacing-lg);">
                    <h3 style="margin: 0 0 var(--dp-spacing-lg); font-family: var(--dp-font-display); font-size: 1.1rem; color: var(--dp-color-ink); font-weight: 700; display: flex; align-items: center; gap: var(--dp-spacing-sm);">
                        <span style="font-size: 1.25rem;">📋</span> Οι Δεσμεύσεις μου & Εκκρεμείς Αξιολογήσεις
                    </h3>
                    <div id="commitments-content">
                        <?php if (empty($my_requests)): ?>
                            <p style="color: var(--dp-color-ink-secondary); text-align: center; padding: var(--dp-spacing-xl);">Δεν έχετε πραγματοποιήσει ακόμα καμία δέσμευση.</p>
                        <?php else: ?>
                            <?php
                            // Separate pending ratings (delivered but not rated)
                            $pendingRatings = array_filter($my_requests, fn($r) => $r['request_status'] === 'delivered' && $r['is_rated'] == 0);
                            $otherRequests = array_filter($my_requests, fn($r) => !($r['request_status'] === 'delivered' && $r['is_rated'] == 0));
                            ?>

                            <?php foreach ($pendingRatings as $req):
                                $delivered_time = strtotime($req['updated_at']);
                                $deadline = $delivered_time + (48 * 3600);
                                $hours_left = max(0, round(($deadline - time()) / 3600));
                                $deadline_str = date('d/m/Y H:i', $deadline);
                                $is_penalized = isset($req['penalized']) && $req['penalized'] == 1;
                                $timeStr = '';
                                if ($hours_left > 0) {
                                    if ($hours_left >= 24) {
                                        $days = floor($hours_left / 24);
                                        $hrs = $hours_left % 24;
                                        $timeStr = "{$days} " . ($days === 1 ? 'ημέρα' : 'ημέρες') . ($hrs > 0 ? " {$hrs} ώρες" : '');
                                    } else {
                                        $timeStr = "{$hours_left} " . ($hours_left === 1 ? 'ώρα' : 'ώρες');
                                    }
                                } else {
                                    $timeStr = 'ήδη παρήλθε';
                                }
                            ?>
                            <div class="penalty-warning-banner" style="
                                background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
                                border: var(--dp-border-hairline) solid var(--dp-color-warning);
                                border-radius: var(--dp-radius-panel);
                                padding: var(--dp-spacing-md) var(--dp-spacing-lg);
                                margin-bottom: var(--dp-spacing-md);
                                display: flex;
                                flex-wrap: wrap;
                                align-items: center;
                                gap: var(--dp-spacing-md);
                            ">
                                <div style="flex: 1; min-width: 280px;">
                                    <div style="display: flex; align-items: center; gap: var(--dp-spacing-sm); margin-bottom: var(--dp-spacing-sm);">
                                        <span style="font-size: 1.25rem;">⚠️</span>
                                        <strong style="color: #92400E; font-size: 1rem;">Εκκρεμεί Αξιολόγηση</strong>
                                    </div>
                                    <div style="color: #78350F; font-size: var(--dp-typography-small); line-height: 1.5;">
                                        <strong><?= htmlspecialchars($req['title']) ?></strong> — Μάγειρας: <?= htmlspecialchars($req['cook_name']) ?><br>
                                        <?php if ($is_penalized): ?>
                                            ❌ <strong>Έληξε η προθεσμία αξιολόγησης (<?= $deadline_str ?>) — Σας αφαιρέθηκε 1 πόντος ποινής.</strong>
                                        <?php else: ?>
                                            Απομένουν <strong><?= $timeStr ?></strong> (έως <?= $deadline_str ?>).<br>Αν δεν αξιολογήσετε εντός 48 ωρών από την παραλαβή, θα σας αφαιρεθεί <strong>1 credit ποινής (-1 πόντους)</strong>.
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="flex-shrink: 0;">
                                    <button type="button" class="btn btn-primary" style="position: relative; z-index: 10; cursor: pointer; touch-action: manipulation;" data-rate-offer-id="<?= $req['offer_id'] ?>" data-rate-title="<?= htmlspecialchars(addslashes($req['title'])) ?>" onclick="openRatingModal(<?= $req['offer_id'] ?>, '<?= htmlspecialchars(addslashes($req['title'])) ?>')">
                                        ⭐ Αξιολόγηση Τώρα
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <?php if (!empty($otherRequests)): ?>
                                <div style="margin-top: <?= !empty($pendingRatings) ? 'var(--dp-spacing-xl)' : '0' ?>;">
                                    <h4 style="margin: 0 0 var(--dp-spacing-md); font-size: var(--dp-typography-small); font-weight: 600; color: var(--dp-color-ink-secondary); text-transform: uppercase; letter-spacing: 0.04em;">Όλες οι Δεσμεύσεις</h4>
                                    <div style="display: flex; flex-direction: column; gap: var(--dp-spacing-md);">
                                        <?php foreach ($otherRequests as $req): ?>
                                            <?php
                                                $statusLabel = match($req['request_status']) {
                                                    'pending' => 'Εκκρεμεί Έγκριση',
                                                    'approved' => 'Εγκρίθηκε',
                                                    'rejected' => 'Απορρίφθηκε',
                                                    'delivered' => 'Παραδόθηκε',
                                                    'absent' => 'Απουσία',
                                                    'cancelled' => 'Ακυρωμένο',
                                                    default => $req['request_status']
                                                };
                                                $statusClass = match($req['request_status']) {
                                                    'pending' => 'badge-pending',
                                                    'approved' => 'badge-approved',
                                                    'rejected' => 'badge-rejected',
                                                    'delivered' => 'badge-delivered',
                                                    'absent' => 'badge-absent',
                                                    'cancelled' => 'badge-cancelled',
                                                    default => 'badge-muted'
                                                };
                                                $locationMap = [
                                                    'Φοιτητική Εστία Α' => 'Φοιτητική Εστία Α',
                                                    'Οδός Πατησίων 42' => 'Οδός Πατησίων 42',
                                                    'Διαγώνιος 15' => 'Διαγώνιος 15'
                                                ];
                                                $locationName = $locationMap[$req['pickup_location']] ?? $req['pickup_location'];
                                            ?>
                                            <div class="commitment-card" style="
                                                background: var(--dp-color-panel);
                                                border: var(--dp-border-hairline) solid var(--dp-color-rule);
                                                border-radius: var(--dp-radius-panel);
                                                padding: var(--dp-spacing-md) var(--dp-spacing-lg);
                                                display: flex;
                                                flex-wrap: wrap;
                                                align-items: center;
                                                gap: var(--dp-spacing-md);
                                                transition: box-shadow var(--dp-motion-interaction) var(--dp-motion-easing), border-color var(--dp-motion-interaction) var(--dp-motion-easing);
                                            " onmouseover="this.style.boxShadow='var(--dp-shadow-hover)'; this.style.borderColor='#cbd5e1';" onmouseout="this.style.boxShadow=''; this.style.borderColor='var(--dp-color-rule)';">
                                                <div style="flex: 1; min-width: 250px;">
                                                    <h4 style="margin: 0 0 var(--dp-spacing-sm); font-family: var(--dp-font-display); font-size: 1rem; font-weight: 700; color: var(--dp-color-ink);"><?= htmlspecialchars($req['title']) ?></h4>
                                                    <div style="font-size: var(--dp-typography-small); color: var(--dp-color-ink-secondary); line-height: 1.6;">
                                                        👨‍🍳 Μάγειρας: <strong><?= htmlspecialchars($req['cook_name']) ?></strong><br>
                                                        📍 Σημείο: <strong><?= htmlspecialchars($locationName) ?></strong><br>
                                                        ⏰ Παραλαβή: <?= htmlspecialchars($req['pickup_time']) ?>
                                                    </div>
                                                </div>
                                                <span class="status-badge <?= $statusClass ?>" style="
                                                    display: inline-flex; align-items: center; gap: var(--dp-spacing-sm);
                                                    font-size: var(--dp-typography-label); font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
                                                    padding: 5px 10px; border-radius: var(--dp-radius-pill); white-space: nowrap;
                                                    flex-shrink: 0;
                                                ">
                                                    <?= $statusLabel ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php elseif (!empty($pendingRatings)): ?>
                                <p style="color: var(--dp-color-ink-secondary); text-align: center; padding: var(--dp-spacing-md);">Κανένα άλλο ενεργό αίτημα.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </section>
                <?php endif; ?>
            </section>

        <?php else: ?>
            <!-- NOT LOGGED IN: LANDING PAGE -->
            <section class="hero-split home-layout" role="main" aria-labelledby="hero-title">
                <!-- LEFT COLUMN -->
                <section class="hero-left" aria-labelledby="hero-title">
                    <h1 id="hero-title" class="hero-title">Μοιράσου το φαγητό σου, στήριξε τους συμφοιτητές σου</h1>
                    <p class="hero-subtitle">Το UniBite συνδέει φοιτητές που έχουν περισσευούμενο σπιτικό φαγητό με όσους το έχουν ανάγκη, γρήγορα και δωρεάν.</p>

                    <div class="features-list" role="list">
                        <!-- Feature 1: Credits -->
                        <div class="feature-item" role="listitem">
                            <div class="feature-badge credits" aria-hidden="true">💎</div>
                            <div class="feature-content">
                                <h3>5 Δωρεάν Credits στην Εκκίνηση</h3>
                                <p>Λάβε 5 πόντους άμεσα με το ακαδημαϊκό σου email για να ξεκινήσεις.</p>
                            </div>
                        </div>

                        <!-- Feature 2: Map -->
                        <div class="feature-item" role="listitem">
                            <div class="feature-badge live-map" aria-hidden="true">📍</div>
                            <div class="feature-content">
                                <h3>Χάρτης & Σημεία Παραλαβής</h3>
                                <p>Εντόπισε ζεστό φαγητό δίπλα στο τμήμα σου ή τις φοιτητικές εστίες.</p>
                            </div>
                        </div>

                        <!-- Feature 3: Rewards -->
                        <div class="feature-item" role="listitem">
                            <div class="feature-badge rewards" aria-hidden="true">⭐</div>
                            <div class="feature-content">
                                <h3>Ανταποδοτικό Σύστημα Αξιολόγησης</h3>
                                <p>Κέρδισε έξτρα +2 credits ανά μερίδα όταν οι συμφοιτητές σε βαθμολογούν με 4-5 αστέρια.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- RIGHT COLUMN - LOGIN CARD -->
                <aside class="hero-right" aria-labelledby="login-title">
                    <div class="login-card">
                        <div class="login-header">
                            <h2 id="login-title">Είσοδος στην Πλατφόρμα</h2>
                            <p>Συνδέσου με το όνομα χρήστη ή το email σου</p>
                        </div>

                        <!-- Ορατό πλαίσιο σφάλματος/ενημέρωσης -->
                        <div id="login-alert-box" style="display:none; padding:10px 14px; border-radius:var(--dp-radius-control); font-size:var(--dp-typography-small); margin-bottom:var(--dp-spacing-md); font-weight:600; text-align:center;"></div>
                        <div id="inline-login-error" style="display:none; background:rgba(220,38,38,0.1); color:var(--dp-color-danger); border:var(--dp-border-hairline) solid var(--dp-color-danger); padding:10px 14px; border-radius:var(--dp-radius-control); font-size:var(--dp-typography-small); margin-bottom:var(--dp-spacing-md); font-weight:600; text-align:center;"></div>

                        <form id="auth-form" onsubmit="event.preventDefault(); doLogin();" novalidate>
                            <div class="form-field">
                                <label class="form-label">Username ή Email</label>
                                <input type="text" id="login-identifier" class="form-control" required autocomplete="username">
                            </div>

                            <div class="form-field">
                                <label class="form-label">Κωδικός</label>
                                <input type="password" id="login-password" class="form-control" required autocomplete="current-password">
                            </div>

                            <button type="submit" id="login-btn" class="btn btn-primary" style="width: 100%; margin-top: var(--dp-spacing-sm);">Σύνδεση στο UniBite →</button>
                        </form>

                        <div class="signup-prompt">
                            <p>Δεν έχετε λογαριασμό; <a href="#" onclick="openAuthModal()">Εγγραφή στο UniBite</a></p>
                        </div>
                    </div>
                </aside>
            </section>
        <?php endif; ?>
    </main>

    <!-- TOAST -->
    <div id="toast" class="toast" role="alert" aria-live="polite"></div>

    <?php if (!$isLoggedIn): ?>
    <!-- MODAL for Registration (only for non-logged-in users) -->
    <div id="auth-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="modal">
            <div class="modal-header">
                <h2 id="modal-title">Σύνδεση στο UniBite</h2>
                <p>Χρησιμοποιήστε τα credentials σας για να συνδεθείτε.</p>
            </div>
            <form id="modal-auth-form" onsubmit="handleAuthSubmit(event)">
                <div id="inline-login-error" style="display:none; background:rgba(220,38,38,0.1); color:var(--dp-color-danger); border:var(--dp-border-hairline) solid var(--dp-color-danger); padding:10px 14px; border-radius:var(--dp-radius-control); font-size:var(--dp-typography-small); margin-bottom:var(--dp-spacing-md); font-weight:600; text-align:center;"></div>
                <div class="form-group" id="username-group">
                    <label class="form-label">Username ή Email</label>
                    <input type="text" id="modal-email" class="form-control" required autocomplete="username">
                </div>
                <div class="form-group" id="email-group" style="display: none;">
                    <label class="form-label">Email (για επικοινωνία)</label>
                    <input type="email" id="modal-email-reg" class="form-control" required autocomplete="email">
                </div>
                <div class="form-group">
                    <label class="form-label">Κωδικός Πρόσβασης</label>
                    <input type="password" id="modal-password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="form-group" style="background: var(--dp-color-ground-muted); padding: var(--dp-spacing-md); border-radius: var(--dp-radius-control); border: var(--dp-border-hairline) solid var(--dp-color-rule);">
                    <label style="font-weight: 600; color: var(--dp-color-accent);">🎓 Όλοι οι φοιτητές εγγράφονται ως: Φοιτητής + Μάγειρας</label>
                </div>
                <div class="modal-footer">
                    <button type="submit" id="modal-auth-submit-btn" class="btn btn-primary">Σύνδεση</button>
                    <button type="button" class="btn btn-outline" onclick="closeAuthModal()">Άκυρο</button>
                </div>
            </form>
            <p style="margin-top: var(--dp-spacing-md); text-align: center; font-size: var(--dp-typography-small);">
                <span id="auth-switch-text">Δεν έχετε λογαριασμό;</span>
                <a href="#" id="auth-switch-link" onclick="toggleAuthMode(event)" style="color: var(--dp-color-accent); font-weight: 600;">Εγγραφή</a>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Rating Modal στο Dashboard -->
    <div id="dashboard-rating-modal" class="modal-overlay" style="display: none;">
        <div class="modal" style="max-width: 480px; width: 90%;">
            <div class="modal-header">
                <h2 id="rating-modal-title">⭐ Αξιολόγηση Γεύματος</h2>
            </div>
            <form id="dashboard-rating-form" onsubmit="submitDashboardRating(event)">
                <input type="hidden" id="dash-rating-offer-id">
                
                <div class="form-group">
                    <label class="form-label">Βαθμολογία (1-5)</label>
                    <select id="dash-rating-stars" class="form-control" required>
                        <option value="5" selected>⭐⭐⭐⭐⭐ - Εξαιρετικό (+2 πόντοι στον μάγειρα)</option>
                        <option value="4">⭐⭐⭐⭐ - Πολύ καλό (+2 πόντοι στον μάγειρα)</option>
                        <option value="3">⭐⭐⭐ - Μέτριο (+1 πόντος στον μάγειρα)</option>
                        <option value="2">⭐⭐ - Ανάγκη βελτίωσης (+1 πόντος στον μάγειρα)</option>
                        <option value="1">⭐ - Κακό (+1 πόντος στον μάγειρα)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Σχόλιο (προαιρετικό)</label>
                    <textarea id="dash-rating-comment" class="form-control" rows="3" placeholder="Πώς ήταν η γεύση, η μερίδα και η παράδοση;"></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeRatingModal()">Άκυρο</button>
                    <button type="submit" id="dash-rating-submit-btn" class="btn btn-primary">Υποβολή Αξιολόγησης</button>
                </div>
            </form>
        </div>
    </div>

    <script src="Shared/js/auth.js?v=<?= time() ?>"></script>
    <script>
        let isLoginMode = true;

        // Toast notification
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + type + ' show';
            setTimeout(() => { toast.classList.remove('show'); }, 4000);
        }

        // Modal controls (for registration)
        function openAuthModal(mode) {
            isLoginMode = (mode === 'login');
            document.getElementById('auth-modal').classList.add('active');
            updateModalUI();
        }
        function closeAuthModal() {
            document.getElementById('auth-modal').classList.remove('active');
        }
        function toggleAuthMode(e) {
            e.preventDefault();
            isLoginMode = !isLoginMode;
            updateModalUI();
        }
        function updateModalUI() {
            const title = document.getElementById('modal-title');
            const submitBtn = document.getElementById('modal-auth-submit-btn');
            const loginGroup = document.getElementById('username-group');
            const emailGroup = document.getElementById('email-group');
            const switchText = document.getElementById('auth-switch-text');
            const switchLink = document.getElementById('auth-switch-link');

            if (isLoginMode) {
                title.textContent = 'Σύνδεση στο UniBite';
                submitBtn.textContent = 'Σύνδεση';
                loginGroup.style.display = 'block';
                emailGroup.style.display = 'none';
                switchText.textContent = 'Δεν έχετε λογαριασμό;';
                switchLink.textContent = 'Εγγραφή';
            } else {
                title.textContent = 'Εγγραφή στο UniBite (Φοιτητής + Μάγειρας)';
                submitBtn.textContent = 'Εγγραφή';
                loginGroup.style.display = 'block';
                emailGroup.style.display = 'block';
                switchText.textContent = 'Έχετε ήδη λογαριασμό;';
                switchLink.textContent = 'Σύνδεση';
            }
        }

        // Handle login from embedded form (right column) and modal
        async function handleAuthSubmit(e) {
            if (e) e.preventDefault();
            const form = e.target;
            const errBox = form.querySelector('#inline-login-error');
            if (errBox) errBox.style.display = 'none';

            const isModal = form.id === 'modal-auth-form';
            const identifier = isModal ? document.getElementById('modal-email').value.trim() : document.getElementById('login-identifier').value.trim();
            const password = isModal ? document.getElementById('modal-password').value : document.getElementById('login-password').value;

            const submitBtn = document.getElementById(isModal ? 'modal-auth-submit-btn' : 'login-btn');
            const originalText = submitBtn ? submitBtn.textContent : 'Σύνδεση';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Έλεγχος...';
            }

            try {
                const result = await UniBiteAuth.apiCall('Shared/api/login.php', {
                    email: identifier,
                    username: identifier,
                    password: password
                }, 'POST');

                if (result.success) {
                    showToast('Επιτυχής σύνδεση! Μεταφορά...', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 300);
                } else {
                    const msg = result.error || 'Σφάλμα σύνδεσης.';
                    if (errBox) {
                        errBox.textContent = msg;
                        errBox.style.display = 'block';
                    } else {
                        showToast(msg, 'error');
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    }
                }
            } catch (err) {
                if (errBox) {
                    errBox.textContent = 'Σφάλμα επικοινωνίας με τον διακομιστή.';
                    errBox.style.display = 'block';
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            }
        }

        // Load dashboard stats when logged in
        async function loadDashboardStats() {
            try {
                const result = await UniBiteAuth.apiCall('Shared/api/get_user_dashboard.php', {}, 'GET');
                if (result.success && result.stats) {
                    const s = result.stats;
                    // Points
                    const pointsEl = document.getElementById('dashboard-points');
                    if (pointsEl) pointsEl.textContent = s.points;
                    // Stats (cook)
                    const offersEl = document.getElementById('stat-offers');
                    if (offersEl) offersEl.textContent = s.offers_created;
                    const portionsEl = document.getElementById('stat-portions');
                    if (portionsEl) portionsEl.textContent = s.portions_offered;
                    const ratingEl = document.getElementById('stat-rating');
                    if (ratingEl) ratingEl.textContent = s.avg_rating.toFixed(1);
                    // Stats (consumer)
                    const mealsEl = document.getElementById('stat-meals');
                    if (mealsEl) mealsEl.textContent = s.meals_received;
                }
            } catch (err) {
                console.warn('Failed to load dashboard stats:', err);
            }
        }

        // Load commitments (requests) for consumer dashboard
        async function loadCommitments() {
            const loadingEl = document.getElementById('commitments-loading');
            const contentEl = document.getElementById('commitments-content');
            if (!loadingEl || !contentEl) return;

            try {
                const result = await UniBiteAuth.apiCall('Consumer/api/get_my_requests.php', {}, 'GET');
                if (!result.success) {
                    loadingEl.textContent = 'Σφάλμα φόρτωσης: ' + (result.error || 'Άγνωστο σφάλμα');
                    return;
                }

                const requests = result.requests || [];
                loadingEl.style.display = 'none';
                contentEl.style.display = 'block';

                if (requests.length === 0) {
                    contentEl.innerHTML = '<p style="color: var(--dp-color-ink-secondary); text-align: center; padding: var(--dp-spacing-xl);">Δεν έχετε καμία δέσμευση ακόμα.</p>';
                    return;
                }

                // Separate pending ratings (delivered but not rated)
                const pendingRatings = requests.filter(r => r.status === 'delivered' && r.is_rated === 0);
                const otherRequests = requests.filter(r => !(r.status === 'delivered' && r.is_rated === 0));

                let html = '';

                // Warning banner for pending ratings (48-hour penalty)
                if (pendingRatings.length > 0) {
                    pendingRatings.forEach(req => {
                        const hoursSinceDelivery = parseInt(req.hours_since_delivery) || 0;
                        const hoursRemaining = 48 - hoursSinceDelivery;
                        // Parse updated_at as UTC to avoid timezone issues (DB stores in UTC+2, JS parses as local)
                        const parseUTC = (dt) => {
                            const [datePart, timePart] = dt.split(' ');
                            const [y, m, d] = datePart.split('-').map(Number);
                            const [h, mi, s] = timePart.split(':').map(Number);
                            return Date.UTC(y, m - 1, d, h, mi, s);
                        };
                        const deadline = new Date(parseUTC(req.updated_at) + 48 * 60 * 60 * 1000);
                        const deadlineStr = deadline.toLocaleString('el-GR', {
                            day: '2-digit', month: '2-digit', year: 'numeric',
                            hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Athens'
                        });
                        const isPenalized = req.penalized === 1 || req.penalized === '1';
                        let timeStr = '';
                        if (hoursRemaining > 0) {
                            if (hoursRemaining >= 24) {
                                const days = Math.floor(hoursRemaining / 24);
                                const hrs = hoursRemaining % 24;
                                timeStr = `${days} ${days === 1 ? 'ημέρα' : 'ημέρες'}${hrs > 0 ? ' ' + hrs + ' ώρες' : ''}`;
                            } else {
                                timeStr = `${hoursRemaining} ${hoursRemaining === 1 ? 'ώρα' : 'ώρες'}`;
                            }
                        } else {
                            timeStr = 'ήδη παρήλθε';
                        }

                        html += `
                            <div class="penalty-warning-banner" style="
                                background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
                                border: var(--dp-border-hairline) solid var(--dp-color-warning);
                                border-radius: var(--dp-radius-panel);
                                padding: var(--dp-spacing-md) var(--dp-spacing-lg);
                                margin-bottom: var(--dp-spacing-md);
                                display: flex;
                                flex-wrap: wrap;
                                align-items: center;
                                gap: var(--dp-spacing-md);
                            ">
                                <div style="flex: 1; min-width: 280px;">
                                    <div style="display: flex; align-items: center; gap: var(--dp-spacing-sm); margin-bottom: var(--dp-spacing-sm);">
                                        <span style="font-size: 1.25rem;">⚠️</span>
                                        <strong style="color: #92400E; font-size: 1rem;">Εκκρεμεί Αξιολόγηση</strong>
                                    </div>
                                    <div style="color: #78350F; font-size: var(--dp-typography-small); line-height: 1.5;">
                                        <strong>${escapeHtml(req.title)}</strong> — Μάγειρας: ${escapeHtml(req.cook_name)}<br>
                                        ${isPenalized
                                            ? `❌ <strong>Έληξε η προθεσμία αξιολόγησης (${deadlineStr}) — Σας αφαιρέθηκε 1 πόντος ποινής.</strong>`
                                            : `Απομένουν <strong>${timeStr}</strong> (έως ${deadlineStr}).<br>Αν δεν αξιολογήσετε εντός 48 ωρών από την παραλαβή, θα σας αφαιρεθεί <strong>1 credit ποινής (-1 πόντους)</strong>.`}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }

                // Regular commitments list
                if (otherRequests.length > 0) {
                    html += `
                        <div style="margin-top: ${pendingRatings.length > 0 ? 'var(--dp-spacing-xl)' : '0'};">
                            <h4 style="margin: 0 0 var(--dp-spacing-md); font-size: var(--dp-typography-small); font-weight: 600; color: var(--dp-color-ink-secondary); text-transform: uppercase; letter-spacing: 0.04em;">Όλες οι Δεσμεύσεις</h4>
                            <div style="display: flex; flex-direction: column; gap: var(--dp-spacing-md);">
                    `;

                    otherRequests.forEach(req => {
                        const statusLabel = translateStatus(req.status);
                        const statusClass = getStatusBadgeClass(req.status);
                        const locationName = getLocationName(req.pickup_location);

                        html += `
                            <div class="commitment-card" style="
                                background: var(--dp-color-panel);
                                border: var(--dp-border-hairline) solid var(--dp-color-rule);
                                border-radius: var(--dp-radius-panel);
                                padding: var(--dp-spacing-md) var(--dp-spacing-lg);
                                display: flex;
                                flex-wrap: wrap;
                                align-items: center;
                                gap: var(--dp-spacing-md);
                                transition: box-shadow var(--dp-motion-interaction) var(--dp-motion-easing), border-color var(--dp-motion-interaction) var(--dp-motion-easing);
                            " onmouseover="this.style.boxShadow='var(--dp-shadow-hover)'; this.style.borderColor='#cbd5e1';" onmouseout="this.style.boxShadow=''; this.style.borderColor='var(--dp-color-rule)';">
                                <div style="flex: 1; min-width: 250px;">
                                    <h4 style="margin: 0 0 var(--dp-spacing-sm); font-family: var(--dp-font-display); font-size: 1rem; font-weight: 700; color: var(--dp-color-ink);">${escapeHtml(req.title)}</h4>
                                    <div style="font-size: var(--dp-typography-small); color: var(--dp-color-ink-secondary); line-height: 1.6;">
                                        👨‍🍳 Μάγειρας: <strong>${escapeHtml(req.cook_name)}</strong><br>
                                        📍 Σημείο: <strong>${escapeHtml(locationName)}</strong><br>
                                        ⏰ Παραλαβή: ${req.pickup_time}
                                    </div>
                                </div>
                                <span class="status-badge ${statusClass}" style="
                                    display: inline-flex; align-items: center; gap: var(--dp-spacing-sm);
                                    font-size: var(--dp-typography-label); font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
                                    padding: 5px 10px; border-radius: var(--dp-radius-pill); white-space: nowrap;
                                    flex-shrink: 0;
                                ">
                                    ${statusLabel}
                                </span>
                            </div>
                        `;
                    });

                    html += '</div></div>';
                } else if (pendingRatings.length > 0) {
                    // Only pending ratings, no other requests
                    html += '<p style="color: var(--dp-color-ink-secondary); text-align: center; padding: var(--dp-spacing-md);">Κανένα άλλο ενεργό αίτημα.</p>';
                }

                contentEl.innerHTML = html;

            } catch (err) {
                console.error('Failed to load commitments:', err);
                loadingEl.textContent = 'Σφάλμα φόρτωσης δεσμεύσεων. Δοκιμάστε ξανά.';
            }
        }

        // Status translation
        function translateStatus(status) {
            switch(status) {
                case 'pending': return 'Εκκρεμεί Έγκριση';
                case 'approved': return 'Εγκρίθηκε';
                case 'rejected': return 'Απορρίφθηκε';
                case 'delivered': return 'Παραδόθηκε';
                case 'absent': return 'Απουσία';
                case 'cancelled': return 'Ακυρωμένο';
                default: return status;
            }
        }

        // Status badge classes with colors
        function getStatusBadgeClass(status) {
            switch(status) {
                case 'pending': return 'badge-pending';
                case 'approved': return 'badge-approved';
                case 'rejected': return 'badge-rejected';
                case 'delivered': return 'badge-delivered';
                case 'absent': return 'badge-absent';
                case 'cancelled': return 'badge-cancelled';
                default: return 'badge-muted';
            }
        }

        // Location name mapping (reuse from consumer.js if available)
        function getLocationName(locationId) {
            const locationMap = {
                'Φοιτητική Εστία Α': 'Φοιτητική Εστία Α',
                'Οδός Πατησίων 42': 'Οδός Πατησίων 42',
                'Διαγώνιος 15': 'Διαγώνιος 15'
            };
            return locationMap[locationId] || locationId;
        }

        // Dashboard Rating Modal functions
        function openRatingModal(offerId, title) {
            document.getElementById('dash-rating-offer-id').value = offerId;
            if (title) {
                document.getElementById('rating-modal-title').textContent = '⭐ Αξιολόγηση: ' + title;
            }
            openModal('dashboard-rating-modal');
        }

        // Expose on window for global access and event delegation
        window.openRatingModal = openRatingModal;

        function closeRatingModal() {
            closeModal('dashboard-rating-modal');
        }

        window.closeRatingModal = closeRatingModal;

        async function submitDashboardRating(e) {
            e.preventDefault();
            const offerId = parseInt(document.getElementById('dash-rating-offer-id').value);
            const rating = parseInt(document.getElementById('dash-rating-stars').value);
            const comment = document.getElementById('dash-rating-comment').value.trim();

            const submitBtn = document.getElementById('dash-rating-submit-btn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Υποβολή...';

            try {
                const result = await UniBiteAuth.apiCall('Consumer/api/submit_rating.php', {
                    offer_id: offerId,
                    rating: rating,
                    comment: comment
                }, 'POST');

                if (result.success) {
                    UniBiteAuth.showToast(result.message || 'Η αξιολόγηση καταχωρήθηκε επιτυχώς!', 'success');
                    closeRatingModal();
                    setTimeout(() => { window.location.reload(); }, 800);
                } else {
                    UniBiteAuth.showToast(result.error || 'Σφάλμα υποβολής.', 'error');
                }
            } catch (err) {
                UniBiteAuth.showToast('Σφάλμα δικτύου κατά την αξιολόγηση.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Υποβολή Αξιολόγησης';
            }
        }

        // HTML escape helper
        function escapeHtml(str) {
            return String(str).replace(/[&<>'\"]/g, function(tag) {
                if (tag === '&') return '&';
                if (tag === '<') return '<';
                if (tag === '>') return '>';
                if (tag === "'") return '&apos;';
                if (tag === '"') return '"';
                return tag;
            });
        }

        // Initialize dashboard on load
        document.addEventListener('DOMContentLoaded', () => {

            // Close rating modal on overlay click -- DISABLED FOR DEBUGGING
            // document.getElementById('dashboard-rating-modal')?.addEventListener('click', function(e) {
            //     if (e.target === this && this.dataset.justOpened !== 'true') closeRatingModal();
            // });

            // Escape key support for rating modal
            // document.addEventListener('keydown', (e) => {
            //     if (e.key === 'Escape') closeRatingModal();
            // });
        });

        async function logout() {
            const result = await UniBiteAuth.apiCall('Shared/api/logout.php', {}, 'POST');
            if (result.success) window.location.reload();
        }

        // Close modal on overlay click
        document.getElementById('auth-modal')?.addEventListener('click', function(e) {
            if (e.target === this) closeAuthModal();
        });

        // Escape key support for modal
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeAuthModal();
        });

        // Initialize dashboard on load
        document.addEventListener('DOMContentLoaded', () => {

            // Close rating modal on overlay click -- DISABLED FOR DEBUGGING
            // document.getElementById('dashboard-rating-modal')?.addEventListener('click', function(e) {
            //     if (e.target === this && this.dataset.justOpened !== 'true') closeRatingModal();
            // });

            // Escape key support for rating modal
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeRatingModal();
            });

            // Event delegation for rating buttons (mobile-friendly, works with dynamic content)
            document.body.addEventListener('click', (e) => {
                const ratingBtn = e.target.closest('[data-rate-offer-id]');
                if (ratingBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    const offerId = ratingBtn.getAttribute('data-rate-offer-id');
                    const title = ratingBtn.getAttribute('data-rate-title');
                    if (offerId && window.openRatingModal) {
                        window.openRatingModal(parseInt(offerId, 10), title);
                    }
                }
            }, { passive: false });
        });

        // --- doLogin() για την κάρτα σύνδεσης (hero-right) - GLOBAL SCOPE ---
        async function doLogin() {
            const errBox = document.getElementById('inline-login-error');
            const submitBtn = document.getElementById('login-btn');
            const identifier = document.getElementById('login-identifier').value.trim();
            const password = document.getElementById('login-password').value;

            function showError(msg) {
                if (errBox) {
                    errBox.textContent = msg;
                    errBox.style.display = 'block';
                }
            }
            function showSuccess(msg) {
                if (errBox) {
                    errBox.style.background = '#DCFCE7';
                    errBox.style.color = '#166534';
                    errBox.style.border = '1px solid #4ADE80';
                    errBox.textContent = msg;
                    errBox.style.display = 'block';
                }
            }
            function hideError() {
                if (errBox) {
                    errBox.style.display = 'none';
                    errBox.style.background = '#FEE2E2';
                    errBox.style.color = '#991B1B';
                    errBox.style.border = '1px solid #F87171';
                }
            }

            if (!identifier || !password) {
                showError('Παρακαλώ συμπληρώστε όνομα χρήστη/email και κωδικό.');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Έλεγχος στοιχείων...';
            hideError();

            const result = await UniBiteAuth.login(identifier, password);

            if (!result.success) {
                showError(result.error || 'Λανθασμένα στοιχεία σύνδεσης.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Σύνδεση στο UniBite →';
            }
        }
    </script>
</body>
</html>