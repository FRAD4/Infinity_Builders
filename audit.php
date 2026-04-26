<?php
/**
 * audit.php - Activity Log / Audit Trail
 * Admin view of all system changes
 */

$pageTitle = 'Activity Log';
$currentPage = 'audit';

require_once 'partials/init.php';
require_once 'includes/security.php';
require_once 'includes/audit.php';

// Only admins can view audit log
$userRole = $_SESSION['user_role'] ?? 'user';
if ($userRole !== 'admin') {
    http_response_code(403);
    die('Access denied. Only administrators can view the activity log.');
}

$csrf_token = csrf_token_generate();

// Handle filters
$filters = [
    'entity_type' => $_GET['entity_type'] ?? '',
    'action_type' => $_GET['action_type'] ?? '',
    'from_date' => !empty($_GET['from_date']) ? $_GET['from_date'] . ' 00:00:00' : '',
    'to_date' => !empty($_GET['to_date']) ? $_GET['to_date'] . ' 23:59:59' : '',
    'limit' => 100
];

// Load logs
$logs = get_audit_logs($filters);

// Get summary stats
$summary = [
    'today' => get_audit_logs(['from_date' => date('Y-m-d 00:00:00'), 'limit' => 1000]),
    'week' => get_audit_logs(['from_date' => date('Y-m-d H:i:s', strtotime('-7 days')), 'limit' => 1000]),
];

$todayCount = count($summary['today']);
$weekCount = count($summary['week']);

$actionCounts = [];
$entityCounts = [];
foreach ($summary['week'] as $log) {
    $action = $log['action_type'];
    $entity = $log['entity_type'];
    $actionCounts[$action] = ($actionCounts[$action] ?? 0) + 1;
    $entityCounts[$entity] = ($entityCounts[$entity] ?? 0) + 1;
}

require_once 'partials/header.php';
?>

<div class="page-header">
  <h1>Activity Log</h1>
  <p class="text-secondary">Complete audit trail of all system changes</p>
</div>

<!-- Summary Cards -->
<div class="stats-grid">
  <div class="stat-card stat-card-blue">
    <div class="stat-value"><?php echo $todayCount; ?></div>
    <div class="stat-label">Actions Today</div>
  </div>
  
  <div class="stat-card stat-card-green">
    <div class="stat-value"><?php echo $weekCount; ?></div>
    <div class="stat-label">This Week</div>
  </div>
  
  <?php foreach (array_slice($actionCounts, 0, 4, true) as $action => $count): ?>
  <div class="stat-card stat-card-blue">
    <div class="stat-value"><?php echo $count; ?></div>
    <div class="stat-label"><?php echo ucfirst($action); ?>s</div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters - Collapsible -->
<div style="margin-bottom: 20px;">
  <button type="button" class="btn btn-secondary" onclick="document.getElementById('audit-filters-panel').classList.toggle('hidden'); this.querySelector('i').classList.toggle('fa-chevron-down'); this.querySelector('i').classList.toggle('fa-chevron-up');">
    <i class="fa-solid fa-chevron-down"></i> Filters
    <?php if (!empty($filters['entity_type']) || !empty($filters['action_type']) || !empty($filters['from_date']) || !empty($filters['to_date'])): ?>
    <span style="background: var(--primary); color: white; border-radius: 50%; width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; margin-left: 6px;">
      <?php echo (int)(!empty($filters['entity_type'])) + (int)(!empty($filters['action_type'])) + (int)(!empty($filters['from_date'])) + (int)(!empty($filters['to_date'])); ?>
    </span>
    <?php endif; ?>
  </button>
</div>

