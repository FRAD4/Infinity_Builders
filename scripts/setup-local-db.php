<?php
require_once __DIR__ . '/../config/config.local.php';

echo "Setting up local database...\n";

$sql = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    password_algo ENUM('sha256','bcrypt') NOT NULL DEFAULT 'sha256',
    password_hash VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('active','completed','pending') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
";

try {
    $pdo->exec($sql);
    echo "Tables created successfully.\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute(['admin@infinity.com']);
    if ($stmt->fetchColumn() == 0) {
        $sha256 = hash('sha256', 'admin123');
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, password_algo) VALUES (?, ?, ?, 'admin', 'sha256')");
        $stmt->execute(['Admin', 'admin@infinity.com', $sha256]);
        echo "Test admin user created.\n";
    }
    
    echo "Local database setup complete!\n";
    echo "Login: admin@infinity.com / admin123\n";
} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
