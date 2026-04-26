<?php
/**
 * Debug permits data
 */

require_once __DIR__ . '/../partials/init.php';

echo "<h1>Permits Data Debug</h1>";

$stmt = $pdo->query("SELECT id, project_id, city, status, permit_required, created_at FROM permits ORDER BY id DESC LIMIT 10");
$permits = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($permits)) {
    echo "<p>No permits found in database.</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>project_id</th><th>city</th><th>status</th><th>permit_required</th><th>created_at</th></tr>";

    foreach ($permits as $p) {
        echo "<tr>";
        echo "<td>" . $p['id'] . "</td>";
        echo "<td>" . $p['project_id'] . "</td>";
        echo "<td>" . ($p['city'] ?? 'NULL') . "</td>";
        echo "<td>[" . ($p['status'] ?? 'NULL') . "]</td>";
        echo "<td>[" . ($p['permit_required'] ?? 'NULL') . "]</td>";
        echo "<td>" . ($p['created_at'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<p><a href='../permits.php'>Back to Permits</a></p>";