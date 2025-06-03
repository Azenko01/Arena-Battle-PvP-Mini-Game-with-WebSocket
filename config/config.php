<?php
/**
 * Database Configuration
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'arena_battle');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * Application Configuration
 */
define('APP_NAME', 'Arena Battle');
define('APP_URL', 'http://localhost/arena_battle');

/**
 * Game Configuration
 */
define('GAME_MAX_PLAYERS', 10);
define('GAME_ROUND_TIME', 120); // seconds
define('GAME_ARENA_SIZE', 800); // pixels

/**
 * WebSocket Configuration
 */
define('WS_HOST', '127.0.0.1');
define('WS_PORT', 8080);

/**
 * Error Reporting
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Session Configuration
 */
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
session_name('ARENA_BATTLE_SESSION');

/**
 * Security Functions
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Helper Functions
 */
function redirectTo($path) {
    header("Location: " . APP_URL . "/" . $path);
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirectTo('public_html/login.php');
    }
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
