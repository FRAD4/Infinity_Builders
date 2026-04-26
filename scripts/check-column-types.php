<?php
/**
 * Check exact column types in permits table
 */

require_once __DIR__ . '/../partials/init.php';

echo "<h1>Permits Table Column Details</h1>";

$stmt = $pdo->query("SHOW FULL COLUMNS FROM permits");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Collation</th><th>Null</th><th>Default</th><th>Extra</th></tr>";

foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>" . $col['Field'] . "</td>";
    echo "<td>" . $col['Type'] . "</td>";
    echo "<td>" . ($col['Collation'] ?? '-') . "</td>";
    echo "<td>" . $col['Null'] . "</td>";
    echo "<td>" . ($col['Default'] ?? 'NONE') . "</td>";
    echo "<td>" . ($col['Extra'] ?? '-') . "</td>";
    echo "</tr>";
}

echo "</table>";

// Also check row format
$stmt = $pdo->query("SHOW TABLE STATUS LIKE 'permits'");
$status = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<p>Row format: " . ($status['Row_format'] ?? 'unknown') . "</p>";
echo "<p>Engine: " . ($status['Engine'] ?? 'unknown') . "</p>";