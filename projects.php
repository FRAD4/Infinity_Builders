<?php
/**
 * projects.php - Project management with edit/delete
 */

$pageTitle = 'Projects';
$currentPage = 'projects';

require_once 'partials/init.php';

// Role-based access: require at least 'pm' role to access projects
$userRole = $_SESSION['user_role'] ?? 'user';
$allowedRoles = ['admin', 'pm', 'estimator'];
if (!in_array($userRole, $allowedRoles, true)) {
    http_response_code(403);
    die('Access denied. Only project team members can access this page.');
}

$message = '';
$projects = [];
$projectsError = '';

// Generate CSRF token
require_once 'includes/security.php';
require_once 'includes/audit.php';
$csrf_token = csrf_token_generate();

// Handle filters
$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'status' => $_GET['status'] ?? '',
];

// Handle direct open from search (open=project_id)
// Also filter by project name when opening directly
$openProjectId = $_GET['open'] ?? null;

// If opening directly, search by that project name to make sure it's visible
if ($openProjectId) {
    // Get project name to filter by
    try {
        $stmt = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
        $stmt->execute([$openProjectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $filters['search'] = $row['name'];
        }
    } catch (Exception $e) {}
}

// Build query with filters
$where = [];
$params = [];

if (!empty($filters['search'])) {
    $where[] = "(name LIKE ? OR client_name LIKE ?)";
    $searchTerm = '%' . $filters['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($filters['status']) && $filters['status'] !== 'all') {
    $where[] = "status = ?";
    $params[] = $filters['status'];
}

$sql = "SELECT id, name, client_name, status, total_budget, start_date, end_date, notes, created_at FROM projects";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY created_at DESC";

// Load projects
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $projectsError = "Projects table not found. Check schema.";
}

// Load ALL projects for metrics (without filters)
try {
    $allProjects = $pdo->query("SELECT id, name, client_name, status, total_budget, start_date, end_date, notes, created_at FROM projects ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $allProjects = $projects;
}

// Calculate project metrics (using all projects for correct stats)
$projectStats = [
    'total' => count($allProjects),
    'active' => 0,
    'planned' => 0,
    'on_hold' => 0,
    'complete' => 0,
    'total_budget' => 0,
    'total_paid' => 0,
    'alerts' => []
];

foreach ($allProjects as $p) {
    $status = strtolower($p['status'] ?? '');
    if ($status === 'active') $projectStats['active']++;
    elseif ($status === 'planned') $projectStats['planned']++;
    elseif ($status === 'on hold' || $status === 'on_hold') $projectStats['on_hold']++;
    elseif ($status === 'complete') $projectStats['complete']++;
    
    $projectStats['total_budget'] += (float)($p['total_budget'] ?? 0);
    
    // Check for alerts
    $daysOnHold = 0;
    if ($status === 'on hold' && !empty($p['start_date'])) {
        $daysOnHold = (strtotime('now') - strtotime($p['start_date'])) / (60*60*24);
    }
    
    // Alert: On Hold > 30 days
    if ($status === 'on hold' && $daysOnHold > 30) {
        $projectStats['alerts'][] = [
            'type' => 'warning',
            'project' => $p['name'],
            'message' => "On Hold for " . round($daysOnHold) . " days"
        ];
    }
    
    // Alert: No budget but not complete
    if (empty($p['total_budget']) && $status !== 'complete' && $status !== 'on hold') {
        $projectStats['alerts'][] = [
            'type' => 'info',
            'project' => $p['name'],
            'message' => "No budget set"
        ];
    }
    
    // Alert: End date passed but not complete
    if (!empty($p['end_date']) && strtotime($p['end_date']) < strtotime('now') && $status !== 'complete') {
        $projectStats['alerts'][] = [
            'type' => 'danger',
            'project' => $p['name'],
            'message' => "Past target completion date"
        ];
    }
    
    // Alert: Planned > 90 days without starting
    if ($status === 'planned' && !empty($p['created_at'])) {
        $daysWaiting = (strtotime('now') - strtotime($p['created_at'])) / (60*60*24);
        if ($daysWaiting > 90) {
            $projectStats['alerts'][] = [
                'type' => 'info',
                'project' => $p['name'],
                'message' => "Planned for " . round($daysWaiting) . " days without starting"
            ];
        }
    }
}

// Get total payments
try {
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM vendor_payments");
    $projectStats['total_paid'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
} catch (Exception $e) {}

// Handle POST forms
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!csrf_token_validate($submitted_token)) {
        $message = "Invalid request. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';

        // Create project
        if ($action === 'create_project') {
            $name        = trim($_POST['name'] ?? '');
            $client_name = trim($_POST['client_name'] ?? '');
            $status      = $_POST['status'] ?? 'Planned';
            $budget      = $_POST['total_budget'] !== '' ? (float)$_POST['total_budget'] : 0;
            $start_date  = $_POST['start_date'] ?? null;
            $end_date    = $_POST['end_date'] ?? null;
            $notes       = trim($_POST['notes'] ?? '');

            if ($name === '') {
                $message = "Project name is required.";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO projects 
                        (name, client_name, status, total_budget, start_date, end_date, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $name,
                        $client_name ?: null,
                        $status,
                        $budget,
                        $start_date ?: null,
                        $end_date ?: null,
                        $notes ?: null
                    ]);
                    
                    // Audit log
                    audit_log('create', 'projects', $pdo->lastInsertId(), $name);
                    
                    $message = "Project created.";
                } catch (Exception $e) {
                    $message = "Error creating project: " . $e->getMessage();
                }
            }
        } // end create_project

        // Update project
        if ($action === 'update_project') {
            $id          = (int)($_POST['project_id'] ?? 0);
            $name        = trim($_POST['edit_name'] ?? '');
            $client_name = trim($_POST['edit_client_name'] ?? '');
            $status      = $_POST['edit_status'] ?? 'Planned';
            $budget      = $_POST['edit_budget'] !== '' ? (float)$_POST['edit_budget'] : 0;
            $start_date  = $_POST['edit_start_date'] ?: null;
            $end_date    = $_POST['edit_end_date'] ?: null;
            $notes       = trim($_POST['edit_notes'] ?? '');

            if ($id <= 0 || $name === '') {
                $message = "Invalid project data.";
            } else {
                try {
                    // Get old values for audit
                    $oldStmt = $pdo->prepare("SELECT name, client_name, status, total_budget, start_date, end_date, notes FROM projects WHERE id = ?");
                    $oldStmt->execute([$id]);
                    $oldData = $oldStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $stmt = $pdo->prepare("UPDATE projects SET 
                        name = ?, client_name = ?, status = ?, total_budget = ?, 
                        start_date = ?, end_date = ?, notes = ? WHERE id = ?");
                    $stmt->execute([
                        $name,
                        $client_name ?: null,
                        $status,
                        $budget,
                        $start_date ?: null,
                        $end_date ?: null,
                        $notes ?: null,
                        $id
                    ]);
                    
                    // Audit log with changes
                    audit_log('update', 'projects', $id, $name, [
                        'old' => $oldData,
                        'new' => [
                            'name' => $name,
                            'client_name' => $client_name,
                            'status' => $status,
                            'total_budget' => $budget,
                            'start_date' => $start_date,
                            'end_date' => $end_date,
                            'notes' => $notes
                        ]
                    ]);
                    
                    $message = "Project updated.";
                } catch (Exception $e) {
                    $message = "Error updating project: " . $e->getMessage();
                }
            }
        } // end update_project

        // Delete project
        if ($action === 'delete_project') {
            $id = (int)($_POST['project_id'] ?? 0);
            if ($id > 0) {
                try {
                    // Get project info for audit before deleting
                    $oldStmt = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
                    $oldStmt->execute([$id]);
                    $oldData = $oldStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Audit log
                    audit_log('delete', 'projects', $id, $oldData['name'] ?? 'Unknown', ['deleted' => $oldData]);
                    
                    $message = "Project deleted.";
                } catch (Exception $e) {
                    $message = "Error deleting project: " . $e->getMessage();
                }
            }
        } // end delete_project

        // Bulk delete
        if ($action === 'bulk_delete') {
            $ids = array_filter(array_map('intval', (array)($_POST['project_ids'] ?? [])), fn($v) => $v > 0);
            if (!empty($ids)) {
                try {
                    $count = count($ids);
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    
                    // Get names for audit
                    $nameStmt = $pdo->prepare("SELECT id, name FROM projects WHERE id IN ($placeholders)");
                    $nameStmt->execute($ids);
                    $deletedProjects = $nameStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $stmt = $pdo->prepare("DELETE FROM projects WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                    
                    // Audit log
                    audit_log('delete', 'projects', null, "$count projects bulk delete", [
                        'deleted_projects' => $deletedProjects
                    ]);
                    
                    $message = $count . " project(s) deleted.";
                } catch (Exception $e) {
                    $message = "Error deleting projects: " . $e->getMessage();
                }
            }
        } // end bulk_delete
    } // end CSRF valid
} // end POST

require_once 'partials/header.php';
?>

<?php if ($message): ?>
  <p class="muted" style="margin-bottom:12px;"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<div class="grid">
  <!-- Quick Stats + Add Button -->
  <!-- Stats Cards -->
<div class="stats-grid">
  <div class="stat-card stat-card-blue">
    <div class="stat-value"><?php echo $projectStats['total']; ?></div>
    <div class="stat-label">Total Projects</div>
  </div>
  
  <div class="stat-card stat-card-green">
    <div class="stat-value"><?php echo $projectStats['active']; ?></div>
    <div class="stat-label">Active</div>
  </div>
  
  <div class="stat-card stat-card-blue">
    <div class="stat-value"><?php echo $projectStats['planned']; ?></div>
    <div class="stat-label">Planned</div>
  </div>
  
  <div class="stat-card stat-card-yellow">
    <div class="stat-value"><?php echo $projectStats['on_hold']; ?></div>
    <div class="stat-label">On Hold</div>
  </div>
  
  <div class="stat-card stat-card-green">
    <div class="stat-value"><?php echo $projectStats['complete']; ?></div>
    <div class="stat-label">Complete</div>
  </div>
  
  <div class="stat-card stat-card-green">
    <div class="stat-value">$<?php echo number_format($projectStats['total_budget'] / 1000, 0); ?>K</div>
    <div class="stat-label">Total Budget</div>
  </div>
</div>

<?php if (!empty($projectStats['alerts'])): ?>
<!-- Alerts Section -->
<div class="card" style="margin-top: 20px; border-left: 4px solid var(--warning);">
  <div class="card-header">
    <h3><i class="fa-solid fa-triangle-exclamation" style="color: var(--warning);"></i> Project Alerts</h3>
  </div>
  <div class="card-subtitle">Projects that need attention</div>
  
  <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 12px;">
    <?php foreach (array_slice($projectStats['alerts'], 0, 10) as $alert): ?>
      <div style="display: flex; align-items: center; gap: 12px; padding: 10px 12px; background: var(--bg-elevated); border-radius: 6px;">
        <?php if ($alert['type'] === 'danger'): ?>
          <i class="fa-solid fa-circle-exclamation" style="color: var(--danger);"></i>
        <?php elseif ($alert['type'] === 'warning'): ?>
          <i class="fa-solid fa-circle-exclamation" style="color: var(--warning);"></i>
        <?php else: ?>
          <i class="fa-solid fa-circle-info" style="color: var(--info);"></i>
        <?php endif; ?>
        <div style="flex: 1;">
          <strong><?php echo htmlspecialchars($alert['project']); ?></strong>
          <span class="small-text" style="color: var(--text-secondary); margin-left: 8px;">
            <?php echo htmlspecialchars($alert['message']); ?>
          </span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Filters - Compact horizontal layout -->
<div class="card filter-bar" style="margin-top: 20px; padding: 12px 16px;">
  <form method="get" class="filter-form">
    <div class="filter-group">
      <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" placeholder="Search projects..." class="filter-input">
      
      <select name="status" class="filter-select">
        <option value="">All</option>
        <option value="Planned" <?php echo ($filters['status'] ?? '') === 'Planned' ? 'selected' : ''; ?>>Planned</option>
        <option value="Active" <?php echo ($filters['status'] ?? '') === 'Active' ? 'selected' : ''; ?>>Active</option>
        <option value="On Hold" <?php echo ($filters['status'] ?? '') === 'On Hold' ? 'selected' : ''; ?>>On Hold</option>
        <option value="Complete" <?php echo ($filters['status'] ?? '') === 'Complete' ? 'selected' : ''; ?>>Complete</option>
      </select>
      
      <button type="submit" class="btn btn-sm"><i class="fa-solid fa-filter"></i></button>
      <?php if (!empty($filters['search']) || !empty($filters['status'])): ?>
      <a href="projects.php" class="btn btn-secondary btn-sm">Clear</a>
      <?php endif; ?>
      
      <span class="filter-count">
        Showing <?php echo count($projects); ?> of <?php echo count($allProjects); ?>
      </span>
    </div>
  </form>
</div>

<!-- Header + Add Button -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
      <div>
        <h3>All Projects</h3>
        <div class="card-subtitle">Double-click row for details, click Edit for modifications</div>
      </div>
      <div style="display: flex; gap: 8px;">
        <a href="export-projects.php" class="btn btn-secondary">
          <i class="fa-solid fa-download"></i> Export CSV
        </a>
        <?php 
          $edit_roles = ['admin', 'pm'];
          if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $edit_roles)): 
        ?>
        <button type="button" class="btn" id="open-create-project-modal">
          <i class="fa-solid fa-plus"></i> New Project
        </button>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($projectsError): ?>
      <p class="muted"><?php echo htmlspecialchars($projectsError); ?></p>
    <?php else: ?>
      <form method="post" id="projects-table-form">
        <input type="hidden" name="action" id="projects-table-action" value="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <?php 
                  $edit_roles = ['admin', 'pm'];
                  if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $edit_roles)): 
                ?>
                <th style="width:32px;"><input type="checkbox" id="select-all-projects"></th>
                <?php endif; ?>
                <th>Project</th>
                <th>Client</th>
                <th>Status</th>
                <th>Budget</th>
                <th>Start</th>
                <th>End</th>
                <?php 
                  $edit_roles = ['admin', 'pm'];
                  if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $edit_roles)): 
                ?>
                <th style="text-align:right;">Actions</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php if (!$projects): ?>
                <tr>
                  <td colspan="8">
                    <div class="empty-state">
                      <div class="empty-state-icon">🏗️</div>
                      <h3>No projects yet</h3>
                      <p>Get started by creating your first project</p>
                      <?php if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'pm'])): ?>
                      <button type="button" class="btn" id="open-create-project-modal-empty">
                        <i class="fa-solid fa-plus"></i> Create Project
                      </button>
                      <script>
                        document.getElementById('open-create-project-modal-empty')?.addEventListener('click', function() {
                          document.getElementById('open-create-project-modal')?.click();
                        });
                      </script>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($projects as $p): ?>
                  <tr class="project-row"
                      data-id="<?php echo (int)$p['id']; ?>"
                      data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>"
                      data-client="<?php echo htmlspecialchars($p['client_name'] ?? '', ENT_QUOTES); ?>"
                      data-status="<?php echo htmlspecialchars($p['status'] ?? '', ENT_QUOTES); ?>"
                      data-budget="<?php echo htmlspecialchars($p['total_budget'] ?? '', ENT_QUOTES); ?>"
                      data-start="<?php echo htmlspecialchars($p['start_date'] ?? '', ENT_QUOTES); ?>"
                      data-end="<?php echo htmlspecialchars($p['end_date'] ?? '', ENT_QUOTES); ?>"
                      data-notes="<?php echo htmlspecialchars($p['notes'] ?? '', ENT_QUOTES); ?>">
                    <?php 
                      $edit_roles = ['admin', 'pm'];
                      if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $edit_roles)): 
                    ?>
                    <td><input type="checkbox" class="project-checkbox" name="project_ids[]" value="<?php echo (int)$p['id']; ?>"></td>
                    <?php endif; ?>
                    <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($p['client_name'] ?? '—'); ?></td>
                    <td>
                      <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $p['status'] ?? 'planned')); ?>">
                        <?php echo htmlspecialchars($p['status'] ?? ''); ?>
                      </span>
                    </td>
                    <td>
                      <?php
                        $val = $p['total_budget'];
                        echo $val !== null ? '$' . number_format((float)$val, 2) : '—';
                      ?>
                    </td>
                    <td><?php echo htmlspecialchars($p['start_date'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($p['end_date'] ?? '—'); ?></td>
                    <?php 
                      $edit_roles = ['admin', 'pm'];
                      if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $edit_roles)): 
                    ?>
                    <td style="text-align:right;">
                      <button type="button" class="btn-secondary edit-project-btn" style="padding:4px 10px;font-size:12px;border-radius:999px;"
                          data-id="<?php echo (int)$p['id']; ?>"
                          data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>"
                          data-client="<?php echo htmlspecialchars($p['client_name'] ?? '', ENT_QUOTES); ?>"
                          data-status="<?php echo htmlspecialchars($p['status'] ?? '', ENT_QUOTES); ?>"
                          data-budget="<?php echo htmlspecialchars($p['total_budget'] ?? '', ENT_QUOTES); ?>"
                          data-start="<?php echo htmlspecialchars($p['start_date'] ?? '', ENT_QUOTES); ?>"
                          data-end="<?php echo htmlspecialchars($p['end_date'] ?? '', ENT_QUOTES); ?>"
                          data-notes="<?php echo htmlspecialchars($p['notes'] ?? '', ENT_QUOTES); ?>">
                        Edit
                      </button>
                    </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<!-- PROJECT DETAIL MODAL (view-only) -->
