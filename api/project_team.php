<?php
/**
 * API: Manage project team members
 * GET: Get team members assigned to a project
 * POST: Assign user to project
 * DELETE: Remove user from project
 */
header('Content-Type: application/json');
require_once '../partials/init.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $project_id = $_GET['project_id'] ?? null;
    if (!$project_id) {
        echo json_encode(['success' => false, 'error' => 'Missing project_id']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT pt.id, pt.user_id, u.username, u.email, u.role as user_role, pt.role as assigned_role, pt.assigned_at
        FROM project_team pt
        LEFT JOIN users u ON pt.user_id = u.id
        WHERE pt.project_id = ?
        ORDER BY pt.assigned_at DESC
    ");
    $stmt->execute([$project_id]);
    $team = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'team' => $team]);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $project_id = $input['project_id'] ?? null;
    $user_id = $input['user_id'] ?? null;
    $role = $input['role'] ?? 'pm';
    
    if (!$project_id || !$user_id) {
        echo json_encode(['success' => false, 'error' => 'Missing project_id or user_id']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO project_team (project_id, user_id, role) VALUES (?, ?, ?)");
        $stmt->execute([$project_id, $user_id, $role]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Missing id']);
        exit;
    }
    
    $stmt = $pdo->prepare("DELETE FROM project_team WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid method']);
