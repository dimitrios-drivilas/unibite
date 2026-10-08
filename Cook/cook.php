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
    <link rel="stylesheet" href="../Shared/css/common.css">
    <link rel="stylesheet" href="css/cook.css">
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
                <li><a href="../Consumer/consumer.php">🎒 Καταναλωτής</a></li>
                <li><a href="#" onclick="UniBiteAuth.logout('../index.php')" style="background: rgba(220, 38, 38, 0.15); color: #EF4444; border: 1px solid #DC2626; padding: 7px 16px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center;">Αποσύνδεση</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <h2 style="margin-bottom: 1.5rem; color: var(--secondary-color);">Διαχείριση Αγγελιών & Φαγητού</h2>
        
        <!-- Tabs -->
        <div class="tabs" style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem;">
            <button type="button" class="tab-btn active" data-tab="offers" style="padding: 0.75rem 1.5rem; background: var(--primary-color); color: white; border: none; border-radius: var(--radius); cursor: pointer; font-weight: 600;">🍽️ Αγγελίες μου</button>
            <button type="button" class="tab-btn" data-tab="stats" style="padding: 0.75rem 1.5rem; background: var(--card-bg); color: var(--text-color); border: 1px solid var(--border-color); border-radius: var(--radius); cursor: pointer; font-weight: 600;">📊 Στατιστικά & Πόντοι</button>
        </div>

        <div class="cook-container">
            <!-- My Offers List (Tab Panel) -->
            <div id="tab-offers" class="tab-panel active">
                <div class="cook-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
                    <!-- Create Offer Column -->
                    <div class="card" style="height: fit-content;">
                        <h3 style="margin-bottom: 1rem; color: var(--primary-color);">Νέα Αγγελία Φαγητού</h3>
                        <form id="create-offer-form" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Τίτλος Γεύματος</label>
                            <input type="text" id="title" name="title" class="form-control" placeholder="π.χ. Παστίτσιο Σπιτικό" required>
                        </div>
                        <div class="form-group">
                            <label>Περιγραφή & Συστατικά</label>
                            <textarea id="description" name="description" class="form-control" rows="3" placeholder="π.χ. Με φρέσκο μοσχαρίσιο κιμά..." required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Φωτογραφία (προαιρετική)</label>
                            <input type="file" id="photo" name="photo" class="form-control" accept="image/*">
                            <small style="color: var(--text-muted);">Μέγιστο 2MB. Υποστηριζόμενα: JPG, PNG, WebP</small>
                        </div>
                        <div class="form-group">
                            <label>Αλλεργιογόνα (προαιρετικά)</label>
                            <div id="allergens-container" style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                <!-- Populated by JS -->
                            </div>
                            <small style="color: var(--text-muted);">Επιλέξτε τα γνωστά αλλεργιογόνα</small>
                        </div>
                        <div class="form-group">
                            <label>Αριθμός Μερίδων</label>
                            <input type="number" id="portions" name="portions" class="form-control" min="1" value="2" required>
                        </div>
                        <div class="form-group">
                            <label>Σημείο Παραλαβής</label>
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
                            </select>
                            <small style="color: var(--text-muted);">Επιλέξτε ένα από τα επίσημα σημεία παράδοσης του Πανεπιστημίου Πατρών</small>
                        </div>
                        <div class="form-group">
                            <label>Συντεταγμένες (αφανές)</label>
                            <input type="text" id="coordinates" class="form-control" placeholder="π.χ. 37.983810, 23.727539" readonly style="background: #f1f5f9;">
                        </div>
                        <div class="form-group">
                            <label>Ώρα Παραλαβής</label>
                            <input type="datetime-local" id="pickup_time" name="pickup_time" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Δημιουργία Αγγελίας</button>
                    </form>
                </div>

                <!-- Offers List Column -->
                <div>
                    <h3 style="margin-bottom: 1rem; color: var(--secondary-color);">Οι Αγγελίες μου</h3>
                    <div id="offers-list">
                        <p style="color: var(--text-muted);">Φόρτωση αγγελιών...</p>
                    </div>
                </div>
            </div>
            </div>

            <!-- Stats Tab Panel -->
            <div id="tab-stats" class="tab-panel" style="display: none;">
                <div class="card" style="margin-bottom: 1.5rem;">
                    <h3 style="margin-bottom: 1rem; color: var(--primary-color);">📊 Συνολική Επισκόπηση</h3>
                    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div class="stat-card">
                            <h3 id="stat-total-points" style="font-size: 2.5rem; color: var(--primary-color);">0</h3>
                            <p style="color: var(--text-muted);">Συνολικοί Πόντοι</p>
                        </div>
                        <div class="stat-card">
                            <h3 id="stat-total-offers" style="font-size: 2.5rem; color: var(--secondary-color);">0</h3>
                            <p style="color: var(--text-muted);">Αγγελίες που Δημιούργησα</p>
                        </div>
                        <div class="stat-card">
                            <h3 id="stat-total-portions" style="font-size: 2.5rem; color: var(--success-color);">0</h3>
                            <p style="color: var(--text-muted);">Μερίδες που Προσέφερα</p>
                        </div>
                        <div class="stat-card">
                            <h3 id="stat-avg-rating" style="font-size: 2.5rem; color: var(--warning-color);">-</h3>
                            <p style="color: var(--text-muted);">Μέση Βαθμολογία</p>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h3 style="margin-bottom: 1rem; color: var(--primary-color);">🍽️ Ιστορικό Γευμάτων & Αξιολογήσεων</h3>
                    <div id="stats-offers-list">
                        <p style="color: var(--text-muted);">Φόρτωση στατιστικών...</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit Offer Modal -->
    <div id="edit-modal" class="modal-overlay">
        <div class="modal" style="max-width: 550px; max-height: 90vh; overflow-y: auto;">
            <h2 style="margin-bottom: 1.5rem; color: var(--secondary-color);">Επεξεργασία Αγγελίας</h2>
            <form id="edit-offer-form" enctype="multipart/form-data">
                <input type="hidden" id="edit-offer-id" name="offer_id">
                <div class="form-group">
                    <label>Τίτλος Γεύματος</label>
                    <input type="text" id="edit-title" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Περιγραφή & Συστατικά</label>
                    <textarea id="edit-description" name="description" class="form-control" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label>Αριθμός Μερίδων</label>
                    <input type="number" id="edit-portions" name="portions" class="form-control" min="1" required>
                </div>
                <div class="form-group">
                    <label>Φωτογραφία (προαιρετική)</label>
                    <input type="file" id="edit-photo" name="photo" class="form-control" accept="image/*">
                    <small style="color: var(--text-muted);">Αφήστε κενό για να διατηρήσετε την υπάρχουσα</small>
                    <div id="edit-photo-preview" style="margin-top: 0.5rem; display: none;">
                        <img id="edit-photo-img" src="" alt="Τρέχουσα φωτογραφία" style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 1px solid var(--border-color);">
                    </div>
                </div>
                <div class="form-group">
                    <label>Αλλεργιογόνα (προαιρετικά)</label>
                    <div id="edit-allergens-container" style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        <!-- Populated by JS -->
                    </div>
                    <small style="color: var(--text-muted);">Επιλέξτε τα γνωστά αλλεργιογόνα</small>
                </div>
                <div class="form-group">
                    <label>Σημείο Παραλαβής</label>
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
                    </select>
                    <small style="color: var(--text-muted);">Επιλέξτε ένα από τα επίσημα σημεία παράδοσης του Πανεπιστημίου Πατρών</small>
                </div>
                <div class="form-group">
                    <label>Συντεταγμένες</label>
                    <input type="text" id="edit-coordinates" name="coordinates" class="form-control" placeholder="π.χ. 37.983810, 23.727539">
                </div>
                <div class="form-group">
                    <label>Ώρα Παραλαβής</label>
                    <input type="datetime-local" id="edit-pickup_time" name="pickup_time" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Κατάσταση</label>
                    <select id="edit-status" name="status" class="form-control">
                        <option value="active">Ενεργή</option>
                        <option value="inactive">Ανενεργή (0 μερίδες)</option>
                        <option value="completed">Ολοκληρωμένη</option>
                        <option value="cancelled">Ακυρωμένη</option>
                    </select>
                </div>
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Αποθήκευση</button>
                    <button type="button" class="btn btn-outline" onclick="closeEditModal()">Άκυρο</button>
                </div>
            </form>
        </div>

    <script src="../Shared/js/auth.js"></script>
            <script src="js/cook.js"></script>
    </body>
    </html>