<div id="project-detail-modal" class="modal-overlay">
  <div class="modal-content modal-large">
    <div class="modal-header">
      <div>
        <h2 id="detail-project-name">Project Details</h2>
        <div id="detail-project-sub" class="modal-subtitle"></div>
      </div>
      <button type="button" id="project-detail-close" class="modal-close">&times;</button>
    </div>
    
    <!-- Tabs -->
    <div class="modal-tabs">
      <button type="button" class="modal-tab active" data-tab="overview">Overview</button>
      <button type="button" class="modal-tab" data-tab="notes">Notes</button>
      <button type="button" class="modal-tab" data-tab="timeline">Timeline</button>
      <button type="button" class="modal-tab" data-tab="tasks">Tasks</button>
      <button type="button" class="modal-tab" data-tab="documents">Documents</button>
    </div>
    
    <!-- Tab Content -->
    <div class="modal-tab-content">
      <div id="tab-overview" class="tab-pane active">
        <div id="detail-project-info"></div>
      </div>
      <div id="tab-notes" class="tab-pane">
        <div id="detail-project-notes" class="detail-notes"></div>
      </div>
      <div id="tab-timeline" class="tab-pane">
        <div id="detail-project-timeline" class="detail-timeline"></div>
      </div>
      <div id="tab-tasks" class="tab-pane">
        <div id="detail-project-tasks" class="detail-tasks">
          <div class="tasks-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div class="tasks-stats" id="tasks-stats"></div>
            <?php 
              $manage_roles = ['admin', 'pm', 'accounting', 'estimator'];
              if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $manage_roles)): 
            ?>
            <button type="button" id="add-task-btn" class="btn btn-primary btn-sm">
              <i class="fa-solid fa-plus"></i> Add Task
            </button>
            <?php endif; ?>
          </div>
          <div id="tasks-list" class="tasks-list"></div>
        </div>
      </div>
      <div id="tab-documents" class="tab-pane">
        <div id="detail-project-documents" class="detail-documents">
          <div class="documents-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div class="documents-count" id="documents-count"></div>
            <?php 
              $manage_roles = ['admin', 'pm', 'accounting', 'estimator'];
              if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $manage_roles)): 
            ?>
            <button type="button" id="add-document-btn" class="btn btn-primary btn-sm">
              <i class="fa-solid fa-upload"></i> Upload Document
            </button>
            <?php endif; ?>
          </div>
          <div id="documents-list" class="documents-list"></div>
        </div>
      </div>
    </div>
    
    <?php 
      $edit_roles = ['admin', 'pm'];
      if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $edit_roles)): 
    ?>
    <div class="modal-actions">
      <button type="button" id="detail-edit-btn" class="btn btn-primary">Edit Project</button>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- DOCUMENT UPLOAD MODAL -->
