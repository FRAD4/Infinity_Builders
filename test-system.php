<?php
/**
 * Test script to verify system functionality
 * Run from browser: http://localhost/Infinity%20Builders/public_html/test-system.php
 */

// Database connection
$db_host = 'localhost';
$db_name = 'infinity_builders';
$db_user = 'root';
$db_pass = '';

echo "<pre style='background:#1E293B;color:#F8FAFC;padding:20px;border-radius:8px;white-space:pre-wrap;'>";
echo "<h1 style='color:#F97316'>🧪 Infinity Builders - System Tests</h1>";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connection OK\n";
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

// ====================================
// Test 1: Check audit_log table (create if needed)
// ====================================
echo "\n<h2>1. Audit Log Table</h2>\n";
try {
    // Try to create if doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            username VARCHAR(100) NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id INT NULL,
            entity_name VARCHAR(255) NULL,
            changes TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_action (action_type),
            INDEX idx_entity (entity_type),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $stmt = $pdo->query("DESCRIBE audit_log");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Table ready\n";
    
    $count = $pdo->query("SELECT COUNT(*) as cnt FROM audit_log")->fetch(PDO::FETCH_ASSOC);
    echo "  Records: " . $count['cnt'] . "\n";
    
    // Insert test record if empty
    if ($count['cnt'] == 0) {
        $pdo->exec("INSERT INTO audit_log (username, action_type, entity_type, entity_name) VALUES 
            ('system', 'create', 'projects', 'Test Project'),
            ('system', 'create', 'vendors', 'Test Vendor'),
            ('system', 'login', 'users', 'admin')");
        echo "  ⚠️ Added test records\n";
    }
    
    $recent = $pdo->query("SELECT id, action_type, entity_type, username, created_at FROM audit_log ORDER BY created_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    if ($recent) {
        echo "  Recent:\n";
        foreach ($recent as $r) {
            echo "    - " . $r['action_type'] . " on " . $r['entity_type'] . " by " . $r['username'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Table error: " . $e->getMessage() . "\n";
}

// ====================================
// Test 2: Check projects table
// ====================================
echo "\n<h2>2. Projects Table</h2>\n";
try {
    $count = $pdo->query("SELECT COUNT(*) as cnt FROM projects")->fetch(PDO::FETCH_ASSOC);
    echo "✅ Records: " . $count['cnt'] . "\n";
    
    // Check status values
    $statuses = $pdo->query("SELECT DISTINCT status FROM projects")->fetchAll(PDO::FETCH_ASSOC);
    echo "  Status values: ";
    foreach ($statuses as $s) {
        echo $s['status'] . " ";
    }
    echo "\n";
} catch (Exception $e) {
    echo "❌ Table error: " . $e->getMessage() . "\n";
}

// ====================================
// Test 3: Check vendors table
// ====================================
echo "\n<h2>3. Vendors Table</h2>\n";
try {
    $count = $pdo->query("SELECT COUNT(*) as cnt FROM vendors")->fetch(PDO::FETCH_ASSOC);
    echo "✅ Records: " . $count['cnt'] . "\n";
} catch (Exception $e) {
    echo "❌ Table error: " . $e->getMessage() . "\n";
}

// ====================================
// Test 4: Check vendor_payments table
// ====================================
echo "\n<h2>4. Vendor Payments Table</h2>\n";
try {
    $count = $pdo->query("SELECT COUNT(*) as cnt FROM vendor_payments")->fetch(PDO::FETCH_ASSOC);
    echo "✅ Records: " . $count['cnt'] . "\n";
    
    $total = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM vendor_payments")->fetch(PDO::FETCH_ASSOC);
    echo "  Total paid: $" . number_format($total['total'], 2) . "\n";
} catch (Exception $e) {
    echo "❌ Table error: " . $e->getMessage() . "\n";
}

// ====================================
// Test 5: Check emails_log table
// ====================================
echo "\n<h2>5. Emails Log Table</h2>\n";
try {
    // Create if doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS emails_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            to_email VARCHAR(255) NOT NULL,
            subject VARCHAR(500) NOT NULL,
            body TEXT NOT NULL,
            status ENUM('sent', 'failed') NOT NULL,
            error_message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✅ Table ready\n";
    
    $count = $pdo->query("SELECT COUNT(*) as cnt FROM emails_log")->fetch(PDO::FETCH_ASSOC);
    echo "  Records: " . $count['cnt'] . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// ====================================
// Test 6: Check includes/audit.php functions
// ====================================
echo "\n<h2>6. Include Files</h2>\n";
if (file_exists(__DIR__ . '/includes/audit.php')) {
    echo "✅ includes/audit.php exists\n";
    require_once __DIR__ . '/includes/audit.php';
    if (function_exists('get_audit_logs')) {
        echo "✅ get_audit_logs() function available\n";
    }
    if (function_exists('audit_log')) {
        echo "✅ audit_log() function available\n";
    }
} else {
    echo "❌ includes/audit.php missing\n";
}

if (file_exists(__DIR__ . '/includes/export.php')) {
    echo "✅ includes/export.php exists\n";
} else {
    echo "❌ includes/export.php missing\n";
}

if (file_exists(__DIR__ . '/includes/email.php')) {
    echo "✅ includes/email.php exists\n";
    require_once __DIR__ . '/includes/email.php';
    if (function_exists('send_email')) {
        echo "✅ send_email() function available\n";
    }
} else {
    echo "⚠️ includes/email.php missing (needed for email notifications)\n";
}

// Quick function test
echo "\n<h2>7. Quick Function Test</h2>\n";
try {
    require_once __DIR__ . '/includes/audit.php';
    $logs = get_audit_logs(['limit' => 3]);
    echo "✅ get_audit_logs() returned " . count($logs) . " records\n";
} catch (Exception $e) {
    echo "❌ Function test failed: " . $e->getMessage() . "\n";
}

echo "\n========================================\n";
echo "📋 Quick Links to Test Pages:\n";
echo "========================================\n";
echo "<a href='audit.php' target='_blank' style='color:#60A5FA'>🔍 Test Audit Log Page</a>\n";
echo "<a href='export-projects.php' target='_blank' style='color:#60A5FA'>📥 Test Export Projects</a>\n";
echo "<a href='export-vendors.php' target='_blank' style='color:#60A5FA'>📥 Test Export Vendors</a>\n";
echo "<a href='export-payments.php' target='_blank' style='color:#60A5FA'>📥 Test Export Payments</a>\n";
echo "<a href='export-audit.php' target='_blank' style='color:#60A5FA'>📥 Test Export Audit</a>\n";
echo "<a href='reports.php' target='_blank' style='color:#60A5FA'>📊 Test Reports Page</a>\n";
echo "<a href='dashboard.php' target='_blank' style='color:#60A5FA'>🏠 Test Dashboard</a>\n";

echo "</pre>";