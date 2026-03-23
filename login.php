<?php
/**
 * Login Handler
 * Infinity Builders - Phase 2 Security
 */

require_once 'config.local.php';
require_once 'includes/security.php';
require_once 'includes/sanitize.php';

// Initialize PDO if available; otherwise try to build a local connection
$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo) {
if (defined('DB_DSN') && defined('DB_USER') && defined('DB_PASSWORD')) {
        $pdo = new PDO(constant('DB_DSN'), constant('DB_USER'), constant('DB_PASSWORD'));
    } else {
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=infinity_builders', 'root', '');
        } catch (Exception $e) {
            $pdo = null;
        }
    }
}

if (!$pdo) {
    $_SESSION['login_error'] = 'Database connection not available.';
    header('Location: index.php');
    exit;
}

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Validate CSRF token
$token = $_POST['csrf_token'] ?? '';
if (!csrf_token_validate($token)) {
    $_SESSION['login_error'] = 'Invalid request. Please try again.';
    header('Location: index.php');
    exit;
}

// Sanitize inputs
$email = sanitize_post('email', 'email');
$password = $_POST['password'] ?? '';

// Basic validation
if (empty($email) || empty($password)) {
    $_SESSION['login_error'] = 'Please enter email and password.';
    header('Location: index.php');
    exit;
}

// Query user from database (handled below)

try {
    $stmt = $pdo->prepare("SELECT id, username, name AS display_name, email, password, role, password_algo, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $_SESSION['login_error'] = 'User lookup failed.';
    header('Location: index.php');
    exit;
}

if (!$user) {
    $_SESSION['login_error'] = 'Invalid email or password.';
    header('Location: index.php');
    exit;
}

// Verify password with migration support
// Use password_hash column for bcrypt, password column for sha256
$storedHash = ($user['password_algo'] === 'bcrypt') ? ($user['password_hash'] ?? '') : $user['password'];
$result = verify_password($password, $storedHash, $user['password_algo']);

if ($result === false) {
    $_SESSION['login_error'] = 'Invalid email or password.';
    header('Location: index.php');
    exit;
}

// Password verified - check if needs migration
if ($result === 'migrate') {
    // Migrate to bcrypt
    $newHash = hash_password($password);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, password_algo = 'bcrypt' WHERE id = ?");
    $stmt->execute([$newHash, $user['id']]);
}

// Regenerate session ID (prevent fixation)
secure_session_start();

// Set session variables
$displayName = $user['display_name'] ?? $user['username'] ?? $user['name'] ?? $user['email'];
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'] ?? $displayName;
$_SESSION['email'] = $user['email'];
$_SESSION['user_role'] = $user['role'];

// DEBUG
error_log("LOGIN: user_id=" . $user['id'] . " role=" . $user['role']);

// Redirect based on role (admins may be redirected elsewhere in future)
header('Location: dashboard.php');
exit;
?>
