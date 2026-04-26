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
// Time filter resolution (supports override via query param)
$timeFilterParam = $_GET['dashboard_time_filter'] ?? null;
$timeFilterValue = $timeFilterParam ?? ($preferences['dashboard_time_filter'] ?? '30d');
$timeDays = 0;
if (preg_match('/^(\\d+)d$/', $timeFilterValue, $m)) {
    $timeDays = (int)$m[1];
}
$timeClause = '';
$paymentsTimeClause = '';
if ($timeDays > 0) {
    $timeClause = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL $timeDays DAY)";
    $paymentsTimeClause = " AND paid_date >= DATE_SUB(CURDATE(), INTERVAL $timeDays DAY)";
}

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
// Build a lightweight activity log (latest 5 records) based on projects and payments within time window
$activityLog = [];
// Projects (latest 3)
try {
  $stmt = $pdo->query("SELECT id, name, created_at FROM projects WHERE 1 $timeClause ORDER BY created_at DESC LIMIT 3");
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $activityLog[] = ['time'=>$row['created_at'], 'type'=>'Project', 'description'=> 'Created project: '.$row['name']];
  }
} catch (Exception $e) {}
// Payments (latest 2)
try {
  $stmt = $pdo->query("SELECT p.paid_date, p.amount, v.name as vendor_name FROM vendor_payments p LEFT JOIN vendors v ON p.vendor_id = v.id WHERE 1 $paymentsTimeClause ORDER BY p.paid_date DESC LIMIT 2");
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $vendor = $row['vendor_name'] ?? '';
    $activityLog[] = ['time'=>$row['paid_date'], 'type'=>'Payment', 'description'=> 'Paid $'.number_format(($row['amount'] ?? 0),0).' to '.$vendor];
  }
} catch (Exception $e) {}
// Sort by time desc
usort($activityLog, function($a,$b){ return strcmp($b['time'], $a['time']); });
// Keep top 5
$activityLog = array_slice($activityLog, 0, 5);

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
        'planned' => max(0, $stats['projects'] - $stats['active_projects'] - $stats['complete_projects'] - $stats['on_hold_projects'])
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

<!-- Personalization Controls - Toggle Pills -->
<style>
.dashboard-toggles {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  padding: 12px 16px;
  background: var(--bg-card);
  border-radius: 12px;
  margin: 12px 0;
}
.toggle-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 20px;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  cursor: pointer;
  font-size: 13px;
  color: var(--text-secondary);
  transition: all 0.2s ease;
  user-select: none;
}
.toggle-pill:hover {
  background: var(--bg-hover);
  color: var(--text-primary);
}
.toggle-pill.active {
  background: var(--primary);
  border-color: var(--primary);
  color: white;
}
.toggle-pill input { display: none; }
.toggle-pill .toggle-icon { font-size: 14px; }
.time-filter-wrapper {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 8px;
}
.time-filter-wrapper label {
  font-size: 13px;
  color: var(--text-secondary);
}
</style>

