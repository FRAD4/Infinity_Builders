<?php
/**
 * print-reports.php - Print-friendly version of reports
 * Can be saved as PDF from browser (Ctrl+P)
 */

session_start();
require_once 'partials/init.php';

$userRole = $_SESSION['user_role'] ?? 'user';
$allowedRoles = ['admin', 'pm', 'accounting', 'estimator'];
if (!in_array($userRole, $allowedRoles, true)) {
    http_response_code(403);
    die('Access denied.');
}

// Load report data (same as reports.php)
$projectStats = ['total' => 0, 'active' => 0, 'planned' => 0, 'on_hold' => 0, 'complete' => 0, 'total_budget' => 0];
try {
    $stmt = $pdo->query("SELECT status, COUNT(*) as cnt, COALESCE(SUM(total_budget), 0) as budget FROM projects GROUP BY status");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $projectStats['total'] += (int)$row['cnt'];
        $projectStats['total_budget'] += (float)$row['budget'];
        $status = strtolower($row['status'] ?? '');
        if ($status === 'active') $projectStats['active'] = (int)$row['cnt'];
        elseif ($status === 'planned') $projectStats['planned'] = (int)$row['cnt'];
        elseif ($status === 'on hold' || $status === 'on_hold') $projectStats['on_hold'] = (int)$row['cnt'];
        elseif ($status === 'complete') $projectStats['complete'] = (int)$row['cnt'];
    }
} catch (Exception $e) {}

$vendorStats = ['total' => 0, 'by_type' => []];
try {
    $stmt = $pdo->query("SELECT type, COUNT(*) as cnt FROM vendors GROUP BY type");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $vendorStats['by_type'][$row['type']] = (int)$row['cnt'];
        $vendorStats['total'] += (int)$row['cnt'];
    }
} catch (Exception $e) {}

$paymentStats = ['total_paid' => 0, 'this_month' => 0, 'this_year' => 0];
try {
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM vendor_payments");
    $paymentStats['total_paid'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM vendor_payments WHERE YEAR(paid_date) = YEAR(CURDATE())");
    $paymentStats['this_year'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM vendor_payments WHERE YEAR(paid_date) = YEAR(CURDATE()) AND MONTH(paid_date) = MONTH(CURDATE())");
    $paymentStats['this_month'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
} catch (Exception $e) {}

$recentPayments = [];
try {
    $recentPayments = $pdo->query("SELECT p.*, v.name as vendor_name FROM vendor_payments p LEFT JOIN vendors v ON p.vendor_id = v.id ORDER BY p.paid_date DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - Infinity Builders</title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
            .card { border: 1px solid #ddd !important; break-inside: avoid; }
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #F97316;
        }
        
        .header h1 {
            font-size: 24px;
            color: #1a1a1a;
        }
        
        .header .subtitle {
            color: #666;
            font-size: 14px;
        }
        
        .header .date {
            text-align: right;
            color: #666;
            font-size: 12px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #F97316;
        }
        
        .stat-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            margin-top: 4px;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .card-header {
            background: #f8f8f8;
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
            font-weight: 600;
        }
        
        .card-body {
            padding: 15px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f8f8;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            color: #666;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-planned { background: #dbeafe; color: #1e40af; }
        .badge-on-hold { background: #fef3c7; color: #92400e; }
        .badge-complete { background: #ede9fe; color: #5b21b6; }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #999;
            font-size: 11px;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #F97316;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>📊 Infinity Builders Reports</h1>
            <p class="subtitle">Executive Summary Report</p>
        </div>
        <div class="date">
            <p>Generated: <?php echo date('M j, Y g:i A'); ?></p>
            <p>Printed by: <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></p>
        </div>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo $projectStats['total']; ?></div>
            <div class="stat-label">Total Projects</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $projectStats['active']; ?></div>
            <div class="stat-label">Active Projects</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $vendorStats['total']; ?></div>
            <div class="stat-label">Vendors</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">$<?php echo number_format($paymentStats['total_paid'] / 1000, 0); ?>K</div>
            <div class="stat-label">Total Paid</div>
        </div>
    </div>
    
    <div class="grid">
        <div class="card">
            <div class="card-header">Project Status</div>
            <div class="card-body">
                <table>
                    <tr>
                        <td>Active</td>
                        <td><?php echo $projectStats['active']; ?></td>
                    </tr>
                    <tr>
                        <td>Planned</td>
                        <td><?php echo $projectStats['planned']; ?></td>
                    </tr>
                    <tr>
                        <td>On Hold</td>
                        <td><?php echo $projectStats['on_hold']; ?></td>
                    </tr>
                    <tr>
                        <td>Complete</td>
                        <td><?php echo $projectStats['complete']; ?></td>
                    </tr>
                    <tr style="font-weight: bold; border-top: 2px solid #ddd;">
                        <td>Total Budget</td>
                        <td>$<?php echo number_format($projectStats['total_budget'], 2); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">Payment Summary</div>
            <div class="card-body">
                <table>
                    <tr>
                        <td>This Month</td>
                        <td>$<?php echo number_format($paymentStats['this_month'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>This Year</td>
                        <td>$<?php echo number_format($paymentStats['this_year'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>All Time</td>
                        <td>$<?php echo number_format($paymentStats['total_paid'], 2); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <?php if (!empty($recentPayments)): ?>
    <div class="card" style="margin-bottom: 30px;">
        <div class="card-header">Recent Payments</div>
        <div class="card-body" style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Vendor</th>
                        <th>Description</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentPayments as $p): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($p['paid_date']); ?></td>
                        <td><?php echo htmlspecialchars($p['vendor_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($p['description'] ?? '-'); ?></td>
                        <td style="text-align: right;">$<?php echo number_format($p['amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="footer">
        <p>Infinity Builders Construction Management System</p>
        <p style="margin-top: 8px;">
            <button onclick="window.print()" class="btn no-print">🖨️ Print / Save as PDF</button>
        </p>
    </div>
</body>
</html>
