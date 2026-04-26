<?php
/**
 * Test API - Debug DB connection
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

global $pdo;

// Get projects
$stmt = $pdo->query("SELECT id, name FROM projects LIMIT 10");
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get vendors  
$stmt = $pdo->query("SELECT id, name FROM vendors LIMIT 10");
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'projects' => $projects,
    'vendors' => $vendors
]);