<div class="dashboard-toggles">
  <label class="toggle-pill<?php echo ($preferences['dashboard_show_projects'] ?? '1') === '1' ? ' active' : ''; ?>">
    <input type="checkbox" id="pref-projects" <?php echo ($preferences['dashboard_show_projects'] ?? '1') === '1' ? 'checked' : ''; ?>>
    <span class="toggle-icon"><i class="fa-solid fa-folder-open"></i></span>
    <span>Projects</span>
  </label>
  
  <label class="toggle-pill<?php echo ($preferences['dashboard_show_permits'] ?? '1') === '1' ? ' active' : ''; ?>">
    <input type="checkbox" id="pref-permits" <?php echo ($preferences['dashboard_show_permits'] ?? '1') === '1' ? 'checked' : ''; ?>>
    <span class="toggle-icon"><i class="fa-solid fa-file-contract"></i></span>
    <span>Permits</span>
  </label>
  
  <label class="toggle-pill<?php echo ($preferences['dashboard_show_financial'] ?? '1') === '1' ? ' active' : ''; ?>">
    <input type="checkbox" id="pref-financial" <?php echo ($preferences['dashboard_show_financial'] ?? '1') === '1' ? 'checked' : ''; ?>>
    <span class="toggle-icon"><i class="fa-solid fa-dollar-sign"></i></span>
    <span>Financial</span>
  </label>
  
  <label class="toggle-pill<?php echo ($preferences['show_charts'] ?? '1') === '1' ? ' active' : ''; ?>">
    <input type="checkbox" id="pref-charts" <?php echo ($preferences['show_charts'] ?? '1') === '1' ? 'checked' : ''; ?>>
    <span class="toggle-icon"><i class="fa-solid fa-chart-pie"></i></span>
    <span>Charts</span>
  </label>
  
  <label class="toggle-pill<?php echo ($preferences['dashboard_show_activity'] ?? '1') === '1' ? ' active' : ''; ?>">
    <input type="checkbox" id="pref-activity" <?php echo ($preferences['dashboard_show_activity'] ?? '1') === '1' ? 'checked' : ''; ?>>
    <span class="toggle-icon"><i class="fa-solid fa-clock"></i></span>
    <span>Activity</span>
  </label>
  
  <div class="time-filter-wrapper">
    <label>Time:</label>
    <select id="timeFilter" class="filter-select" style="min-width:110px;">
      <option value="7d" <?php if (($preferences['dashboard_time_filter'] ?? '30d') === '7d') echo 'selected'; ?>>7 Days</option>
      <option value="30d" <?php if (($preferences['dashboard_time_filter'] ?? '30d') === '30d') echo 'selected'; ?>>30 Days</option>
      <option value="90d" <?php if (($preferences['dashboard_time_filter'] ?? '30d') === '90d') echo 'selected'; ?>>90 Days</option>
      <option value="all" <?php if (($preferences['dashboard_time_filter'] ?? '30d') === 'all') echo 'selected'; ?>>All Time</option>
    </select>
  </div>
</div>

<script>
// Toggle pills - instant save without reload
(function(){
  var toggles = [
    { id: 'pref-projects', key: 'dashboard_show_projects' },
    { id: 'pref-permits', key: 'dashboard_show_permits' },
    { id: 'pref-financial', key: 'dashboard_show_financial' },
    { id: 'pref-charts', key: 'show_charts' },
    { id: 'pref-activity', key: 'dashboard_show_activity' }
  ];
  
  toggles.forEach(function(t){
    var checkbox = document.getElementById(t.id);
    var pill = checkbox ? checkbox.parentElement : null;
    
    if (checkbox && pill) {
      checkbox.addEventListener('change', function(){
        // Instant pill UI update
        if (this.checked) {
          pill.classList.add('active');
        } else {
          pill.classList.remove('active');
        }
        
        // Save to server (non-blocking, no reload)
        var xhr = new XMLHttpRequest();
        xhr.open('POST','includes/save-preference.php',true);
        xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
        xhr.send('key='+encodeURIComponent(t.key)+'&value='+(this.checked ? '1' : '0'));
      });
    }
  });
  
  // Time filter - needs reload for data refresh
  var tf = document.getElementById('timeFilter');
  if (tf) tf.addEventListener('change', function(){
    var url = new URL(window.location.href);
    url.searchParams.set('dashboard_time_filter', this.value);
    window.location.href = url.toString();
  });
})();
</script>
</div>

<!-- Dashboard Redesign: Projects, Permits, Financial, Charts, Activity -->
<?php if (($preferences['dashboard_show_projects'] ?? '1') === '1'): ?>
<div class="dashboard-section" style="margin: 20px 0 12px;">
    <h3 class="section-title">Projects</h3>
