-- UniBite Database Initialization Script
-- Run this script to create all tables and insert dummy data

SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables if they exist (in reverse order of dependencies)
DROP TABLE IF EXISTS `ratings`;
DROP TABLE IF EXISTS `requests`;
DROP TABLE IF EXISTS `offers`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- USERS TABLE
-- ============================================
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `roles` JSON NOT NULL,
    `points` INT DEFAULT 5,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- OFFERS TABLE
-- ============================================
CREATE TABLE `offers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cook_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `photo` VARCHAR(500) DEFAULT NULL,
    `allergens` TEXT DEFAULT NULL,
    `portions` INT NOT NULL DEFAULT 0,
    `portions_at_creation` INT NOT NULL DEFAULT 0,
    `pickup_location` VARCHAR(255) NOT NULL,
    `latitude` DECIMAL(10, 8) NOT NULL,
    `longitude` DECIMAL(11, 8) NOT NULL,
    `pickup_time` DATETIME NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `status` ENUM('active', 'inactive', 'completed', 'cancelled', 'expired') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`cook_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- REQUESTS TABLE
-- ============================================
CREATE TABLE `requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `offer_id` INT NOT NULL,
    `consumer_id` INT NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'delivered', 'absent', 'cancelled') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `penalized` TINYINT(1) DEFAULT 0,
    FOREIGN KEY (`offer_id`) REFERENCES `offers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`consumer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_offer_consumer` (`offer_id`, `consumer_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- RATINGS TABLE
-- ============================================
CREATE TABLE `ratings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `offer_id` INT NOT NULL,
    `consumer_id` INT NOT NULL,
    `rating` TINYINT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
    `comment` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`offer_id`) REFERENCES `offers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`consumer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_rating` (`offer_id`, `consumer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSERT DUMMY DATA
-- ============================================

-- Insert test users (password is 'password' for all, hashed with password_hash)
-- password_hash('password', PASSWORD_DEFAULT) = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'

