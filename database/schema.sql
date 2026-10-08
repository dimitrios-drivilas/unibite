-- database/schema.sql
CREATE DATABASE IF NOT EXISTS unibite_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE unibite_db;

DROP TABLE IF EXISTS ratings;
DROP TABLE IF EXISTS requests;
DROP TABLE IF EXISTS offers;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    roles JSON NOT NULL,
    points INT NOT NULL DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE offers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cook_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    photo TEXT,
    allergens TEXT,
    portions INT NOT NULL,
    portions_at_creation INT NOT NULL,
    pickup_location VARCHAR(255) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    pickup_time DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    status ENUM('active', 'inactive', 'completed', 'cancelled', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cook_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_offers_status_expires (status, expires_at),
    INDEX idx_offers_cook (cook_id)
);

CREATE TABLE requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    offer_id INT NOT NULL,
    consumer_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'delivered', 'absent', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE,
    FOREIGN KEY (consumer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_requests_consumer (consumer_id),
    INDEX idx_requests_offer (offer_id)
);

CREATE TABLE ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    offer_id INT NOT NULL,
    consumer_id INT NOT NULL,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE,
    FOREIGN KEY (consumer_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_rating (offer_id, consumer_id)
);

-- Mock Data (Password is 'password' hashed with bcrypt)
INSERT INTO users (username, password_hash, email, roles, points) VALUES
('cook_giannis', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'giannis@unibite.gr', '["cook", "consumer"]', 10),
('cook_maria', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'maria@unibite.gr', '["cook", "consumer"]', 8),
('student_kostas', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kostas@unibite.gr', '["consumer", "cook"]', 5),
('student_elena', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'elena@unibite.gr', '["consumer", "cook"]', 5),
('admin_nikos', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@unibite.gr', '["admin"]', 0);

INSERT INTO offers (cook_id, title, description, photo, allergens, portions, portions_at_creation, pickup_location, latitude, longitude, pickup_time, expires_at, status) VALUES
(1, 'Παστίτσιο Παραδοσιακό', 'Σπιτικό παστίτσιο με φρέσκο κιμά και μπεσαμέλ, 2 μερίδες διαθέσιμες.', NULL, 'Γλούτη (Gluten), Γάλα (Milk), Αυγά (Eggs)', 1, 2, 'Φοιτητική Εστία Α', 37.983810, 23.727539, DATE_ADD(NOW(), INTERVAL 2 HOUR), DATE_ADD(NOW(), INTERVAL 48 HOUR), 'active'),
(2, 'Φακές Σούπα Θρεπτικές', 'Παραδοσιακές φακές με ξύδι, καρότο και ελαιόλαδο. 3 μερίδες.', NULL, 'Σελήρι (Celery)', 3, 3, 'Οδός Πατησίων 42', 37.990120, 23.733210, DATE_ADD(NOW(), INTERVAL 4 HOUR), DATE_ADD(NOW(), INTERVAL 48 HOUR), 'active'),
(1, 'Μουσακάς Σπιτική', 'Μουσακάς με πατάτες, μελιτζάνες και κιμάς. 1 μερίδα.', NULL, 'Γλούτη (Gluten), Γάλα (Milk)', 0, 1, 'Διαγώνιος 15', 37.985000, 23.730000, DATE_ADD(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 30 HOUR), 'inactive');

INSERT INTO requests (offer_id, consumer_id, status, created_at, updated_at) VALUES
(1, 3, 'delivered', DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 30 MINUTE));

INSERT INTO ratings (offer_id, consumer_id, rating, comment) VALUES
(1, 3, 5, 'Εξαιρετικό φαγητό, πολύ ζεστό και ευγενικός ο μάγειρας!');