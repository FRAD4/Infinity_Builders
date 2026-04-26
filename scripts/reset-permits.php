<?php
/**
 * Clear and reset permits table for testing
 */

require_once __DIR__ . '/../partials/init.php';

echo "<h1>Reset Permits Table</h1>";

// Delete all existing permits
$count = $pdo->exec("DELETE FROM permits");
echo "<p>Deleted $count existing permits</p>";

// Reset auto_increment
$pdo->exec("ALTER TABLE permits AUTO_INCREMENT = 1");
echo "<p>Reset auto_increment</p>";

echo "<p style='color:green'>✅ Permits table reset! Now create a new permit to test.</p>";
echo "<p><a href='../permits.php'>Go to Permits page</a></p>";