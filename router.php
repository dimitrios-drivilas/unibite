<?php
// router.php for PHP built-in server
// Handles SPA routing for kata-strofi architecture

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve static files directly
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot|json)$/', $uri)) {
    $file = __DIR__ . $uri;
    if (is_file($file)) {
        return false; // Let PHP built-in server handle it
    }
}

// API routes - pass through to actual PHP files
$apiPrefixes = [
    '/Cook/api/',
    '/Consumer/api/',
    '/Admin/api/',
    '/Shared/api/'
];

foreach ($apiPrefixes as $prefix) {
    if (str_starts_with($uri, $prefix)) {
        $file = __DIR__ . $uri;
        if (is_file($file)) {
            return false; // Let PHP built-in server handle it
        }
    }
}

// For all other routes, serve index.php (SPA fallback)
$_SERVER['REQUEST_URI'] = '/index.php';
require_once __DIR__ . '/index.php';