<?php
// Shared/php/auth_guard.php
// Usage: require_once __DIR__ . '/../Shared/php/auth_guard.php'; at top of PHP pages
// Call: authGuard(['consumer']); or authGuard(['cook']); or authGuard(['admin']); or authGuard(['consumer', 'cook', 'admin']);
// Roles are now stored as JSON array in the database

require_once __DIR__ . '/../../config/db.php';

function authGuard(array $allowedRoles = []) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['roles'])) {
        // AJAX request? Return JSON error
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'error' => 'Απαιτείται σύνδεση.', 'redirect' => '/index.php']);
            exit;
        }
        // Regular page request - redirect to login
        header('Location: /index.php');
        exit;
    }
    
    $userRoles = $_SESSION['roles'];
    if (!is_array($userRoles)) {
        $userRoles = json_decode($userRoles, true) ?: [];
    }
    
    if (!empty($allowedRoles)) {
        $hasRole = false;
        foreach ($allowedRoles as $allowedRole) {
            if (in_array($allowedRole, $userRoles, true)) {
                $hasRole = true;
                break;
            }
        }
        if (!$hasRole) {
            // Wrong role - redirect to first available role page
            $redirectMap = [
                'consumer' => '/Consumer/consumer.php',
                'cook' => '/Cook/cook.php',
                'admin' => '/Admin/admin.php'
            ];
            $redirect = '/index.php';
            foreach ($userRoles as $role) {
                if (isset($redirectMap[$role])) {
                    $redirect = $redirectMap[$role];
                    break;
                }
            }
            header('Location: ' . $redirect);
            exit;
        }
    }
    
    return [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'roles' => $userRoles
    ];
}

// Helper to get current user without redirecting (for APIs)
function getCurrentUser() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['user_id']) && isset($_SESSION['roles'])) {
        $roles = $_SESSION['roles'];
        if (!is_array($roles)) {
            $roles = json_decode($roles, true) ?: [];
        }
        return [
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'roles' => $roles
        ];
    }
    return null;
}

// Helper to check if user has a specific role
function hasRole(string $role): bool {
    $user = getCurrentUser();
    if (!$user) return false;
    return in_array($role, $user['roles'], true);
}

// Helper to check if user has any of the given roles
function hasAnyRole(array $roles): bool {
    $user = getCurrentUser();
    if (!$user) return false;
    foreach ($roles as $role) {
        if (in_array($role, $user['roles'], true)) {
            return true;
        }
    }
    return false;
}