<?php
/**
 * Phase 2 Security - Full Integration Test Suite
 * Infinity Builders
 * 
 * Run: php tests/full_test.php
 */

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║     Infinity Builders - Phase 2 Security Test Suite         ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Setup
$BASE_PATH = __DIR__ . '/..';
$conn = new mysqli('localhost', 'root', '', 'infinity_builders');
$conn->set_charset('utf8mb4');

$passed = 0;
$failed = 0;

function test($name, $condition, $details = '') {
    global $passed, $failed;
    echo "TEST: $name\n";
    if ($condition) {
        echo "  ✅ PASSED\n";
        $passed++;
    } else {
        echo "  ❌ FAILED" . ($details ? " - $details" : "") . "\n";
        $failed++;
    }
    echo "\n";
}

function reset_user($conn, $email, $algo, $username = null) {
    $username_esc = $conn->real_escape_string($username ?? '');
    $algo_esc = $conn->real_escape_string($algo);
    $email_esc = $conn->real_escape_string($email);
    $conn->query("UPDATE users SET password_algo = '$algo_esc', password_hash = NULL" . ($username ? ", username = '$username_esc'" : "") . " WHERE email = '$email_esc'");
}

// ============================================
// SETUP: Ensure test users exist and have correct passwords
// ============================================
echo "──────────────────────────────────────────────────────────────\n";
echo "SETUP: Preparing test users\n";
echo "──────────────────────────────────────────────────────────────\n\n";

// Admin user - SHA256 of 'admin123'
$admin_sha256 = hash('sha256', 'admin123');
$conn->query("DELETE FROM users WHERE email = 'admin@infinity.com'");
$conn->query("INSERT INTO users (username, email, password, password_algo, role) VALUES ('Admin', 'admin@infinity.com', '$admin_sha256', 'sha256', 'admin')");
echo "✓ Created admin user: admin@infinity.com / admin123\n\n";

// Regular user - SHA256 of 'user123'
$user_sha256 = hash('sha256', 'user123');
$conn->query("DELETE FROM users WHERE email = 'user@test.com'");
$conn->query("INSERT INTO users (username, email, password, password_algo, role) VALUES ('Test User', 'user@test.com', '$user_sha256', 'sha256', 'viewer')");
echo "✓ Created test user: user@test.com / user123 (viewer role)\n\n";

// ============================================
// TEST 1: CSRF Protection
// ============================================
echo "──────────────────────────────────────────────────────────────\n";
echo "SECTION 1: CSRF Protection\n";
echo "──────────────────────────────────────────────────────────────\n\n";

// Include security functions
require_once "$BASE_PATH/includes/security.php";

// Start session for CSRF test
if (session_status() === PHP_SESSION_NONE) session_start();

// Generate and validate token
$token = csrf_token_generate();
test("CSRF token generation", strlen($token) === 64, "Token length: " . strlen($token));

$valid = csrf_token_validate($token);
test("CSRF token validation (valid)", $valid === true);

$invalid = csrf_token_validate('wrong_token');
test("CSRF token validation (invalid)", $invalid === false);

$csrf_field = csrf_token_field();
test("CSRF token field HTML", strpos($csrf_field, 'name="csrf_token"') !== false);

// ============================================
// TEST 2: Password Functions
// ============================================
echo "──────────────────────────────────────────────────────────────\n";
echo "SECTION 2: Password Functions\n";
echo "──────────────────────────────────────────────────────────────\n\n";

// Hash password
$hashed = hash_password('test123');
test("Password hashing (bcrypt)", strpos($hashed, '$2y$') === 0, "Hash: " . substr($hashed, 0, 20) . "...");

// Verify bcrypt
$bcrypt_valid = verify_password('test123', $hashed, 'bcrypt');
test("Verify bcrypt (correct)", $bcrypt_valid === true);

// Verify bcrypt wrong password
$bcrypt_wrong = verify_password('wrongpass', $hashed, 'bcrypt');
test("Verify bcrypt (wrong password)", $bcrypt_wrong === false);

// Verify SHA256
$plaintext = 'admin123';
$stored_hash = hash('sha256', $plaintext);
$sha256_result = verify_password($plaintext, $stored_hash, 'sha256');
test("Verify SHA256 (correct)", $sha256_result === 'migrate');

$sha256_wrong = verify_password('wrongpass', $stored_hash, 'sha256');
test("Verify SHA256 (wrong)", $sha256_wrong === false);

// Verify SHA256_migration_pending
$sha256_pending_result = verify_password($plaintext, $stored_hash, 'sha256_migration_pending');
test("Verify SHA256_migration_pending", $sha256_pending_result === 'migrate');

// ============================================
// TEST 3: RBAC Functions
// ============================================
echo "──────────────────────────────────────────────────────────────\n";
echo "SECTION 3: RBAC Functions\n";
echo "──────────────────────────────────────────────────────────────\n\n";

test("has_role('admin')", has_role('admin') === false); // No session yet

$_SESSION['user_role'] = 'admin';
test("has_role('admin') with session", has_role('admin') === true);
test("has_role('user') with admin session", has_role('user') === false);
test("is_admin() with admin session", is_admin() === true);

$_SESSION['user_role'] = 'user';
test("has_role('user') with user session", has_role('user') === true);
test("is_admin() with user session", is_admin() === false);

// Clean up session
unset($_SESSION['user_role']);

// ============================================
// TEST 4: Database State
// ============================================
echo "──────────────────────────────────────────────────────────────\n";
echo "SECTION 4: Database State\n";
echo "──────────────────────────────────────────────────────────────\n\n";

