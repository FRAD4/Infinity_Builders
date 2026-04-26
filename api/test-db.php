<?php
/**
 * Test API - Debug DB connection
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

global $pdo;

echo json_encode([
    'config_loaded' => true,
    'db_host' => $db_host ?? 'NOT SET',
    'db_name' => $db_name ?? 'NOT SET',
    'db_user' => $db_user ?? 'NOT SET',
    'db_pass_set' => !empty($db_pass),
    'pdo_exists' => isset($pdo) && $pdo instanceof PDO,
    'pdo_in_runtime' => (isset($pdo) ? 'yes' : 'no')
]);