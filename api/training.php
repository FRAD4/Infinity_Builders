<?php
/**
 * Training API - Infinity Builders
 * CRUD for training materials (videos and documents)
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

// Only admin can manage training materials
$canManage = ($userRole === 'admin');

// Handle file upload
if ($method === 'POST' && isset($_FILES['file'])) {
    handleUpload($pdo, $userId, $userRole);
    exit;
}

switch ($method) {
    case 'GET':
        handleGet($pdo, $userId, $userRole);
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
        if (!$canManage) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            exit;
        }
        handleDelete($pdo, $userId, $userRole);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
}

/**
 * Get training materials
 */
function handleGet($pdo, $userId, $userRole) {
    $id = $_GET['id'] ?? null;
    $type = $_GET['type'] ?? null;
    
    if ($id) {
        // Get single material
        $stmt = $pdo->prepare("SELECT * FROM training_materials WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$id]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$material) {
            http_response_code(404);
            echo json_encode(['error' => 'Material not found']);
            exit;
        }
        
        echo json_encode(['success' => true, 'data' => $material]);
    } else {
        // Get all materials
        $sql = "SELECT t.*, u.username as created_by_name 
                FROM training_materials t 
                LEFT JOIN users u ON t.created_by = u.id 
                WHERE t.is_active = TRUE";
        $params = [];
        
        if ($type) {
            $sql .= " AND t.type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY t.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $materials]);
    }
}

/**
 * Upload new training material
 */
function handleUpload($pdo, $userId, $userRole) {
    if ($userRole !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        exit;
    }
    
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'document';
    $durationSeconds = $_POST['duration_seconds'] ?? null;
    
    if (empty($title)) {
        echo json_encode(['success' => false, 'error' => 'Title is required']);
        exit;
    }
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'File is required']);
        exit;
    }
    
    $file = $_FILES['file'];
    $maxSize = 100 * 1024 * 1024; // 100MB
    
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'error' => 'File size exceeds 100MB limit']);
        exit;
    }
    
    // Allowed extensions
    $allowedVideo = ['mp4', 'webm', 'mov', 'avi'];
    $allowedDoc = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($type === 'video' && !in_array($ext, $allowedVideo)) {
        echo json_encode(['success' => false, 'error' => 'Invalid video format. Allowed: ' . implode(', ', $allowedVideo)]);
        exit;
    }
    
    if ($type === 'document' && !in_array($ext, $allowedDoc)) {
        echo json_encode(['success' => false, 'error' => 'Invalid document format. Allowed: ' . implode(', ', $allowedDoc)]);
        exit;
    }
    
    // Create upload directory if needed
    $uploadDir = __DIR__ . '/../uploads/training/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $newFilename = uniqid('training_') . '_' . basename($file['name']);
    $targetPath = $uploadDir . $newFilename;
    
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
        exit;
    }
    
    // Insert into database
    try {
        $stmt = $pdo->prepare("
            INSERT INTO training_materials (title, description, type, file_path, file_name, file_type, file_size, duration_seconds, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $title,
            $description,
            $type,
            'uploads/training/' . $newFilename,
            $file['name'],
            $file['type'],
            $file['size'],
            $durationSeconds ? (int)$durationSeconds : null,
            $userId
        ]);
        
        $materialId = $pdo->lastInsertId();
        
        // Audit log
        audit_log('create', 'training', $materialId, 'Created training material: ' . $title, null, $userId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Training material uploaded successfully',
            'id' => $materialId
        ]);
    } catch (Exception $e) {
        // Remove uploaded file on error
        @unlink($targetPath);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Update training material
 */
function handlePut($pdo, $userId, $userRole) {
    parse_str(file_get_contents('php://input'), $data);
    
    $id = $data['id'] ?? null;
    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    
    if (!$id || empty($title)) {
        echo json_encode(['success' => false, 'error' => 'ID and title are required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE training_materials 
            SET title = ?, description = ?, updated_at = NOW()
            WHERE id = ? AND is_active = TRUE
        ");
        
        $stmt->execute([$title, $description, $id]);
        
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'error' => 'Material not found']);
            exit;
        }
        
        // Audit log
        audit_log('update', 'training', $id, 'Updated training material: ' . $title, null, $userId);
        
        echo json_encode(['success' => true, 'message' => 'Training material updated']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Delete (soft) training material
 */
function handleDelete($pdo, $userId, $userRole) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID is required']);
        exit;
    }
    
    try {
        // Get material info first
        $stmt = $pdo->prepare("SELECT file_path, title FROM training_materials WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$id]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$material) {
            echo json_encode(['success' => false, 'error' => 'Material not found']);
            exit;
        }
        
        // Soft delete
        $stmt = $pdo->prepare("UPDATE training_materials SET is_active = FALSE WHERE id = ?");
        $stmt->execute([$id]);
        
        // Delete file
        $filePath = __DIR__ . '/../' . $material['file_path'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        
        // Audit log
        audit_log('delete', 'training', $id, 'Deleted training material: ' . $material['title'], null, $userId);
        
        echo json_encode(['success' => true, 'message' => 'Training material deleted']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}
