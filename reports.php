<?php
/**
 * Reports Page
 * Real reports and analytics for Infinity Builders
 */

$pageTitle = 'Reports';
$currentPage = 'reports';

require_once 'partials/init.php';

// Role check - only PM, Accounting, Admin can view reports
$userRole = $_SESSION['user_role'] ?? 'user';
$allowedRoles = ['admin', 'pm', 'accounting', 'estimator'];
if (!in_array($userRole, $allowedRoles, true)) {
    http_response_code(403);
    die('Access denied. Only project team members can view reports.');
}

$message = '';

// Generate CSRF token for any forms
require_once 'includes/security.php';
$csrf_token = csrf_token_generate();

// ===== Load Report Data =====

// 1. Project Summary
$projectStats = [
    'total' => 0,
    'active' => 0,
    'planned' => 0,
    'on_hold' => 0,
    'complete' => 0,
    'total_budget' => 0
];
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

// 2. Vendor Summary
$vendorStats = [
    'total' => 0,
    'by_type' => []
];
try {
    $stmt = $pdo->query("SELECT type, COUNT(*) as cnt FROM vendors GROUP BY type");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $vendorStats['by_type'][$row['type']] = (int)$row['cnt'];
        $vendorStats['total'] += (int)$row['cnt'];
    }
} catch (Exception $e) {}