<div id="document-modal" class="modal-overlay" style="display: none;">
  <div class="modal-content" style="max-width: 500px;">
    <div class="modal-header">
      <h2>Upload Document</h2>
      <button type="button" id="document-modal-close" class="modal-close">&times;</button>
    </div>
    <form id="document-form" enctype="multipart/form-data">
      <input type="hidden" name="project_id" id="document-project-id" value="">
      
      <div class="form-group">
        <label for="document-label">Document Label *</label>
        <input type="text" id="document-label" name="label" class="form-control" placeholder="e.g., Contract, Blueprint, Invoice" required>
      </div>
      
      <div class="form-group">
        <label for="document-file">File *</label>
        <input type="file" id="document-file" name="file" class="form-control" required>
        <small class="text-secondary" style="display: block; margin-top: 4px;">Max 50MB. PDF, Word, Excel, PowerPoint, Images, TXT, ZIP</small>
      </div>
      
      <div class="modal-actions" style="margin-top: 20px;">
        <button type="button" id="document-cancel-btn" class="btn btn-secondary">Cancel</button>
        <button type="submit" class="btn btn-primary">Upload</button>
      </div>
    </form>
  </div>
</div>

<!-- TASK MODAL -->
<div id="task-modal" class="modal-overlay" style="display: none;">
  <div class="modal-content" style="max-width: 500px;">
    <div class="modal-header">
      <h2 id="task-modal-title">Add Task</h2>
      <button type="button" id="task-modal-close" class="modal-close">&times;</button>
    </div>
    <form id="task-form">
      <input type="hidden" id="task-id" value="">
      <input type="hidden" id="task-project-id" value="">
      
      <div class="form-group">
        <label for="task-title">Title *</label>
        <input type="text" id="task-title" class="form-control" required>
      </div>
      
      <div class="form-group">
        <label for="task-description">Description</label>
        <textarea id="task-description" class="form-control" rows="3"></textarea>
      </div>
      
      <div class="grid grid-2" style="gap: 16px;">
        <div class="form-group">
          <label for="task-priority">Priority</label>
          <select id="task-priority" class="form-control">
            <option value="low">Low</option>
            <option value="medium" selected>Medium</option>
            <option value="high">High</option>
            <option value="urgent">Urgent</option>
          </select>
        </div>
        
        <div class="form-group">
          <label for="task-status">Status</label>
          <select id="task-status" class="form-control">
            <option value="pending" selected>Pending</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
      </div>
      
      <div class="grid grid-2" style="gap: 16px;">
        <div class="form-group">
          <label for="task-assigned">Assigned To</label>
          <select id="task-assigned" class="form-control">
            <option value="">— Unassigned —</option>
            <?php
            // Get users for dropdown
            $stmt = $pdo->query("SELECT id, username FROM users ORDER BY username");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
              echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['username']) . '</option>';
            }
            ?>
          </select>
        </div>
        
        <div class="form-group">
          <label for="task-due-date">Due Date</label>
          <input type="date" id="task-due-date" class="form-control">
        </div>
      </div>
      
      <div class="modal-actions" style="margin-top: 20px;">
        <button type="button" id="task-cancel-btn" class="btn btn-secondary">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Task</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT PROJECT MODAL -->
