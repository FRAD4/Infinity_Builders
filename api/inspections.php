<?php
/**
 * Inspections API - Infinity Builders
 * CRUD for project inspections (linked to permits)
 */

header('Content-Type: application/json');

require_once '../partials/init.php';
require_once '../includes/audit.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'viewer';
$method = $_SERVER['REQUEST_METHOD'];

// Only admin, pm, accounting, estimator can manage inspections
$canManage = in_array($userRole, ['admin', 'pm', 'accounting', 'estimator']);

switch ($method) {
    case 'GET':
        // Check if requesting history for a specific inspection
        $historyForInspection = $_GET['history_for'] ?? null;
        if ($historyForInspection) {
            handleGetHistory($pdo, $historyForInspection);
            exit;
        }
        // Check if requesting recent changes (activity log)
        $recentChanges = $_GET['recent_changes'] ?? null;
        if ($recentChanges) {
            handleGetRecentChanges($pdo);
            exit;
        }
        handleGet($pdo, $userId, $userRole);
        break;
    case 'POST':
        if (!$canManage) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            exit;
        }
        handlePost($pdo, $userId, $userRole);
        break;
    case 'PUT':
    case 'PATCH':
        if (!$canManage) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            exit;
        }
        handlePut($pdo, $userId, $userRole);
        break;
    case 'DELETE':
        if ($userRole !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Only admins can delete']);
            exit;
        }
        handleDelete($pdo, $userId);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
}

function handleGetHistory($pdo, $inspectionId) {
    $stmt = $pdo->prepare("
        SELECT h.id, h.old_status, h.new_status, h.changed_at, h.note, u.username
        FROM inspection_status_history h
        LEFT JOIN users u ON h.changed_by = u.id
        WHERE h.inspection_id = ?
        ORDER BY h.changed_at DESC
    ");
    $stmt->execute([$inspectionId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'history' => $history
    ]);
}

function handleGetRecentChanges($pdo) {
    try {
        $limit = 20;
        
        $stmt = $pdo->prepare("
            SELECT h.id, h.old_status, h.new_status, h.changed_at, h.note, 
                   u.username, i.inspection_type, pr.name as project_name
            FROM inspection_status_history h
            LEFT JOIN users u ON h.changed_by = u.id
            LEFT JOIN inspections i ON h.inspection_id = i.id
            LEFT JOIN projects pr ON i.project_id = pr.id
            ORDER BY h.changed_at DESC
            LIMIT " . $limit . "
        ");
        $stmt->execute();
        $changes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'changes' => $changes ?? []
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'changes' => []
        ]);
    }
}

function handleGet($pdo, $userId, $userRole) {
    $projectId = $_GET['project_id'] ?? null;
    $permitId = $_GET['permit_id'] ?? null;
    
    if ($projectId) {
        $stmt = $pdo->prepare("
            SELECT i.*, p.city as permit_city, p.permit_number
            FROM inspections i
            LEFT JOIN permits p ON i.permit_id = p.id
            WHERE i.project_id = ? 
            ORDER BY i.scheduled_date DESC, i.created_at DESC
        ");
        $stmt->execute([$projectId]);
    } elseif ($permitId) {
        $stmt = $pdo->prepare("
            SELECT i.*, p.city as permit_city, p.permit_number
            FROM inspections i
            LEFT JOIN permits p ON i.permit_id = p.id
            WHERE i.permit_id = ? 
            ORDER BY i.scheduled_date DESC
        ");
        $stmt->execute([$permitId]);
    } else {
        $stmt = $pdo->query("
            SELECT i.*, p.city as permit_city, p.permit_number
            FROM inspections i
            LEFT JOIN permits p ON i.permit_id = p.id
            ORDER BY i.scheduled_date DESC, i.created_at DESC
        ");
    }
    
    $inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'inspections' => $inspections
    ]);
}

function handlePost($pdo, $userId, $userRole) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $projectId = $data['project_id'] ?? null;
    $inspectionType = $data['inspection_type'] ?? null;
    
    if (!$projectId || !$inspectionType) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID and inspection type are required']);
        return;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO inspections (
            project_id, permit_id, inspection_type, city, requested_by,
            date_requested, scheduled_date, inspector_name, status, inspector_notes,
            reinspection_needed, reinspection_date, notes, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $projectId,
        $data['permit_id'] ?? null,
        $inspectionType,
        $data['city'] ?? null,
        $data['requested_by'] ?? null,
        $data['date_requested'] ?? null,
        $data['scheduled_date'] ?? null,
        $data['inspector_name'] ?? null,
        $data['status'] ?? 'not_scheduled',
        $data['inspector_notes'] ?? null,
        $data['reinspection_needed'] ?? 'no',
        $data['reinspection_date'] ?? null,
        $data['notes'] ?? null,
        $userId
    ]);
    
    $inspectionId = $pdo->lastInsertId();
    audit_log('create', 'inspections', $inspectionId, "Inspection: {$inspectionType} for project #{$projectId}", null, $userId);
    
    echo json_encode([
        'success' => true,
        'inspection_id' => $inspectionId,
        'message' => 'Inspection created successfully'
    ]);
}

function handlePut($pdo, $userId, $userRole) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = $data['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Inspection ID is required']);
        return;
    }
    
    // Get old data for audit
    $oldStmt = $pdo->prepare("SELECT * FROM inspections WHERE id = ?");
    $oldStmt->execute([$id]);
    $oldInspection = $oldStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$oldInspection) {
        http_response_code(404);
        echo json_encode(['error' => 'Inspection not found']);
        return;
    }
    
    // Build update query
    $updates = [];
    $params = [];
    
    $fields = [
        'permit_id', 'inspection_type', 'city', 'requested_by',
        'date_requested', 'scheduled_date', 'inspector_name', 'status', 'inspector_notes',
        'reinspection_needed', 'reinspection_date', 'notes'
    ];
    
    foreach ($fields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (!empty($updates)) {
        $params[] = $id;
        $stmt = $pdo->prepare("UPDATE inspections SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);
        
        // Track status changes
        if (isset($data['status']) && $data['status'] !== $oldInspection['status']) {
            $changedByUserId = !empty($userId) ? $userId : 1;
            
            // Verify user exists
            $userCheck = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $userCheck->execute([$changedByUserId]);
            $userExists = $userCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$userExists) {
                $anyUser = $pdo->query("SELECT id FROM users LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                $changedByUserId = $anyUser ? $anyUser['id'] : 1;
            }
            
            try {
                $historyStmt = $pdo->prepare("
                    INSERT INTO inspection_status_history (inspection_id, old_status, new_status, changed_by, note)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $historyStmt->execute([
                    $id,
                    $oldInspection['status'],
                    $data['status'],
                    $changedByUserId,
                    $data['status_note'] ?? null
                ]);
            } catch (Exception $e) {
                // Table might not exist - skip
            }
        }
        
        audit_log('update', 'inspections', $id, "Inspection #$id", [
            'old' => $oldInspection,
            'new' => $data
        ], $userId);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Inspection updated successfully'
    ]);
}

function handleDelete($pdo, $userId) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID required']);
        return;
    }
    
    // Get name for audit
    $stmt = $pdo->prepare("SELECT inspection_type, project_id FROM inspections WHERE id = ?");
    $stmt->execute([$id]);
    $inspection = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("DELETE FROM inspections WHERE id = ?");
    $stmt->execute([$id]);
    
    audit_log('delete', 'inspections', $id, "Inspection {$inspection['inspection_type']} for project {$inspection['project_id']}", null, $userId);
    
    echo json_encode([
        'success' => true,
        'message' => 'Inspection deleted'
    ]);
}
