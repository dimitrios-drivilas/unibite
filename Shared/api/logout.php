<?php
// Shared/api/logout.php
require_once '../../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

session_destroy();

echo json_encode(['success' => true, 'message' => 'Αποσυνδεθήκατε επιτυχώς.']);