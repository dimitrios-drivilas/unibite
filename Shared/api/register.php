<?php
// Shared/api/register.php
require_once '../../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$username = trim($data['username'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (empty($username) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Συμπληρώστε όλα τα πεδία.']);
    exit;
}

// All students get both cook and consumer roles
$roles = json_encode(['consumer', 'cook']);

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
$stmt->execute([$email, $username]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Το email ή το username χρησιμοποιείται ήδη.']);
    exit;
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, roles) VALUES (?, ?, ?, ?)");
if ($stmt->execute([$username, $password_hash, $email, $roles])) {
    echo json_encode(['success' => true, 'message' => 'Η εγγραφή ολοκληρώθηκε επιτυχώς!']);
} else {
    echo json_encode(['success' => false, 'error' => 'Σφάλμα κατά την εγγραφή.']);
}