<?php
/**
 * Notifications API
 * Returns pending notifications for the current user
 */

header('Content-Type: application/json');
require_once '../partials/init-api.php';

// Start session to get user info
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? 0;
$userRole = $_SESSION['user_role'] ?? '';

$notifications = [];

// Only admins and PMs get notifications
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
                'message' => "{$row['name']} has been on hold for {$row['days']} days",
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
                'message' => "{$row['name']} has no budget configured",
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
                'message' => "{$row['name']} is due in {$row['days_left']} days",
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
                'message' => "{$row['name']} past due date",
                'url' => 'projects.php?search=' . $row['id'],
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // Get overdue tasks (past due date and not completed)
        try {
            $stmt = $pdo->query("
                SELECT t.id, t.title, t.due_date, p.name as project_name
                FROM project_tasks t
                LEFT JOIN projects p ON t.project_id = p.id
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
                    'message' => "Task '{$row['title']}' is past due",
                    'url' => 'projects.php?open=' . $row['project_id'],
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
        } catch (Exception $e) {
            // Tasks table might not exist
        }
        
    } catch (Exception $e) {
        // Table might not exist
    }
}

echo json_encode([
    'notifications' => $notifications,
    'count' => count($notifications)
]);