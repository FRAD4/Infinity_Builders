<?php
/**
 * Email Notifications System
 * Infinity Builders
 * 
 * Provides functions to send various email notifications
 */

require_once __DIR__ . '/config.email.php';
require_once __DIR__ . '/includes/email.php';

/**
 * Get all admin email addresses
 */
function get_admin_emails(): array {
    $emails = [];
    
    try {
        require_once __DIR__ . '/../config.php';
        global $db_host, $db_name, $db_user, $db_pass;
        
        $pdo = new PDO(
            'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4',
            $db_user,
            $db_pass
        );
        
        $stmt = $pdo->query("SELECT email FROM users WHERE role = 'admin' AND email IS NOT NULL AND email != ''");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $emails[] = $row['email'];
        }
    } catch (Exception $e) {
        error_log('Failed to get admin emails: ' . $e->getMessage());
    }
    
    return $emails;
}

/**
 * Send project alert notification
 */
function notify_project_alert(int $project_id, string $alert_type, string $message): bool {
    $admins = get_admin_emails();
    
    if (empty($admins)) {
        error_log('No admin emails configured for project alert');
        return false;
    }
    
    $subject = "🚨 Project Alert: $alert_type";
    $body = generate_project_alert_email($project_id, $alert_type, $message);
    
    $results = [];
    foreach ($admins as $email) {
        $results[] = send_email($email, $subject, $body);
    }
    
    return !empty(array_filter($results, fn($r) => $r['status'] === 'sent'));
}

