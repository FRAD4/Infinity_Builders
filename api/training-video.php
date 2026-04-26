<?php
/**
 * Video streaming API for training materials
 */

require_once '../partials/init.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT file_name, file_type FROM training_materials WHERE id = ? AND type = 'video' AND is_active = TRUE");
    $stmt->execute([$id]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$material) {
        http_response_code(404);
        exit;
    }
    
    $filePath = __DIR__ . '/../uploads/training/' . $material['file_name'];
    
    if (!file_exists($filePath)) {
        http_response_code(404);
        exit;
    }
    
    // Get file info
    $fileSize = filesize($filePath);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);
    
    // Set headers for streaming
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $fileSize);
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=3600');
    
    // Read and output file
    readfile($filePath);
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    exit;
}
