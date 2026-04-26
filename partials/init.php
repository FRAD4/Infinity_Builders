<?php
/**
 * init.php - Common initialization for all pages
 * Includes config and auth check
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
require_login();

// Support both 'user_name' and 'username' session keys for compatibility
$currentUser = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'User';
$companyName = 'Infinity Builders';

// Generate logout token if not exists (for CSRF-protected logout)
if (empty($_SESSION['logout_token'])) {
    $_SESSION['logout_token'] = bin2hex(random_bytes(32));
}
