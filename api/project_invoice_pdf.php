<?php
/**
 * API: Project Invoice PDF Upload
 * POST: Upload invoice PDF for a project
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

$canManage = in_array($userRole, ['admin', 'pm', 'accounting']);
if (!$canManage) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$projectId = $_POST['project_id'] ?? null;
if (!$projectId) {
    http_response_code(400);
    echo json_encode(['error' => 'Project ID required']);
    exit;
}

$file = $_FILES['invoice_pdf'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Upload failed']);
    exit;
}

// Check file type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if ($mimeType !== 'application/pdf') {
    http_response_code(400);
    echo json_encode(['error' => 'Only PDF files allowed']);
    exit;
}

// Create uploads directory if not exists
$uploadDir = __DIR__ . '/../uploads/invoices/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'invoice_' . $projectId . '_' . time() . '.' . $extension;
$filepath = $uploadDir . $filename;

if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Get old invoice for audit
    $oldStmt = $pdo->prepare("SELECT invoice_pdf FROM projects WHERE id = ?");
    $oldStmt->execute([$projectId]);
    $oldProject = $oldStmt->fetch(PDO::FETCH_ASSOC);
    
    // Update project record
    $stmt = $pdo->prepare("UPDATE projects SET invoice_pdf = ? WHERE id = ?");
    $stmt->execute([$filename, $projectId]);
    
    audit_log('update', 'projects', $projectId, "Uploaded invoice PDF", [
        'old' => $oldProject['invoice_pdf'] ?? null,
        'new' => $filename
    ], $userId);
    
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'message' => 'Invoice PDF uploaded successfully'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file']);
}
