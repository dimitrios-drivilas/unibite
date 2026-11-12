<?php
// Admin/admin.php
require_once '../Shared/php/auth_guard.php';
$user = authGuard(['admin']);
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniBite - Πίνακας Ελέγχου Διαχειριστή</title>
    <!-- Google Fonts: Host Grotesk (display) + Instrument Sans (body) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Host+Grotesk:wght@400;500;600;700;800&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Shared/css/common.css?v=<?= time() ?>">
</head>
<body>
    <header class="navbar">
        <div class="navbar-brand">
            <a href="../index.php" class="navbar-brand">
                <span class="logo-uni">Uni</span><span class="logo-bite">Bite</span>
            </a>
            <span class="navbar-badge">CAMPUS FOOD</span>
            <span class="navbar-user">[🛡️ Διαχειριστής: <?= htmlspecialchars($user['username']) ?>]</span>
        </div>
        <nav>
            <ul class="navbar-links">
                <li><a href="#" class="navbar-logout" onclick="UniBiteAuth.logout('../index.php')">Αποσύνδεση</a></li>
            </ul>
        </nav>
    </header>

    <main class="main">
        <h2>Στατιστικά Πλατφόρμας (Τελευταίος Μήνας)</h2>

        <div class="stats-grid">
            <div class="stat-card">
                <h3 id="stat-portions">0</h3>
                <p>Συνολικές Μερίδες</p>
            </div>
            <div class="stat-card">
                <h3 id="stat-offers">0</h3>
                <p>Συνολικές Αγγελίες</p>
            </div>
            <div class="stat-card">
                <h3 id="stat-requests">0</h3>
                <p>Συνολικά Αιτήματα</p>
            </div>
            <div class="stat-card">
                <h3 id="stat-delivered">0</h3>
                <p>Ολοκληρωμένες Παραλαβές</p>
            </div>
        </div>

        <div class="leaderboard-container">
            <div class="card">
                <h3>🏆 Top Donors (Μερίδες)</h3>
                <div class="table-wrap" style="overflow-x: auto; -webkit-overflow-scrolling: touch; max-width: 100%;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Φοιτητής / Μάγειρας</th>
                                <th>Μερίδες</th>
                                <th>Αγγελίες</th>
                            </tr>
                        </thead>
                        <tbody id="top-donors-body">
                            <tr><td colspan="3" style="text-align: center;">Φόρτωση...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <h3>🍽️ Top Rated Γεύματα</h3>
                <div class="table-wrap" style="overflow-x: auto; -webkit-overflow-scrolling: touch; max-width: 100%;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Γεύμα</th>
                                <th>Βαθμολογία</th>
                                <th># Αξιολογήσεων</th>
                            </tr>
                        </thead>
                        <tbody id="top-meals-body">
                            <tr><td colspan="3" style="text-align: center;">Φόρτωση...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Admin: User Management -->
        <section style="margin-top: var(--dp-spacing-2xl);">
            <h3>👥 Διαχείριση Χρηστών</h3>
            <div class="card">
                <div class="table-wrap" style="overflow-x: auto; -webkit-overflow-scrolling: touch; max-width: 100%;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Ρόλος</th>
                                <th>Εγγραφή</th>
                                <th>Ενέργειες</th>
                            </tr>
                        </thead>
                        <tbody id="users-table-body">
                            <tr><td colspan="6" style="text-align: center;">Φόρτωση...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Admin: All Offers Moderation -->
        <section style="margin-top: var(--dp-spacing-xl);">
            <h3>🍽️ Όλες οι Αγγελίες (Moderation)</h3>
            <div class="card">
                <div class="table-wrap" style="overflow-x: auto; -webkit-overflow-scrolling: touch; max-width: 100%;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Τίτλος</th>
                                <th>Μάγειρας</th>
                                <th>Μερίδες</th>
                                <th>Κατάσταση</th>
                                <th>Δημιουργήθηκε</th>
                                <th>Ενέργειες</th>
                            </tr>
                        </thead>
                        <tbody id="all-offers-body">
                            <tr><td colspan="7" style="text-align: center;">Φόρτωση...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <script src="../Shared/js/auth.js"></script>
    <script src="js/admin.js?v=<?= time() ?>"></script>
</body>
</html>