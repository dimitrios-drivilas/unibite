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
    <link rel="stylesheet" href="../Shared/css/common.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <header>
        <div class="navbar-brand" style="display: flex; align-items: center; gap: 12px;">
            <a href="../index.php" style="text-decoration: none; font-size: 1.5rem; font-weight: 800; color: #FFFFFF; display: flex; align-items: center;">
                Uni<span style="color: #FB923C;">Bite</span>
            </a>
            <span style="background: #1E293B; color: #94A3B8; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 12px; letter-spacing: 0.5px;">CAMPUS FOOD</span>
            <span style="color: #CBD5E1; font-size: 0.85rem; margin-left: 6px;">[Διαχειριστής: <?= htmlspecialchars($user['username']) ?>]</span>
        </div>
        <nav>
            <ul>
                <li><a href="../index.php">Αρχική</a></li>
                <li><a href="#" onclick="UniBiteAuth.logout('../index.php')" style="background: rgba(220, 38, 38, 0.15); color: #EF4444; border: 1px solid #DC2626; padding: 7px 16px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center;">Αποσύνδεση</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <h2 style="margin-bottom: 1.5rem; color: var(--secondary-color);">Στατιστικά Πλατφόρμας (Τελευταίος Μήνας)</h2>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3 id="stat-portions">0</h3>
                <p style="color: var(--text-muted);">Συνολικές Μερίδες</p>
            </div>
            <div class="stat-card">
                <h3 id="stat-offers">0</h3>
                <p style="color: var(--text-muted);">Συνολικές Αγγελίες</p>
            </div>
            <div class="stat-card">
                <h3 id="stat-requests">0</h3>
                <p style="color: var(--text-muted);">Συνολικά Αιτήματα</p>
            </div>
            <div class="stat-card">
                <h3 id="stat-delivered">0</h3>
                <p style="color: var(--text-muted);">Ολοκληρωμένες Παραλαβές</p>
            </div>
        </div>

        <div class="leaderboard-container">
            <div class="card">
                <h3 style="color: var(--primary-color);">🏆 Top Donors (Μερίδες)</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Φοιτητής / Μάγειρας</th>
                            <th>Μερίδες</th>
                            <th>Αγγελίες</th>
                        </tr>
                    </thead>
                    <tbody id="top-donors-body">
                        <tr><td colspan="3">Φόρτωση...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h3 style="color: var(--primary-color);">🍽️ Top Rated Γεύματα</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Γεύμα</th>
                            <th>Βαθμολογία</th>
                            <th># Αξιολογήσεων</th>
                        </tr>
                    </thead>
                    <tbody id="top-meals-body">
                        <tr><td colspan="3">Φόρτωση...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Admin: User Management -->
        <section style="margin-top: 3rem;">
            <h3 style="margin-bottom: 1rem; color: var(--primary-color);">👥 Διαχείριση Χρηστών</h3>
            <div class="card">
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
        </section>

        <!-- Admin: All Offers Moderation -->
        <section style="margin-top: 2rem;">
            <h3 style="margin-bottom: 1rem; color: var(--primary-color);">🍽️ Όλες οι Αγγελίες (Μoderation)</h3>
            <div class="card">
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
        </section>
    </main>

    <script src="../Shared/js/auth.js"></script>
    <script src="js/admin.js"></script>
</body>
</html>