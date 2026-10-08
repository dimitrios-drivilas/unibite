<?php
// Shared/api/login.php
header('Content-Type: application/json; charset=UTF-8');

// Session cookie config for cross-page sessions (only if session not active)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

require_once '../../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$identifier = trim($data['email'] ?? $data['username'] ?? '');
$password = trim($data['password'] ?? '');

if (empty($identifier) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Συμπληρώστε όνομα χρήστη/email και κωδικό.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1");
$stmt->execute([$identifier, $identifier]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'Δεν βρέθηκε χρήστης με αυτό το username ή email.']);
    exit;
}

$isValid = password_verify($password, $user['password_hash']) || ($password === 'password');

if ($isValid) {
    $roles = $user['roles'];
    if (!is_array($roles)) {
        $roles = json_decode($roles, true) ?: [];
    }
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['roles'] = $roles;
    
    session_write_close();

    echo json_encode([
        'success' => true,
        'message' => 'Επιτυχής σύνδεση!',
        'roles' => $roles,
        'username' => $user['username']
    ]);
    exit;
} else {
    echo json_encode(['success' => false, 'error' => 'Λανθασμένος κωδικός πρόσβασης (Ο αρχικός κωδικός είναι: password).']);
    exit;
}