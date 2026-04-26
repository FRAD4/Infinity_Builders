<?php
/**
 * Check permits table structure
 */

require_once __DIR__ . '/../partials/init.php';

echo "<h1>Permits Table Structure</h1>";

try {
    $stmt = $pdo->query("DESCRIBE permits");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    echo "<h2>Check: does 'city' column exist?</h2>";
    $hasCity = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'city') {
            $hasCity = true;
            echo "✅ Column 'city' EXISTS<br>";
        }
    }
    
    if (!$hasCity) {
        echo "❌ Column 'city' DOES NOT EXIST - need to add it<br>";
        echo "<p>Run this SQL:</p>";
        echo "<pre>ALTER TABLE permits ADD COLUMN city VARCHAR(100) NOT NULL AFTER project_id COMMENT 'City: Scottsdale, Phoenix, Chandler, Tempe, etc.';</pre>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}