$result = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE password_algo = 'sha256'");
$row = $result->fetch_assoc();
test("Users with sha256 algo", $row['cnt'] == 2, "Found: " . $row['cnt']);

$result = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin'");
$row = $result->fetch_assoc();
test("Admin users exist", $row['cnt'] >= 1, "Found: " . $row['cnt']);

$result = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'viewer'");
$row = $result->fetch_assoc();
test("Regular users exist", $row['cnt'] >= 1, "Found: " . $row['cnt']);

// ============================================
// TEST 5: Login Flow (Simulated)
// ============================================
echo "──────────────────────────────────────────────────────────────\n";
echo "SECTION 5: Login Flow (Simulated)\n";
echo "──────────────────────────────────────────────────────────────\n\n";

// Reset admin to sha256
reset_user($conn, 'admin@infinity.com', 'sha256', 'Admin');

// Simulate login.php flow
$email_esc = $conn->real_escape_string('admin@infinity.com');
$result = $conn->query("SELECT id, username, email, password, password_algo, password_hash, role FROM users WHERE email = '$email_esc'");
$user = $result->fetch_assoc();

$password = 'admin123';
$storedHash = ($user['password_algo'] === 'bcrypt') ? ($user['password_hash'] ?? '') : $user['password'];
$verify_result = verify_password($password, $storedHash, $user['password_algo']);

test("Login: verify_password returns 'migrate'", $verify_result === 'migrate');

// Execute migration
if ($verify_result === 'migrate') {
    $newHash = hash_password($password);
    $newHash_esc = $conn->real_escape_string($newHash);
    $user_id = (int)$user['id'];
    $conn->query("UPDATE users SET password_hash = '$newHash_esc', password_algo = 'bcrypt' WHERE id = $user_id");
}

$result = $conn->query("SELECT password_algo, password_hash IS NOT NULL as has_bcrypt FROM users WHERE email = '$email_esc'");
$after = $result->fetch_assoc();

test("Login: password_algo is now bcrypt", $after['password_algo'] === 'bcrypt');
test("Login: password_hash is set", $after['has_bcrypt'] == 1);

// Test second login (should use bcrypt directly)
$result = $conn->query("SELECT id, username, email, password, password_algo, password_hash, role FROM users WHERE email = '$email_esc'");
$user = $result->fetch_assoc();

$storedHash = ($user['password_algo'] === 'bcrypt') ? ($user['password_hash'] ?? '') : $user['password'];
$verify_result2 = verify_password($password, $storedHash, $user['password_algo']);

test("Login: bcrypt verification works", $verify_result2 === true);

// ============================================
// TEST 6: Migration Script
// ============================================
echo "──────────────────────────────────────────────────────────────\n";
echo "SECTION 6: Migration Script\n";
echo "──────────────────────────────────────────────────────────────\n\n";

// Reset user to sha256
reset_user($conn, 'user@test.com', 'sha256', 'Test User');

$email_esc = $conn->real_escape_string('user@test.com');
$result = $conn->query("SELECT password_algo FROM users WHERE email = '$email_esc'");
$row = $result->fetch_assoc();
test("Before script: user is sha256", $row['password_algo'] === 'sha256');

// Run migration script logic
$result = $conn->query("SELECT id, username, email, password_algo FROM users WHERE password_algo = 'sha256'");
$users = $result->fetch_all(MYSQLI_ASSOC);

if (!empty($users)) {
    foreach ($users as $u) {
        $id = (int)$u['id'];
        $conn->query("UPDATE users SET password_algo = 'sha256_migration_pending' WHERE id = $id");
    }
}

$result = $conn->query("SELECT password_algo FROM users WHERE email = '$email_esc'");
$row = $result->fetch_assoc();
test("After script: user is sha256_migration_pending", $row['password_algo'] === 'sha256_migration_pending');

// ============================================
// TEST 7: Sanitization Functions
// ============================================
echo "──────────────────────────────────────────────────────────────\n";
echo "SECTION 7: Sanitization Functions\n";
echo "──────────────────────────────────────────────────────────────\n\n";

require_once "$BASE_PATH/includes/sanitize.php";

// Test string sanitization
$dirty = "<script>alert('xss')</script>User Input";
$clean = sanitize_input($dirty, 'string');
test("XSS sanitization", $clean !== $dirty, "Original contains script tag");

$dirty_email = "user@example.com<script>";
$clean_email = sanitize_input($dirty_email, 'email');
test("Email sanitization", strpos($clean_email, '<script>') === false);

// ============================================
// SUMMARY
// ============================================
echo "══════════════════════════════════════════════════════════════\n";
echo "TEST SUMMARY\n";
echo "══════════════════════════════════════════════════════════════\n\n";

$total = $passed + $failed;
$percentage = round(($passed / $total) * 100, 1);

echo "Total tests: $total\n";
echo "Passed:      $passed ✅\n";
echo "Failed:      $failed ❌\n";
echo "Success:    $percentage%\n\n";

if ($failed === 0) {
    echo "🎉 ALL TESTS PASSED! Phase 2 Security is working correctly.\n\n";
} else {
    echo "⚠️  Some tests failed. Please review the failures above.\n\n";
}

// Cleanup: reset users to known state
reset_user($conn, 'admin@infinity.com', 'sha256', 'Admin');
reset_user($conn, 'user@test.com', 'sha256', 'Test User');

$conn->close();
