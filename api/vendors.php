<?php
/**
 * API: List all vendors
 * GET: Returns list of vendors with id and name
 */
header('Content-Type: application/json');
require_once '../partials/init.php';

$stmt = $pdo->prepare("SELECT id, name, email FROM vendors ORDER BY name ASC");
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'vendors' => $vendors]);
