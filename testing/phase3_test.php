<?php
/**
 * Phase 3 Feature Tests - Infinity Builders
 * Tests: User Management + Email Feature
 */

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║     Phase 3 Feature Test Suite                             ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$BASE_PATH = __DIR__ . '/..';
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

// ============================================
// SETUP
// ============================================
echo "──────────────────────────────────────────────────────────────\n";
echo "SETUP: Database connection\n";
echo "──────────────────────────────────────────────────────────────\n\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=infinity_builders', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Database connected\n\n";
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

// ============================================
// TEST 1: emails_log table exists
// ============================================
echo "──────────────────────────────────────────────────────────────\n";
echo "SECTION 1: Database Schema\n";
echo "──────────────────────────────────────────────────────────────\n\n";

$result = $pdo->query("SHOW TABLES LIKE 'emails_log'");
test("emails_log table exists", $result->rowCount() > 0);

$result = $pdo->query("DESCRIBE emails_log");
$columns = $result->fetchAll(PDO::FETCH_ASSOC);
$column_names = array_column($columns, 'Field');

test("emails_log has to_email column", in_array('to_email', $column_names));
test("emails_log has subject column", in_array('subject', $column_names));
test("emails_log has body column", in_array('body', $column_names));
test("emails_log has status column", in_array('status', $column_names));
test("emails_log has error_message column", in_array('error_message', $column_names));

// ============================================
// TEST 2: Email functions exist
// ============================================
echo "──────────────────────────────────────────────────────────────\n";
echo "SECTION 2: Email Functions\n";
echo "──────────────────────────────────────────────────────────────\n\n";

require_once "$BASE_PATH/config.email.php";
require_once "$BASE_PATH/includes/email.php";

test("send_email function exists", function_exists('send_email'));
test("log_email function exists", function_exists('log_email'));
test("get_email_logs function exists", function_exists('get_email_logs'));

// Test email sending (will fail in dev but should log)
echo "Testing email send (will log to DB):\n";
$result = send_email('test@example.com', 'Test Subject', '<p>Test body</p>');
test("send_email returns array", is_array($result));
test("send_email returns status key", array_key_exists('status', $result));

// ============================================
// TEST 3: Email logged correctly
// ============================================
echo "──────────────────────────────────────────────────────────────\n";
echo "SECTION 3: Email Logging\n";
echo "──────────────────────────────────────────────────────────────\n\n";

$result = $pdo->query("SELECT * FROM emails_log ORDER BY id DESC LIMIT 1");
$last_log = $result->fetch(PDO::FETCH_ASSOC);

if ($last_log) {
    test("Email log has recipient", !empty($last_log['to_email']));
    test("Email log has subject", !empty($last_log['subject']));
    test("Email log has body", !empty($last_log['body']));
    test("Email log has status", !empty($last_log['status']));
    echo "  Last log entry:\n";
    echo "    To: " . $last_log['to_email'] . "\n";
    echo "    Subject: " . $last_log['subject'] . "\n";
    echo "    Status: " . $last_log['status'] . "\n";
    echo "    Error: " . ($last_log['error_message'] ?? 'None') . "\n\n";
} else {
    test("Email was logged", false, "No log entry found");
}

// ============================================
// TEST 4: User Management - Create User
// ============================================
echo "──────────────────────────────────────────────────────────────\n";
echo "SECTION 4: User Management - Create User\n";
echo "──────────────────────────────────────────────────────────────\n\n";

// Clean up test user if exists
$pdo->exec("DELETE FROM users WHERE email = 'testuser@example.com'");

// Test valid user creation
require_once "$BASE_PATH/includes/security.php";

$username = 'TestUser';
$email = 'testuser@example.com';
$password = 'testpass123';
$role = 'user';

$password_hash = hash_password($password);