INSERT INTO `users` (`username`, `email`, `password_hash`, `roles`, `points`) VALUES
('cook_giannis', 'giannis@unibite.gr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '["cook", "consumer"]', 5),
('cook_maria', 'maria@unibite.gr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '["cook", "consumer"]', 5),
('student_kostas', 'kostas@unibite.gr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '["consumer", "cook"]', 5),
('student_elena', 'elena@unibite.gr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '["consumer", "cook"]', 5),
('admin_nikos', 'admin@unibite.gr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '["admin"]', 5);

-- Get the cook IDs for inserting offers
-- cook_giannis = 1, cook_maria = 2, student_kostas = 3, student_elena = 4

-- ============================================
-- OFFERS DATA
-- ============================================
INSERT INTO `offers` (`cook_id`, `title`, `description`, `photo`, `allergens`, `portions`, `portions_at_creation`, `pickup_location`, `latitude`, `longitude`, `pickup_time`, `expires_at`, `status`) VALUES
-- cook_giannis (id=1)
(1, '🎓 Προώθηση: Φοιτητική Κάρτα - 10% έκπτωση σε όλα τα φαγητά', 'Ειδική προσφορά για φοιτητές! Χρησιμοποιήστε τον κωδικό STUDENT10 και πάρτε 10% έκπτωση σε κάθε παραγγελία. Ισχύει για όλες τις κατηγορίες φαγητών. Περισσότερες πληροφορίες στο students.unibite.gr/promo', NULL, 'Κανένα', 999, 999, 'Online / Κωδικός STUDENT10', 38.29450000, 21.78920000, '2026-12-31 23:59:00', '2026-12-31 23:59:00', 'active'),
(1, '📦 UniBite Premium - Δωρεάν μεταφορικά για μήνα', 'Αναβαθμίστε στο UniBite Premium και απολαύστε δωρεάν μεταφορικά για 30 ημέρες! Κωδικός: FREESHIP26. Περισσότερα: premium.unibite.gr. ＊Προωθημένο περιεχόμενο＊', NULL, 'Κανένα', 1000, 1000, 'Online / Κωδικός FREESHIP26', 38.29450000, 21.78920000, '2026-11-01 00:00:00', '2026-12-31 23:59:00', 'active'),
(1, '🍔 Burger Joint "Smash Bros" - Grand Opening', 'Αύριο η μεγάλη άνοιξη! Πρώτοι 100 πελάτες παίρνουν δωρεάν cheeseburger + πατάτες. Ώρα: 13:00. Διεύθυνση: Ναυτικής Στρατιωτικής 22. Μην χάσετε το event του χρόνου! ＊Sponsored＊', NULL, 'Γλούτη, Γάλα, Αυγό, Σουσάμι', 100, 100, 'Smash Bros, Ναυτικής Στρατιωτικής 22', 38.24310000, 21.73180000, '2026-11-05 13:00:00', '2026-11-05 22:00:00', 'active'),

-- cook_maria (id=2)
(2, '🍕 Νέα Ανοιχτή Πιτσαρία "Il Forno" - Δείγμα δωρεάν!', 'Η πιτσαρία "Il Forno" άφησε τα φριτάγια! Μπαίνετε πρώτοι και πάρτε δωρεάν μικρό κομμάτι Μαργαρίτα με την παραγγελία σας. Χρησιμοποιήστε κωδικό: WELCOMEPIZZA. Διεύθυνση: Κεντρικός Δρόμος 45, Πάτρα.', NULL, 'Γλούτη (Gluten), Γάλα (Dairy)', 50, 50, 'Πιτσαρία Il Forno, Κεντρικός Δρόμος 45', 38.24670000, 21.73450000, '2026-11-15 12:00:00', '2026-11-30 23:59:00', 'active'),
(2, '🎉 Event: Food Festival Πάτρας 15-17 Νοεμβρίου', 'Το μεγαλύτερο φεστιβάλ φαγητού της Πάτρας! 50+ stands, ζωντανή μουσική, δωρεάν δείγματα. Εισόδους δωρεάν με την εφαρμογή UniBite. Εγγραφείτε τώρα: festival.unibite.gr. ＊Event promotion＊', NULL, 'Διάφορα (ανά stand)', 5000, 5000, 'Πλατεία Γεωργίου Α\' & Πεζοδρόμιο Ρίσσου', 38.24780000, 21.73560000, '2026-11-15 18:00:00', '2026-11-17 23:59:00', 'active'),

-- student_kostas (id=3) - also a cook
(3, '☕ Καφετέρια "Bean & Brew" - Loyalty Card: 10ος καφές δωρεάν', 'Συλλέξτε 9 σφραγίδες και ο 10ός καφές είναι δωρεάν! Εγγράψτε τώρα και πάρτε την πρώτη σας σφραγίδα δωρεάν με την πρώτη αγορά. Ψηφιακή κάρτα διαθέσιμη στην εφαρμογή. Μέρος της προώθησης "Φοίτηση με γεύση".', NULL, 'Κανένα', 200, 200, 'Bean & Brew, Αγοραίος 12', 38.25120000, 21.73890000, '2026-11-01 08:00:00', '2027-06-30 23:59:00', 'active'),
(3, '🥗 Healthy Meal Prep - Εβδομαδιαία συνδρομή με 20% off', 'Υγιεινά φαγητά για την εβδομάδα, έτοιμα να τα ζεστάνετε! Μενού από διατροφологούς. Προσφορά εκκίνησης: 20% έκπτωση στην πρώτη εβδομάδα. Κωδικός: HEALTHY20. Περιμένει παραδοχή...', NULL, 'Καταγράφεται ανά γεύμα', 30, 30, 'Healthy Kitchen, Μαξιμου 8', 38.25890000, 21.74120000, '2026-11-10 10:00:00', '2026-11-20 23:59:00', 'inactive'),

-- student_elena (id=4) - also a cook
(4, '🍝 Παστίτσιο της γιαγιάς - Σπιτικό με αγάπη', 'Αντιγραμμένο από την συνταγή της γιαγιάς μου! Με ντόπιο κρέας, μπεσασαμέλ και καθαρό τυρί. Δεν περιέχει συντηρητικά.', NULL, 'Γλούτη, Γάλα, Αυγό', 8, 8, 'Κολλέγιο Αποκέντρωσης, Πάτρα', 38.24950000, 21.73820000, '2026-11-10 13:00:00', '2026-11-10 20:00:00', 'active'),
(4, '🥗 Σαλάτα Κολοκυθάκια & Φέτα - Ελαφρύ Γεύμα', 'Φρέσκα κολοκυθάκια, φέτα Δωδώνης, ελιάδες Καλαμάτας, ρίγανη. Ιδανικό για λigten lunch.', NULL, 'Γάλα', 12, 12, 'Μπαλκόνι Πανεπιστημίου, Πάτρα', 38.29180000, 21.78820000, '2026-11-12 12:30:00', '2026-11-12 19:00:00', 'active');

-- ============================================
-- REQUESTS DATA
-- ============================================
-- Request statuses: pending, approved, rejected, delivered, absent, cancelled
-- Note: request IDs will be auto-assigned

-- student_kostas (id=3) requests cook_maria's offer (id=2) - pending
INSERT INTO `requests` (`offer_id`, `consumer_id`, `status`) VALUES
(2, 3, 'pending');

-- student_kostas (id=3) requests cook_giannis's offer (id=1) - approved
INSERT INTO `requests` (`offer_id`, `consumer_id`, `status`) VALUES
(1, 3, 'approved');

-- cook_maria (id=2) requests cook_giannis's offer (id=1) - delivered
INSERT INTO `requests` (`offer_id`, `consumer_id`, `status`) VALUES
(1, 2, 'delivered');

-- cook_maria (id=2) requests student_kostas's offer (id=7) - delivered
INSERT INTO `requests` (`offer_id`, `consumer_id`, `status`) VALUES
(7, 2, 'delivered');

-- student_elena (id=4) requests cook_giannis's offer (id=1) - delivered
INSERT INTO `requests` (`offer_id`, `consumer_id`, `status`) VALUES
(1, 4, 'delivered');

-- student_elena (id=4) requests student_kostas's offer (id=7) - delivered
INSERT INTO `requests` (`offer_id`, `consumer_id`, `status`) VALUES
(7, 4, 'delivered');

-- ============================================
-- RATINGS DATA
-- ============================================
-- rating: 1-5, comment: optional

-- cook_maria (id=2) rated cook_giannis's offer (id=1) - 5 stars
INSERT INTO `ratings` (`offer_id`, `consumer_id`, `rating`, `comment`) VALUES
(1, 2, 5, 'Εξαιρετικό φαγητό! Πολύ νόστιμο και ζεστό.');

-- student_kostas (id=3) rated cook_giannis's offer (id=1) - 4 stars
INSERT INTO `ratings` (`offer_id`, `consumer_id`, `rating`, `comment`) VALUES
(1, 3, 4, 'Πολύ καλό, αλλά λίγο ψυχρόταν έφτασε.');

-- student_elena (id=4) rated cook_giannis's offer (id=1) - 5 stars
INSERT INTO `ratings` (`offer_id`, `consumer_id`, `rating`, `comment`) VALUES
(1, 4, 5, 'Περфект! Θα ξαναπαραγγείλω σίγουρα.');

-- cook_giannis (id=1) rated cook_maria's offer (id=2) - 3 stars
INSERT INTO `ratings` (`offer_id`, `consumer_id`, `rating`, `comment`) VALUES
(2, 1, 3, 'Μέτρια πίτσα, το χάδι δεν ήταν και τόσο παράγωγο.');

-- student_kostas (id=3) rated cook_maria's offer (id=2) - 4 stars
INSERT INTO `ratings` (`offer_id`, `consumer_id`, `rating`, `comment`) VALUES
(2, 3, 4, 'Πολύ καλή πίτσα! Το χάδι ήταν τέλειο.');

-- student_elena (id=4) rated student_kostas's offer (id=7) - 5 stars
INSERT INTO `ratings` (`offer_id`, `consumer_id`, `rating`, `comment`) VALUES
(7, 4, 5, 'Οι καλύτεροι καφέδες στην πόλη! Το loyalty card είναι υπέροχη ιδέα.');

-- cook_giannis (id=1) rated student_elena's offer (id=8) - 5 stars
INSERT INTO `ratings` (`offer_id`, `consumer_id`, `rating`, `comment`) VALUES
(8, 1, 5, 'Το καλύτερο παστίτσιο που έχω φάει έξω από σπίτι!');

-- ============================================
-- UPDATE POINTS BASED ON RATINGS AND REQUESTS
-- ============================================
-- Points logic:
-- Base points: 5 for all users
-- Rating points: 1-3 stars = +1, 4-5 stars = +2 (to cook)
-- Point deduction on make_request: NO (deducted on approve)
-- Point deduction on approve: -1 to consumer
-- Point addition on delivered: +1 to cook
-- Point addition on rating: 1-3 stars = +1, 4-5 stars = +2 (to cook)
-- Point penalty on absent: -1 to consumer, +1 portion restored

-- After the above data:
-- cook_giannis (id=1): 5 (base) + 1 (delivered req #3) + 2 (rating 5) + 1 (rating 4) + 2 (rating 5) + 1 (rating 3) + 2 (rating 4) + 2 (rating 5) = 5 + 1 + 2 + 1 + 2 + 1 + 2 + 2 = 16
-- cook_maria (id=2): 5 (base) + 1 (delivered req #4) + 1 (rating 3) + 2 (rating 4) = 5 + 1 + 1 + 2 = 9
-- student_kostas (id=3): 5 (base) + 1 (delivered req #6) + 2 (rating 5) = 5 + 1 + 2 = 8
-- student_elena (id=4): 5 (base) + 1 (delivered req #5) + 2 (rating 5) + 2 (rating 5) = 5 + 1 + 2 + 2 = 10
-- admin_nikos (id=5): 5 (base)

-- Note: The points will be updated automatically by the application logic when actions are performed.
-- The initial values are set to 5 for all users.

-- Update points to reflect the above calculations
UPDATE `users` SET `points` = 16 WHERE `id` = 1; -- cook_giannis
UPDATE `users` SET `points` = 9 WHERE `id` = 2; -- cook_maria
UPDATE `users` SET `points` = 8 WHERE `id` = 3; -- student_kostas
UPDATE `users` SET `points` = 10 WHERE `id` = 4; -- student_elena
UPDATE `users` SET `points` = 5 WHERE `id` = 5; -- admin_nikos

-- ============================================
-- CREATE INDEXES FOR PERFORMANCE
-- ============================================
CREATE INDEX `idx_offers_cook_status` ON `offers` (`cook_id`, `status`);
CREATE INDEX `idx_offers_expires` ON `offers` (`expires_at`);
CREATE INDEX `idx_requests_offer_status` ON `requests` (`offer_id`, `status`);
CREATE INDEX `idx_requests_consumer_status` ON `requests` (`consumer_id`, `status`);
CREATE INDEX `idx_ratings_offer` ON `ratings` (`offer_id`);
CREATE INDEX `idx_ratings_consumer` ON `ratings` (`consumer_id`);

-- ============================================
-- VERIFICATION QUERIES
-- ============================================
-- Uncomment to verify the data after import
-- SELECT 'Users' as table_name, COUNT(*) as count FROM users
-- UNION ALL SELECT 'Offers', COUNT(*) FROM offers
-- UNION ALL SELECT 'Requests', COUNT(*) FROM requests
-- UNION ALL SELECT 'Ratings', COUNT(*) FROM ratings;