</div>
<div class="grid grid-4" style="gap:12px;">
  <?php
  $projectCounts = ['Active'=>0,'Starting Soon'=>0,'Waiting on Permit'=>0,'Signed'=>0];
  $stmt = $pdo->query("SELECT status, COUNT(*) AS c FROM projects WHERE 1 $timeClause GROUP BY status");
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
     $s = $row['status'];
     if (strcasecmp($s, 'Active')===0) $projectCounts['Active'] = (int)$row['c'];
     if (strcasecmp($s, 'Starting Soon')===0) $projectCounts['Starting Soon'] = (int)$row['c'];
     if (strcasecmp($s, 'Waiting on Permit')===0) $projectCounts['Waiting on Permit'] = (int)$row['c'];
     if (strcasecmp($s, 'Signed')===0) $projectCounts['Signed'] = (int)$row['c'];
  }
  ?>
  <div class="stat-card" style="padding:14px;">
    <div class="stat-value"><?php echo $projectCounts['Active']; ?></div>
    <div class="stat-label">Active</div>
  </div>
  <div class="stat-card" style="padding:14px;">
    <div class="stat-value"><?php echo $projectCounts['Starting Soon']; ?></div>
    <div class="stat-label">Starting Soon</div>
  </div>
  <div class="stat-card" style="padding:14px;">
    <div class="stat-value"><?php echo $projectCounts['Waiting on Permit']; ?></div>
    <div class="stat-label">Waiting on Permit</div>
  </div>
  <?php if ($projectCounts['Signed'] > 0): ?>
  <div class="stat-card" style="padding:14px;">
    <div class="stat-value"><?php echo $projectCounts['Signed']; ?></div>
    <div class="stat-label">Signed</div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (($preferences['dashboard_show_permits'] ?? '1') === '1'): ?>
<div class="dashboard-section" style="margin: 20px 0 12px;">
    <h3 class="section-title">Permits</h3>
</div>
<div class="grid grid-3" style="gap:12px;">
  <?php
  $permCounts = ['Approved'=>0,'In Review'=>0,'Correction Needed'=>0];
  $stmt = $pdo->query("SELECT status, COUNT(*) AS c FROM permits WHERE 1 $timeClause GROUP BY status");
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
     $s = $row['status'];
     $lc = strtolower(preg_replace('/[^a-z0-9_]/','_', $s));
     if (strpos($lc, 'approved') !== false) $permCounts['Approved'] = (int)$row['c'];
     if (strpos($lc, 'in_review') !== false) $permCounts['In Review'] = (int)$row['c'];
     if (strpos($lc, 'correction_needed') !== false) $permCounts['Correction Needed'] = (int)$row['c'];
  }
  ?>
  <div class="stat-card" style="padding:14px;">
    <div class="stat-value"><?php echo $permCounts['Approved']; ?></div>
    <div class="stat-label">Approved</div>
  </div>
  <div class="stat-card" style="padding:14px;">
    <div class="stat-value"><?php echo $permCounts['In Review']; ?></div>
    <div class="stat-label">In Review</div>
  </div>
  <div class="stat-card" style="padding:14px;">
    <div class="stat-value"><?php echo $permCounts['Correction Needed']; ?></div>
    <div class="stat-label">Correction Needed</div>
  </div>
</div>
<?php endif; ?>

<?php if (($preferences['dashboard_show_financial'] ?? '1') === '1'): ?>
<div class="dashboard-section" style="margin: 20px 0 12px;">
    <h3 class="section-title">Financial Overview</h3>
</div>
<div class="grid grid-3" style="gap:12px;">
  <?php
  $budgetTotal = 0; $moneyOut = 0; $stmt = $pdo->query("SELECT COALESCE(SUM(total_budget),0) AS t FROM projects WHERE 1 $timeClause"); $budgetTotal = (float)($stmt->fetch(PDO::FETCH_ASSOC)['t'] ?? 0);
  $stmt2 = $pdo->query("SELECT COALESCE(SUM(amount),0) AS t FROM vendor_payments WHERE 1 $paymentsTimeClause"); $moneyOut = (float)($stmt2->fetch(PDO::FETCH_ASSOC)['t'] ?? 0);
  $completionRate = ($budgetTotal > 0) ? (int)round(($moneyOut / $budgetTotal) * 100) : 0;
  ?>
  <div class="stat-card" style="padding:14px;">
    <div class="stat-value">$<?php echo number_format($budgetTotal / 1000, 0); ?>K</div>
    <div class="stat-label">Total Agreement</div>
  </div>
  <div class="stat-card" style="padding:14px;">
    <div class="stat-value">$<?php echo number_format($moneyOut / 1000, 0); ?>K</div>
    <div class="stat-label">Money Out</div>
  </div>
  <div class="stat-card" style="padding:14px;">
    <div class="stat-value"><?php echo $completionRate; ?>%</div>
    <div class="stat-label">Completion Rate</div>
  </div>
