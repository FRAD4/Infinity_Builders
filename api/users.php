<?php
/**
 * API: List all users
 * GET: Returns list of users with id, name, email, role
 * Requires authentication
 */
header('Content-Type: application/json');
require_once '../partials/init.php';

// Require admin role to list all users
if ($_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied. Admin access required.']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, username, email, role FROM users ORDER BY username ASC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'users' => $users]);
