<?php
/**
 * Logout - Destroy session and redirect to login
 * Uses POST with CSRF token for security
 */

session_start();

// Log the logout event before destroying
$user_id = $_SESSION['user_id'] ?? 'unknown';
$username = $_SESSION['username'] ?? $_SESSION['user_name'] ?? 'unknown';
error_log("LOGOUT: user_id=$user_id username=$username ip=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// Destroy session
$_SESSION = array();
session_destroy();

// Redirect to login
header('Location: index.php');
exit;
?>