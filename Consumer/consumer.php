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
    <link rel="stylesheet" href="../Shared/css/common.css">
    <link rel="stylesheet" href="css/consumer.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
</head>
<body>
    <header>
        <div class="navbar-brand" style="display: flex; align-items: center; gap: 12px;">
            <a href="../index.php" style="text-decoration: none; font-size: 1.5rem; font-weight: 800; color: #FFFFFF; display: flex; align-items: center;">
                Uni<span style="color: #FB923C;">Bite</span>
            </a>
            <span style="background: #1E293B; color: #94A3B8; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 12px; letter-spacing: 0.5px;">CAMPUS FOOD</span>
            <span style="color: #CBD5E1; font-size: 0.85rem; margin-left: 6px;">[<?= htmlspecialchars($user['username']) ?>]</span>
        </div>
        <nav>
            <ul>
                <li><a href="../index.php">Αρχική</a></li>
                <li><a href="../Cook/cook.php">🍳 Μαγείρας</a></li>
                <li><a href="#" onclick="UniBiteAuth.logout('../index.php')" style="background: rgba(220, 38, 38, 0.15); color: #EF4444; border: 1px solid #DC2626; padding: 7px 16px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center;">Αποσύνδεση</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <h2 style="margin-bottom: 1.5rem; color: var(--secondary-color);">Διαθέσιμα Γεύματα & Χάρτης Παραλαβής</h2>
        
        <!-- Filter Controls -->
        <div class="card" style="margin-bottom: 1.5rem; padding: 1rem;">
            <h3 style="margin-bottom: 1rem; color: var(--primary-color);">🔍 Φίλτρα & Ταξινόμηση</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Η θέση μου (για απόσταση)</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" id="user-location" class="form-control" placeholder="π.χ. Φοιτητική Εστία Α" style="flex: 1;">
                        <button type="button" class="btn btn-secondary" onclick="geocodeUserLocation()" style="white-space: nowrap;">Εύρεση</button>
                    </div>
                    <small style="color: var(--text-muted);">Αφήστε κενό για να δείτε όλα χωρίς φίλτρο απόστασης</small>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Μέγιστη Απόσταση (km)</label>
                    <input type="number" id="max-distance" class="form-control" min="1" max="50" value="10" step="1">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Ταξινόμηση</label>
                    <select id="sort-by" class="form-control">
                        <option value="created_desc">Πιο Νέα Πρώτα</option>
                        <option value="distance_asc">Πιο Κοντά Πρώτα</option>
                        <option value="portions_desc">Πιο Πολλές Μερίδες</option>
                        <option value="pickup_asc">Πιο Σύντομα Παραλαβή</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Αποτελέσματα ανά σελίδα</label>
                    <select id="results-limit" class="form-control">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" class="btn btn-primary" onclick="loadFeed()">Εφαρμογή Φίλτρων</button>
                    <button type="button" class="btn btn-outline" onclick="resetFilters()">Επαναφορά</button>
                </div>
            </div>
            <div id="filter-info" style="margin-top: 0.5rem; font-size: 0.85rem; color: var(--text-muted);"></div>
        </div>

        <div class="consumer-layout">
            <!-- Feed Column -->
            <div>
                <h3 style="margin-bottom: 1rem; color: var(--primary-color);">Live Feed Γεύμάτων</h3>
                <div id="meals-feed" class="feed-grid">
                    <p style="color: var(--text-muted);">Φόρτωση ενεργών γευμάτων...</p>
                </div>
            </div>

            <!-- Map Column -->
            <div>
                <h3 style="margin-bottom: 1rem; color: var(--secondary-color);">Χάρτης Σημείων Παραλαβής</h3>
                <div id="map"></div>
            </div>
        </div>

        <!-- My Requests Section -->
        <section style="margin-top: 3rem;">
            <h3 style="margin-bottom: 1rem; color: var(--primary-color);">Τα Αιτήματά μου</h3>
            <div id="my-requests" class="feed-grid">
                <p style="color: var(--text-muted);">Φόρτωση αιτημάτων...</p>
            </div>
        </section>
    </main>

    <!-- Rating Modal -->
    <div id="rating-modal" class="modal-overlay">
        <div class="modal">
            <h2 style="margin-bottom: 1.5rem; color: var(--secondary-color);">Αξιολόγηση Γεύματος</h2>
            <form id="rating-form">
                <input type="hidden" id="rating-offer-id">
                <div class="form-group">
                    <label>Βαθμολογία (1-5)</label>
                    <select id="rating-stars" class="form-control" required>
                        <option value="5">⭐⭐⭐⭐⭐ - Εξαιρετικό</option>
                        <option value="4">⭐⭐⭐⭐ - Πολύ καλό</option>
                        <option value="3">⭐⭐⭐ - Μέτριο</option>
                        <option value="2">⭐⭐ - Ανάγκη βελτίωσης</option>
                        <option value="1">⭐ - Κακό</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Σχόλιο (προαιρετικό)</label>
                    <textarea id="rating-comment" class="form-control" rows="3" placeholder="Περιγράψτε την εμπειρία σας..."></textarea>
                </div>
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Υποβολή</button>
                    <button type="button" class="btn btn-outline" onclick="closeRatingModal()">Άκυρο</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="../Shared/js/auth.js"></script>
    <script src="js/consumer.js"></script>
</body>
</html>