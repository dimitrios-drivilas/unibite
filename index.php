<?php
// index.php
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
    <link rel="stylesheet" href="Shared/css/common.css">
    <style>
        /* ===== RESET & BASE ===== */
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: #F8FAFC; color: #1E293B; line-height: 1.6; }

        /* ===== NAVBAR ===== */
        .navbar {
            position: sticky; top: 0; z-index: 100;
            background: #0F172A; color: #E2E8F0;
            padding: 16px 48px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 24px;
        }
        .navbar-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: inherit; }
        .navbar-brand .logo-uni { font-weight: 800; font-size: 1.35rem; color: #FFFFFF; letter-spacing: -0.02em; }
        .navbar-brand .logo-bite { font-weight: 800; font-size: 1.35rem; color: #FB923C; letter-spacing: -0.02em; }
        .navbar-badge {
            background: rgba(251, 146, 60, 0.15); color: #FB923C;
            font-size: 0.65rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
            padding: 3px 8px; border-radius: 999px; white-space: nowrap;
        }
        .navbar-links { display: flex; align-items: center; gap: 32px; list-style: none; margin: 0; padding: 0; }
        .navbar-links a { color: #94A3B8; text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: color 0.2s; }
        .navbar-links a:hover { color: #FFFFFF; }
        .navbar-cta {
            background: #EA580C; color: #FFFFFF;
            font-weight: 600; font-size: 0.875rem;
            padding: 10px 22px; border-radius: 8px; border: none; cursor: pointer;
            text-decoration: none; transition: background 0.2s, transform 0.1s;
        }
        .navbar-cta:hover { background: #FB923C; transform: translateY(-1px); }
        .navbar-cta:active { transform: translateY(0); }

        /* ===== HERO SPLIT SCREEN ===== */
        .hero-split {
            display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 50px; align-items: center;
            max-width: 1280px; margin: 0 auto; padding: 80px 48px; min-height: calc(100vh - 72px);
        }
        .hero-left { padding-right: 24px; }
        .hero-title { font-size: clamp(2.25rem, 5vw, 3.5rem); font-weight: 800; line-height: 1.15; color: #0F172A; margin: 0 0 1.5rem; letter-spacing: -0.03em; }
        .hero-subtitle { font-size: 1.15rem; color: #64748B; margin: 0 0 3rem; max-width: 520px; line-height: 1.7; }

        /* Feature Items (horizontal) */
        .features-list { display: flex; flex-direction: column; gap: 1.5rem; margin-top: 2.5rem; }
        .feature-item {
            display: flex; align-items: flex-start; gap: 1.25rem;
            background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px;
            padding: 1.25rem 1.5rem; transition: box-shadow 0.2s, border-color 0.2s, transform 0.15s;
        }
        .feature-item:hover { box-shadow: 0 10px 30px -10px rgba(15, 23, 42, 0.12); border-color: #CBD5E1; transform: translateY(-2px); }
        .feature-badge {
            flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center;
            width: 44px; height: 44px; border-radius: 12px; font-size: 0.65rem; font-weight: 700;
            letter-spacing: 0.04em; text-transform: uppercase; color: #FFFFFF;
        }
        .feature-badge.credits { background: linear-gradient(135deg, #0D9488, #14B8A6); } /* Teal */
        .feature-badge.live-map { background: linear-gradient(135deg, #2563EB, #3B82F6); } /* Blue */
        .feature-badge.rewards { background: linear-gradient(135deg, #D97706, #F59E0B); } /* Amber */
        .feature-content { flex: 1; min-width: 0; }
        .feature-content h3 { margin: 0 0 0.35rem; font-size: 1.05rem; font-weight: 700; color: #0F172A; line-height: 1.35; }
        .feature-content p { margin: 0; font-size: 0.9rem; color: #64748B; line-height: 1.55; }

        /* ===== LOGIN CARD (Right Column) ===== */
        .login-card {
            background: #FFFFFF; border-radius: 20px; border: 1px solid #E2E8F0;
            box-shadow: 0 4px 24px rgba(15, 23, 42, 0.06), 0 1px 3px rgba(15, 23, 42, 0.05);
            padding: 2.5rem; position: relative; overflow: hidden;
        }
        .login-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, #0F172A, #1E293B, #334155);
        }
        .login-header { text-align: center; margin-bottom: 2rem; }
        .login-header h2 { margin: 0 0 0.5rem; font-size: 1.5rem; font-weight: 800; color: #0F172A; letter-spacing: -0.02em; }
        .login-header p { margin: 0; font-size: 0.9rem; color: #64748B; }
        .login-form { display: flex; flex-direction: column; gap: 1.25rem; }
        .form-field { display: flex; flex-direction: column; gap: 0.5rem; }
        .form-field label { font-size: 0.8rem; font-weight: 600; color: #334155; text-transform: uppercase; letter-spacing: 0.04em; }
        .form-field input {
            width: 100%; padding: 14px 16px; font-size: 1rem; color: #0F172A;
            background: #F8FAFC; border: 1.5px solid #E2E8F0; border-radius: 10px;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }
        .form-field input::placeholder { color: #94A3B8; }
        .form-field input:focus { outline: none; border-color: #EA580C; box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.12); background: #FFFFFF; }
        .form-field input:invalid:not(:placeholder-shown) { border-color: #EF4444; }

        .form-options { display: flex; align-items: center; justify-content: space-between; margin-top: -0.25rem; }
        .checkbox-wrapper { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
        .checkbox-wrapper input[type="checkbox"] {
            width: 18px; height: 18px; accent-color: #EA580C; cursor: pointer;
        }
        .checkbox-wrapper span { font-size: 0.85rem; color: #475569; }
        .forgot-link { font-size: 0.85rem; color: #EA580C; text-decoration: none; font-weight: 500; transition: color 0.2s; }
        .forgot-link:hover { color: #FB923C; text-decoration: underline; }

        .btn-login {
            width: 100%; margin-top: 0.5rem;
            background: #0F172A; color: #FFFFFF; font-weight: 700; font-size: 1rem;
            padding: 15px 24px; border: none; border-radius: 10px; cursor: pointer;
            transition: background 0.2s, transform 0.1s, box-shadow 0.2s;
        }
        .btn-login:hover { background: #1E293B; box-shadow: 0 6px 20px rgba(15, 23, 42, 0.2); }
        .btn-login:active { transform: scale(0.98); }
        .btn-login:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .signup-prompt { text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #E2E8F0; }
        .signup-prompt p { margin: 0; font-size: 0.9rem; color: #64748B; }
        .signup-prompt a { color: #EA580C; font-weight: 600; text-decoration: none; margin-left: 4px; transition: color 0.2s; }
        .signup-prompt a:hover { color: #FB923C; text-decoration: underline; }

        /* Toast (reusing from common.css but ensure it works) */
        .toast { position: fixed; bottom: 24px; right: 24px; z-index: 1000;
            background: #0F172A; color: #FFFFFF; padding: 14px 20px; border-radius: 10px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25); font-size: 0.9rem; font-weight: 500;
            animation: slideIn 0.3s ease; display: none; }
        .toast.success { background: #0D9488; }
        .toast.error { background: #DC2626; }
        .toast.show { display: block; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* ===== STATUS BADGES ===== */
        .badge-pending { background: #FEF3C7; color: #B45309; border: 1px solid #FCD34D; }
        .badge-approved { background: #DBEAFE; color: #1D4ED8; border: 1px solid #93C5FD; }
        .badge-rejected { background: #FEE2E2; color: #B91C1C; border: 1px solid #FCA5A5; }
        .badge-delivered { background: #D1FAE5; color: #065F46; border: 1px solid #6EE7B7; }
        .badge-absent { background: #F3F4F6; color: #374151; border: 1px solid #D1D5DB; }
        .badge-cancelled { background: #F3F4F6; color: #6B7280; border: 1px solid #D1D5DB; }
        .badge-default { background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; }

        /* ===== RATING MODAL ===== */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5);
            display: none; align-items: center; justify-content: center;
            z-index: 2000; padding: 24px; animation: fadeIn 0.2s ease;
        }
        .modal-overlay.active { display: flex; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .modal {
            background: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0;
            box-shadow: 0 20px 50px -12px rgba(15, 23, 42, 0.25);
            width: 100%; max-width: 420px; padding: 1.5rem;
            animation: slideUp 0.25s ease;
        }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .modal h2 { margin: 0 0 1rem; font-size: 1.25rem; font-weight: 800; color: #0F172A; text-align: center; }
        .modal p { margin: 0 0 1.25rem; font-size: 0.9rem; color: #64748B; text-align: center; }

        .star-rating { display: flex; direction: rtl; justify-content: center; gap: 8px; margin-bottom: 1rem; }
        .star-rating input { display: none; }
        .star-rating label {
            font-size: 2rem; cursor: pointer; color: #E2E8F0;
            transition: color 0.15s, transform 0.1s;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label { color: #FBBF24; transform: scale(1.1); }

        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-size: 0.85rem; font-weight: 600; color: #334155; }
        .form-group textarea {
            width: 100%; min-height: 80px; padding: 0.75rem 1rem; font-size: 0.95rem;
            font-family: inherit; color: #0F172A; background: #F8FAFC;
            border: 1.5px solid #E2E8F0; border-radius: 10px; resize: vertical;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }
        .form-group textarea::placeholder { color: #94A3B8; }
        .form-group textarea:focus { outline: none; border-color: #EA580C; box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.12); background: #FFFFFF; }

        .modal-actions { display: flex; gap: 0.75rem; margin-top: 1.25rem; }
        .modal-actions .btn { flex: 1; padding: 0.75rem 1rem; font-size: 0.9rem; font-weight: 600; border-radius: 8px; cursor: pointer; transition: background 0.2s, transform 0.1s; }
        .modal-actions .btn-primary { background: #EA580C; color: #FFFFFF; border: none; }
        .modal-actions .btn-primary:hover { background: #FB923C; }
        .modal-actions .btn-outline { background: transparent; color: #64748B; border: 1.5px solid #E2E8F0; }
        .modal-actions .btn-outline:hover { background: #F8FAFC; border-color: #CBD5E1; color: #334155; }

        /* ===== MOBILE RESPONSIVE ===== */
        @media (max-width: 960px) {
            .navbar { padding: 14px 20px; }
            .navbar-links { display: none; } /* Could add hamburger menu later */
            .hero-split { grid-template-columns: 1fr; gap: 40px; padding: 48px 20px; text-align: center; }
            .hero-left { padding-right: 0; max-width: 100%; }
            .hero-subtitle { max-width: 100%; margin-left: auto; margin-right: auto; }
            .feature-item { flex-direction: column; align-items: center; text-align: center; gap: 1rem; }
            .feature-badge { width: 40px; height: 40px; }
            .login-card { padding: 2rem 1.5rem; }
        }
        @media (max-width: 640px) {
            .hero-title { font-size: 2rem; }
            .hero-split { padding: 32px 16px; }
            .login-card { padding: 1.5rem 1.25rem; }
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar" role="navigation" aria-label="Main navigation">
        <a href="index.php" class="navbar-brand" aria-label="UniBite Home">
            <span class="logo-uni">Uni</span><span class="logo-bite">Bite</span>
            <span class="navbar-badge">CAMPUS FOOD</span>
        </a>
        <?php if ($isLoggedIn): ?>
            <span class="navbar-user" style="display:flex; align-items:center; gap:12px; color:#94A3B8; font-size:0.85rem; font-weight:500;">
                <span style="color:#FB923C;">●</span> Φοιτητής: <strong><?= htmlspecialchars($username) ?></strong>
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
            <button class="navbar-cta" onclick="logout()" style="background:#DC2626;">Αποσύνδεση</button>
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
            <section class="dashboard" style="max-width: 1200px; margin: 0 auto; padding: 40px 24px;">
                <!-- Points Card -->
                <div class="card points-card" style="margin-bottom: 1.5rem; text-align: center; border-left: 5px solid #FB923C; background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%);">
                    <h2 style="margin: 0 0 0.5rem; font-size: 1.25rem; color: #9A3412;">💎 Οι Πόντοι σου</h2>
                    <div id="dashboard-points" style="font-size: 3.5rem; font-weight: 800; color: #EA580C; line-height: 1.2;"><?= $current_points ?></div>
                    <p style="margin: 0.75rem 0 0; color: #9A3412; font-size: 0.95rem;">Χρειάζεσαι τουλάχιστον <strong>1 πόντου</strong> για δέσμευση μερίδας</p>
                </div>

                <!-- Stats Grid + Action Cards -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <!-- Stats Card -->
                    <div class="card stats-card" style="padding: 1.5rem;">
                        <h3 style="margin: 0 0 1.25rem; font-size: 1.1rem; color: #0F172A; font-weight: 700; display:flex; align-items:center; gap:0.5rem;">
                            <span style="font-size:1.25rem;">📊</span> Συνολικά Στατιστικά
                        </h3>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                            <?php if ($hasCook): ?>
                            <div class="stat-item" style="background:#F8FAFC; border-radius:12px; padding:1rem; border:1px solid #E2E8F0;">
                                <div style="font-size:1.75rem; font-weight:700; color:#EA580C;" id="stat-offers"><?= $cook_offers_count ?></div>
                                <div style="font-size:0.8rem; color:#64748B; margin-top:0.25rem;">Αγγελίες που Δημιούργησα</div>
                            </div>
                            <div class="stat-item" style="background:#F8FAFC; border-radius:12px; padding:1rem; border:1px solid #E2E8F0;">
                                <div style="font-size:1.75rem; font-weight:700; color:#0D9488;" id="stat-portions"><?= $cook_portions_count ?></div>
                                <div style="font-size:0.8rem; color:#64748B; margin-top:0.25rem;">Μερίδες που Προσέφερα</div>
                            </div>
                            <div class="stat-item" style="background:#F8FAFC; border-radius:12px; padding:1rem; border:1px solid #E2E8F0;">
                                <div style="font-size:1.75rem; font-weight:700; color:#D97706;" id="stat-rating"><?= $avg_rating ?></div>
                                <div style="font-size:0.8rem; color:#64748B; margin-top:0.25rem;">Μέση Βαθμολογία (⭐)</div>
                            </div>
                            <?php endif; ?>
                            <?php if ($hasConsumer): ?>
                            <div class="stat-item" style="background:#F8FAFC; border-radius:12px; padding:1rem; border:1px solid #E2E8F0;">
                                <div style="font-size:1.75rem; font-weight:700; color:#2563EB;" id="stat-meals"><?= $consumer_delivered_count ?></div>
                                <div style="font-size:0.8rem; color:#64748B; margin-top:0.25rem;">Γεύματα που Πέρασα</div>
                            </div>
                            <?php endif; ?>
                            <?php if (!$hasCook && !$hasConsumer): ?>
                            <div style="grid-column:1/-1; text-align:center; color:#64748B; padding:2rem;">
                                Δεν υπάρχουν διαθέσιμα στατιστικά για τον ρόλο σας.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Action Cards -->
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php if ($hasCook): ?>
                        <a href="Cook/cook.php" class="action-card" style="display:flex; align-items:center; gap:1.5rem; padding:1.5rem; background:#FFFFFF; border:1px solid #E2E8F0; border-radius:16px; text-decoration:none; color:inherit; transition:box-shadow 0.2s, transform 0.15s, border-color 0.2s;" onmouseover="this.style.boxShadow='0 12px 32px -8px rgba(15,23,42,0.15)'; this.style.transform='translateY(-2px)'; this.style.borderColor='#FB923C';" onmouseout="this.style.boxShadow=''; this.style.transform=''; this.style.borderColor='#E2E8F0';">
                            <div style="flex-shrink:0; width:64px; height:64px; border-radius:16px; background:linear-gradient(135deg,#FB923C,#EA580C); display:flex; align-items:center; justify-content:center; font-size:1.75rem;">🍳</div>
                            <div>
                                <h4 style="margin:0 0 0.25rem; font-size:1.1rem; font-weight:700; color:#0F172A;">Προσφορά Φαγητού</h4>
                                <p style="margin:0; font-size:0.9rem; color:#64748B;">Δημιούργησε νέα αγγελία, διαχειρίσετε τις δικές σας</p>
                            </div>
                        </a>
                        <?php endif; ?>
                        <?php if ($hasConsumer): ?>
                        <a href="Consumer/consumer.php" class="action-card" style="display:flex; align-items:center; gap:1.5rem; padding:1.5rem; background:#FFFFFF; border:1px solid #E2E8F0; border-radius:16px; text-decoration:none; color:inherit; transition:box-shadow 0.2s, transform 0.15s, border-color 0.2s;" onmouseover="this.style.boxShadow='0 12px 32px -8px rgba(15,23,42,0.15)'; this.style.transform='translateY(-2px)'; this.style.borderColor='#2563EB';" onmouseout="this.style.boxShadow=''; this.style.transform=''; this.style.borderColor='#E2E8F0';">
                            <div style="flex-shrink:0; width:64px; height:64px; border-radius:16px; background:linear-gradient(135deg,#3B82F6,#2563EB); display:flex; align-items:center; justify-content:center; font-size:1.75rem;">🔍</div>
                            <div>
                                <h4 style="margin:0 0 0.25rem; font-size:1.1rem; font-weight:700; color:#0F172A;">Εξερεύνηση Γευμάτων</h4>
                                <p style="margin:0; font-size:0.9rem; color:#64748B;">Διαδραστικός χάρτης, live feed, αιτήματα</p>
                            </div>
                        </a>
                        <?php endif; ?>
                        <?php if (!$hasCook && !$hasConsumer): ?>
                        <div class="card" style="text-align:center; padding:3rem; color:#64748B;">
                            Ο λογαριασμός σας δεν έχει ενεργούς ρόλους. Επικοινωνήστε με τη διαχείριση.
                        </div>
                        <?php endif; ?>
                    </div>
                                </div>

                                <!-- My Commitments & Pending Ratings Section (Consumer only) -->
                                <?php if ($hasConsumer): ?>
                                <section class="card commitments-section" style="margin-top: 1.5rem; padding: 1.5rem; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 16px;">
                                    <h3 style="margin: 0 0 1.25rem; font-size: 1.1rem; color: #0F172A; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                                        <span style="font-size: 1.25rem;">📋</span> Οι Δεσμεύσεις μου & Εκκρεμείς Αξιολογήσεις
                                    </h3>
                                    <div id="commitments-content">
                                        <?php if (empty($my_requests)): ?>
                                            <p style="color: #64748B; text-align: center; padding: 2rem;">Δεν έχετε πραγματοποιήσει ακόμα καμία δέσμευση.</p>
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
                                                $timeStr = '';
                                                if ($hours_left > 0) {
                                                    if ($hours_left >= 24) {
                                                        $days = floor($hours_left / 24);
                                                        $hrs = $hours_left % 24;
                                                        $timeStr = "{$days} ημέρα" . ($days > 1 ? 'ς' : '') . ($hrs > 0 ? " {$hrs} ώρες" : '');
                                                    } else {
                                                        $timeStr = "{$hours_left} ώρα" . ($hours_left > 1 ? 'ς' : '');
                                                    }
                                                } else {
                                                    $timeStr = 'ήδη παρήλθε';
                                                }
                                            ?>
                                            <div class="penalty-warning-banner" style="
                                                background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
                                                border: 1px solid #F59E0B;
                                                border-radius: 12px;
                                                padding: 1rem 1.25rem;
                                                margin-bottom: 1rem;
                                                display: flex;
                                                flex-wrap: wrap;
                                                align-items: center;
                                                gap: 1rem;
                                            ">
                                                <div style="flex: 1; min-width: 280px;">
                                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                                        <span style="font-size: 1.25rem;">⚠️</span>
                                                        <strong style="color: #92400E; font-size: 1rem;">Εκκρεμεί Αξιολόγηση</strong>
                                                    </div>
                                                    <div style="color: #78350F; font-size: 0.9rem; line-height: 1.5;">
                                                        <strong><?= htmlspecialchars($req['title']) ?></strong> — Μάγειρας: <?= htmlspecialchars($req['cook_name']) ?><br>
                                                        Απομένουν <strong><?= $timeStr ?></strong> (έως <?= $deadline_str ?>).
                                                        <br>Αν δεν αξιολογήσετε εντός 48 ωρών από την παραλαβή, θα σας αφαιρεθεί <strong>1 credit ποινής (-1 πόντους)</strong>.
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                            
                                            <?php if (!empty($otherRequests)): ?>
                                                <div style="margin-top: <?= !empty($pendingRatings) ? '1.5rem' : '0' ?>;">
                                                    <h4 style="margin: 0 0 1rem; font-size: 0.95rem; font-weight: 600; color: #334155; text-transform: uppercase; letter-spacing: 0.04em;">Όλες οι Δεσμεύσεις</h4>
                                                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
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
                                                                    default => 'badge-default'
                                                                };
                                                                $locationMap = [
                                                                    'Φοιτητική Εστία Α' => 'Φοιτητική Εστία Α',
                                                                    'Οδός Πατησίων 42' => 'Οδός Πατησίων 42',
                                                                    'Διαγώνιος 15' => 'Διαγώνιος 15'
                                                                ];
                                                                $locationName = $locationMap[$req['pickup_location']] ?? $req['pickup_location'];
                                                            ?>
                                                            <div class="commitment-card" style="
                                                                background: #FFFFFF;
                                                                border: 1px solid #E2E8F0;
                                                                border-radius: 12px;
                                                                padding: 1rem 1.25rem;
                                                                display: flex;
                                                                flex-wrap: wrap;
                                                                align-items: center;
                                                                gap: 1rem;
                                                                transition: box-shadow 0.2s, border-color 0.2s;
                                                            " onmouseover="this.style.boxShadow='0 8px 24px -8px rgba(15,23,42,0.12)'; this.style.borderColor='#CBD5E1';" onmouseout="this.style.boxShadow=''; this.style.borderColor='#E2E8F0';">
                                                                <div style="flex: 1; min-width: 250px;">
                                                                    <h4 style="margin: 0 0 0.25rem; font-size: 1rem; font-weight: 700; color: #0F172A;"><?= htmlspecialchars($req['title']) ?></h4>
                                                                    <div style="font-size: 0.85rem; color: #64748B; line-height: 1.6;">
                                                                        👨‍🍳 Μάγειρας: <strong><?= htmlspecialchars($req['cook_name']) ?></strong><br>
                                                                        📍 Σημείο: <strong><?= htmlspecialchars($locationName) ?></strong><br>
                                                                        ⏰ Παραλαβή: <?= htmlspecialchars($req['pickup_time']) ?>
                                                                    </div>
                                                                </div>
                                                                <span class="status-badge <?= $statusClass ?>" style="
                                                                    display: inline-flex; align-items: center; gap: 0.35rem;
                                                                    font-size: 0.7rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
                                                                    padding: 0.35rem 0.75rem; border-radius: 999px; white-space: nowrap;
                                                                    flex-shrink: 0;
                                                                ">
                                                                    <?= $statusLabel ?>
                                                                </span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php elseif (!empty($pendingRatings)): ?>
                                                <p style="color: #64748B; text-align: center; padding: 1rem;">Κανένα άλλο ενεργό αίτημα.</p>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </section>
                                <?php endif; ?>
                            </section>

                        <?php else: ?>
            <!-- NOT LOGGED IN: LANDING PAGE -->
            <section class="hero-split" role="main" aria-labelledby="hero-title">
                <!-- LEFT COLUMN -->
                <section class="hero-left" aria-labelledby="hero-title">
                    <h1 id="hero-title" class="hero-title">Μοιράσου το φαγητό σου, στήριξε τους συμφοιτητές σου</h1>
                    <p class="hero-subtitle">Το UniBite συνδέει φοιτητές που έχουν περισσευούμενο σπιτικό φαγητό με όσους το έχουν ανάγκη, γρήγορα και δωρεάν.</p>

                    <div class="features-list" role="list">
                        <!-- Feature 1: Credits -->
                        <div class="feature-item" style="display: flex; align-items: center; gap: 1.25rem; padding: 1.25rem; background: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0; margin-bottom: 1rem; box-shadow: 0 4px 16px -4px rgba(15, 23, 42, 0.04);" role="listitem">
                            <div style="width: 52px; height: 52px; min-width: 52px; border-radius: 14px; background: linear-gradient(135deg, #0D9488, #14B8A6); display: flex; align-items: center; justify-content: center; font-size: 1.6rem; box-shadow: 0 4px 12px rgba(13, 148, 136, 0.25);" aria-hidden="true">
                                💎
                            </div>
                            <div class="feature-content">
                                <h3 style="margin: 0 0 0.35rem; font-size: 1.05rem; font-weight: 700; color: #0F172A;">5 Δωρεάν Credits στην Εκκίνηση</h3>
                                <p style="margin: 0; font-size: 0.9rem; color: #64748B; line-height: 1.5;">Λάβε 5 πόντους άμεσα με το ακαδημαϊκό σου email για να ξεκινήσεις.</p>
                            </div>
                        </div>

                        <!-- Feature 2: Map -->
                        <div class="feature-item" style="display: flex; align-items: center; gap: 1.25rem; padding: 1.25rem; background: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0; margin-bottom: 1rem; box-shadow: 0 4px 16px -4px rgba(15, 23, 42, 0.04);" role="listitem">
                            <div style="width: 52px; height: 52px; min-width: 52px; border-radius: 14px; background: linear-gradient(135deg, #2563EB, #3B82F6); display: flex; align-items: center; justify-content: center; font-size: 1.6rem; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);" aria-hidden="true">
                                📍
                            </div>
                            <div class="feature-content">
                                <h3 style="margin: 0 0 0.35rem; font-size: 1.05rem; font-weight: 700; color: #0F172A;">Χάρτης & Σημεία Παραλαβής</h3>
                                <p style="margin: 0; font-size: 0.9rem; color: #64748B; line-height: 1.5;">Εντόπισε ζεστό φαγητό δίπλα στο τμήμα σου ή τις φοιτητικές εστίες.</p>
                            </div>
                        </div>

                        <!-- Feature 3: Rewards -->
                        <div class="feature-item" style="display: flex; align-items: center; gap: 1.25rem; padding: 1.25rem; background: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0; margin-bottom: 1rem; box-shadow: 0 4px 16px -4px rgba(15, 23, 42, 0.04);" role="listitem">
                            <div style="width: 52px; height: 52px; min-width: 52px; border-radius: 14px; background: linear-gradient(135deg, #EA580C, #F97316); display: flex; align-items: center; justify-content: center; font-size: 1.6rem; box-shadow: 0 4px 12px rgba(234, 88, 12, 0.25);" aria-hidden="true">
                                ⭐
                            </div>
                            <div class="feature-content">
                                <h3 style="margin: 0 0 0.35rem; font-size: 1.05rem; font-weight: 700; color: #0F172A;">Ανταποδοτικό Σύστημα Αξιολόγησης</h3>
                                <p style="margin: 0; font-size: 0.9rem; color: #64748B; line-height: 1.5;">Κέρδισε έξτρα +2 credits ανά μερίδα όταν οι συμφοιτητές σε βαθμολογούν με 4-5 αστέρια.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- RIGHT COLUMN - LOGIN CARD -->
                <aside class="hero-right" aria-labelledby="login-title">
                    <div class="login-card" style="background:#fff; border-radius:20px; padding:2rem; border:1px solid #E2E8F0; box-shadow:0 4px 20px rgba(0,0,0,0.06);">
                        <div style="text-align: center; margin-bottom: 1.5rem;">
                            <h2 id="login-title" style="margin: 0 0 0.5rem; font-size: 1.5rem; font-weight: 800; color: #0F172A;">Είσοδος στην Πλατφόρμα</h2>
                            <p style="margin: 0; font-size: 0.9rem; color: #64748B;">Συνδέσου με το όνομα χρήστη ή το email σου</p>
                        </div>

                        <!-- Ορατό πλαίσιο σφάλματος/ενημέρωσης -->
                        <div id="login-alert-box" style="display:none; padding:10px 14px; border-radius:8px; font-size:0.88rem; margin-bottom:1rem; font-weight:600; text-align:center;"></div>
                        <div id="inline-login-error" style="display:none; background:#FEE2E2; color:#991B1B; border:1px solid #F87171; padding:10px 14px; border-radius:8px; font-size:0.9rem; margin-bottom:1rem; font-weight:600; text-align:center;"></div>

                        <form id="auth-form" onsubmit="event.preventDefault(); doLogin();" novalidate>
                            <div style="display:flex; flex-direction:column; gap:0.4rem; margin-bottom:1rem;">
                                <label style="font-size:0.8rem; font-weight:700; color:#334155; text-transform:uppercase;">Username ή Email</label>
                                <input type="text" id="login-identifier" class="form-control" style="width:100%; padding:12px; border-radius:8px; border:1.5px solid #CBD5E1; font-size:1rem;" required>
                            </div>
                            
                            <div style="display:flex; flex-direction:column; gap:0.4rem; margin-bottom:1.5rem;">
                                <label style="font-size:0.8rem; font-weight:700; color:#334155; text-transform:uppercase;">Κωδικός</label>
                                <input type="password" id="login-password" class="form-control" style="width:100%; padding:12px; border-radius:8px; border:1.5px solid #CBD5E1; font-size:1rem;" required>
                            </div>

                            <button type="submit" id="login-btn" style="width:100%; padding:13px; background:#0F172A; color:#FFFFFF; border:none; border-radius:10px; font-weight:700; font-size:1rem; cursor:pointer; transition:0.2s;">
                                Σύνδεση στο UniBite →
                            </button>
                        </form>
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
            <h2 id="modal-title" style="margin-bottom: 1.5rem; color: var(--secondary-color);">Σύνδεση στο UniBite</h2>
            <form id="modal-auth-form" onsubmit="handleAuthSubmit(event)">
                <div id="inline-login-error" style="display:none; background:#FEE2E2; color:#991B1B; border:1px solid #F87171; padding:10px 14px; border-radius:8px; font-size:0.9rem; margin-bottom:1rem; font-weight:600; text-align:center;"></div>
                <div class="form-group" id="username-group">
                    <label>Username ή Email</label>
                    <input type="text" id="modal-email" class="form-control" required autocomplete="username">
                </div>
                <div class="form-group" id="email-group" style="display: none;">
                    <label>Email (για επικοινωνία)</label>
                    <input type="email" id="modal-email-reg" class="form-control" required autocomplete="email">
                </div>
                <div class="form-group">
                    <label>Κωδικός Πρόσβασης</label>
                    <input type="password" id="modal-password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="form-group" style="background: var(--bg-color); padding: 1rem; border-radius: var(--radius); border: 1px solid var(--border-color);">
                    <label style="font-weight: 600; color: var(--primary-color);">🎓 Όλοι οι φοιτητές εγγράφονται ως: Φοιτητής + Μάγειρας</label>
                </div>
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" id="modal-auth-submit-btn" class="btn btn-primary" style="flex: 1;">Σύνδεση</button>
                    <button type="button" class="btn btn-outline" onclick="closeAuthModal()">Άκυρο</button>
                </div>
            </form>
            <p style="margin-top: 1rem; text-align: center; font-size: 0.9rem;">
                <span id="auth-switch-text">Δεν έχετε λογαριασμό;</span>
                <a href="#" id="auth-switch-link" onclick="toggleAuthMode(event)" style="color: var(--primary-color); font-weight: 600;">Εγγραφή</a>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Rating Modal στο Dashboard -->
    <div id="dashboard-rating-modal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center;">
        <div class="modal" style="background: #FFFFFF; border-radius: 20px; padding: 2rem; max-width: 480px; width: 90%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
            <h3 id="rating-modal-title" style="margin-top: 0; color: #0F172A; font-size: 1.3rem;">⭐ Αξιολόγηση Γεύματος</h3>
            <form id="dashboard-rating-form" onsubmit="submitDashboardRating(event)">
                <input type="hidden" id="dash-rating-offer-id">
                
                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #334155;">Βαθμολογία (1-5)</label>
                    <select id="dash-rating-stars" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #CBD5E1; font-size: 1rem;" required>
                        <option value="5" selected>⭐⭐⭐⭐⭐ - Εξαιρετικό (+2 πόντοι στον μάγειρα)</option>
                        <option value="4">⭐⭐⭐⭐ - Πολύ καλό (+2 πόντοι στον μάγειρα)</option>
                        <option value="3">⭐⭐⭐ - Μέτριο (+1 πόντος στον μάγειρα)</option>
                        <option value="2">⭐⭐ - Ανάγκη βελτίωσης (+1 πόντος στον μάγειρα)</option>
                        <option value="1">⭐ - Κακό (+1 πόντος στον μάγειρα)</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #334155;">Σχόλιο (προαιρετικό)</label>
                    <textarea id="dash-rating-comment" class="form-control" rows="3" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #CBD5E1; font-family: inherit;" placeholder="Πώς ήταν η γεύση, η μερίδα και η παράδοση;"></textarea>
                </div>
                
                <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <button type="button" onclick="closeRatingModal()" style="padding: 10px 18px; border-radius: 10px; border: 1px solid #CBD5E1; background: transparent; cursor: pointer; font-weight: 600;">Άκυρο</button>
                    <button type="submit" id="dash-rating-submit-btn" style="padding: 10px 20px; border-radius: 10px; border: none; background: #EA580C; color: white; cursor: pointer; font-weight: 700;">Υποβολή Αξιολόγησης</button>
                </div>
            </form>
        </div>
    </div>

    <script src="Shared/js/auth.js"></script>
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
            const identifier = isModal ? document.getElementById('modal-email').value.trim() : document.getElementById('email').value.trim();
            const password = isModal ? document.getElementById('modal-password').value : document.getElementById('password').value;

            const submitBtn = document.getElementById(isModal ? 'modal-auth-submit-btn' : 'auth-submit-btn');
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
                    contentEl.innerHTML = '<p style="color: #64748B; text-align: center; padding: 2rem;">Δεν έχετε καμία δέσμευση ακόμα.</p>';
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
                        const deadline = new Date(new Date(req.updated_at).getTime() + 48 * 60 * 60 * 1000);
                        const deadlineStr = deadline.toLocaleString('el-GR', {
                            day: '2-digit', month: '2-digit', year: 'numeric',
                            hour: '2-digit', minute: '2-digit'
                        });
                        let timeStr = '';
                        if (hoursRemaining > 0) {
                            if (hoursRemaining >= 24) {
                                const days = Math.floor(hoursRemaining / 24);
                                const hrs = hoursRemaining % 24;
                                timeStr = `${days} ημέρα${days > 1 ? 'ς' : ''}${hrs > 0 ? ' ' + hrs + ' ώρες' : ''}`;
                            } else {
                                timeStr = `${hoursRemaining} ώρα${hoursRemaining > 1 ? 'ς' : ''}`;
                            }
                        } else {
                            timeStr = 'ήδη παρήλθε';
                        }

                        html += `
                            <div class="penalty-warning-banner" style="
                                background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
                                border: 1px solid #F59E0B;
                                border-radius: 12px;
                                padding: 1rem 1.25rem;
                                margin-bottom: 1rem;
                                display: flex;
                                flex-wrap: wrap;
                                align-items: center;
                                gap: 1rem;
                            ">
                                <div style="flex: 1; min-width: 280px;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                        <span style="font-size: 1.25rem;">⚠️</span>
                                        <strong style="color: #92400E; font-size: 1rem;">Εκκρεμεί Αξιολόγηση</strong>
                                    </div>
                                    <div style="color: #78350F; font-size: 0.9rem; line-height: 1.5;">
                                        <strong>${escapeHtml(req.title)}</strong> — Μάγειρας: ${escapeHtml(req.cook_name)}<br>
                                        Απομένουν <strong>${timeStr}</strong> (έως ${deadlineStr}).
                                        <br>Αν δεν αξιολογήσετε εντός 48 ωρών από την παραλαβή, θα σας αφαιρεθεί <strong>1 credit ποινής (-1 πόντους)</strong>.
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }

                // Regular commitments list
                if (otherRequests.length > 0) {
                    html += `
                        <div style="margin-top: ${pendingRatings.length > 0 ? '1.5rem' : '0'};">
                            <h4 style="margin: 0 0 1rem; font-size: 0.95rem; font-weight: 600; color: #334155; text-transform: uppercase; letter-spacing: 0.04em;">Ολες οι Δεσμεύσεις</h4>
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    `;

                    otherRequests.forEach(req => {
                        const statusLabel = translateStatus(req.status);
                        const statusClass = getStatusBadgeClass(req.status);
                        const locationName = getLocationName(req.pickup_location);

                        html += `
                            <div class="commitment-card" style="
                                background: #FFFFFF;
                                border: 1px solid #E2E8F0;
                                border-radius: 12px;
                                padding: 1rem 1.25rem;
                                display: flex;
                                flex-wrap: wrap;
                                align-items: center;
                                gap: 1rem;
                                transition: box-shadow 0.2s, border-color 0.2s;
                            " onmouseover="this.style.boxShadow='0 8px 24px -8px rgba(15,23,42,0.12)'; this.style.borderColor='#CBD5E1';" onmouseout="this.style.boxShadow=''; this.style.borderColor='#E2E8F0';">
                                <div style="flex: 1; min-width: 250px;">
                                    <h4 style="margin: 0 0 0.25rem; font-size: 1rem; font-weight: 700; color: #0F172A;">${escapeHtml(req.title)}</h4>
                                    <div style="font-size: 0.85rem; color: #64748B; line-height: 1.6;">
                                        👨‍🍳 Μάγειρας: <strong>${escapeHtml(req.cook_name)}</strong><br>
                                        📍 Σημείο: <strong>${escapeHtml(locationName)}</strong><br>
                                        ⏰ Παραλαβή: ${req.pickup_time}
                                    </div>
                                </div>
                                <span class="status-badge ${statusClass}" style="
                                    display: inline-flex; align-items: center; gap: 0.35rem;
                                    font-size: 0.7rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
                                    padding: 0.35rem 0.75rem; border-radius: 999px; white-space: nowrap;
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
                    html += '<p style="color: #64748B; text-align: center; padding: 1rem;">Κανένα άλλο ενεργό αίτημα.</p>';
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
                default: return 'badge-default';
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
            const modal = document.getElementById('dashboard-rating-modal');
            modal.style.display = 'flex';
            
            // Ασπίδα για να μην κλείσει ακαριαία
            modal.dataset.justOpened = 'true';
            setTimeout(() => { modal.dataset.justOpened = 'false'; }, 200);
        }

        function closeRatingModal() {
    console.trace("Κάποιος προσπάθησε να κλείσει το modal, αλλά το μπλοκάραμε!");
    // modal.style.display = 'none'; <-- ΑΦΑΙΡΕΘΗΚΕ ΕΠΙΤΗΔΕΣ
}

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
            return String(str).replace(/[&<>'"]/g, function(tag) {
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
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeRatingModal();
            });
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