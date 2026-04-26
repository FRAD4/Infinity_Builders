<?php
/**
 * Permits API - Infinity Builders
 * CRUD for project permits with PDF upload
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

// Only admin, pm, accounting, estimator can manage permits
$canManage = in_array($userRole, ['admin', 'pm', 'accounting', 'estimator']);

// Handle file upload for permit PDF
if ($method === 'POST' && isset($_FILES['permit_pdf'])) {
    handleUpload($pdo, $userId, $userRole);
    exit;
}

switch ($method) {
    case 'GET':
        // Check if requesting history for a specific permit
        $historyForPermit = $_GET['history_for'] ?? null;
        if ($historyForPermit) {
            handleGetHistory($pdo, $historyForPermit);
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

function handleGetHistory($pdo, $permitId) {
    $stmt = $pdo->prepare("
        SELECT h.id, h.old_status, h.new_status, h.changed_at, h.note, u.username
        FROM permit_status_history h
        LEFT JOIN users u ON h.changed_by = u.id
        WHERE h.permit_id = ?
        ORDER BY h.changed_at DESC
    ");
    $stmt->execute([$permitId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'history' => $history
    ]);
}

function handleGetRecentChanges($pdo) {
    try {
        // Hardcode limit for MariaDB compatibility
        $limit = 20;
        
        $stmt = $pdo->prepare("
            SELECT h.id, h.old_status, h.new_status, h.changed_at, h.note, 
                   u.username, p.permit_number, pr.name as project_name
            FROM permit_status_history h
            LEFT JOIN users u ON h.changed_by = u.id
            LEFT JOIN permits p ON h.permit_id = p.id
            LEFT JOIN projects pr ON p.project_id = pr.id
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

function handleUpload($pdo, $userId, $userRole) {
    $canManage = in_array($userRole, ['admin', 'pm', 'accounting', 'estimator']);
    if (!$canManage) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        return;
    }
    
    $permitId = $_POST['permit_id'] ?? null;
    if (!$permitId) {
        http_response_code(400);
        echo json_encode(['error' => 'Permit ID required']);
        return;
    }
    
    $file = $_FILES['permit_pdf'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Upload failed']);
        return;
    }
    
    // Check file type
    $allowedTypes = ['application/pdf', 'application/octet-stream'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if ($mimeType !== 'application/pdf') {
        http_response_code(400);
        echo json_encode(['error' => 'Only PDF files allowed']);
        return;
    }
    
    // Create uploads directory if not exists
    $uploadDir = __DIR__ . '/../uploads/permits/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'permit_' . $permitId . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Update permit record
        $stmt = $pdo->prepare("UPDATE permits SET permit_pdf = ? WHERE id = ?");
        $stmt->execute([$filename, $permitId]);
        
        audit_log('update', 'permits', $permitId, "Uploaded permit PDF", null, $userId);
        
        echo json_encode([
            'success' => true,
            'filename' => $filename,
            'message' => 'PDF uploaded successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save file']);
    }
}

function handleGet($pdo, $userId, $userRole) {
    $projectId = $_GET['project_id'] ?? null;
    
    if ($projectId) {
        $stmt = $pdo->prepare("
            SELECT p.*, pr.name as project_name, pr.city as project_city, pr.project_manager 
            FROM permits p 
            LEFT JOIN projects pr ON p.project_id = pr.id
            WHERE p.project_id = ? 
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$projectId]);
    } else {
        $stmt = $pdo->query("
            SELECT p.*, pr.name as project_name, pr.city as project_city, pr.project_manager 
            FROM permits p 
            LEFT JOIN projects pr ON p.project_id = pr.id
            ORDER BY p.created_at DESC
        ");
    }
    
    $permits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate days since submission
    foreach ($permits as &$permit) {
        if ($permit['submission_date']) {
            $submitted = new DateTime($permit['submission_date']);
            $now = new DateTime();
            $permit['days_since_submitted'] = $now->diff($submitted)->days;
        } else {
            $permit['days_since_submitted'] = null;
        }
    }
    
    echo json_encode([
        'success' => true,
        'permits' => $permits
    ]);
}

function handlePost($pdo, $userId, $userRole) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $projectId = $data['project_id'] ?? null;
    $city = $data['city'] ?? null;
    $status = $data['status'] ?? 'not_started';
    
    if (!$projectId || !$city) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID and city are required']);
        return;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO permits (
            project_id, city, permit_required, status,
            submitted_by, permit_number, corrections_required, corrections_due_date,
            submission_date, approval_date, notes, internal_comments, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $projectId,
        $city,
        $data['permit_required'] ?? 'yes',
        $status,
        $data['submitted_by'] ?? null,
        $data['permit_number'] ?? null,
        $data['corrections_required'] ?? 'no',
        $data['corrections_due_date'] ?? null,
        $data['submission_date'] ?? null,
        $data['approval_date'] ?? null,
        $data['notes'] ?? null,
        $data['internal_comments'] ?? null,
        $userId
    ]);
    
    $permitId = $pdo->lastInsertId();
    
    // Get project name for audit log
    $projectStmt = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
    $projectStmt->execute([$projectId]);
    $project = $projectStmt->fetch(PDO::FETCH_ASSOC);
    $projectName = $project ? $project['name'] : "Project #{$projectId}";
    
    audit_log('create', 'permits', $permitId, "Permit for {$projectName} in {$city}", null, $userId);
    
    echo json_encode([
        'success' => true,
        'permit_id' => $permitId,
        'message' => 'Permit created successfully'
    ]);
}

function handlePut($pdo, $userId, $userRole) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = $data['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Permit ID is required']);
        return;
    }
    
    // Get old data for audit
    $oldStmt = $pdo->prepare("SELECT * FROM permits WHERE id = ?");
    $oldStmt->execute([$id]);
    $oldPermit = $oldStmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug: log old permit data
    error_log("Old permit: " . json_encode($oldPermit));
    
    if (!$oldPermit) {
        http_response_code(404);
        echo json_encode(['error' => 'Permit not found']);
        return;
    }
    
    // Build update query
    $updates = [];
    $params = [];
    
    $fields = [
        'city', 'permit_required', 'status',
        'submitted_by', 'permit_number', 'corrections_required', 'corrections_due_date',
        'submission_date', 'approval_date', 'notes', 'internal_comments'
    ];
    
    foreach ($fields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (!empty($updates)) {
        $params[] = $id;
        $stmt = $pdo->prepare("UPDATE permits SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);
        
        // Track status changes (if table exists)
        $historyResult = '';
        if (isset($data['status']) && $data['status'] !== $oldPermit['status']) {
            // Use session user ID or default to 1 (admin) if not available
            $changedByUserId = !empty($userId) ? $userId : 1;
            
            // Debug: verify user exists
            $userCheck = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
            $userCheck->execute([$changedByUserId]);
            $userExists = $userCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$userExists) {
                // Try to get any valid user
                $anyUser = $pdo->query("SELECT id FROM users LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                $changedByUserId = $anyUser ? $anyUser['id'] : 1;
                $historyResult = 'User ' . $userId . ' not found, using user ' . $changedByUserId;
            }
            
            try {
                $historyStmt = $pdo->prepare("
                    INSERT INTO permit_status_history (permit_id, old_status, new_status, changed_by, note)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $historyStmt->execute([
                    $id,
                    $oldPermit['status'],
                    $data['status'],
                    $changedByUserId,
                    $data['status_note'] ?? null
                ]);
                $historyResult = 'Inserted: ' . $oldPermit['status'] . ' -> ' . $data['status'] . ' by user ' . $changedByUserId;
            } catch (Exception $e) {
                $historyResult = 'Error: ' . $e->getMessage() . ' (userId=' . $changedByUserId . ')';
            }
        } else {
            $historyResult = 'No change: old=' . ($oldPermit['status'] ?? 'null') . ', new=' . ($data['status'] ?? 'null');
        }
        
        // Get project name for audit log
        $projectStmt = $pdo->prepare("SELECT p.name FROM projects p JOIN permits pm ON p.id = pm.project_id WHERE pm.id = ?");
        $projectStmt->execute([$id]);
        $project = $projectStmt->fetch(PDO::FETCH_ASSOC);
        $projectName = $project ? $project['name'] : "Permit #{$id}";
        
        audit_log('update', 'permits', $id, $projectName, [
            'old' => $oldPermit,
            'new' => $data
        ], $userId);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Permit updated successfully'
    ]);
}

function handleDelete($pdo, $userId) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID required']);
        return;
    }
    
    // Get info for audit
    $stmt = $pdo->prepare("SELECT project_id, city FROM permits WHERE id = ?");
    $stmt->execute([$id]);
    $permit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("DELETE FROM permits WHERE id = ?");
    $stmt->execute([$id]);
    
    // Get project name for audit log
    if ($permit && $permit['project_id']) {
        $projectStmt = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
        $projectStmt->execute([$permit['project_id']]);
        $project = $projectStmt->fetch(PDO::FETCH_ASSOC);
        $projectName = $project ? $project['name'] : "Project #{$permit['project_id']}";
    } else {
        $projectName = "Unknown Project";
    }
    
    audit_log('delete', 'permits', $id, "Permit for {$projectName} in {$permit['city']}", null, $userId);
    
    echo json_encode([
        'success' => true,
        'message' => 'Permit deleted'
    ]);
}
