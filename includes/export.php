<?php
/**
 * Export Helper for Infinity Builders
 * Generate CSV exports of data
 */

/**
 * Export projects to CSV
 */
function export_projects_csv(): void {
    require_once __DIR__ . '/../config.php';
    
    try {
        global $db_host, $db_name, $db_user, $db_pass;
        $pdo = new PDO(
            'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4',
            $db_user,
            $db_pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $stmt = $pdo->query("
            SELECT id, name, client_name, status, total_budget, start_date, end_date, notes, created_at 
            FROM projects 
            ORDER BY created_at DESC
        ");
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log export (optional - don't fail if audit not available)
        @include_once __DIR__ . '/audit.php';
        if (function_exists('audit_log')) {
            audit_log('export', 'projects', null, 'All projects CSV export');
        }
        
        // Set headers for download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=projects_export_' . date('Y-m-d') . '.csv');
        
        // Open output
        $output = fopen('php://output', 'w');
        
        // BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($output, ['ID', 'Name', 'Client', 'Status', 'Budget', 'Start Date', 'End Date', 'Notes', 'Created']);
        
        // Data
        foreach ($projects as $p) {
            fputcsv($output, [
                $p['id'],
                $p['name'],
                $p['client_name'] ?? '',
                $p['status'] ?? '',
                $p['total_budget'] ?? '',
                $p['start_date'] ?? '',
                $p['end_date'] ?? '',
                $p['notes'] ?? '',
                $p['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    } catch (Exception $e) {
        error_log('CSV export failed: ' . $e->getMessage());
        die('Export failed');
    }
}

/**
 * Export vendors to CSV
 */
function export_vendors_csv(): void {
    require_once __DIR__ . '/../config.php';
    
    try {
        global $db_host, $db_name, $db_user, $db_pass;
        $pdo = new PDO(
            'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4',
            $db_user,
            $db_pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $stmt = $pdo->query("
            SELECT v.id, v.name, v.type, v.trade, v.phone, v.email, 
                   COALESCE(SUM(p.amount), 0) as total_paid,
                   v.created_at
            FROM vendors v
            LEFT JOIN vendor_payments p ON v.id = p.vendor_id
            GROUP BY v.id
            ORDER BY v.name ASC
        ");
        $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log export (optional)
        @include_once __DIR__ . '/audit.php';
        if (function_exists('audit_log')) {
            audit_log('export', 'vendors', null, 'All vendors CSV export');
        }
        
        // Set headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=vendors_export_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, ['ID', 'Name', 'Type', 'Trade', 'Phone', 'Email', 'Total Paid', 'Created']);
        
        foreach ($vendors as $v) {
            fputcsv($output, [
                $v['id'],
                $v['name'],
                $v['type'] ?? '',
                $v['trade'] ?? '',
                $v['phone'] ?? '',
                $v['email'] ?? '',
                $v['total_paid'] ?? 0,
                $v['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    } catch (Exception $e) {
        error_log('CSV export failed: ' . $e->getMessage());
        die('Export failed');
    }
}

/**
 * Export payments to CSV
 */
function export_payments_csv(): void {
    require_once __DIR__ . '/../config.php';
    
    try {
        global $db_host, $db_name, $db_user, $db_pass;
        $pdo = new PDO(
            'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4',
            $db_user,
            $db_pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $stmt = $pdo->query("
            SELECT p.id, v.name as vendor_name, p.amount, p.paid_date, p.description, p.created_at
            FROM vendor_payments p
            LEFT JOIN vendors v ON p.vendor_id = v.id
            ORDER BY p.paid_date DESC
        ");
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log export (optional)
        @include_once __DIR__ . '/audit.php';
        if (function_exists('audit_log')) {
            audit_log('export', 'payments', null, 'All payments CSV export');
        }
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=payments_export_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, ['ID', 'Vendor', 'Amount', 'Date', 'Description', 'Created']);
        
        foreach ($payments as $p) {
            fputcsv($output, [
                $p['id'],
                $p['vendor_name'] ?? 'Unknown',
                $p['amount'],
                $p['paid_date'],
                $p['description'] ?? '',
                $p['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    } catch (Exception $e) {
        error_log('CSV export failed: ' . $e->getMessage());
        die('Export failed');
    }
}

/**
 * Export audit log to CSV
 */
function export_audit_csv(): void {
    require_once __DIR__ . '/../config.php';
    
    try {
        global $db_host, $db_name, $db_user, $db_pass;
        $pdo = new PDO(
            'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4',
            $db_user,
            $db_pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $stmt = $pdo->query("
            SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 5000
        ");
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=audit_log_export_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, ['ID', 'User', 'Action', 'Entity Type', 'Entity ID', 'Entity Name', 'Changes', 'IP Address', 'Timestamp']);
        
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['username'] ?? 'System',
                $log['action_type'],
                $log['entity_type'],
                $log['entity_id'] ?? '',
                $log['entity_name'] ?? '',
                $log['changes'] ?? '',
                $log['ip_address'] ?? '',
                $log['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    } catch (Exception $e) {
        error_log('CSV export failed: ' . $e->getMessage());
        die('Export failed');
    }
}
