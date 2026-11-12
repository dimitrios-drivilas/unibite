<?php
// Cook/cook.php
require_once '../Shared/php/auth_guard.php';
$user = authGuard(['cook']);
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniBite - Πάνελ Μάγειρα (Cook)</title>
    <!-- Google Fonts: Host Grotesk (display) + Instrument Sans (body) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Host+Grotesk:wght@400;500;600;700;800&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Shared/css/common.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/cook.css?v=<?= time() ?>">
    <!-- Leaflet CSS for map pin picker -->
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
                <li><a href="../Consumer/consumer.php">🎒 Καταναλωτής</a></li>
                <li><a href="#" class="navbar-logout" onclick="UniBiteAuth.logout('../index.php')">Αποσύνδεση</a></li>
            </ul>
        </nav>
    </header>

    <main class="main">
        <h2>Διαχείριση Αγγελιών & Φαγητού</h2>

        <!-- Tabs -->
        <div class="tabs">
            <button type="button" class="tab-btn active" data-tab="offers">🍽️ Αγγελίες μου</button>
            <button type="button" class="tab-btn" data-tab="stats">📊 Στατιστικά & Πόντοι</button>
        </div>

        <!-- Tab: Αγγελίες μου - Split Layout (Form + List) -->
        <div id="tab-offers" class="tab-panel active">
            <div class="cook-grid">
                <!-- Create Offer Column -->
                <div class="card" style="height: fit-content;">
                    <h3>Νέα Αγγελία Φαγητού</h3>
                        <form id="create-offer-form" enctype="multipart/form-data">
                            <div class="form-group">
                                <label class="form-label">Τίτλος Γεύματος</label>
                                <input type="text" id="title" name="title" class="form-control" placeholder="π.χ. Παστίτσιο Σπιτικό" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Περιγραφή & Συστατικά</label>
                                <textarea id="description" name="description" class="form-control" rows="3" placeholder="π.χ. Με φρέσκο μοσχαρίσιο κιμά..." required></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Φωτογραφία (προαιρετική)</label>
                                <input type="file" id="photo" name="photo" class="form-control" accept="image/*">
                                <small class="form-hint">Μέγιστο 2MB. Υποστηριζόμενα: JPG, PNG, WebP</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Αλλεργιογόνα (προαιρετικά)</label>
                                <div id="allergens-container"></div>
                                <small class="form-hint">Επιλέξτε τα γνωστά αλλεργιογόνα</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Αριθμός Μερίδων</label>
                                <input type="number" id="portions" name="portions" class="form-control" min="1" value="2" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Σημείο Παραλαβής</label>
                                <select id="pickup_location" name="pickup_location" class="form-control" required>
                                    <option value="">-- Επιλέξτε σημείο παράδοσης --</option>
                                    <optgroup label="🏛️ Campus Ρίο">
                                        <optgroup label="🏠 Φοιτητικές Εστίες (ΦΕΠ)">
                                            <option value="fep-building-a" data-lat="38.2942" data-lng="21.7887">ΦΕΠ - Κτίριο Α (Κοιτονομήματα)</option>
                                            <option value="fep-building-b" data-lat="38.2945" data-lng="21.7892">ΦΕΠ - Κτίριο Β (Κοιτονομήματα)</option>
                                            <option value="fep-building-c" data-lat="38.2948" data-lng="21.7897">ΦΕΠ - Κτίριο Γ (Κοιτονομήματα)</option>
                                            <option value="fep-building-z" data-lat="38.2951" data-lng="21.7902">ΦΕΠ - Κτίριο Ζ (Κοιτονομήματα)</option>
                                            <option value="fep-building-h" data-lat="38.2954" data-lng="21.7907">ΦΕΠ - Κτίριο Η (Κοιτονομήματα)</option>
                                            <option value="fep-building-th" data-lat="38.2957" data-lng="21.7912">ΦΕΠ - Κτίριο Θ (Κοιτονομήματα)</option>
                                        </optgroup>
                                        <optgroup label="🏫 Τμήματα & Σχολές">
                                            <option value="building-b" data-lat="38.2920" data-lng="21.7870">Κτίριο Β - Πολυτεχνική / Αρχιτέκτονες / Φυσικοθεραπεία</option>
                                            <option value="ceid-building" data-lat="38.2905" data-lng="21.7920">CEID - Μηχανικοί Η/Υ & Πληροφορικής</option>
                                            <option value="ece-building" data-lat="38.2910" data-lng="21.7930">ECE - Ηλεκτρονικοί Υπολογιστές & Τεχνολογία Υπολογιστών</option>
                                            <option value="mech-aero-building" data-lat="38.2925" data-lng="21.7940">Μηχανολόγοι & Αεροναυπηγικοί Μηχανικοί</option>
                                            <option value="civil-eng-building" data-lat="38.2930" data-lng="21.7945">Πολιτικοί Μηχανικοί</option>
                                            <option value="chem-eng-building" data-lat="38.2935" data-lng="21.7950">Χημικοί Μηχανικοί</option>
                                            <option value="med-preclinical-building" data-lat="38.2900" data-lng="21.7850">Ιατρική - Προκλινικά / Αμφιθέατρα</option>
                                            <option value="med-clinical-building" data-lat="38.2895" data-lng="21.7845">Ιατρική - Κλινικές Λειτουργίες / Νοσοκομείο</option>
                                            <option value="physics-math-building" data-lat="38.2918" data-lng="21.7830">Φυσική / Μαθηματικά</option>
                                            <option value="chemistry-building-north" data-lat="38.2922" data-lng="21.7825">Χημεία - Βόρειο Κτίριο</option>
                                            <option value="chemistry-building-south" data-lat="38.2928" data-lng="21.7835">Χημεία - Νότιο Κτίριο</option>
                                            <option value="biology-building" data-lat="38.2910" data-lng="21.7810">Βιολογία / Μαθηματικά</option>
                                            <option value="geology-building" data-lat="38.2905" data-lng="21.7805">Γεωλογία</option>
                                            <option value="education-social-work-building" data-lat="38.2900" data-lng="21.7795">Επιστήμες Εκπαίδευσης & Κοινωνικής Εργασίας</option>
                                        </optgroup>
                                        <optgroup label="🏛️ Κοινόχρηστοι & Διοικητικοί Χώροι">
                                            <option value="admin-building-a" data-lat="38.2915" data-lng="21.7865">Κτίριο Α - Διοίκηση / Πρυτανεία</option>
                                            <option value="central-library" data-lat="38.2928" data-lng="21.7858">Κεντρική Βιβλιοθήκη</option>
                                            <option value="conference-center" data-lat="38.2912" data-lng="21.7848">Συνεδριακό & Πολιτιστικό Κέντρο</option>
                                            <option value="peace-park" data-lat="38.2918" data-lng="21.7855">Πάρκο Ειρήνης</option>
                                        </optgroup>
                                        <optgroup label="🍽️ Διατροφή & Υποστήριξη">
                                            <option value="fep-canteen" data-lat="38.2940" data-lng="21.7875">Φοιτητική Λέσχη / Εστιατόριο</option>
                                        </optgroup>
                                    </optgroup>
                                    <optgroup label="📍 Campus Κουκούλι">
                                        <optgroup label="🏠 Φοιτητική Εστία">
                                            <option value="koukouli-dormitory" data-lat="38.2540" data-lng="21.7330">Φοιτητική Εστία Κουκούλι</option>
                                        </optgroup>
                                        <optgroup label="🏛️ Διοίκηση & Χώροι">
                                            <option value="koukouli-admin-building" data-lat="38.2530" data-lng="21.7320">Κεντρικό Κτίριο - Διοίκηση</option>
                                            <option value="koukouli-student-union" data-lat="38.2535" data-lng="21.7325">Φοιτητική Λέσχη Κουκούλι</option>
                                        </optgroup>
                                    </optgroup>
                                    <optgroup label="🏢 Φοιτητικές Εστίες (Προσαρμοσμένο)">
                                        <option value="dorm_custom" data-lat="38.2865" data-lng="21.7862">🏢 Φοιτητικές Εστίες (με Αριθμό Δωματίου)</option>
                                    </optgroup>
                                    <optgroup label="🏠 Στο Σπίτι μου (Προσαρμοσμένο)">
                                        <option value="home_custom" data-lat="38.2466" data-lng="21.7346">🏠 Στο Σπίτι μου (Οδός & Αριθμός)</option>
                                    </optgroup>
                                </select>
                                <small class="form-hint" id="pickup-hint">Επιλέξτε ένα από τα επίσημα σημεία παράδοσης του Πανεπιστημίου Πατρών</small>
                            </div>
                            <!-- Dynamic field for dorm_custom -->
                            <div class="form-group" id="dorm-custom-field" style="display: none;">
                                <label class="form-label">ΚΤΙΡΙΟ & ΑΡΙΘΜΟΣ ΔΩΜΑΤΙΟΥ ΕΣΤΙΑΣ <span style="color: var(--dp-color-danger);">*</span></label>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label">ΚΤΙΡΙΟ</label>
                                        <input type="text" id="dorm-building" name="dorm_building" class="form-control" placeholder="π.χ. ΦΕΠ Κτίριο Α" autocomplete="off">
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label">ΑΡΙΘΜΟΣ ΔΩΜΑΤΙΟΥ</label>
                                        <input type="text" id="dorm-number" name="dorm_number" class="form-control" placeholder="π.χ. 112" autocomplete="off">
                                    </div>
                                </div>
                                <small class="form-hint">Συμπληρώστε κτίριο/πτέρυγα και αριθμό δωματίου (π.χ. ΦΕΠ Κτίριο Β, Δωμ. 204)</small>
                            </div>
                            <!-- Dynamic fields for home_custom -->
                            <div class="form-group" id="home-custom-fields" style="display: none;">
                                <label class="form-label">ΕΠΙΛΟΓΗ ΣΠΙΤΙΟΥ ΣΤΟΝ ΧΆΡΤΗ <span style="color: var(--dp-color-danger);">*</span></label>
                                
                                <!-- Map Pin Picker -->
                                <div style="margin-bottom: var(--dp-spacing-md);">
                                    <p style="margin: 0 0 8px; font-size: var(--dp-typography-small); color: var(--dp-color-ink-secondary);">
                                        <strong>📍 Οδηγίες:</strong> Κάντε κλικ στον χάρτη ή σύρετε την πινέζα ακριβώς πάνω στο σπίτι σας. Η διεύθυνση θα συμπληρωθεί αυτόματα.
                                    </p>
                                    <div id="home-map" style="height: 240px; border-radius: var(--dp-radius-control); border: var(--dp-border-hairline) solid var(--dp-color-rule); background: var(--dp-color-ground-muted);"></div>
                                </div>
                                
                                <!-- Editable address fields (auto-filled from map) -->
                                <div class="address-grid">
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label">ΟΔΟΣ & ΠΕΡΙΟΧΗ <span style="color: var(--dp-color-danger);">*</span></label>
                                        <input type="text" id="home-street" name="home_street" class="form-control" placeholder="π.χ. Κορίνθου, Πάτρα" autocomplete="off" required>
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label">ΑΡΙΘΜΟΣ <span style="color: var(--dp-color-danger);">*</span></label>
                                        <input type="text" id="home-number" name="home_number" class="form-control" placeholder="π.χ. 120" autocomplete="off" required>
                                    </div>
                                </div>
                                
                                <!-- Floor and Bell fields (side by side) -->
                                <div class="floor-bell-grid">
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label">ΟΡΟΦΟΣ</label>
                                        <input type="text" id="home-floor" name="home_floor" class="form-control" placeholder="π.χ. 2ος" autocomplete="off">
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label">ΟΝΟΜΑ ΣΤΟ ΚΟΥΔΟΥΝΙ</label>
                                        <input type="text" id="home-bell" name="home_bell" class="form-control" placeholder="π.χ. Παπαδόπουλος" autocomplete="off">
                                    </div>
                                </div>
                                
                                <!-- Hidden coordinates field -->
                                <input type="hidden" id="coordinates" name="coordinates" value="38.2466, 21.7346">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Ώρα Παραλαβής</label>
                                <input type="datetime-local" id="pickup_time" name="pickup_time" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%;">Δημιουργία Αγγελίας</button>
                        </form>
                    </div>

                    <!-- Offers List Column -->
                    <div>
                        <h3>Οι Αγγελίες μου</h3>
                        <div id="offers-list">
                            <p style="color: var(--dp-color-ink-secondary);">Φόρτωση αγγελιών...</p>
                        </div>
                    </div>
                            </div>
                        </div>

                        <!-- Tab: Στατιστικά & Πόντοι - Full Width Layout -->
                        <div id="tab-stats" class="tab-panel">
                            <div class="stats-container" style="max-width: 1100px; margin: 0 auto;">
                                <div class="card" style="margin-bottom: var(--dp-spacing-xl);">
                                    <h3>📊 Συνολική Επισκόπηση</h3>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3 id="stat-total-points">0</h3>
                            <p>Συνολικοί Πόντοι</p>
                        </div>
                        <div class="stat-card">
                            <h3 id="stat-total-offers">0</h3>
                            <p>Αγγελίες που Δημιούργησα</p>
                        </div>
                        <div class="stat-card">
                            <h3 id="stat-total-portions">0</h3>
                            <p>Μερίδες που Προσέφερα</p>
                        </div>
                        <div class="stat-card">
                            <h3 id="stat-avg-rating">-</h3>
                            <p>Μέση Βαθμολογία</p>
                        </div>
                        <div class="stat-card">
                            <h3 id="stat-points-from-offers">0</h3>
                            <p>Πόντοι από Προσφορές</p>
                        </div>
                        <div class="stat-card">
                            <h3 id="stat-penalties-received">0</h3>
                            <p>Ποινές που Έλαβα</p>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h3>🍽️ Ιστορικό Γευμάτων & Αξιολογήσεων</h3>
                    <div id="stats-offers-list">
                        <p style="color: var(--dp-color-ink-secondary);">Φόρτωση στατιστικών...</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit Offer Modal -->
    <div id="edit-modal" class="modal-overlay">
        <div class="modal" style="max-width: 550px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <h2>Επεξεργασία Αγγελίας</h2>
            </div>
            <form id="edit-offer-form" enctype="multipart/form-data">
                <input type="hidden" id="edit-offer-id" name="offer_id">
                <div class="form-group">
                    <label class="form-label">Τίτλος Γεύματος</label>
                    <input type="text" id="edit-title" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Περιγραφή & Συστατικά</label>
                    <textarea id="edit-description" name="description" class="form-control" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Αριθμός Μερίδων</label>
                    <input type="number" id="edit-portions" name="portions" class="form-control" min="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Φωτογραφία (προαιρετική)</label>
                    <input type="file" id="edit-photo" name="photo" class="form-control" accept="image/*">
                    <small class="form-hint">Αφήστε κενό για να διατηρήσετε την υπάρχουσα</small>
                    <div id="edit-photo-preview" style="margin-top: var(--dp-spacing-sm); display: none;">
                        <img id="edit-photo-img" src="" alt="Τρέχουσα φωτογραφία" style="max-width: 200px; max-height: 200px; border-radius: 8px; border: var(--dp-border-hairline) solid var(--dp-color-rule);">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Αλλεργιογόνα (προαιρετικά)</label>
                    <div id="edit-allergens-container"></div>
                    <small class="form-hint">Επιλέξτε τα γνωστά αλλεργιογόνα</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Σημείο Παραλαβής</label>
                    <select id="edit-pickup_location" name="pickup_location" class="form-control" required>
                        <option value="">-- Επιλέξτε σημείο παράδοσης --</option>
                        <optgroup label="🏛️ Campus Ρίο">
                            <optgroup label="🏠 Φοιτητικές Εστίες (ΦΕΠ)">
                                <option value="fep-building-a" data-lat="38.2942" data-lng="21.7887">ΦΕΠ - Κτίριο Α (Κοιτονομήματα)</option>
                                <option value="fep-building-b" data-lat="38.2945" data-lng="21.7892">ΦΕΠ - Κτίριο Β (Κοιτονομήματα)</option>
                                <option value="fep-building-c" data-lat="38.2948" data-lng="21.7897">ΦΕΠ - Κτίριο Γ (Κοιτονομήματα)</option>
                                <option value="fep-building-z" data-lat="38.2951" data-lng="21.7902">ΦΕΠ - Κτίριο Ζ (Κοιτονομήματα)</option>
                                <option value="fep-building-h" data-lat="38.2954" data-lng="21.7907">ΦΕΠ - Κτίριο Η (Κοιτονομήματα)</option>
                                <option value="fep-building-th" data-lat="38.2957" data-lng="21.7912">ΦΕΠ - Κτίριο Θ (Κοιτονομήματα)</option>
                            </optgroup>
                            <optgroup label="🏫 Τμήματα & Σχολές">
                                <option value="building-b" data-lat="38.2920" data-lng="21.7870">Κτίριο Β - Πολυτεχνική / Αρχιτέκτονες / Φυσικοθεραπεία</option>
                                <option value="ceid-building" data-lat="38.2905" data-lng="21.7920">CEID - Μηχανικοί Η/Υ & Πληροφορικής</option>
                                <option value="ece-building" data-lat="38.2910" data-lng="21.7930">ECE - Ηλεκτρονικοί Υπολογιστές & Τεχνολογία Υπολογιστών</option>
                                <option value="mech-aero-building" data-lat="38.2925" data-lng="21.7940">Μηχανολόγοι & Αεροναυπηγικοί Μηχανικοί</option>
                                <option value="civil-eng-building" data-lat="38.2930" data-lng="21.7945">Πολιτικοί Μηχανικοί</option>
                                <option value="chem-eng-building" data-lat="38.2935" data-lng="21.7950">Χημικοί Μηχανικοί</option>
                                <option value="med-preclinical-building" data-lat="38.2900" data-lng="21.7850">Ιατρική - Προκλινικά / Αμφιθέατρα</option>
                                <option value="med-clinical-building" data-lat="38.2895" data-lng="21.7845">Ιατρική - Κλινικές Λειτουργίες / Νοσοκομείο</option>
                                <option value="physics-math-building" data-lat="38.2918" data-lng="21.7830">Φυσική / Μαθηματικά</option>
                                <option value="chemistry-building-north" data-lat="38.2922" data-lng="21.7825">Χημεία - Βόρειο Κτίριο</option>
                                <option value="chemistry-building-south" data-lat="38.2928" data-lng="21.7835">Χημεία - Νότιο Κτίριο</option>
                                <option value="biology-building" data-lat="38.2910" data-lng="21.7810">Βιολογία / Μαθηματικά</option>
                                <option value="geology-building" data-lat="38.2905" data-lng="21.7805">Γεωλογία</option>
                                <option value="education-social-work-building" data-lat="38.2900" data-lng="21.7795">Επιστήμες Εκπαίδευσης & Κοινωνικής Εργασίας</option>
                            </optgroup>
                            <optgroup label="🏛️ Κοινόχρηστοι & Διοικητικοί Χώροι">
                                <option value="admin-building-a" data-lat="38.2915" data-lng="21.7865">Κτίριο Α - Διοίκηση / Πρυτανεία</option>
                                <option value="central-library" data-lat="38.2928" data-lng="21.7858">Κεντρική Βιβλιοθήκη</option>
                                <option value="conference-center" data-lat="38.2912" data-lng="21.7848">Συνεδριακό & Πολιτιστικό Κέντρο</option>
                                <option value="peace-park" data-lat="38.2918" data-lng="21.7855">Πάρκο Ειρήνης</option>
                            </optgroup>
                            <optgroup label="🍽️ Διατροφή & Υποστήριξη">
                                <option value="fep-canteen" data-lat="38.2940" data-lng="21.7875">Φοιτητική Λέσχη / Εστιατόριο</option>
                            </optgroup>
                        </optgroup>
                        <optgroup label="📍 Campus Κουκούλι">
                            <optgroup label="🏠 Φοιτητική Εστία">
                                <option value="koukouli-dormitory" data-lat="38.2540" data-lng="21.7330">Φοιτητική Εστία Κουκούλι</option>
                            </optgroup>
                            <optgroup label="🏛️ Διοίκηση & Χώροι">
                                <option value="koukouli-admin-building" data-lat="38.2530" data-lng="21.7320">Κεντρικό Κτίριο - Διοίκηση</option>
                                <option value="koukouli-student-union" data-lat="38.2535" data-lng="21.7325">Φοιτητική Λέσχη Κουκούλι</option>
                            </optgroup>
                        </optgroup>
                        <optgroup label="🏢 Φοιτητικές Εστίες (Προσαρμοσμένο)">
                            <option value="dorm_custom" data-lat="38.2865" data-lng="21.7862">🏢 Φοιτητικές Εστίες (με Αριθμό Δωματίου)</option>
                        </optgroup>
                        <optgroup label="🏠 Στο Σπίτι μου (Προσαρμοσμένο)">
                            <option value="home_custom" data-lat="38.2466" data-lng="21.7346">🏠 Στο Σπίτι μου (Οδός & Αριθμός)</option>
                        </optgroup>
                    </select>
                    <small class="form-hint" id="edit-pickup-hint">Επιλέξτε ένα από τα επίσημα σημεία παράδοσης του Πανεπιστημίου Πατρών</small>
                </div>
                <!-- Dynamic field for dorm_custom in edit modal -->
                <div class="form-group" id="edit-dorm-custom-field" style="display: none;">
                    <label class="form-label">ΚΤΙΡΙΟ & ΑΡΙΘΜΟΣ ΔΩΜΑΤΙΟΥ ΕΣΤΙΑΣ <span style="color: var(--dp-color-danger);">*</span></label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">ΚΤΙΡΙΟ</label>
                            <input type="text" id="edit-dorm-building" name="dorm_building" class="form-control" placeholder="π.χ. ΦΕΠ Κτίριο Α" autocomplete="off">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">ΑΡΙΘΜΟΣ ΔΩΜΑΤΙΟΥ</label>
                            <input type="text" id="edit-dorm-number" name="dorm_number" class="form-control" placeholder="π.χ. 112" autocomplete="off">
                        </div>
                    </div>
                    <small class="form-hint">Συμπληρώστε κτίριο/πτέρυγα και αριθμό δωματίου (π.χ. ΦΕΠ Κτίριο Β, Δωμ. 204)</small>
                </div>
                <!-- Dynamic fields for home_custom in edit modal -->
                <div class="form-group" id="edit-home-custom-fields" style="display: none;">
                    <label class="form-label">ΕΠΙΛΟΓΗ ΣΠΙΤΙΟΥ ΣΤΟΝ ΧΆΡΤΗ <span style="color: var(--dp-color-danger);">*</span></label>
                    
                    <!-- Map Pin Picker -->
                    <div style="margin-bottom: var(--dp-spacing-md);">
                        <p style="margin: 0 0 8px; font-size: var(--dp-typography-small); color: var(--dp-color-ink-secondary);">
                            <strong>📍 Οδηγίες:</strong> Κάντε κλικ στον χάρτη ή σύρετε την πινέζα ακριβώς πάνω στο σπίτι σας. Η διεύθυνση θα συμπληρωθεί αυτόματα.
                        </p>
                        <div id="edit-home-map" style="height: 240px; border-radius: var(--dp-radius-control); border: var(--dp-border-hairline) solid var(--dp-color-rule); background: var(--dp-color-ground-muted);"></div>
                    </div>
                    
                    <!-- Editable address fields (auto-filled from map) -->
                    <div class="address-grid">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">ΟΔΟΣ & ΠΕΡΙΟΧΗ <span style="color: var(--dp-color-danger);">*</span></label>
                            <input type="text" id="edit-home-street" name="home_street" class="form-control" placeholder="π.χ. Κορίνθου, Πάτρα" autocomplete="off" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">ΑΡΙΘΜΟΣ <span style="color: var(--dp-color-danger);">*</span></label>
                            <input type="text" id="edit-home-number" name="home_number" class="form-control" placeholder="π.χ. 120" autocomplete="off" required>
                        </div>
                    </div>
                    
                    <!-- Floor and Bell fields (side by side) -->
                    <div class="floor-bell-grid">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">ΟΡΟΦΟΣ</label>
                            <input type="text" id="edit-home-floor" name="home_floor" class="form-control" placeholder="π.χ. 2ος" autocomplete="off">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">ΟΝΟΜΑ ΣΤΟ ΚΟΥΔΟΥΝΙ</label>
                            <input type="text" id="edit-home-bell" name="home_bell" class="form-control" placeholder="π.χ. Παπαδόπουλος" autocomplete="off">
                        </div>
                    </div>
                    
                    <!-- Hidden coordinates field -->
                    <input type="hidden" id="edit-coordinates" name="coordinates" value="38.2466, 21.7346">
                </div>
                <div class="form-group">
                    <label class="form-label">Ώρα Παραλαβής</label>
                    <input type="datetime-local" id="edit-pickup_time" name="pickup_time" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Κατάσταση</label>
                    <select id="edit-status" name="status" class="form-control">
                        <option value="active">Ενεργή</option>
                        <option value="inactive">Ανενεργή (0 μερίδες)</option>
                        <option value="completed">Ολοκληρωμένη</option>
                        <option value="cancelled">Ακυρωμένη</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Αποθήκευση</button>
                    <button type="button" class="btn btn-outline" onclick="closeEditModal()">Άκυρο</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="../Shared/js/auth.js?v=<?= time() ?>"></script>
    <script src="js/cook.js?v=<?= time() ?>"></script>
</body>
</html>