// 3. Payment Summary
$paymentStats = [
    'total_paid' => 0,
    'this_month' => 0,
    'this_year' => 0,
    'by_year' => []
];
try {
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM vendor_payments");
    $paymentStats['total_paid'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM vendor_payments WHERE YEAR(paid_date) = YEAR(CURDATE())");
    $paymentStats['this_year'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM vendor_payments WHERE YEAR(paid_date) = YEAR(CURDATE()) AND MONTH(paid_date) = MONTH(CURDATE())");
    $paymentStats['this_month'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $stmt = $pdo->query("SELECT YEAR(paid_date) as yr, SUM(amount) as total FROM vendor_payments GROUP BY YEAR(paid_date) ORDER BY yr DESC LIMIT 5");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $paymentStats['by_year'][(int)$row['yr']] = (float)$row['total'];
    }
} catch (Exception $e) {}

// 4. Top Vendors by Payments
$topVendors = [];
try {
    $stmt = $pdo->query("
        SELECT v.id, v.name, v.type, COALESCE(SUM(p.amount), 0) as total_paid 
        FROM vendors v 
        LEFT JOIN vendor_payments p ON v.id = p.vendor_id 
        GROUP BY v.id 
        ORDER BY total_paid DESC 
        LIMIT 10
    ");
    $topVendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// 5. Task Analytics
$taskStats = [
    'total' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'cancelled' => 0
];
try {
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as cnt 
        FROM project_tasks 
        GROUP BY status
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $taskStats['total'] += (int)$row['cnt'];
        $status = $row['status'];
        if (isset($taskStats[$status])) {
            $taskStats[$status] = (int)$row['cnt'];
        }
    }
} catch (Exception $e) {}

// 6. Project Budget Utilization
$budgetUtilization = [];
try {
    $stmt = $pdo->query("
        SELECT 
            p.name,
            p.total_budget,
            COALESCE(SUM(vp.amount), 0) as paid
        FROM projects p
        LEFT JOIN project_vendors pv ON pv.project_id = p.id
        LEFT JOIN vendor_payments vp ON vp.vendor_id = pv.vendor_id
        WHERE p.total_budget > 0
        GROUP BY p.id
        ORDER BY (COALESCE(SUM(vp.amount), 0) / p.total_budget) DESC
        LIMIT 10
    ");
    $budgetUtilization = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// 7. Monthly Payment Trend (last 12 months)
$monthlyPayments = [];
try {
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(paid_date, '%Y-%m') as month, SUM(amount) as total
        FROM vendor_payments
        WHERE paid_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(paid_date, '%Y-%m')
        ORDER BY month ASC
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $monthlyPayments['labels'][] = date('M y', strtotime($row['month'] . '-01'));
        $monthlyPayments['data'][] = (float)$row['total'];
    }
} catch (Exception $e) {}

$monthlyPaymentsJson = json_encode($monthlyPayments);

// 6. Recent Activity
$recentPayments = [];
try {
    $stmt = $pdo->query("
        SELECT p.*, v.name as vendor_name 
        FROM vendor_payments p 
        LEFT JOIN vendors v ON p.vendor_id = v.id 
        ORDER BY p.paid_date DESC 
        LIMIT 10
    ");
    $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

require_once 'partials/header.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
  <div>
    <h1>Reports & Analytics</h1>
    <p class="text-secondary">Financial summaries and project status overview</p>
  </div>
  <div style="display: flex; gap: 8px; flex-wrap: wrap;">
    <a href="print-reports.php" target="_blank" class="btn btn-sm" style="background: var(--primary); color: white;">
      <i class="fa-solid fa-print"></i> Print / PDF
    </a>
    <a href="export-projects.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-download"></i> Projects
    </a>
    <a href="export-vendors.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-download"></i> Vendors
    </a>
    <a href="export-payments.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-download"></i> Payments
    </a>
    <?php if ($userRole === 'admin'): ?>
    <a href="export-audit.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-clipboard-list"></i> Audit
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Quick Stats -->
<div class="stats-grid">
  <div class="stat-card stat-card-blue">
    <div class="stat-value"><?php echo $projectStats['total']; ?></div>
    <div class="stat-label">Total Projects</div>
  </div>
  
  <div class="stat-card stat-card-green">
    <div class="stat-value"><?php echo $projectStats['active']; ?></div>
    <div class="stat-label">Active Projects</div>
  </div>
  
  <div class="stat-card stat-card-blue">
    <div class="stat-value"><?php echo $vendorStats['total']; ?></div>
    <div class="stat-label">Vendors</div>
  </div>
  
  <div class="stat-card stat-card-green">
    <div class="stat-value">$<?php echo number_format($paymentStats['total_paid'] / 1000, 0); ?>K</div>
    <div class="stat-label">Total Paid to Vendors</div>
  </div>
</div>

<div class="grid">
  <!-- Project Status -->
  <div class="card">
    <div class="card-header">
      <h3>Project Status</h3>
    </div>
    <div class="card-body">
      <div class="card-subtitle">Overview of all projects</div>
      
      <div style="display: flex; justify-content: space-between; margin-bottom: 12px; padding: 0 4px;">
        <span class="text-secondary">Status</span>
        <span class="text-secondary">Count</span>
      </div>
      
      <?php 
        $statuses = [
          'Active' => $projectStats['active'],
          'Planned' => $projectStats['planned'],
          'On Hold' => $projectStats['on_hold'],
          'Complete' => $projectStats['complete']
        ];
        foreach ($statuses as $label => $count): 
      ?>
        <div style="display: flex; justify-content: space-between; padding: 10px 4px; border-bottom: 1px solid var(--border-color);">
          <span><?php echo htmlspecialchars($label); ?></span>
          <strong><?php echo $count; ?></strong>
        </div>
      <?php endforeach; ?>
      
      <div style="margin-top: 16px; padding-top: 12px; border-top: 2px solid var(--border-color);">
        <div style="display: flex; justify-content: space-between;">
          <span>Total Agreement Amount</span>
          <strong class="text-primary">$<?php echo number_format($projectStats['total_budget'], 2); ?></strong>
        </div>
      </div>
    </div>
  </div>

  <!-- Vendor by Type -->
  <div class="card">
    <div class="card-header">
      <h3>Vendors by Type</h3>
    </div>
    <div class="card-body">
      <div class="card-subtitle">Distribution of vendor categories</div>
      
      <?php if (empty($vendorStats['by_type'])): ?>
        <p class="muted">No vendors found.</p>
      <?php else: ?>
        <?php foreach ($vendorStats['by_type'] as $type => $count): ?>
          <div style="display: flex; justify-content: space-between; padding: 10px 4px; border-bottom: 1px solid var(--border-color);">
            <span><?php echo htmlspecialchars($type); ?></span>
            <strong><?php echo $count; ?></strong>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Charts Row -->
<div class="grid grid-2" style="margin-top: 24px;">
  <!-- Project Status Chart -->
  <div class="card">
    <div class="card-header">
      <h3>Project Status</h3>
    </div>
    <div class="card-body">
      <div class="card-subtitle">Distribution by status</div>
      <div style="height: 220px; display: flex; align-items: center; justify-content: center;">
        <canvas id="projectStatusChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Vendor Type Chart -->
  <div class="card">
    <div class="card-header">
      <h3>Vendors by Type</h3>
    </div>
    <div class="card-body">
      <div class="card-subtitle">Distribution by category</div>
      <div style="height: 220px; display: flex; align-items: center; justify-content: center;">
        <canvas id="vendorTypeChart"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Task Analytics -->
<div class="grid grid-2" style="margin-top: 24px;">
  <!-- Task Summary -->
  <div class="card">
    <div class="card-header">
      <h3>Task Summary</h3>
    </div>
    <div class="card-body">
      <div class="card-subtitle">Project task status overview</div>
      
      <?php if ($taskStats['total'] === 0): ?>
        <p class="muted">No tasks found.</p>
      <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 12px;">
          <div class="stat-card stat-card-blue">
            <div class="stat-value"><?php echo $taskStats['total']; ?></div>
            <div class="stat-label">Total Tasks</div>
          </div>
          <div class="stat-card stat-card-green">
            <div class="stat-value"><?php echo $taskStats['completed']; ?></div>
            <div class="stat-label">Completed</div>
          </div>
          <div class="stat-card stat-card-yellow">
            <div class="stat-value"><?php echo $taskStats['in_progress']; ?></div>
            <div class="stat-label">In Progress</div>
          </div>
          <div class="stat-card stat-card-info">
            <div class="stat-value"><?php echo $taskStats['pending']; ?></div>
            <div class="stat-label">Pending</div>
          </div>
        </div>
        
        <?php if ($taskStats['total'] > 0): ?>
        <div style="margin-top: 16px;">
          <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
            <span class="text-secondary">Completion Rate</span>
            <strong><?php echo round(($taskStats['completed'] / $taskStats['total']) * 100); ?>%</strong>
          </div>
          <div style="background: var(--bg-hover); height: 8px; border-radius: 4px; overflow: hidden;">
            <div style="background: var(--success); height: 100%; width: <?php echo ($taskStats['completed'] / $taskStats['total']) * 100; ?>%;"></div>
          </div>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  
  <!-- Task Status Chart -->
  <div class="card">
    <div class="card-header">
      <h3>Task Status</h3>
    </div>
    <div class="card-body">
      <div class="card-subtitle">Distribution by status</div>
      <div style="height: 220px; display: flex; align-items: center; justify-content: center;">
        <canvas id="taskStatusChart"></canvas>
      </div>
    </div>
</div>

<!-- Monthly Payment Trend -->
<div class="card" style="margin-top: 24px;">
  <div class="card-header">
    <h3>Payment Trend</h3>
  </div>
  <div class="card-body">
    <div class="card-subtitle">Monthly payment activity (last 12 months)</div>
    <div style="height: 280px;">
      <canvas id="paymentTrendChart"></canvas>
    </div>
  </div>
</div>

<!-- Payment Summary -->
<div class="card" style="margin-top: 24px;">
  <div class="card-header">
    <h3>Payment Summary</h3>
  </div>
  <div class="card-body">
    <div class="card-subtitle">Vendor payment analytics</div>
    
    <div class="stats-grid" style="margin-top: 12px;">
      <div class="stat-card stat-card-blue">
        <div class="stat-value">$<?php echo number_format($paymentStats['this_month'] / 1000, 1); ?>K</div>
        <div class="stat-label">This Month</div>
      </div>
      
      <div class="stat-card stat-card-blue">
        <div class="stat-value">$<?php echo number_format($paymentStats['this_year'] / 1000, 0); ?>K</div>
        <div class="stat-label">This Year</div>
      </div>
      
      <div class="stat-card stat-card-green">
        <div class="stat-value">$<?php echo number_format($paymentStats['total_paid'] / 1000, 0); ?>K</div>
        <div class="stat-label">All Time</div>
      </div>
    </div>
    
    <?php if (!empty($paymentStats['by_year'])): ?>
      <div style="margin-top: 20px;">
        <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 12px;">Payments by Year</h4>
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
          <?php foreach ($paymentStats['by_year'] as $yr => $total): ?>
            <div style="background: var(--bg-hover); padding: 10px 14px; border-radius: 8px; min-width: 90px;">
              <div style="font-size: 11px; color: var(--text-muted);"><?php echo $yr; ?></div>
              <div style="font-size: 16px; font-weight: 600;">$<?php echo number_format($total / 1000, 0); ?>K</div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Top Vendors -->
<div class="card" style="margin-top: 24px;">
  <div class="card-header">
    <h3>Top Vendors by Payments</h3>
  </div>
  <div class="card-body">
    <div class="card-subtitle">Highest paid vendors</div>
    
    <?php if (empty($topVendors)): ?>
      <p class="muted">No payment data available.</p>
    <?php else: ?>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Vendor</th>
              <th>Type</th>
              <th style="text-align: right;">Total Paid</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($topVendors as $v): ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($v['name']); ?></strong></td>
                <td><?php echo htmlspecialchars($v['type'] ?? '-'); ?></td>
                <td class="text-right">$<?php echo number_format($v['total_paid'], 2); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Recent Payments -->
<div class="card" style="margin-top: 24px;">
  <div class="card-header">
    <h3>Recent Payments</h3>
  </div>
  <div class="card-body">
    <div class="card-subtitle">Latest vendor payments</div>
    
    <?php if (empty($recentPayments)): ?>
      <p class="muted">No recent payments.</p>
    <?php else: ?>
      <div class="table-wrapper">
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
                <td class="text-right">$<?php echo number_format($p['amount'], 2); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Charts -->
<script>
(function() {
    function getThemeColors() {
        const html = document.documentElement;
        let theme = html.getAttribute('data-theme');
        
        if (theme === 'system') {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            theme = prefersDark ? 'dark' : 'light';
        }
        
        const isDark = theme !== 'light';
        return {
            isDark,
            textColor: isDark ? '#F8FAFC' : '#0F172A',
            gridColor: isDark ? '#334155' : '#E2E8F0',
            tickColor: isDark ? '#94A3B8' : '#64748B',
            legendColor: isDark ? '#F8FAFC' : '#334155'
        };
    }
    
    function initCharts() {
        if (typeof Chart === 'undefined') return;
        
        const theme = getThemeColors();
        Chart.defaults.color = theme.textColor;
        Chart.defaults.borderColor = theme.gridColor;
        
        // Project Status Chart (Doughnut)
        const projectCtx = document.getElementById('projectStatusChart');
        if (projectCtx) {
            new Chart(projectCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Active', 'Planned', 'On Hold', 'Complete'],
                    datasets: [{
                        data: [
                            <?php echo $projectStats['active']; ?>,
                            <?php echo $projectStats['planned']; ?>,
                            <?php echo $projectStats['on_hold']; ?>,
                            <?php echo $projectStats['complete']; ?>
                        ],
                        backgroundColor: ['#3B82F6', '#6366F1', '#F59E0B', '#22C55E'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: theme.legendColor,
                                padding: 16,
                                usePointStyle: true,
                                font: { size: 12 }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        }
        
        // Vendor Type Chart (Pie)
        const vendorCtx = document.getElementById('vendorTypeChart');
        if (vendorCtx) {
            new Chart(vendorCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_keys($vendorStats['by_type'])); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_values($vendorStats['by_type'])); ?>,
                        backgroundColor: ['#F97316', '#3B82F6', '#22C55E', '#8B5CF6', '#EC4899', '#06B6D4'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: theme.legendColor,
                                padding: 12,
                                usePointStyle: true,
                                font: { size: 12 }
                            }
                        }
                    }
                }
            });
        }
        
        // Task Status Chart (Doughnut)
        const taskCtx = document.getElementById('taskStatusChart');
        if (taskCtx) {
            new Chart(taskCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'In Progress', 'Pending', 'Cancelled'],
                    datasets: [{
                        data: [
                            <?php echo $taskStats['completed']; ?>,
                            <?php echo $taskStats['in_progress']; ?>,
                            <?php echo $taskStats['pending']; ?>,
                            <?php echo $taskStats['cancelled']; ?>
                        ],
                        backgroundColor: ['#22C55E', '#F59E0B', '#3B82F6', '#6B7280'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: theme.legendColor,
                                padding: 12,
                                usePointStyle: true,
                                font: { size: 12 }
                            }
                        }
                    }
                }
            });
        }
        
        // Payment Trend Chart (Line)
        const trendCtx = document.getElementById('paymentTrendChart');
        if (trendCtx) {
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo $monthlyPaymentsJson; ?>.labels || [],
                    datasets: [{
                        label: 'Payments',
                        data: <?php echo $monthlyPaymentsJson; ?>.data || [],
                        borderColor: '#F97316',
                        backgroundColor: theme.isDark ? 'rgba(249, 115, 22, 0.15)' : 'rgba(249, 115, 22, 0.25)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: '#F97316'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: {
                            ticks: { color: theme.tickColor },
                            grid: { color: theme.gridColor, drawBorder: false }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { 
                                color: theme.tickColor,
                                callback: function(value) { return '$' + (value / 1000) + 'K'; }
                            },
                            grid: { color: theme.gridColor, drawBorder: false }
                        }
                    }
                }
            });
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCharts);
    } else {
        initCharts();
    }
})();
</script>

<?php require_once 'partials/footer.php'; ?>
