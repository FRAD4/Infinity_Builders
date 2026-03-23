<?php
/**
 * Password Migration Script - SHA256 to bcrypt
 * Infinity Builders Phase 2 Security
 * 
 * Usage:
 *   php migrate_passwords.php           # Dry-run (preview only)
 *   php migrate_passwords.php --live    # Apply migration
 *   php migrate_passwords.php --status  # Show current status
 */

// Define DB constants directly (avoid config conflicts)
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'infinity_builders');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
}

// Create mysqli connection directly
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    die("ERROR: Cannot connect to database: " . $db->connect_error . "\n");
}
$db->set_charset(DB_CHARSET);

// Determine mode
$mode = $argv[1] ?? '--dry-run';

echo "=== Infinity Builders Password Migration Script ===\n";
echo "Mode: " . ($mode === '--live' ? 'LIVE (will apply changes)' : 'DRY-RUN (preview only)') . "\n";
echo str_repeat("-", 50) . "\n\n";

// Get users with sha256 passwords
try {
    $result = $db->query("SELECT id, username, email, password_algo FROM users WHERE password_algo = 'sha256'");
    $users = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    die("ERROR: Cannot query users: " . $e->getMessage() . "\n");
}

if (empty($users)) {
    echo "✓ No users need migration (all users are already on bcrypt or no sha256 users found)\n";
    exit(0);
}

echo "Found " . count($users) . " user(s) with SHA256 passwords:\n\n";

$count = 0;
foreach ($users as $user) {
    $count++;
    echo "  [$count] ID: {$user['id']}\n";
    echo "      Username: {$user['username']}\n";
    echo "      Email: {$user['email']}\n";
    echo "      Current algo: {$user['password_algo']}\n";
    echo "      Action: Will be marked for bcrypt migration on next login\n";
    
    if ($mode === '--live') {
        // Mark user for migration - they'll migrate on next login
        try {
            $db->query("UPDATE users SET password_algo = 'sha256_migration_pending' WHERE id = " . (int)$user['id']);
            echo "      Status: ✓ Marked for migration\n";
        } catch (Exception $e) {
            echo "      Status: ✗ ERROR - " . $e->getMessage() . "\n";
        }
    } else {
        echo "      Status: (dry-run - no changes applied)\n";
    }
    echo "\n";
}

echo str_repeat("-", 50) . "\n";

if ($mode === '--live') {
    echo "✓ Migration complete: $count user(s) marked for bcrypt migration\n";
    echo "  These users will be migrated to bcrypt on their next login.\n";
} else {
    echo "✓ Dry-run complete: $count user(s) would be marked for migration\n";
    echo "  Run with --live to apply changes.\n";
}

// Log migration if table exists
if ($mode === '--live' && table_exists($db, 'password_migration_log')) {
    try {
        foreach ($users as $user) {
            $db->query("INSERT INTO password_migration_log (user_id, action, migrated_at) VALUES (" . (int)$user['id'] . ", 'marked_for_migration', NOW())");
        }
        echo "\n✓ Migration logged to password_migration_log table\n";
    } catch (Exception $e) {
        // Silent fail for logging
    }
}

/**
 * Check if a table exists
 */
function table_exists(mysqli $db, string $table): bool {
    try {
        $result = $db->query("SHOW TABLES LIKE '$table'");
        return $result->num_rows > 0;
    } catch (Exception $e) {
        return false;
    }
}
