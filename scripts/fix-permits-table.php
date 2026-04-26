<?php
/**
 * Fix permits table - add all missing columns
 */

require_once __DIR__ . '/../partials/init.php';

echo "<h1>Fix Permits Table</h1>";

$sql = [
    "ALTER TABLE permits ADD COLUMN city VARCHAR(100) NOT NULL AFTER project_id",
    "ALTER TABLE permits ADD COLUMN permit_required ENUM('yes', 'no') DEFAULT 'yes' AFTER city",
    "ALTER TABLE permits ADD COLUMN status ENUM('not_started', 'submitted', 'in_review', 'correction_needed', 'resubmitted', 'approved', 'rejected') DEFAULT 'not_started' AFTER permit_required",
    "ALTER TABLE permits ADD COLUMN submitted_by VARCHAR(255) AFTER status",
    "ALTER TABLE permits ADD COLUMN submission_date DATE AFTER submitted_by",
    "ALTER TABLE permits ADD COLUMN permit_number VARCHAR(100) AFTER submission_date",
    "ALTER TABLE permits ADD COLUMN corrections_required ENUM('yes', 'no') DEFAULT 'no' AFTER permit_number",
    "ALTER TABLE permits ADD COLUMN corrections_due_date DATE AFTER corrections_required",
    "ALTER TABLE permits ADD COLUMN approval_date DATE AFTER corrections_due_date",
    "ALTER TABLE permits ADD COLUMN notes TEXT AFTER approval_date",
    "ALTER TABLE permits ADD COLUMN created_by INT AFTER notes",
    "ALTER TABLE permits ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER created_by",
    "ALTER TABLE permits ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
];

foreach ($sql as $query) {
    try {
        $pdo->exec($query);
        echo "✅ $query<br>";
    } catch (Exception $e) {
        echo "⚠️ $query<br>&nbsp;&nbsp;Error: " . $e->getMessage() . "<br>";
    }
}

echo "<h2>Final table structure:</h2>";
$stmt = $pdo->query("DESCRIBE permits");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "<pre>" . implode("\n", $columns) . "</pre>";

echo "<p><a href='../permits.php'>Go to Permits page</a></p>";