<?php 
  $edit_roles = ['admin', 'pm'];
  if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $edit_roles)): 
?>
<div id="edit-project-modal" class="modal-overlay">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Edit Project</h2>
      <button type="button" id="edit-project-close" class="modal-close">&times;</button>
    </div>

    <form method="post" id="edit-project-form">
      <input type="hidden" name="action" value="update_project">
      <input type="hidden" name="project_id" id="edit-project-id">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

      <div class="form-group">
        <label for="edit-name">Project Name *</label>
        <input type="text" name="edit_name" id="edit-name" required>
      </div>

      <div class="form-group">
        <label for="edit-client">Client / Owner</label>
        <input type="text" name="edit_client_name" id="edit-client">
      </div>

      <div class="form-group">
        <label for="edit-status">Status</label>
        <select name="edit_status" id="edit-status">
          <option value="Planned">Planned</option>
          <option value="Active">Active</option>
          <option value="On Hold">On Hold</option>
          <option value="Complete">Complete</option>
        </select>
      </div>

      <div class="form-group">
        <label for="edit-budget">Total Budget</label>
        <input type="number" step="0.01" name="edit_budget" id="edit-budget" placeholder="0.00">
      </div>

      <div class="form-group">
        <label for="edit-start">Start Date</label>
        <input type="date" name="edit_start_date" id="edit-start">
      </div>

      <div class="form-group">
        <label for="edit-end">Target Completion</label>
        <input type="date" name="edit_end_date" id="edit-end">
      </div>

      <div class="form-group">
        <label for="edit-notes">Notes</label>
        <textarea name="edit_notes" id="edit-notes" rows="3"></textarea>
      </div>

      <div class="modal-actions">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <button type="button" id="edit-project-delete" class="btn btn-danger-outline">Delete Project</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- CREATE PROJECT MODAL -->
