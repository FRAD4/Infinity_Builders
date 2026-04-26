<?php
/**
 * Documents API - Project Document Management
 * Handles upload, list, delete for project documents
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

// Allow admin, pm, accounting, estimator to manage documents
$canManage = in_array($userRole, ['admin', 'pm', 'accounting', 'estimator']);

switch ($method) {
    case 'GET':
        handleGet($pdo);
        break;
    case 'POST':
        handlePost($pdo, $canManage);
        break;
    case 'DELETE':
        handleDelete($pdo, $canManage);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function handleGet($pdo) {
    $projectId = $_GET['project_id'] ?? null;
    
    if (!$projectId) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID required']);
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
    
    // Get documents
    $stmt = $pdo->prepare("
        SELECT d.*, u.username as uploaded_by_name
        FROM project_documents d
        LEFT JOIN users u ON d.uploaded_by = u.id
        WHERE d.project_id = ?
        ORDER BY d.uploaded_at DESC
    ");
    $stmt->execute([$projectId]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['documents' => $documents]);
}

function handlePost($pdo, $canManage) {
    global $userId;
    
    if (!$canManage) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        return;
    }
    
    $projectId = $_POST['project_id'] ?? null;
    $label = trim($_POST['label'] ?? '');
    
    if (!$projectId || !$label) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID and label required']);
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
    
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'No file uploaded or upload error']);
        return;
    }
    
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $fileSize = $file['size'];
    $mimeType = $file['type'];
    
    // Validate file
    $allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'image/jpeg',
        'image/png',
        'image/gif',
        'text/plain',
        'application/zip',
        'application/x-zip-compressed'
    ];
    
    // Allow common types even if not in strict list
    $isAllowed = in_array($mimeType, $allowedTypes) || 
                 preg_match('/\.(pdf|doc|docx|xls|xlsx|ppt|pptx|jpg|jpeg|png|gif|txt|zip)$/i', $fileName);
    
    if (!$isAllowed) {
        http_response_code(400);
        echo json_encode(['error' => 'File type not allowed']);
        return;
    }
    
    // Max file size: 50MB
    $maxSize = 50 * 1024 * 1024;
    if ($fileSize > $maxSize) {
        http_response_code(400);
        echo json_encode(['error' => 'File too large (max 50MB)']);
        return;
    }
    
    // Generate unique filename
    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
    $newFileName = uniqid('proj_') . '_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $label) . '.' . $extension;
    $uploadDir = __DIR__ . '/../uploads/projects/';
    $filePath = $uploadDir . $newFileName;
    
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        // Save to database
        $stmt = $pdo->prepare("
            INSERT INTO project_documents (project_id, label, file_path, file_name, mime_type, file_size, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $relativePath = 'uploads/projects/' . $newFileName;
        
        if ($stmt->execute([$projectId, $label, $relativePath, $fileName, $mimeType, $fileSize, $userId])) {
            $docId = $pdo->lastInsertId();
            
            // Log audit
            logAudit($pdo, 'document_uploaded', "Uploaded document '$label' for project ID $projectId", $userId);
            
            echo json_encode([
                'success' => true,
                'document_id' => $docId,
                'message' => 'Document uploaded successfully'
            ]);
        } else {
            // Delete uploaded file if DB insert failed
            @unlink($filePath);
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save document']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to upload file']);
    }
}

function handleDelete($pdo, $canManage) {
    global $userId;
    
    if (!$canManage) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $docId = $data['id'] ?? null;
    
    if (!$docId) {
        http_response_code(400);
        echo json_encode(['error' => 'Document ID required']);
        return;
    }
    
    // Get document info
    $stmt = $pdo->prepare("SELECT file_path, project_id, label FROM project_documents WHERE id = ?");
    $stmt->execute([$docId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doc) {
        http_response_code(404);
        echo json_encode(['error' => 'Document not found']);
        return;
    }
    
    // Delete file
    $filePath = __DIR__ . '/../' . $doc['file_path'];
    @unlink($filePath);
    
    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM project_documents WHERE id = ?");
    if ($stmt->execute([$docId])) {
        logAudit($pdo, 'document_deleted', "Deleted document ID $docId from project", $userId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Document deleted successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete document']);
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
        // Silently fail
    }
}
