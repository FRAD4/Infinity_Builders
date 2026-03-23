<?php
/**
 * dashboard.php - Personalized dashboard by role
 */

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

require_once 'partials/init.php';

$userRole = $_SESSION['user_role'] ?? 'viewer';
$userId = $_SESSION['user_id'] ?? 0;

// Load user preferences for personalization
require_once __DIR__ . '/includes/preferences.php';
$preferences = get_dashboard_preferences($userId);

// Apply theme preference
$themePref = $preferences['dashboard_theme'] ?? 'system';
if ($themePref !== 'system') {
    $_SESSION['theme_override'] = $themePref;
}

// Get role-specific data
$stats = [
    'projects' => 0,
    'active_projects' => 0,
    'complete_projects' => 0,
    'on_hold_projects' => 0,
    'total_budget' => 0,
    'users' => 0,
    'vendors' => 0,
    'total_paid' => 0,
    'paid_this_month' => 0,
    'paid_this_year' => 0,
    'pending_projects' => 0,
    'completion_rate' => 0,
    'budget_utilization' => 0
];

// Common stats
try {
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM projects");
    $stats['projects'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
} catch (Exception $e) {}

try {
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM projects WHERE status = 'Active'");
    $stats['active_projects'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
} catch (Exception $e) {}

try {
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM projects WHERE status = 'Complete'");
    $stats['complete_projects'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
} catch (Exception $e) {}

try {
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM projects WHERE status = 'On Hold'");
    $stats['on_hold_projects'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
} catch (Exception $e) {}

try {
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_budget), 0) AS total FROM projects");
    $stats['total_budget'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
} catch (Exception $e) {}

try {
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM vendors");
    $stats['vendors'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
} catch (Exception $e) {}

try {
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) AS total FROM vendor_payments");
    $stats['total_paid'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
} catch (Exception $e) {}

try {
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) AS total FROM vendor_payments WHERE YEAR(paid_date) = YEAR(CURDATE()) AND MONTH(paid_date) = MONTH(CURDATE())");
    $stats['paid_this_month'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
} catch (Exception $e) {}

try {
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) AS total FROM vendor_payments WHERE YEAR(paid_date) = YEAR(CURDATE())");
    $stats['paid_this_year'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
} catch (Exception $e) {}

// Calculate rates
$stats['completion_rate'] = $stats['projects'] > 0 ? round(($stats['complete_projects'] / $stats['projects']) * 100) : 0;
$stats['budget_utilization'] = $stats['total_budget'] > 0 ? round(($stats['total_paid'] / $stats['total_budget']) * 100) : 0;

// Admin-only stats
if ($userRole === 'admin') {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) AS c FROM users");
        $stats['users'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    } catch (Exception $e) {}
}

// Get data for charts
$chartData = [
    'project_status' => [
        'active' => $stats['active_projects'],
        'complete' => $stats['complete_projects'],
        'on_hold' => $stats['on_hold_projects'],
        'planned' => $stats['projects'] - $stats['active_projects'] - $stats['complete_projects'] - $stats['on_hold_projects']
    ],
    'budget' => [
        'total' => $stats['total_budget'],
        'paid' => $stats['total_paid'],
        'remaining' => max(0, $stats['total_budget'] - $stats['total_paid'])
    ]
];

// Get last 6 months of payment data
$paymentTrend = [];
try {
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(paid_date, '%Y-%m') as month, SUM(amount) as total
        FROM vendor_payments
        WHERE paid_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(paid_date, '%Y-%m')
        ORDER BY month ASC
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $paymentTrend['labels'][] = date('M', strtotime($row['month'] . '-01'));
        $paymentTrend['data'][] = (float)$row['total'];
    }
} catch (Exception $e) {
    $paymentTrend = ['labels' => [], 'data' => []];
}
$recentProjects = [];
$recentVendors = [];
$recentPayments = [];

try {
    $stmt = $pdo->query("SELECT id, name, status, client_name, total_budget FROM projects ORDER BY created_at DESC LIMIT 5");
    $recentProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

try {
    $stmt = $pdo->query("SELECT id, name, type, trade FROM vendors ORDER BY created_at DESC LIMIT 5");
    $recentVendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if (in_array($userRole, ['admin', 'pm', 'accounting'])) {
    try {
        $stmt = $pdo->query("SELECT p.id, p.amount, p.paid_date, p.description, v.name as vendor_name 
                            FROM vendor_payments p 
                            LEFT JOIN vendors v ON p.vendor_id = v.id 
                            ORDER BY p.paid_date DESC LIMIT 5");
        $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Welcome message based on time of day
$hour = (int)date('H');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

// Role title
$roleTitles = [
    'admin' => 'Administrator',
    'pm' => 'Project Manager',
    'estimator' => 'Estimator',
    'accounting' => 'Accounting',
    'viewer' => 'Team Member'
];

require_once 'partials/header.php';
?>

<!-- Personalized Header -->
<div class="dashboard-welcome" style="margin-bottom: 24px;">
    <h1 style="margin-bottom: 4px;"><?php echo $greeting ?>, <?php echo htmlspecialchars($currentUser); ?>!</h1>
    <p class="text-secondary"><?php echo $roleTitles[$userRole] ?? 'Team Member'; ?> Dashboard • <?php echo date('l, F j, Y'); ?></p>
</div>

<!-- Role-specific Stats -->
<?php if ($userRole === 'admin'): ?>
<div class="stats-grid">
  <div class="stat-card stat-card-blue">
    <div class="stat-value"><?php echo $stats['projects']; ?></div>
    <div class="stat-label">Total Projects</div>
  </div>
  
  <div class="stat-card stat-card-green">
    <div class="stat-value"><?php echo $stats['active_projects']; ?></div>
    <div class="stat-label">Active</div>
  </div>
  
  <div class="stat-card stat-card-yellow">
    <div class="stat-value"><?php echo $stats['on_hold_projects']; ?></div>
    <div class="stat-label">On Hold</div>
  </div>
  
  <div class="stat-card stat-card-green">
    <div class="stat-value"><?php echo $stats['complete_projects']; ?></div>
    <div class="stat-label">Complete</div>
  </div>
  
  <div class="stat-card stat-card-blue">
    <div class="stat-value"><?php echo $stats['vendors']; ?></div>
    <div class="stat-label">Vendors</div>
  </div>
  
  <div class="stat-card stat-card-blue">
    <div class="stat-value"><?php echo $stats['users']; ?></div>
    <div class="stat-label">Users</div>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card stat-card-green">
    <div class="stat-value">$<?php echo number_format($stats['total_budget'] / 1000, 0); ?>K</div>
    <div class="stat-label">Total Budget</div>
  </div>
  
  <div class="stat-card stat-card-green">
    <div class="stat-value">$<?php echo number_format($stats['total_paid'] / 1000, 0); ?>K</div>
    <div class="stat-label">Total Paid</div>
  </div>
  
  <div class="stat-card stat-card-blue">
    <div class="stat-value">$<?php echo number_format($stats['paid_this_month'] / 1000, 0); ?>K</div>
    <div class="stat-label">Paid This Month</div>
  </div>
  
  <div class="stat-card stat-card-blue">
    <div class="stat-value">$<?php echo number_format($stats['paid_this_year'] / 1000, 0); ?>K</div>
    <div class="stat-label">Paid This Year</div>
  </div>
  
  <div class="stat-card stat-card-yellow">
    <div class="stat-value"><?php echo $stats['budget_utilization']; ?>%</div>
    <div class="stat-label">Budget Used</div>
  </div>
  
  <div class="stat-card stat-card-green">
    <div class="stat-value"><?php echo $stats['completion_rate']; ?>%</div>
    <div class="stat-label">Completion Rate</div>
  </div>
</div>

<?php elseif ($userRole === 'pm'): ?>
<div class="stats-grid">
  <div class="stat-card stat-card-blue">
    <div class="stat-value"><?php echo $stats['projects']; ?></div>
    <div class="stat-label">Total Projects</div>
  </div>
  
  <div class="stat-card stat-card-green">
    <div class="stat-value"><?php echo $stats['active_projects']; ?></div>
    <div class="stat-label">Active</div>
  </div>
  
  <div class="stat-card stat-card-yellow">
    <div class="stat-value"><?php echo $stats['on_hold_projects']; ?></div>
    <div class="stat-label">On Hold</div>
  </div>
  
  <div class="stat-card stat-card-green">
    <div class="stat-value"><?php echo $stats['complete_projects']; ?></div>
    <div class="stat-label">Complete</div>
  </div>
  
  <div class="stat-card stat-card-blue">
    <div class="stat-value"><?php echo $stats['vendors']; ?></div>
    <div class="stat-label">Vendors</div>
  </div>
  
  <div class="stat-card stat-card-green">
    <div class="stat-value">$<?php echo number_format($stats['total_budget'] / 1000, 0); ?>K</div>
    <div class="stat-label">Total Budget</div>
  </div>
</div>

<?php elseif ($userRole === 'accounting'): ?>
<div class="stats-grid">
  <div class="stat-card stat-card-green">
    <div class="stat-value">$<?php echo number_format($stats['total_budget'] / 1000, 0); ?>K</div>
    <div class="stat-label">Project Budgets</div>
  </div>
  
  <div class="stat-card stat-card-green">
    <div class="stat-value">$<?php echo number_format($stats['total_paid'] / 1000, 0); ?>K</div>
    <div class="stat-label">Total Paid</div>
  </div>
  
  <div class="stat-card stat-card-blue">
    <div class="stat-value">$<?php echo number_format($stats['paid_this_month'] / 1000, 0); ?>K</div>
    <div class="stat-label">Paid This Month</div>
  </div>
  
  <div class="stat-card stat-card-blue">
    <div class="stat-value">$<?php echo number_format($stats['paid_this_year'] / 1000, 0); ?>K</div>
    <div class="stat-label">Paid This Year</div>
  </div>
  
  <div class="stat-card stat-card-yellow">
    <div class="stat-value"><?php echo $stats['budget_utilization']; ?>%</div>
    <div class="stat-label">Budget Used</div>
  </div>
  
  <div class="stat-card stat-card-blue">
    <div class="stat-value"><?php echo $stats['vendors']; ?></div>
    <div class="stat-label">Vendors</div>
  </div>
</div>

<?php elseif ($userRole === 'estimator'): ?>
<div class="stats-grid">
  <div class="stat-card stat-card-blue">
    <div class="stat-value"><?php echo $stats['projects']; ?></div>
    <div class="stat-label">Total Projects</div>
  </div>
  
  <div class="stat-card stat-card-green">
    <div class="stat-value"><?php echo $stats['active_projects']; ?></div>
    <div class="stat-label">Active</div>
  </div>
  
  <div class="stat-card stat-card-blue">
    <div class="stat-value">$<?php echo number_format($stats['total_budget'] / 1000, 0); ?>K</div>
    <div class="stat-label">Total Budget</div>
  </div>
</div>

<?php else: // viewer or unknown ?>
<div class="stats-grid">
  <div class="stat-card stat-card-blue">
    <div class="stat-value"><?php echo $stats['projects']; ?></div>
    <div class="stat-label">Projects</div>
  </div>
  
  <div class="stat-card stat-card-green">
    <div class="stat-value"><?php echo $stats['active_projects']; ?></div>
    <div class="stat-label">Active</div>
  </div>
</div>
<?php endif; ?>

<!-- Quick Stats with Chart -->
<?php if (in_array($userRole, ['admin', 'pm', 'accounting']) && ($preferences['show_charts'] ?? '1') === '1'): ?>
<div class="grid grid-2" style="margin-top: 24px;">
  <!-- Budget Overview Chart -->
  <div class="card">
    <div class="card-header">
      <h3>Budget Overview</h3>
    </div>
    <div style="padding: 16px; height: 220px; position: relative;">
      <canvas id="budgetChart"></canvas>
    </div>
  </div>
  
  <!-- Payment Trend -->
  <div class="card">
    <div class="card-header">
      <h3>Payment Activity</h3>
    </div>
    <div style="padding: 16px; height: 220px; position: relative;">
      <canvas id="paymentChart"></canvas>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Recent Data Widgets -->
<div class="grid grid-3" style="margin-top: 24px;">
<?php if (($preferences['show_recent_projects'] ?? '1') === '1' && !empty($recentProjects)): ?>
  <div class="card">
    <div class="card-header">
      <h3>Recent Projects</h3>
    </div>
    <div style="padding: 16px;">
      <ul class="recent-list">
        <?php foreach ($recentProjects as $project): ?>
        <li>
          <a href="projects.php?open=<?php echo $project['id']; ?>">
            <?php echo htmlspecialchars($project['name']); ?>
          </a>
          <span class="badge badge-<?php echo strtolower($project['status']); ?>"><?php echo $project['status']; ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<?php if (($preferences['show_recent_vendors'] ?? '1') === '1' && !empty($recentVendors)): ?>
  <div class="card">
    <div class="card-header">
      <h3>Recent Vendors</h3>
    </div>
    <div style="padding: 16px;">
      <ul class="recent-list">
        <?php foreach ($recentVendors as $vendor): ?>
        <li>
          <a href="vendors.php?open=<?php echo $vendor['id']; ?>">
            <?php echo htmlspecialchars($vendor['name']); ?>
          </a>
          <span class="text-secondary"><?php echo htmlspecialchars($vendor['trade'] ?? ''); ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<?php if (($preferences['show_recent_payments'] ?? '1') === '1' && !empty($recentPayments)): ?>
  <div class="card">
    <div class="card-header">
      <h3>Recent Payments</h3>
    </div>
    <div style="padding: 16px;">
      <ul class="recent-list">
        <?php foreach ($recentPayments as $payment): ?>
        <li>
          <span><?php echo htmlspecialchars($payment['vendor_name'] ?? 'Unknown'); ?></span>
          <span class="text-success">$<?php echo number_format($payment['amount'], 0); ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>
</div>

<!-- Dashboard Charts Script -->
<?php if (in_array($userRole, ['admin', 'pm', 'accounting']) && ($preferences['show_charts'] ?? '1') === '1'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') {
        console.error('Chart.js not loaded!');
        return;
    }
    
    const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
    const textColor = isDark ? '#F8FAFC' : '#0F172A';
    const gridColor = isDark ? '#334155' : '#E2E8F0';
    
    Chart.defaults.color = textColor;
    Chart.defaults.borderColor = gridColor;
    
    // Budget Overview (Bar)
    const budgetCtx = document.getElementById('budgetChart');
    if (budgetCtx) {
        new Chart(budgetCtx, {
            type: 'bar',
            data: {
                labels: ['Total Budget', 'Paid', 'Remaining'],
                datasets: [{
                    data: [
                        <?php echo $chartData['budget']['total']; ?>,
                        <?php echo $chartData['budget']['paid']; ?>,
                        <?php echo $chartData['budget']['remaining']; ?>
                    ],
                    backgroundColor: ['#3B82F6', '#22C55E', '#F59E0B'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + (value / 1000) + 'K';
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Payment Trend (Line)
    const paymentCtx = document.getElementById('paymentChart');
    if (paymentCtx) {
        new Chart(paymentCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($paymentTrend['labels'] ?? []); ?>,
                datasets: [{
                    label: 'Payments',
                    data: <?php echo json_encode($paymentTrend['data'] ?? []); ?>,
                    borderColor: '#F97316',
                    backgroundColor: 'rgba(249, 115, 22, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#F97316'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + (value / 1000) + 'K';
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>
<?php endif; ?>

<?php require_once 'partials/footer.php'; ?>