<div id="create-project-modal" class="modal-overlay">
  <div class="modal-content">
    <div class="modal-header">
      <h2>New Project</h2>
      <button type="button" id="create-project-close" class="modal-close">&times;</button>
    </div>

    <form method="post" id="create-project-form">
      <input type="hidden" name="action" value="create_project">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

      <div class="form-group">
        <label for="create-name">Project Name *</label>
        <input type="text" name="name" id="create-name" required placeholder="Enter project name">
        <span class="form-error">Project name is required</span>
      </div>

      <div class="form-group">
        <label for="create-client">Client / Owner</label>
        <input type="text" name="client_name" id="create-client" placeholder="Client or owner name">
      </div>

      <div class="form-group">
        <label for="create-status">Status</label>
        <select name="status" id="create-status">
          <option value="Planned">Planned</option>
          <option value="Active">Active</option>
          <option value="On Hold">On Hold</option>
          <option value="Complete">Complete</option>
        </select>
      </div>

      <div class="form-group">
        <label for="create-budget">Total Budget</label>
        <input type="number" step="0.01" name="total_budget" id="create-budget" placeholder="0.00" min="0">
        <span class="input-hint">Enter 0 or leave blank if no budget</span>
      </div>

      <div class="form-group">
        <label for="create-start">Start Date</label>
        <input type="date" name="start_date" id="create-start">
      </div>

      <div class="form-group">
        <label for="create-end">Target Completion</label>
        <input type="date" name="end_date" id="create-end">
      </div>

      <div class="form-group">
        <label for="create-notes">Notes</label>
        <textarea name="notes" id="create-notes" rows="3"></textarea>
      </div>

      <div class="modal-actions">
        <button type="submit" class="btn btn-primary">Create Project</button>
      </div>
    </form>
  </div>
</div>

