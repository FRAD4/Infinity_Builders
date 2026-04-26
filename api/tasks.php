<?php
/**
 * Tasks API - Project Task Management
 * Handles CRUD operations for project tasks via AJAX
 */

require_once __DIR__ . '/../partials/init.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'viewer';

$method = $_SERVER['REQUEST_METHOD'];

// Only admin, pm, and accounting can manage tasks
$canManage = in_array($userRole, ['admin', 'pm', 'accounting', 'estimator']);

switch ($method) {
    case 'GET':
        handleGet($pdo, $canManage);
        break;
    case 'POST':
        handlePost($pdo, $canManage);
        break;
    case 'PUT':
    case 'PATCH':
        handlePut($pdo, $canManage);
        break;
    case 'DELETE':
        handleDelete($pdo, $canManage);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function handleGet($pdo, $canManage) {
    $projectId = $_GET['project_id'] ?? null;
    
    if (!$projectId) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID required']);
        return;
    }
    
    // Verify project access
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Project not found']);
        return;
    }
    
    // Get tasks
    $stmt = $pdo->prepare("
        SELECT t.*, u.username as assigned_name, c.username as creator_name
        FROM project_tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        LEFT JOIN users c ON t.created_by = c.id
        WHERE t.project_id = ?
        ORDER BY 
            CASE t.status 
                WHEN 'in_progress' THEN 1 
                WHEN 'pending' THEN 2 
                WHEN 'completed' THEN 3 
                WHEN 'cancelled' THEN 4 
            END,
            CASE t.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END,
            t.due_date ASC,
            t.created_at DESC
    ");
    $stmt->execute([$projectId]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get task stats
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(COUNT(*), 0) as total,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending,
            COALESCE(SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END), 0) as in_progress,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) as completed,
            COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled
        FROM project_tasks 
        WHERE project_id = ?
    ");
    $stmt->execute([$projectId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'tasks' => $tasks,
        'stats' => $stats
    ]);
}

function handlePost($pdo, $canManage) {
    global $userId;
    
    if (!$canManage) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $projectId = $data['project_id'] ?? null;
    $title = trim($data['title'] ?? '');
    $description = $data['description'] ?? '';
    $priority = $data['priority'] ?? 'medium';
    $assignedTo = $data['assigned_to'] ?? null;
    $dueDate = $data['due_date'] ?? null;
    
    if (!$projectId || !$title) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID and title required']);
        return;
    }
    
    // Verify project exists
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Project not found']);
        return;
    }
    
    // Validate priority
    $validPriorities = ['low', 'medium', 'high', 'urgent'];
    if (!in_array($priority, $validPriorities)) {
        $priority = 'medium';
    }
    
    // Validate assigned_to if provided
    if ($assignedTo) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$assignedTo]);
        if (!$stmt->fetch()) {
            $assignedTo = null;
        }
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO project_tasks (project_id, title, description, priority, assigned_to, due_date, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$projectId, $title, $description, $priority, $assignedTo, $dueDate, $userId])) {
        $taskId = $pdo->lastInsertId();
        
        // Log the action
        logAudit($pdo, 'task_created', "Created task '$title' for project ID $projectId", $userId);
        
        echo json_encode([
            'success' => true,
            'task_id' => $taskId,
            'message' => 'Task created successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create task']);
    }
}

function handlePut($pdo, $canManage) {
    global $userId;
    
    if (!$canManage) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $taskId = $data['id'] ?? null;
    $title = $data['title'] ?? null;
    $description = $data['description'] ?? null;
    $status = $data['status'] ?? null;
    $priority = $data['priority'] ?? null;
    $assignedTo = $data['assigned_to'] ?? null;
    $dueDate = $data['due_date'] ?? null;
    
    if (!$taskId) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID required']);
        return;
    }
    
    // Verify task exists
    $stmt = $pdo->prepare("SELECT id, project_id, status FROM project_tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        http_response_code(404);
        echo json_encode(['error' => 'Task not found']);
        return;
    }
    
    // Build update query
    $updates = [];
    $params = [];
    
    if ($title !== null) {
        $updates[] = 'title = ?';
        $params[] = trim($title);
    }
    if ($description !== null) {
        $updates[] = 'description = ?';
        $params[] = $description;
    }
    if ($status !== null) {
        $validStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        if (in_array($status, $validStatuses)) {
            $updates[] = 'status = ?';
            $params[] = $status;
            if ($status === 'completed') {
                $updates[] = 'completed_at = NOW()';
            }
        }
    }
    if ($priority !== null) {
        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        if (in_array($priority, $validPriorities)) {
            $updates[] = 'priority = ?';
            $params[] = $priority;
        }
    }
    if ($assignedTo !== null) {
        if ($assignedTo) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$assignedTo]);
            if ($stmt->fetch()) {
                $updates[] = 'assigned_to = ?';
                $params[] = $assignedTo;
            }
        } else {
            $updates[] = 'assigned_to = NULL';
        }
    }
    if ($dueDate !== null) {
        if ($dueDate) {
            $updates[] = 'due_date = ?';
            $params[] = $dueDate;
        } else {
            $updates[] = 'due_date = NULL';
        }
    }
    
    if (empty($updates)) {
        echo json_encode(['success' => true, 'message' => 'No changes to update']);
        return;
    }
    
    $params[] = $taskId;
    
    $sql = "UPDATE project_tasks SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute($params)) {
        // Log status changes
        if ($status) {
            logAudit($pdo, 'task_updated', "Updated task ID $taskId - Status: $status", $userId);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Task updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update task']);
    }
}

function handleDelete($pdo, $canManage) {
    global $userId, $userRole;
    
    // Only admin can delete tasks
    if ($userRole !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $taskId = $data['id'] ?? null;
    
    if (!$taskId) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID required']);
        return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM project_tasks WHERE id = ?");
    
    if ($stmt->execute([$taskId])) {
        logAudit($pdo, 'task_deleted', "Deleted task ID $taskId", $userId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Task deleted successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete task']);
    }
}

function logAudit($pdo, $action, $details, $userId) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_log (user_id, action, details, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    } catch (Exception $e) {
        // Silently fail - audit logging shouldn't break the app
    }
}