function generate_project_alert_email(int $project_id, string $alert_type, string $message): string {
    require_once __DIR__ . '/../config.php';
    global $db_host, $db_name, $db_user, $db_pass;
    
    $project = [
        'name' => 'Unknown',
        'client_name' => 'N/A',
        'status' => 'N/A',
        'total_budget' => 0,
        'end_date' => null
    ];
    
    try {
        $pdo = new PDO(
            'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4',
            $db_user,
            $db_pass
        );
        
        $stmt = $pdo->prepare("SELECT name, client_name, status, total_budget, end_date FROM projects WHERE id = ?");
        $stmt->execute([$project_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $project = $result;
        }
    } catch (Exception $e) {}
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #1E293B; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #F97316, #EA580C); color: white; padding: 20px; border-radius: 8px 8px 0 0; }
            .content { background: #F8FAFC; padding: 20px; border: 1px solid #E2E8F0; border-top: none; }
            .alert-badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 600; }
            .alert-hold { background: #FEF3C7; color: #D97706; }
            .alert-budget { background: #FEE2E2; color: #DC2626; }
            .alert-date { background: #DBEAFE; color: #2563EB; }
            .project-info { background: white; padding: 16px; border-radius: 8px; margin: 16px 0; }
            .project-info td { padding: 8px; }
            .label { font-weight: 600; color: #64748B; }
            .footer { text-align: center; padding: 16px; color: #94A3B8; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2 style="margin:0;">🚨 Project Alert</h2>
            </div>
            <div class="content">
                <p><strong>Alert Type:</strong> <span class="alert-badge alert-' . ($alert_type === 'on_hold' ? 'hold' : ($alert_type === 'no_budget' ? 'budget' : 'date')) . '">' . ucwords(str_replace('_', ' ', $alert_type)) . '</span></p>
                
                <div class="project-info">
                    <table>
                        <tr><td class="label">Project</td><td>' . htmlspecialchars($project['name']) . '</td></tr>
                        <tr><td class="label">Client</td><td>' . htmlspecialchars($project['client_name'] ?? 'N/A') . '</td></tr>
                        <tr><td class="label">Status</td><td>' . htmlspecialchars($project['status']) . '</td></tr>
                        <tr><td class="label">Budget</td><td>$' . number_format($project['total_budget'] ?? 0, 2) . '</td></tr>
                        <tr><td class="label">End Date</td><td>' . htmlspecialchars($project['end_date'] ?? 'Not set') . '</td></tr>
                    </table>
                </div>
                
                <p><strong>Message:</strong></p>
                <p>' . htmlspecialchars($message) . '</p>
                
                <p style="margin-top: 20px;">
                    <a href="' . (defined('BASE_URL') ? BASE_URL : '') . 'projects.php?search=' . $project_id . '" style="color: #F97316;">View Project →</a>
                </p>
            </div>
            <div class="footer">
                Infinity Builders - Project Management System
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Send weekly activity summary
 */
function notify_weekly_summary(): bool {
    $admins = get_admin_emails();
    
    if (empty($admins)) {
        return false;
    }
    
    require_once __DIR__ . '/../config.php';
    global $db_host, $db_name, $db_user, $db_pass;
    
    $pdo = new PDO(
        'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4',
        $db_user,
        $db_pass
    );
    
    // Get stats for the week
    $stats = [
        'new_projects' => 0,
        'new_vendors' => 0,
        'new_payments' => 0,
        'total_paid' => 0,
        'login_count' => 0
    ];
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM projects WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['new_projects'] = ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM vendors WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['new_vendors'] = ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        
        $stmt = $pdo->query("SELECT COUNT(*) as cnt, COALESCE(SUM(amount), 0) as total FROM vendor_payments WHERE paid_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['new_payments'] = $row['cnt'] ?? 0;
        $stats['total_paid'] = $row['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM audit_log WHERE action_type = 'login' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['login_count'] = ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
    } catch (Exception $e) {}
    
    $subject = "📊 Weekly Summary - " . date('M j, Y');
    $body = generate_weekly_summary_email($stats);
    
    $results = [];
    foreach ($admins as $email) {
        $results[] = send_email($email, $subject, $body);
    }
    
    return !empty(array_filter($results, fn($r) => $r['status'] === 'sent'));
}

function generate_weekly_summary_email(array $stats): string {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #1E293B; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #3B82F6, #2563EB); color: white; padding: 20px; border-radius: 8px 8px 0 0; }
            .content { background: #F8FAFC; padding: 20px; border: 1px solid #E2E8F0; border-top: none; }
            .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin: 20px 0; }
            .stat-card { background: white; padding: 16px; border-radius: 8px; text-align: center; }
            .stat-value { font-size: 28px; font-weight: 700; color: #F97316; }
            .stat-label { font-size: 12px; color: #64748B; text-transform: uppercase; }
            .footer { text-align: center; padding: 16px; color: #94A3B8; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2 style="margin:0;">📊 Weekly Summary</h2>
                <p style="margin:8px 0 0 0; opacity: 0.9;">' . date('F j, Y') . '</p>
            </div>
            <div class="content">
                <p>Here\'s what happened this week in Infinity Builders:</p>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value">' . $stats['new_projects'] . '</div>
                        <div class="stat-label">New Projects</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">' . $stats['new_vendors'] . '</div>
                        <div class="stat-label">New Vendors</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">' . $stats['new_payments'] . '</div>
                        <div class="stat-label">Payments</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">$' . number_format($stats['total_paid'] / 1000, 1) . 'K</div>
                        <div class="stat-label">Total Paid</div>
                    </div>
                </div>
                
                <p><strong>User Activity:</strong> ' . $stats['login_count'] . ' login(s) this week</p>
                
                <p style="margin-top: 20px;">
                    <a href="' . (defined('BASE_URL') ? BASE_URL : '') . 'reports.php" style="color: #3B82F6;">View Detailed Reports →</a>
                </p>
            </div>
            <div class="footer">
                Infinity Builders - Project Management System
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Send deadline reminder
 */
function notify_deadline_reminder(int $project_id, int $days_until): bool {
    $admins = get_admin_emails();
    
    if (empty($admins)) {
        return false;
    }
    
    require_once __DIR__ . '/../config.php';
    global $db_host, $db_name, $db_user, $db_pass;
    
    $pdo = new PDO(
        'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4',
        $db_user,
        $db_pass
    );
    
    $stmt = $pdo->prepare("SELECT name, client_name, end_date FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$project) {
        return false;
    }
    
    $subject = "⏰ Deadline Reminder: {$project['name']}";
    $body = generate_deadline_email($project, $days_until);
    
    $results = [];
    foreach ($admins as $email) {
        $results[] = send_email($email, $subject, $body);
    }
    
    return !empty(array_filter($results, fn($r) => $r['status'] === 'sent'));
}

function generate_deadline_email(array $project, int $days_until): string {
    $urgency = $days_until <= 3 ? '🔴' : ($days_until <= 7 ? '🟡' : '🟢');
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #1E293B; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #F59E0B, #D97706); color: white; padding: 20px; border-radius: 8px 8px 0 0; }
            .content { background: #F8FAFC; padding: 20px; border: 1px solid #E2E8F0; border-top: none; }
            .deadline { font-size: 24px; font-weight: 700; color: ' . ($days_until <= 3 ? '#DC2626' : ($days_until <= 7 ? '#D97706' : '#22C55E')) . '; }
            .footer { text-align: center; padding: 16px; color: #94A3B8; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2 style="margin:0;">⏰ Deadline Reminder</h2>
            </div>
            <div class="content">
                <p>A project deadline is coming up:</p>
                
                <h3>' . htmlspecialchars($project['name']) . '</h3>
                <p><strong>Client:</strong> ' . htmlspecialchars($project['client_name'] ?? 'N/A') . '</p>
                <p><strong>Due Date:</strong> ' . htmlspecialchars($project['end_date']) . '</p>
                
                <p class="deadline">' . $urgency . ' ' . $days_until . ' day(s) remaining</p>
                
                <p style="margin-top: 20px;">
                    <a href="' . (defined('BASE_URL') ? BASE_URL : '') . 'projects.php?search=' . ($project['id'] ?? '') . '" style="color: #F59E0B;">View Project →</a>
                </p>
            </div>
            <div class="footer">
                Infinity Builders - Project Management System
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Check for projects that need alerts and send notifications
 * Run this periodically (e.g., daily via cron or manual)
 */
function check_and_notify_project_alerts(): array {
    require_once __DIR__ . '/../config.php';
    global $db_host, $db_name, $db_user, $db_pass;
    
    $pdo = new PDO(
        'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4',
        $db_user,
        $db_pass
    );
    
    $alerts_sent = [];
    
    // Check projects On Hold for > 30 days
    try {
        $stmt = $pdo->query("
            SELECT id, name, DATEDIFF(NOW(), start_date) as days_on_hold 
            FROM projects 
            WHERE status = 'On Hold' 
            AND start_date IS NOT NULL 
            AND DATEDIFF(NOW(), start_date) > 30
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $msg = "Project has been On Hold for {$row['days_on_hold']} days";
            if (notify_project_alert($row['id'], 'on_hold', $msg)) {
                $alerts_sent[] = "On Hold alert for Project #{$row['id']}";
            }
        }
    } catch (Exception $e) {
        error_log('On Hold check failed: ' . $e->getMessage());
    }
    
    // Check projects with no budget
    try {
        $stmt = $pdo->query("SELECT id, name FROM projects WHERE (total_budget IS NULL OR total_budget = 0) AND status = 'Active'");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $msg = "Project has no budget set";
            if (notify_project_alert($row['id'], 'no_budget', $msg)) {
                $alerts_sent[] = "No budget alert for Project #{$row['id']}";
            }
        }
    } catch (Exception $e) {
        error_log('No budget check failed: ' . $e->getMessage());
    }
    
    // Check projects due in 7 days
    try {
        $stmt = $pdo->query("
            SELECT id, name, end_date, DATEDIFF(end_date, NOW()) as days_left
            FROM projects 
            WHERE status = 'Active' 
            AND end_date IS NOT NULL 
            AND DATEDIFF(end_date, NOW()) BETWEEN 1 AND 7
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (notify_deadline_reminder($row['id'], (int)$row['days_left'])) {
                $alerts_sent[] = "Deadline reminder for Project #{$row['id']} ({$row['days_left']} days)";
            }
        }
    } catch (Exception $e) {
        error_log('Deadline check failed: ' . $e->getMessage());
    }
    
    // Check projects past due
    try {
        $stmt = $pdo->query("
            SELECT id, name, end_date
            FROM projects 
            WHERE status = 'Active' 
            AND end_date IS NOT NULL 
            AND end_date < CURDATE()
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $msg = "Project deadline has passed";
            if (notify_project_alert($row['id'], 'past_due', $msg)) {
                $alerts_sent[] = "Past due alert for Project #{$row['id']}";
            }
        }
    } catch (Exception $e) {
        error_log('Past due check failed: ' . $e->getMessage());
    }
    
    return $alerts_sent;
}