</div>
<?php endif; ?>

<?php if (($preferences['show_charts'] ?? '1') === '1'): ?>
<div class="dashboard-section" style="margin: 20px 0 12px;">
    <h3 class="section-title">Charts</h3>
</div>
<div class="grid grid-2" style="gap:12px;">
  <div class="card"><div class="card-header"><h3>Budget Overview</h3></div><div style="padding:16px; height:240px; position:relative;"><canvas id="budgetChart"></canvas></div></div>
  <div class="card"><div class="card-header"><h3>Payment Activity</h3></div><div style="padding:16px; height:240px; position:relative;"><canvas id="paymentChart"></canvas></div></div>
</div>
<?php endif; ?>

<?php if (($preferences['dashboard_show_activity'] ?? '1') === '1'): ?>
<div class="dashboard-section" style="margin: 20px 0 12px;">
    <h3 class="section-title">Activity Log</h3>
</div>
<div class="card" style="margin-top:0;">
  <div class="card-header"><h3></h3></div>
  <div class="card-body" style="padding:16px;">
    <table class="activities-table" style="width:100%; border-collapse: collapse;">
      <thead><tr><th>Time</th><th>Type</th><th>Description</th></tr></thead>
      <tbody>
        <?php foreach ($activityLog as $al): ?>
        <tr><td><?php echo htmlspecialchars($al['time']); ?></td><td><?php echo htmlspecialchars($al['type']); ?></td><td><?php echo htmlspecialchars($al['description']); ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<!-- End redesigned sections -->

<!-- Charts Initialization -->
<script>
(function() {
    function initCharts() {
        if (typeof Chart === 'undefined') {
            console.error('Chart.js not loaded!');
            return;
        }
        
        // Resolve theme
        const htmlEl = document.documentElement;
        let theme = htmlEl.getAttribute('data-theme');
        if (theme === 'system') {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            theme = prefersDark ? 'dark' : 'light';
        }
        const isDark = theme !== 'light';
        
        const darkText = '#F8FAFC', darkGrid = '#334155', darkTick = '#94A3B8';
        const lightText = '#0F172A', lightGrid = '#E2E8F0', lightTick = '#64748B';
        const textColor = isDark ? darkText : lightText;
        const gridColor = isDark ? darkGrid : lightGrid;
        const tickColor = isDark ? darkTick : lightTick;
        
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
                        data: [<?php echo $budgetTotal ?? 0; ?>, <?php echo $moneyOut ?? 0; ?>, <?php echo max(0, ($budgetTotal ?? 0) - ($moneyOut ?? 0)); ?>],
                        backgroundColor: ['#3B82F6', '#22C55E', '#F59E0B'],
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: tickColor }, grid: { color: gridColor } },
                        y: { beginAtZero: true, ticks: { color: tickColor, callback: function(v) { return '$' + (v/1000) + 'K'; } }, grid: { color: gridColor } }
                    }
                }
            });
        }
        
        // Payment Activity (Line)
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
                        backgroundColor: isDark ? 'rgba(249,115,22,0.15)' : 'rgba(249,115,22,0.25)',
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
                        x: { ticks: { color: tickColor }, grid: { color: gridColor } },
                        y: { beginAtZero: true, ticks: { color: tickColor, callback: function(v) { return '$' + (v/1000) + 'K'; } }, grid: { color: gridColor } }
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