<script>
// Bulk delete checkboxes
(function() {
  var selectAll = document.getElementById('select-all-projects');
  var checkboxes = document.querySelectorAll('.project-checkbox');
  var bulkBtn = document.getElementById('bulk-delete-projects-btn');
  var tableForm = document.getElementById('projects-table-form');
  var tableAction = document.getElementById('projects-table-action');

  function updateBulkButton() {
    var anyChecked = Array.from(checkboxes).some(function(cb) { return cb.checked; });
    if (bulkBtn) {
      bulkBtn.style.opacity = anyChecked ? '1' : '0.6';
      bulkBtn.style.cursor = anyChecked ? 'pointer' : 'not-allowed';
    }
  }

  if (selectAll) {
    selectAll.addEventListener('change', function() {
      checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
      updateBulkButton();
    });
  }

  checkboxes.forEach(function(cb) {
    cb.addEventListener('change', updateBulkButton);
  });

  if (bulkBtn) {
    bulkBtn.addEventListener('click', function() {
      if (!Array.from(checkboxes).some(function(cb) { return cb.checked; })) return;
      if (confirm('Delete all selected projects? This cannot be undone.')) {
        tableAction.value = 'bulk_delete';
        tableForm.submit();
      }
    });
  }

  // Create project modal
  var createModal = document.getElementById('create-project-modal');
  var createCloseBtn = document.getElementById('create-project-close');
  var openCreateBtn = document.getElementById('open-create-project-modal');

  if (openCreateBtn) {
    openCreateBtn.addEventListener('click', function() {
      if (createModal) createModal.style.display = 'flex';
    });
  }

  if (createCloseBtn) createCloseBtn.addEventListener('click', function(e) { e.preventDefault(); if (createModal) createModal.style.display = 'none'; });
  if (createModal) createModal.addEventListener('click', function(e) { if (e.target === createModal && createModal) createModal.style.display = 'none'; });
  
  // Form validation for create project
  var createForm = document.getElementById('create-project-form');
  if (createForm) {
    createForm.addEventListener('submit', function(e) {
      var nameInput = document.getElementById('create-name');
      var formGroup = nameInput.closest('.form-group');
      
      // Clear previous error
      formGroup.classList.remove('error');
      
      // Validate
      if (!nameInput.value.trim()) {
        e.preventDefault();
        formGroup.classList.add('error');
        nameInput.focus();
        return false;
      }
      
      return true;
    });
    
    // Real-time validation
    document.getElementById('create-name').addEventListener('blur', function() {
      var formGroup = this.closest('.form-group');
      if (!this.value.trim() && this.value.length > 0) {
        formGroup.classList.add('error');
      } else {
        formGroup.classList.remove('error');
      }
    });
  }

  // Edit project modal
  var editModal = document.getElementById('edit-project-modal');
  var editCloseBtn = document.getElementById('edit-project-close');
  var editForm = document.getElementById('edit-project-form');
  var editDeleteBtn = document.getElementById('edit-project-delete');

  document.querySelectorAll('.edit-project-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      document.getElementById('edit-project-id').value = btn.getAttribute('data-id');
      document.getElementById('edit-name').value = btn.getAttribute('data-name') || '';
      document.getElementById('edit-client').value = btn.getAttribute('data-client') || '';
      document.getElementById('edit-status').value = btn.getAttribute('data-status') || 'Planned';
      document.getElementById('edit-budget').value = btn.getAttribute('data-budget') || '';
      document.getElementById('edit-start').value = btn.getAttribute('data-start') || '';
      document.getElementById('edit-end').value = btn.getAttribute('data-end') || '';
      document.getElementById('edit-notes').value = btn.getAttribute('data-notes') || '';
      if (editModal) editModal.style.display = 'flex';
    });
  });

  if (editCloseBtn) editCloseBtn.addEventListener('click', function(e) { e.preventDefault(); if (editModal) editModal.style.display = 'none'; });
  if (editModal) editModal.addEventListener('click', function(e) { if (e.target === editModal && editModal) editModal.style.display = 'none'; });

  if (editDeleteBtn && editForm) {
    editDeleteBtn.addEventListener('click', function() {
      if (confirm('Delete this project? This cannot be undone.')) {
        tableAction.value = 'delete_project';
        document.getElementById('edit-project-id').value = document.getElementById('edit-project-id').value;
        // Submit the main form with delete action
        tableAction.value = 'delete_project';
        var deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'project_id';
        deleteInput.value = document.getElementById('edit-project-id').value;
        tableForm.appendChild(deleteInput);
        tableForm.submit();
      }
    });
  }

  // ESC closes modal
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      if (createModal) createModal.style.display = 'none';
      if (editModal) editModal.style.display = 'none';
      if (detailModal) detailModal.style.display = 'none';
    }
  });

  // Project detail modal (click on row)
  var detailModal = document.getElementById('project-detail-modal');
  var detailClose = document.getElementById('project-detail-close');
  var detailName = document.getElementById('detail-project-name');
  var detailSub = document.getElementById('detail-project-sub');
  var detailInfo = document.getElementById('detail-project-info');
  var detailNotes = document.getElementById('detail-project-notes');
  var detailTimeline = document.getElementById('detail-project-timeline');
  var detailEditBtn = document.getElementById('detail-edit-btn');

  document.querySelectorAll('.project-row').forEach(function(row) {
    row.addEventListener('dblclick', function(e) {
      // If clicked on edit button, don't open detail modal
      if (e.target.closest('.edit-project-btn')) return;
      
      var id = row.getAttribute('data-id');
      var name = row.getAttribute('data-name') || '';
      var client = row.getAttribute('data-client') || '';
      var status = row.getAttribute('data-status') || '';
      var budget = row.getAttribute('data-budget') || '';
      var start = row.getAttribute('data-start') || '';
      var end = row.getAttribute('data-end') || '';
      var notes = row.getAttribute('data-notes') || '';

      if (detailName) detailName.textContent = name || 'Project Details';
      if (detailSub) detailSub.textContent = client || 'No client';
      
      if (detailInfo) {
        var html = '<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">';
        html += '<div><strong>Status:</strong><br><span class="status-badge status-' + (status || 'planned').toLowerCase().replace(' ', '-') + '">' + (status || '-') + '</span></div>';
        html += '<div><strong>Budget:</strong><br>' + (budget ? '$' + Number(budget).toLocaleString(undefined, {minimumFractionDigits: 2}) : '-') + '</div>';
        html += '<div><strong>Start Date:</strong><br>' + (start || '-') + '</div>';
        html += '<div><strong>Target End:</strong><br>' + (end || '-') + '</div>';
        html += '<div style="grid-column: span 2;"><strong>Client:</strong><br>' + (client || '-') + '</div>';
        html += '</div>';
        detailInfo.innerHTML = html;
      }
      
      if (detailNotes) {
        detailNotes.textContent = notes || 'No notes for this project.';
      }
      
      if (detailTimeline) {
        var timelineHtml = '';
        if (start) {
          timelineHtml += '<div class="timeline-item"><div class="timeline-date">' + start + '</div><div class="timeline-content">Project Start</div></div>';
        }
        if (end) {
          timelineHtml += '<div class="timeline-item"><div class="timeline-date">' + end + '</div><div class="timeline-content">Target Completion</div></div>';
        }
        if (!timelineHtml) {
          timelineHtml = '<p class="muted">No timeline dates set.</p>';
        }
        detailTimeline.innerHTML = timelineHtml;
      }
      
      if (detailModal) detailModal.style.display = 'flex';
    });
  });

  if (detailClose) detailClose.addEventListener('click', function(e) { e.preventDefault(); if (detailModal) detailModal.style.display = 'none'; });
  if (detailModal) detailModal.addEventListener('click', function(e) { if (e.target === detailModal && detailModal) detailModal.style.display = 'none'; });

  // Tab switching
  document.querySelectorAll('.modal-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
      var tabId = tab.getAttribute('data-tab');
      
      // Update active tab button
      document.querySelectorAll('.modal-tab').forEach(function(t) { t.classList.remove('active'); });
      tab.classList.add('active');
      
      // Show corresponding content
      document.querySelectorAll('.tab-pane').forEach(function(pane) { pane.classList.remove('active'); });
      document.getElementById('tab-' + tabId).classList.add('active');
    });
  });

