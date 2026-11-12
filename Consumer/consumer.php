<?php
// Consumer/consumer.php
require_once '../Shared/php/auth_guard.php';
$user = authGuard(['consumer']);
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniBite - Ροή Γευμάτων & Χάρτης (Consumer)</title>
    <!-- Google Fonts: Host Grotesk (display) + Instrument Sans (body) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Host+Grotesk:wght@400;500;600;700;800&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Shared/css/common.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/consumer.css?v=<?= time() ?>">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
</head>
<body>
    <header class="navbar">
        <div class="navbar-brand">
            <a href="../index.php" class="navbar-brand">
                <span class="logo-uni">Uni</span><span class="logo-bite">Bite</span>
            </a>
            <span class="navbar-badge">CAMPUS FOOD</span>
            <span class="navbar-user">[<?= htmlspecialchars($user['username']) ?>]</span>
        </div>
        <nav>
            <ul class="navbar-links">
                <li><a href="../index.php">Αρχική</a></li>
                <li><a href="../Cook/cook.php">🍳 Μαγείρας</a></li>
                <li><a href="#" class="navbar-logout" onclick="UniBiteAuth.logout('../index.php')">Αποσύνδεση</a></li>
            </ul>
        </nav>
    </header>

    <main class="main">
        <h2>Διαθέσιμα Γεύματα & Χάρτης Παραλαβής</h2>

        <!-- Filter Controls -->
        <div class="filter-card">
            <h3>🔍 Φίλτρα & Ταξινόμηση</h3>
            <div class="filter-grid">
                <div class="filter-group">
                    <label class="form-label">Η θέση μου (για απόσταση)</label>
                    <div style="display: flex; gap: var(--dp-spacing-sm); position: relative;">
                        <input type="text" id="user-location" class="form-control" placeholder="π.χ. Φοιτητική Εστία Α" style="flex: 1;" autocomplete="off">
                        <div id="user-location-suggestions" class="autocomplete-dropdown" style="display: none;"></div>
                        <button type="button" class="btn btn-secondary" onclick="geocodeUserLocation()" style="white-space: nowrap;">Εύρεση</button>
                    </div>
                    <small class="form-hint">Αφήστε κενό για να δείτε όλα χωρίς φίλτρο απόστασης</small>
                </div>
                <div class="filter-group">
                    <label class="form-label">Μέγιστη Απόσταση (km)</label>
                    <input type="number" id="max-distance" class="form-control" min="1" max="50" value="10" step="1">
                </div>
                <div class="filter-group">
                    <label class="form-label">Τύπος Τοποθεσίας</label>
                    <select id="location-type" class="form-control">
                        <option value="">Όλοι οι τύποι</option>
                        <option value="campus">🏛️ Campus (Κτίρια Πανεπιστημίου)</option>
                        <option value="dorm">🏢 Φοιτητικές Εστίες</option>
                        <option value="home">🏠 Στο Σπίτι Μάγειρα</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="form-label">Ταξινόμηση</label>
                    <select id="sort-by" class="form-control">
                        <option value="created_desc">Πιο Νέα Πρώτα</option>
                        <option value="distance_asc">Πιο Κοντά Πρώτα</option>
                        <option value="portions_desc">Πιο Πολλές Μερίδες</option>
                        <option value="pickup_asc">Πιο Σύντομα Παραλαβή</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="form-label">Αποτελέσματα ανά σελίδα</label>
                    <select id="results-limit" class="form-control">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div style="display: flex; gap: var(--dp-spacing-sm);">
                    <button type="button" class="btn btn-primary" onclick="loadFeed()">Εφαρμογή Φίλτρων</button>
                    <button type="button" class="btn btn-outline" onclick="resetFilters()">Επαναφορά</button>
                </div>
            </div>
            <div id="filter-info" style="margin-top: var(--dp-spacing-sm); font-size: var(--dp-typography-metadata); color: var(--dp-color-ink-secondary);"></div>
        </div>

        <div class="consumer-layout">
            <!-- Feed Column -->
            <div>
                <h3>Live Feed Γεύμάτων</h3>
                <div id="meals-feed" class="feed-grid">
                    <p style="color: var(--dp-color-ink-secondary);">Φόρτωση ενεργών γευμάτων...</p>
                </div>
            </div>

            <!-- Map Column -->
            <div>
                <h3>Χάρτης Σημείων Παραλαβής</h3>
                <div id="map"></div>
            </div>
        </div>

        <!-- My Requests Section -->
        <section style="margin-top: var(--dp-spacing-2xl);">
            <h3>Τα Αιτήματά μου</h3>
            <div id="my-requests" class="feed-grid">
                <p style="color: var(--dp-color-ink-secondary);">Φόρτωση αιτημάτων...</p>
            </div>
        </section>
    </main>

    <!-- Rating Modal -->
    <div id="rating-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2>Αξιολόγηση Γεύματος</h2>
            </div>
            <form id="rating-form">
                <input type="hidden" id="rating-offer-id">
                <div class="form-group">
                    <label class="form-label">Βαθμολογία (1-5)</label>
                    <select id="rating-stars" class="form-control" required>
                        <option value="5">⭐⭐⭐⭐⭐ - Εξαιρετικό</option>
                        <option value="4">⭐⭐⭐⭐ - Πολύ καλό</option>
                        <option value="3">⭐⭐⭐ - Μέτριο</option>
                        <option value="2">⭐⭐ - Ανάγκη βελτίωσης</option>
                        <option value="1">⭐ - Κακό</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Σχόλιο (προαιρετικό)</label>
                    <textarea id="rating-comment" class="form-control" rows="3" placeholder="Περιγράψτε την εμπειρία σας..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Υποβολή</button>
                    <button type="button" class="btn btn-outline" onclick="closeRatingModal()">Άκυρο</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="../Shared/js/auth.js"></script>
    <script src="js/consumer.js?v=<?= time() ?>"></script>
</body>
</html>