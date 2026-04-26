<?php
/**
 * Run permits migration
 * Access: http://localhost/Infinity%20Builders/public_html/scripts/run-permits-migration.php
 */

require_once __DIR__ . '/../partials/init.php';

echo "<h1>Running Permits Migration</h1>";

try {
    // Create permits table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS permits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            city VARCHAR(100) NOT NULL COMMENT 'City: Scottsdale, Phoenix, Chandler, Tempe, etc.',
            permit_required ENUM('yes', 'no') DEFAULT 'yes',
            status ENUM('not_started', 'submitted', 'in_review', 'correction_needed', 'resubmitted', 'approved', 'rejected') DEFAULT 'not_started',
            submitted_by VARCHAR(255) COMMENT 'Person responsible: Lucas Martelli, Azul Ortelli, Nicolas Ortiz',
            submission_date DATE,
            permit_number VARCHAR(100),
            corrections_required ENUM('yes', 'no') DEFAULT 'no',
            corrections_due_date DATE,
            approval_date DATE,
            notes TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            INDEX idx_project_id (project_id),
            INDEX idx_status (status),
            INDEX idx_city (city)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p>✅ Table 'permits' created</p>";
    
    // Verify columns
    $stmt = $pdo->query("DESCRIBE permits");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Columns: " . implode(', ', $columns) . "</p>";
    
    echo "<p style='color:green'>✅ Migration complete!</p>";
    echo "<p><a href='../permits.php'>Go to Permits page</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}