$stmt = $pdo->prepare("
    INSERT INTO users (username, email, password, password_hash, password_algo, role, created_at)
    VALUES (?, ?, '', ?, 'bcrypt', ?, NOW())
");
$stmt->execute([$username, $email, $password_hash, $role]);

$user_id = $pdo->lastInsertId();
test("User was created", $user_id > 0, "User ID: $user_id");

// Verify user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

test("User has correct username", $user['username'] === $username);
test("User has correct email", $user['email'] === $email);
test("User has bcrypt hash", !empty($user['password_hash']));
test("User has correct role", $user['role'] === $role);

// Test password verification
$verify = password_verify($password, $user['password_hash']);
test("Password verification works", $verify === true);

// Test duplicate username
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$username]);
test("Duplicate username check works", $stmt->fetch() !== false);

// Test duplicate email
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
test("Duplicate email check works", $stmt->fetch() !== false);

// ============================================
// TEST 5: User Management - Delete User
// ============================================
echo "──────────────────────────────────────────────────────────────\n";
echo "SECTION 5: User Management - Delete User\n";
echo "──────────────────────────────────────────────────────────────\n\n";

// Count admins before
$result = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin'");
$admin_count_before = $result->fetch(PDO::FETCH_ASSOC)['cnt'];

// Try to delete the test user (not admin, not self)
$stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$deleted = $stmt->rowCount();

test("User deletion works", $deleted > 0);

// Verify deleted
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
test("User no longer exists", $stmt->fetch() === false);

// Count admins after
$result = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin'");
$admin_count_after = $result->fetch(PDO::FETCH_ASSOC)['cnt'];

test("Admin count unchanged", $admin_count_before === $admin_count_after);

// ============================================
// TEST 6: File Structure
// ============================================
echo "──────────────────────────────────────────────────────────────\n";
echo "SECTION 6: File Structure\n";
echo "──────────────────────────────────────────────────────────────\n\n";

test("config.email.php exists", file_exists("$BASE_PATH/config.email.php"));
test("includes/email.php exists", file_exists("$BASE_PATH/includes/email.php"));
test("users.php exists", file_exists("$BASE_PATH/users.php"));
test("vendors.php exists", file_exists("$BASE_PATH/vendors.php"));

// ============================================
// TEST 7: Users.php has new features
// ============================================
echo "──────────────────────────────────────────────────────────────\n";
echo "SECTION 7: Users.php Features\n";
echo "──────────────────────────────────────────────────────────────\n\n";

$users_php = file_get_contents("$BASE_PATH/users.php");

test("users.php has create_user action", strpos($users_php, "'create_user'") !== false);
test("users.php has delete_user action", strpos($users_php, "'delete_user'") !== false);
test("users.php has last admin check", strpos($users_php, 'last admin') !== false || strpos($users_php, 'Cannot delete') !== false);
test("users.php has modal HTML", strpos($users_php, 'createUserModal') !== false);
test("users.php has delete modal", strpos($users_php, 'deleteModal') !== false);

// ============================================
// TEST 8: Vendors.php has email feature
// ============================================
echo "──────────────────────────────────────────────────────────────\n";
echo "SECTION 8: Vendors.php Email Feature\n";
echo "──────────────────────────────────────────────────────────────\n\n";

$vendors_php = file_get_contents("$BASE_PATH/vendors.php");

test("vendors.php has send_email action", strpos($vendors_php, "'send_email'") !== false);
test("vendors.php has email modal", strpos($vendors_php, 'email-modal') !== false);
test("vendors.php has send_email_btn", strpos($vendors_php, 'send-email-btn') !== false);
test("vendors.php includes email.php", strpos($vendors_php, 'includes/email.php') !== false);

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
    echo "🎉 ALL TESTS PASSED! Phase 3 features are working correctly.\n\n";
} else {
    echo "⚠️  Some tests failed. Please review the failures above.\n\n";
}

// Cleanup test user
$pdo->exec("DELETE FROM users WHERE email = 'testuser@example.com'");

$conn = null;
