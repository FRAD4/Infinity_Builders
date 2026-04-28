<?php
/**
 * Internal Notes API
 * Create and retrieve internal notes for projects
 */

// Disable ALL error output FIRST
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);
@ini_set('output_buffering', 1);

ob_start();

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

ob_clean();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Get current user
$currentUserId = $_SESSION['user_id'] ?? 0;
$currentUserRole = $_SESSION['user_role'] ?? '';

if (!$currentUserId) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Only admins, PMs, and estimators can create notes
$allowedRoles = ['admin', 'pm', 'estimator', 'accounting'];
if (!in_array($currentUserRole, $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden - insufficient permissions']);
    exit;
}

if ($method === 'POST' && $action === 'create') {
    // Create new note
    $input = json_decode(file_get_contents('php://input'), true);
    
    $projectId = intval($input['project_id'] ?? 0);
    $toUserIds = $input['to_user_ids'] ?? [];
    $message = trim($input['message'] ?? '');
    
    // Validation
    if (!$projectId) {
        echo json_encode(['error' => 'Project is required']);
        exit;
    }
    
    if (empty($toUserIds)) {
        echo json_encode(['error' => 'At least one recipient is required']);
        exit;
    }
    
    if (empty($message)) {
        echo json_encode(['error' => 'Message is required']);
        exit;
    }
    
    // Verify project exists
    $stmt = $pdo->prepare("SELECT id, name FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$project) {
        echo json_encode(['error' => 'Project not found']);
        exit;
    }
    
    // Get sender name
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$currentUserId]);
    $sender = $stmt->fetch(PDO::FETCH_ASSOC);
    $senderName = $sender['username'] ?? 'Unknown';
    
    // Insert note
    $toUserIdsJson = json_encode($toUserIds);
    $stmt = $pdo->prepare("INSERT INTO internal_notes (project_id, from_user_id, to_user_ids, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$projectId, $currentUserId, $toUserIdsJson, $message]);
    
    $noteId = $pdo->lastInsertId();
    
    // Send notifications to recipients
    try {
        foreach ($toUserIds as $userId) {
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, url) VALUES (?, 'info', ?, ?, ?)");
            $title = "Nueva nota de " . $senderName;
            $messageNotif = substr($message, 0, 100) . (strlen($message) > 100 ? '...' : '');
            $url = "projects.php?open=" . $projectId . "&tab=notes";
            $stmt->execute([$userId, $title, $messageNotif, $url]);
        }
    } catch (Exception $e) {
        // Ignore
    }
    
    echo json_encode(['success' => true, 'note_id' => $noteId, 'message' => 'Note sent successfully']);
    exit;
}

// Get users by project
if ($action === 'users_by_project') {
    $projectId = intval($_GET['project_id'] ?? 0);
    
    if (!$projectId) {
        echo json_encode(['error' => 'Project ID is required']);
        exit;
    }
    
    // Get users from project team + all admins/PMs
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.username, u.role
        FROM users u
        LEFT JOIN project_team pt ON pt.user_id = u.id AND pt.project_id = ?
        WHERE u.role IN ('admin', 'pm') OR pt.user_id IS NOT NULL
        ORDER BY u.role DESC, u.username ASC
    ");
    $stmt->execute([$projectId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['users' => $users]);
    exit;
}

// Default: Get notes for a project (GET request)
$projectId = intval($_GET['project_id'] ?? 0);

if (!$projectId) {
    echo json_encode(['error' => 'Project ID is required']);
    exit;
}

// Get notes for this project (admins see all, others see what they sent/received)
if ($currentUserRole === 'admin') {
    $stmt = $pdo->prepare("
        SELECT 
            n.id,
            n.project_id,
            n.from_user_id,
            n.to_user_ids,
            n.message,
            n.created_at,
            u.username as from_user_name,
            u.role as from_user_role
        FROM internal_notes n
        JOIN users u ON n.from_user_id = u.id
        WHERE n.project_id = ?
        ORDER BY n.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$projectId]);
} else {
    // Non-admins: get notes they sent or received (use LIKE for compatibility)
    $stmt = $pdo->prepare("
        SELECT 
            n.id,
            n.project_id,
            n.from_user_id,
            n.to_user_ids,
            n.message,
            n.created_at,
            u.username as from_user_name,
            u.role as from_user_role
        FROM internal_notes n
        JOIN users u ON n.from_user_id = u.id
        WHERE n.project_id = ?
        AND (n.from_user_id = ? OR n.to_user_ids LIKE ? OR n.to_user_ids LIKE ?)
        ORDER BY n.created_at DESC
        LIMIT 50
    ");
    // Match [30] or "30" or just 30 in JSON
    $like1 = '%"' . $currentUserId . '"%';
    $like2 = '%' . $currentUserId . ']%';
    $stmt->execute([$projectId, $currentUserId, $like1, $like2]);
}

$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recipient names for each note
foreach ($notes as &$note) {
    $toUserIds = json_decode($note['to_user_ids'] ?? '[]', true);
    $toNames = [];
    if (!empty($toUserIds)) {
        $placeholders = implode(',', array_fill(0, count($toUserIds), '?'));
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id IN ($placeholders)");
        $stmt->execute($toUserIds);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $toNames[] = $row['username'];
        }
    }
    $note['to_user_names'] = implode(', ', $toNames);
}

echo json_encode(['notes' => $notes]);