<?php
/**
 * Notifications API
 * Returns pending notifications for the current user (DB + automatic)
 */

// Disable ALL error output FIRST
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);
@ini_set('output_buffering', 1);

// Start output buffering to catch any unwanted output
ob_start();

// Include config FIRST (creates $pdo and starts session)
require_once __DIR__ . '/../config/config.php';

// Now send JSON header
header('Content-Type: application/json');

// Clear any buffered output (warnings)
ob_clean();

// Check user is logged in
$userId = $_SESSION['user_id'] ?? 0;
$userRole = $_SESSION['user_role'] ?? '';

if (!$userId) {
    echo json_encode(['notifications' => [], 'count' => 0]);
    exit;
}

// Get action
$action = $_GET['action'] ?? '';

// Handle mark as read
if ($action === 'mark_read' && $userId) {
    $notificationId = intval($_GET['id'] ?? 0);
    if ($notificationId) {
        @$pdo->exec("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = " . intval($notificationId) . " AND user_id = " . intval($userId));
    }
    echo json_encode(['success' => true]);
    exit;
}

// Handle mark all as read
if ($action === 'mark_all_read' && $userId) {
    @$pdo->exec("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = " . intval($userId));
    echo json_encode(['success' => true]);
    exit;
}

$notifications = [];

// 1. Get saved notifications from DB
try {
    $stmt = $pdo->prepare("SELECT id, type, title, message, url, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC LIMIT 20");
    $stmt->execute([$userId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $notifications[] = [
            'id' => 'db_' . $row['id'],
            'type' => $row['type'],
            'icon' => 'fa-envelope',
            'title' => $row['title'],
            'message' => $row['message'],
            'url' => $row['url'],
            'is_read' => $row['is_read'],
            'created_at' => $row['created_at'],
            'is_db_notification' => true
        ];
    }
} catch (Exception $e) {
    // Table might not exist yet - ignore
}

// 2. Get automatic system notifications (only for admin/PM)
if (in_array($userRole, ['admin', 'pm'])) {
    try {
        // Get projects On Hold > 30 days
        $stmt = $pdo->query("
            SELECT id, name, start_date, DATEDIFF(NOW(), start_date) as days
            FROM projects 
            WHERE status = 'On Hold' 
            AND start_date IS NOT NULL 
            AND DATEDIFF(NOW(), start_date) > 30
            ORDER BY days DESC
            LIMIT 5
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $notifications[] = [
                'id' => 'hold_' . $row['id'],
                'type' => 'warning',
                'icon' => 'fa-pause-circle',
                'title' => 'Project On Hold',
                'message' => $row['name'] . ' has been on hold for ' . $row['days'] . ' days',
                'url' => 'projects.php?search=' . $row['id'],
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // Get projects without budget
        $stmt = $pdo->query("
            SELECT id, name FROM projects 
            WHERE (total_budget IS NULL OR total_budget = 0) 
            AND status = 'Active'
            LIMIT 5
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $notifications[] = [
                'id' => 'budget_' . $row['id'],
                'type' => 'danger',
                'icon' => 'fa-dollar-sign',
                'title' => 'No Budget Set',
                'message' => $row['name'] . ' has no budget configured',
                'url' => 'projects.php?search=' . $row['id'],
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // Get projects due in 7 days
        $stmt = $pdo->query("
            SELECT id, name, end_date, DATEDIFF(end_date, NOW()) as days_left
            FROM projects 
            WHERE status = 'Active' 
            AND end_date IS NOT NULL 
            AND DATEDIFF(end_date, NOW()) BETWEEN 1 AND 7
            ORDER BY end_date ASC
            LIMIT 5
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $notifications[] = [
                'id' => 'due_' . $row['id'],
                'type' => 'info',
                'icon' => 'fa-calendar',
                'title' => 'Deadline Coming',
                'message' => $row['name'] . ' is due in ' . $row['days_left'] . ' days',
                'url' => 'projects.php?search=' . $row['id'],
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // Get past due projects
        $stmt = $pdo->query("
            SELECT id, name, end_date
            FROM projects 
            WHERE status = 'Active' 
            AND end_date IS NOT NULL 
            AND end_date < CURDATE()
            LIMIT 5
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $notifications[] = [
                'id' => 'overdue_' . $row['id'],
                'type' => 'danger',
                'icon' => 'fa-triangle-exclamation',
                'title' => 'Project Overdue',
                'message' => $row['name'] . ' past due date',
                'url' => 'projects.php?search=' . $row['id'],
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // Get overdue tasks
        $stmt = $pdo->query("
            SELECT t.id, t.title, t.due_date, t.project_id
            FROM project_tasks t
            WHERE t.status NOT IN ('completed', 'cancelled')
            AND t.due_date IS NOT NULL
            AND t.due_date < CURDATE()
            ORDER BY t.due_date ASC
            LIMIT 5
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $notifications[] = [
                'id' => 'task_overdue_' . $row['id'],
                'type' => 'warning',
                'icon' => 'fa-list-check',
                'title' => 'Task Overdue',
                'message' => "Task '" . $row['title'] . "' is past due",
                'url' => 'projects.php?open=' . $row['project_id'],
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
    } catch (Exception $e) {
        // Table might not exist - ignore
    }
}

$count = 0;
foreach ($notifications as $n) {
    if (empty($n['is_read'])) $count++;
}

echo json_encode([
    'notifications' => $notifications,
    'count' => $count
]);