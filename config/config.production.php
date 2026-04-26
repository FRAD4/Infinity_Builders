<?php
/**
 * Production Config for Infinity Builders
 * Copy this file to config.php before deploying to production
 * 
 * IMPORTANT: Update the credentials below!
 */

// Disable error display in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// =======================
// DATABASE CONFIGURATION
// =======================
// ⚠️ UPDATE THESE VALUES FOR PRODUCTION
$db_host = 'localhost';           // Or your production DB host
$db_name = 'infinity_builders';  // Your production DB name
$db_user = 'your_prod_user';      // ⚠️ Create dedicated user with limited privileges
$db_pass = 'your_strong_password'; // ⚠️ Use strong password

// =======================
// SECURITY SETTINGS
// =======================

// Session security
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // ⚠️ Enable when HTTPS is active
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Timezone
date_default_timezone_set('America/Phoenix');

// =======================
// PDO CONFIGURATION
// =======================
try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, // Use real prepared statements
        ]
    );
} catch (PDOException $e) {
    // Log error in production, don't show details
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// =======================
// RATE LIMITING CONFIG
// =======================
// Track login attempts (implement in login.php)
define('RATE_LIMIT_MAX_ATTEMPTS', 5);      // Max attempts before lockout
define('RATE_LIMIT_WINDOW', 900);          // 15 minutes window
define('RATE_LOCKOUT_DURATION', 1800);     // 30 minutes lockout

// =======================
// FILE UPLOAD LIMITS
// =======================
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB max
define('ALLOWED_FILE_TYPES', ['application/pdf', 'image/jpeg', 'image/png', 'video/mp4']);

// session_start() moved here - only start if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: index.php");
        exit;
    }
}
