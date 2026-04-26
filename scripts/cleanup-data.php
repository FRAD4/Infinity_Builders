<?php
/**
 * Clean up old test data - keep first admin user
 */

require_once __DIR__ . '/../partials/init.php';

echo "<h1>🧹 Cleanup Old Data</h1>";
echo "<p>Keeping first admin user, deleting all test data...</p>";

// Delete in correct order (respecting foreign keys)
$tables = [
    'audit_log',
    'project_tasks',
    'project_documents',
    'vendor_payments',
    'permits',
    'projects',
    'vendors'
];

foreach ($tables as $table) {
    try {
        $count = $pdo->exec("DELETE FROM $table");
        echo "<p>✅ Deleted from $table</p>";
    } catch (Exception $e) {
        echo "<p>⚠️ $table: " . $e->getMessage() . "</p>";
    }
}

// Reset auto increment for clean IDs
$tables = ['projects', 'vendors', 'permits', 'vendor_payments', 'project_documents', 'project_tasks', 'audit_log'];
foreach ($tables as $table) {
    try {
        $pdo->exec("ALTER TABLE $table AUTO_INCREMENT = 1");
    } catch (Exception $e) {}
}

echo "<h2>Remaining Users:</h2>";
$stmt = $pdo->query("SELECT id, username, email, role FROM users ORDER BY id");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($users);
echo "</pre>";

echo "<p style='color: green; font-weight: bold;'>✅ Cleanup complete! Now create fresh data.</p>";
echo "<p><a href='../projects.php' class='btn btn-primary'>Go to Projects</a></p>";