// Edit button from detail modal
  document.getElementById('detail-edit-btn').addEventListener('click', function() {
    // Collect all data from the current modal display
    // We'll get it from the hidden rows data
    
    // Find the row with matching data from the detail modal
    var currentRow = null;
    
    // Find which row's data is showing in the modal
    var detailNameText = detailName ? detailName.textContent : '';
    document.querySelectorAll('.project-row').forEach(function(row) {
      if (row.getAttribute('data-name') === detailNameText) {
        currentRow = row;
      }
    });
    
    if (currentRow) {
      if (detailModal) detailModal.style.display = 'none';
      
      document.getElementById('edit-project-id').value = currentRow.getAttribute('data-id');
      document.getElementById('edit-name').value = currentRow.getAttribute('data-name') || '';
      document.getElementById('edit-client').value = currentRow.getAttribute('data-client') || '';
      document.getElementById('edit-status').value = currentRow.getAttribute('data-status') || 'Planned';
      document.getElementById('edit-budget').value = currentRow.getAttribute('data-budget') || '';
      document.getElementById('edit-start').value = currentRow.getAttribute('data-start') || '';
      document.getElementById('edit-end').value = currentRow.getAttribute('data-end') || '';
      document.getElementById('edit-notes').value = currentRow.getAttribute('data-notes') || '';
      
      if (editModal) editModal.style.display = 'flex';
    }
  });
  
  // Auto-open project from search
  var openProjectId = '<?php echo $openProjectId; ?>';
  if (openProjectId) {
    var row = document.querySelector('.project-row[data-id="' + openProjectId + '"]');
    if (row) {
      // Simulate dblclick to open detail modal
      var event = new MouseEvent('dblclick', {
        bubbles: true,
        cancelable: true,
        view: window
      });
      row.dispatchEvent(event);
    }
  }
  
  // ========================================
  // TASK MANAGEMENT
  // ========================================
  var currentProjectId = null;
  var addTaskBtn = document.getElementById('add-task-btn');
  var tasksList = document.getElementById('tasks-list');
  var tasksStats = document.getElementById('tasks-stats');
  
  function loadTasks(projectId) {
    if (!projectId) return;
    currentProjectId = projectId;
    
    fetch('api/tasks.php?project_id=' + projectId)
      .then(function(response) { return response.json(); })
      .then(function(data) {
        if (data.error) {
          tasksList.innerHTML = '<p class="muted">Error loading tasks.</p>';
          return;
        }
        
        // Show stats
        if (data.stats) {
          tasksStats.innerHTML = '<span class="badge badge-success">' + data.stats.completed + ' completed</span> ' +
            '<span class="badge badge-warning">' + data.stats.in_progress + ' in progress</span> ' +
            '<span class="badge badge-info">' + data.stats.pending + ' pending</span>';
        }
        
        // Show tasks
        if (data.tasks && data.tasks.length > 0) {
          var html = '<table class="tasks-table"><thead><tr><th>Status</th><th>Title</th><th>Priority</th><th>Assigned</th><th>Due</th></tr></thead><tbody>';
          data.tasks.forEach(function(task) {
            var statusClass = task.status === 'completed' ? 'task-completed' : (task.status === 'in_progress' ? 'task-in-progress' : 'task-pending');
            var priorityClass = 'priority-' + task.priority;
            var dueDate = task.due_date ? new Date(task.due_date).toLocaleDateString() : '—';
            var assigned = task.assigned_name || '—';
            
            html += '<tr class="' + statusClass + '" data-id="' + task.id + '">' +
              '<td><select class="task-status-select" onchange="updateTaskStatus(' + task.id + ', this.value)">' +
                '<option value="pending" ' + (task.status === 'pending' ? 'selected' : '') + '>Pending</option>' +
                '<option value="in_progress" ' + (task.status === 'in_progress' ? 'selected' : '') + '>In Progress</option>' +
                '<option value="completed" ' + (task.status === 'completed' ? 'selected' : '') + '>Completed</option>' +
                '<option value="cancelled" ' + (task.status === 'cancelled' ? 'selected' : '') + '>Cancelled</option>' +
              '</select></td>' +
              '<td><a href="#" class="task-title-link" onclick="editTask(' + task.id + ', \'' + escapeHtml(task.title) + '\', \'' + escapeHtml(task.description || '') + '\', \'' + task.priority + '\', \'' + task.status + '\', \'' + (task.assigned_to || '') + '\', \'' + (task.due_date || '') + '\'); return false;">' + escapeHtml(task.title) + '</a></td>' +
              '<td><span class="badge ' + priorityClass + '">' + task.priority + '</span></td>' +
              '<td>' + escapeHtml(assigned) + '</td>' +
              '<td>' + dueDate + '</td>' +
            '</tr>';
          });
          html += '</tbody></table>';
          tasksList.innerHTML = html;
        } else {
          tasksList.innerHTML = '<p class="muted">No tasks yet. Click "Add Task" to create one.</p>';
        }
      })
      .catch(function() {
        tasksList.innerHTML = '<p class="muted">Error loading tasks.</p>';
      });
  }
  
  window.updateTaskStatus = function(taskId, status) {
    fetch('api/tasks.php', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: taskId, status: status })
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
      if (data.success && currentProjectId) {
        loadTasks(currentProjectId);
      }
    });
  };
  
  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
  
  // Task Modal Elements
  var taskModal = document.getElementById('task-modal');
  var taskModalTitle = document.getElementById('task-modal-title');
  var taskForm = document.getElementById('task-form');
  var taskIdInput = document.getElementById('task-id');
  var taskProjectIdInput = document.getElementById('task-project-id');
  var taskTitleInput = document.getElementById('task-title');
  var taskDescInput = document.getElementById('task-description');
  var taskPrioritySelect = document.getElementById('task-priority');
  var taskStatusSelect = document.getElementById('task-status');
  var taskAssignedSelect = document.getElementById('task-assigned');
  var taskDueDateInput = document.getElementById('task-due-date');
  var taskModalClose = document.getElementById('task-modal-close');
  var taskCancelBtn = document.getElementById('task-cancel-btn');
  
  function openTaskModal(task) {
    taskIdInput.value = task ? task.id : '';
    taskProjectIdInput.value = currentProjectId;
    taskTitleInput.value = task ? task.title : '';
    taskDescInput.value = task ? (task.description || '') : '';
    taskPrioritySelect.value = task ? task.priority : 'medium';
    taskStatusSelect.value = task ? task.status : 'pending';
    taskAssignedSelect.value = task ? (task.assigned_to || '') : '';
    taskDueDateInput.value = task ? task.due_date : '';
    
    taskModalTitle.textContent = task ? 'Edit Task' : 'Add Task';
    taskModal.style.display = 'flex';
    taskTitleInput.focus();
  }
  
  function closeTaskModal() {
    taskModal.style.display = 'none';
    taskForm.reset();
  }
  
  if (taskModalClose) taskModalClose.addEventListener('click', closeTaskModal);
  if (taskCancelBtn) taskCancelBtn.addEventListener('click', closeTaskModal);
  if (taskModal) taskModal.addEventListener('click', function(e) {
    if (e.target === taskModal) closeTaskModal();
  });
  
  // Task form submit
  if (taskForm) {
    taskForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      var taskData = {
        project_id: currentProjectId,
        title: taskTitleInput.value,
        description: taskDescInput.value,
        priority: taskPrioritySelect.value,
        status: taskStatusSelect.value,
        assigned_to: taskAssignedSelect.value || null,
        due_date: taskDueDateInput.value || null
      };
      
      var method = taskIdInput.value ? 'PUT' : 'POST';
      if (taskIdInput.value) {
        taskData.id = taskIdInput.value;
      }
      
      fetch('api/tasks.php', {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(taskData)
      })
      .then(function(response) { return response.json(); })
      .then(function(data) {
        if (data.success) {
          closeTaskModal();
          loadTasks(currentProjectId);
        } else {
          alert(data.error || 'Error saving task');
        }
      });
    });
  }
  
  // Add task button - open modal
  if (addTaskBtn) {
    addTaskBtn.addEventListener('click', function() {
      openTaskModal(null);
    });
  }
  
  // Make task rows clickable to edit
  window.editTask = function(taskId, title, description, priority, status, assignedTo, dueDate) {
    openTaskModal({
      id: taskId,
      title: title,
      description: description,
      priority: priority,
      status: status,
      assigned_to: assignedTo,
      due_date: dueDate
    });
  };
  
  // Load tasks when Tasks tab is clicked
  document.querySelectorAll('.modal-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
      if (tab.getAttribute('data-tab') === 'tasks' && currentProjectId) {
        loadTasks(currentProjectId);
      }
      if (tab.getAttribute('data-tab') === 'documents' && currentProjectId) {
        loadDocuments(currentProjectId);
      }
    });
  });
  
  // ========================================
  // DOCUMENT MANAGEMENT
  // ========================================
  var addDocBtn = document.getElementById('add-document-btn');
  var documentsList = document.getElementById('documents-list');
  var documentsCount = document.getElementById('documents-count');
  var documentModal = document.getElementById('document-modal');
  var documentForm = document.getElementById('document-form');
  var documentProjectIdInput = document.getElementById('document-project-id');
  var documentModalClose = document.getElementById('document-modal-close');
  var documentCancelBtn = document.getElementById('document-cancel-btn');
  
  function loadDocuments(projectId) {
    if (!projectId) return;
    
    fetch('api/documents.php?project_id=' + projectId)
      .then(function(response) { return response.json(); })
      .then(function(data) {
        if (data.error) {
          documentsList.innerHTML = '<p class="muted">Error loading documents.</p>';
          return;
        }
        
        var docs = data.documents || [];
        
        // Show count
        documentsCount.innerHTML = '<span class="text-secondary">' + docs.length + ' document' + (docs.length !== 1 ? 's' : '') + '</span>';
        
        if (docs.length > 0) {
          var html = '<table class="documents-table"><thead><tr><th>Name</th><th>Type</th><th>Size</th><th>Uploaded</th><th>By</th><th></th></tr></thead><tbody>';
          docs.forEach(function(doc) {
            var fileSize = doc.file_size ? formatFileSize(doc.file_size) : '—';
            var uploadDate = doc.uploaded_at ? new Date(doc.uploaded_at).toLocaleDateString() : '—';
            var icon = getFileIcon(doc.mime_type);
            
            html += '<tr>' +
              '<td><a href="' + doc.file_path + '" target="_blank" class="document-link"><i class="' + icon + '"></i> ' + escapeHtml(doc.file_name) + '</a></td>' +
              '<td><span class="text-secondary">' + escapeHtml(doc.label) + '</span></td>' +
              '<td>' + fileSize + '</td>' +
              '<td>' + uploadDate + '</td>' +
              '<td>' + escapeHtml(doc.uploaded_by_name || '—') + '</td>' +
              '<td style="text-align: right;">' +
                '<a href="' + doc.file_path + '" download class="btn btn-sm btn-secondary"><i class="fa-solid fa-download"></i></a> ' +
                '<button type="button" class="btn btn-sm btn-danger" onclick="deleteDocument(' + doc.id + ')"><i class="fa-solid fa-trash"></i></button>' +
              '</td>' +
            '</tr>';
          });
          html += '</tbody></table>';
          documentsList.innerHTML = html;
        } else {
          documentsList.innerHTML = '<p class="muted">No documents yet. Click "Upload Document" to add one.</p>';
        }
      })
      .catch(function() {
        documentsList.innerHTML = '<p class="muted">Error loading documents.</p>';
      });
  }
  
  function formatFileSize(bytes) {
    if (!bytes) return '—';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }
  
  function getFileIcon(mimeType) {
    if (!mimeType) return 'fa-solid fa-file';
    if (mimeType.includes('pdf')) return 'fa-solid fa-file-pdf';
    if (mimeType.includes('word') || mimeType.includes('document')) return 'fa-solid fa-file-word';
    if (mimeType.includes('excel') || mimeType.includes('sheet')) return 'fa-solid fa-file-excel';
    if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) return 'fa-solid fa-file-powerpoint';
    if (mimeType.includes('image')) return 'fa-solid fa-file-image';
    if (mimeType.includes('zip') || mimeType.includes('compressed')) return 'fa-solid fa-file-zipper';
    if (mimeType.includes('text')) return 'fa-solid fa-file-lines';
    return 'fa-solid fa-file';
  }
  
  window.deleteDocument = function(docId) {
    if (!confirm('Delete this document? This cannot be undone.')) return;
    
    fetch('api/documents.php', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: docId })
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
      if (data.success) {
        loadDocuments(currentProjectId);
      } else {
        alert(data.error || 'Error deleting document');
      }
    });
  };
  
  // Open document modal
  function openDocumentModal() {
    documentProjectIdInput.value = currentProjectId;
    documentModal.style.display = 'flex';
    document.getElementById('document-label').focus();
  }
  
  function closeDocumentModal() {
    documentModal.style.display = 'none';
    documentForm.reset();
  }
  
  if (documentModalClose) documentModalClose.addEventListener('click', closeDocumentModal);
  if (documentCancelBtn) documentCancelBtn.addEventListener('click', closeDocumentModal);
  if (documentModal) documentModal.addEventListener('click', function(e) {
    if (e.target === documentModal) closeDocumentModal();
  });
  
  // Document form submit
  if (documentForm) {
    documentForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      var formData = new FormData(documentForm);
      
      fetch('api/documents.php', {
        method: 'POST',
        body: formData
      })
      .then(function(response) { return response.json(); })
      .then(function(data) {
        if (data.success) {
          closeDocumentModal();
          loadDocuments(currentProjectId);
        } else {
          alert(data.error || 'Error uploading document');
        }
      });
    });
  }
  
  // Add document button
  if (addDocBtn) {
    addDocBtn.addEventListener('click', openDocumentModal);
  }
  
  // Store current project ID when detail modal opens
  var detailModal = document.getElementById('project-detail-modal');
  var detailName = document.getElementById('detail-project-name');
  if (detailModal) {
    var observer = new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        if (mutation.attributeName === 'style' && detailModal.style.display !== 'none') {
          // Modal opened - try to get project ID from current data
          var detailNameText = detailName ? detailName.textContent : '';
          document.querySelectorAll('.project-row').forEach(function(row) {
            if (row.getAttribute('data-name') === detailNameText) {
              currentProjectId = row.getAttribute('data-id');
            }
          });
        }
      });
    });
    observer.observe(detailModal, { attributes: true });
  }
})();
</script>

<?php require_once 'partials/footer.php'; ?>