<div id="audit-filters-panel" class="card filter-bar hidden" style="margin-bottom: 20px; padding: 12px 16px;">
  <form method="get" class="filter-form">
    <div class="filter-group">
      <select name="entity_type" class="filter-select">
        <option value="">All Types</option>
        <option value="projects" <?php echo $filters['entity_type'] === 'projects' ? 'selected' : ''; ?>>Projects</option>
        <option value="vendors" <?php echo $filters['entity_type'] === 'vendors' ? 'selected' : ''; ?>>Vendors</option>
        <option value="users" <?php echo $filters['entity_type'] === 'users' ? 'selected' : ''; ?>>Users</option>
        <option value="payments" <?php echo $filters['entity_type'] === 'payments' ? 'selected' : ''; ?>>Payments</option>
      </select>
      
      <select name="action_type" class="filter-select">
        <option value="">All Actions</option>
        <option value="create" <?php echo $filters['action_type'] === 'create' ? 'selected' : ''; ?>>Create</option>
        <option value="update" <?php echo $filters['action_type'] === 'update' ? 'selected' : ''; ?>>Update</option>
        <option value="delete" <?php echo $filters['action_type'] === 'delete' ? 'selected' : ''; ?>>Delete</option>
        <option value="login" <?php echo $filters['action_type'] === 'login' ? 'selected' : ''; ?>>Login</option>
        <option value="logout" <?php echo $filters['action_type'] === 'logout' ? 'selected' : ''; ?>>Logout</option>
      </select>
      
      <input type="date" name="from_date" value="<?php echo isset($_GET['from_date']) ? htmlspecialchars($_GET['from_date']) : ''; ?>" class="filter-date" placeholder="From">
      <input type="date" name="to_date" value="<?php echo isset($_GET['to_date']) ? htmlspecialchars($_GET['to_date']) : ''; ?>" class="filter-date" placeholder="To">
      
      <button type="submit" class="btn btn-sm"><i class="fa-solid fa-filter"></i></button>
      <a href="audit.php" class="btn btn-secondary btn-sm">Clear</a>
    </div>
  </form>
</div>

<!-- Audit Log Table -->
<div class="card">
  <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
    <h3>Recent Activity</h3>
    <span class="small-text"><?php echo count($logs); ?> records</span>
  </div>
  
  <?php if (empty($logs)): ?>
    <p class="muted" style="padding: 40px; text-align: center;">No activity found matching your filters.</p>
  <?php else: ?>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th style="width: 150px;">Timestamp</th>
            <th style="width: 100px;">User</th>
            <th style="width: 80px;">Action</th>
            <th style="width: 100px;">Type</th>
            <th>Details</th>
            <th style="width: 100px;">IP Address</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <?php 
              $changes = $log['changes'] ? json_decode($log['changes'], true) : null;
              $details = '';
              
              if ($log['action_type'] === 'update' && $changes) {
                $changes_summary = [];
                if (isset($changes['old']) && isset($changes['new'])) {
                  foreach ($changes['new'] as $key => $value) {
                    if ($changes['old'][$key] !== $value) {
                      $changes_summary[] = "$key: " . ($changes['old'][$key] ?? '—') . " → " . ($value ?? '—');
                    }
                  }
                }
                $details = implode(', ', array_slice($changes_summary, 0, 3));
                if (count($changes_summary) > 3) $details .= '...';
              } elseif ($log['action_type'] === 'delete' && $changes) {
                $details = 'Deleted: ' . ($changes['deleted']['name'] ?? 'Unknown');
              } elseif ($log['action_type'] === 'create') {
                $details = 'Created: ' . ($log['entity_name'] ?? 'New record');
              } elseif ($log['action_type'] === 'login') {
                $details = 'User logged in';
              } elseif ($log['action_type'] === 'logout') {
                $details = 'User logged out';
              } else {
                $details = $log['entity_name'] ?? '';
              }
            ?>
            <tr>
              <td class="small-text">
                <?php echo date('M j, Y', strtotime($log['created_at'])); ?><br>
                <?php echo date('g:i A', strtotime($log['created_at'])); ?>
              </td>
              <td>
                <strong><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></strong>
              </td>
              <td>
                <?php 
                  $actionClass = '';
                  $actionIcon = '';
                  if ($log['action_type'] === 'create') { $actionClass = 'status-active'; $actionIcon = 'fa-plus'; }
                  elseif ($log['action_type'] === 'update') { $actionClass = 'status-on-hold'; $actionIcon = 'fa-pen'; }
                  elseif ($log['action_type'] === 'delete') { $actionClass = 'status-complete'; $actionIcon = 'fa-trash'; }
                  elseif ($log['action_type'] === 'login') { $actionClass = 'status-active'; $actionIcon = 'fa-right-to-bracket'; }
                  elseif ($log['action_type'] === 'logout') { $actionClass = 'status-active'; $actionIcon = 'fa-right-from-bracket'; }
                ?>
                <span class="status-badge <?php echo $actionClass; ?>">
                  <i class="fa-solid <?php echo $actionIcon; ?>"></i>
                  <?php echo ucfirst($log['action_type']); ?>
                </span>
              </td>
              <td class="small-text"><?php echo ucfirst($log['entity_type']); ?></td>
              <td class="small-text" style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($details); ?>">
                <?php echo htmlspecialchars($details ?: '—'); ?>
              </td>
              <td class="small-text"><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once 'partials/footer.php'; ?>