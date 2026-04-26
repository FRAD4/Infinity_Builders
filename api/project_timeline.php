<?php
/**
 * API: Project Timeline (Activity Log)
 * GET: Get audit log entries for a specific project
 */

header('Content-Type: application/json');
require_once '../partials/init.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$projectId = (int)($_GET['project_id'] ?? 0);

if (!$projectId) {
    echo json_encode(['success' => false, 'error' => 'Missing project_id']);
    exit;
}

// Get all audit log entries for this project and its related entities
$timeline = [];

// 1. Project itself
$stmt = $pdo->prepare("
    SELECT a.id, a.action_type, a.entity_type, a.entity_id, a.entity_name, a.created_at, u.username
    FROM audit_log a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.entity_type = 'projects' AND a.entity_id = ?
    ORDER BY a.created_at DESC
    LIMIT 50
");
$stmt->execute([$projectId]);
$timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));

// 2. Permits for this project
$stmt = $pdo->prepare("SELECT id FROM permits WHERE project_id = ?");
$stmt->execute([$projectId]);
$permitIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (!empty($permitIds)) {
    $placeholders = implode(',', array_fill(0, count($permitIds), '?'));
    $stmt = $pdo->prepare("
        SELECT a.id, a.action_type, a.entity_type, a.entity_id, a.entity_name, a.created_at, u.username
        FROM audit_log a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.entity_type = 'permits' AND a.entity_id IN ($placeholders)
        ORDER BY a.created_at DESC
    ");
    $stmt->execute($permitIds);
    $timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// 3. Inspections for this project
$stmt = $pdo->prepare("SELECT id FROM inspections WHERE project_id = ?");
$stmt->execute([$projectId]);
$inspectionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (!empty($inspectionIds)) {
    $placeholders = implode(',', array_fill(0, count($inspectionIds), '?'));
    $stmt = $pdo->prepare("
        SELECT a.id, a.action_type, a.entity_type, a.entity_id, a.entity_name, a.created_at, u.username
        FROM audit_log a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.entity_type = 'inspections' AND a.entity_id IN ($placeholders)
        ORDER BY a.created_at DESC
    ");
    $stmt->execute($inspectionIds);
    $timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// 4. Documents for this project
$stmt = $pdo->prepare("SELECT id FROM project_documents WHERE project_id = ?");
$stmt->execute([$projectId]);
$docIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (!empty($docIds)) {
    $placeholders = implode(',', array_fill(0, count($docIds), '?'));
    $stmt = $pdo->prepare("
        SELECT a.id, a.action_type, a.entity_type, a.entity_id, a.entity_name, a.created_at, u.username
        FROM audit_log a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.entity_type = 'documents' AND a.entity_id IN ($placeholders)
        ORDER BY a.created_at DESC
    ");
    $stmt->execute($docIds);
    $timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// 5. Tasks for this project
$stmt = $pdo->prepare("SELECT id FROM project_tasks WHERE project_id = ?");
$stmt->execute([$projectId]);
$taskIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (!empty($taskIds)) {
    $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
    $stmt = $pdo->prepare("
        SELECT a.id, a.action_type, a.entity_type, a.entity_id, a.entity_name, a.created_at, u.username
        FROM audit_log a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.entity_type = 'tasks' AND a.entity_id IN ($placeholders)
        ORDER BY a.created_at DESC
    ");
    $stmt->execute($taskIds);
    $timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// 6. Vendors linked to this project
$stmt = $pdo->prepare("SELECT id FROM project_vendors WHERE project_id = ?");
$stmt->execute([$projectId]);
$vendorLinkIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (!empty($vendorLinkIds)) {
    $placeholders = implode(',', array_fill(0, count($vendorLinkIds), '?'));
    $stmt = $pdo->prepare("
        SELECT a.id, a.action_type, a.entity_type, a.entity_id, a.entity_name, a.created_at, u.username
        FROM audit_log a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.entity_type = 'vendors' AND a.entity_id IN ($placeholders)
        ORDER BY a.created_at DESC
    ");
    $stmt->execute($vendorLinkIds);
    $timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// Sort all by date descending
usort($timeline, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Limit to 50 most recent
$timeline = array_slice($timeline, 0, 50);

echo json_encode([
    'success' => true,
    'timeline' => $timeline
]);
