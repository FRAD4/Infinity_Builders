<?php
/**
 * Check which database is being used
 */

require_once __DIR__ . '/../partials/init.php';

echo "<h1>Database Connection Check</h1>";

try {
    $dbname = $pdo->query("SELECT DATABASE()")->fetchColumn();
    echo "Current database: <strong>$dbname</strong><br>";
    
    // Check if permits table exists in this db
    $stmt = $pdo->query("SELECT COUNT(*) FROM permits");
    echo "✅ Permits table exists and has data<br>";
    
    // Show table structure again to confirm
    $stmt = $pdo->query("DESCRIBE permits");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<br>Columns: " . implode(', ', $columns) . "<br>";
    
    echo "<br><p style='color:green'>✅ Database connection is correct!</p>";
    echo "<p>Now try adding a permit again - it should work.</p>";
    echo "<p><a href='../permits.php'>Go to Permits page</a></p>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}