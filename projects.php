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

// Handle success messages from redirects
$message = '';
if (isset($_GET['message'])) {
    $messages = [
        'created' => 'Project created successfully.',
        'updated' => 'Project updated successfully.',
        'deleted' => 'Project deleted successfully.',
        'bulk_deleted' => 'Selected projects deleted successfully.'
    ];
    $message = $messages[$_GET['message']] ?? '';
}

$projects = [];
$projectsError = '';

// Generate CSRF token
require_once 'includes/security.php';
require_once 'includes/audit.php';
$csrf_token = csrf_token_generate();

// Get PM users for PM dropdown
$usersList = [];
try {
    $stmt = $pdo->query("SELECT id, username FROM users WHERE role = 'pm' ORDER BY username");
    $usersList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $usersList = [];
}

// Handle filters
$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'status' => $_GET['status'] ?? '',
    'city' => $_GET['city'] ?? '',
    'manager' => $_GET['manager'] ?? '',
    'permit_status' => $_GET['permit_status'] ?? '',
    'inspection_status' => $_GET['inspection_status'] ?? ''
];

// Get unique cities for filter
$cityOptions = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT city FROM projects WHERE city IS NOT NULL AND city != '' ORDER BY city");
    $cityOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Get unique project types for filter
$projectTypeOptions = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT project_type FROM projects WHERE project_type IS NOT NULL AND project_type != '' ORDER BY project_type");
    $projectTypeOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Get unique project managers for filter
$managerOptions = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT project_manager FROM projects WHERE project_manager IS NOT NULL AND project_manager != '' ORDER BY project_manager");
    $managerOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

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
    $where[] = "(name LIKE ? OR client_name LIKE ? OR address LIKE ?)";
    $searchTerm = '%' . $filters['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($filters['status']) && $filters['status'] !== 'all') {
    $where[] = "status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['city']) && $filters['city'] !== 'all') {
    $where[] = "city = ?";
    $params[] = $filters['city'];
}

if (!empty($filters['manager']) && $filters['manager'] !== 'all') {
    $where[] = "project_manager = ?";
    $params[] = $filters['manager'];
}

// Filter by permit status (has permits in specific status)
if (!empty($filters['permit_status']) && $filters['permit_status'] !== 'all') {
    if ($filters['permit_status'] === 'none') {
        $where[] = "(SELECT COUNT(*) FROM permits pm WHERE pm.project_id = p.id) = 0";
    } elseif ($filters['permit_status'] === 'pending') {
        $where[] = "(SELECT COUNT(*) FROM permits pm WHERE pm.project_id = p.id AND pm.status IN ('not_started', 'submitted', 'in_review', 'correction_needed', 'resubmitted')) > 0";
    } elseif ($filters['permit_status'] === 'approved') {
        $where[] = "(SELECT COUNT(*) FROM permits pm WHERE pm.project_id = p.id AND pm.status = 'approved') > 0";
    } elseif ($filters['permit_status'] === 'rejected') {
        $where[] = "(SELECT COUNT(*) FROM permits pm WHERE pm.project_id = p.id AND pm.status = 'rejected') > 0";
    }
}

// Filter by inspection status (has inspections in specific status)
if (!empty($filters['inspection_status']) && $filters['inspection_status'] !== 'all') {
    if ($filters['inspection_status'] === 'none') {
        $where[] = "(SELECT COUNT(*) FROM inspections ins WHERE ins.project_id = p.id) = 0";
    } elseif ($filters['inspection_status'] === 'pending') {
        $where[] = "(SELECT COUNT(*) FROM inspections ins WHERE ins.project_id = p.id AND ins.status IN ('requested', 'scheduled')) > 0";
    } elseif ($filters['inspection_status'] === 'passed') {
        $where[] = "(SELECT COUNT(*) FROM inspections ins WHERE ins.project_id = p.id AND ins.status = 'passed') > 0";
    } elseif ($filters['inspection_status'] === 'failed') {
        $where[] = "(SELECT COUNT(*) FROM inspections ins WHERE ins.project_id = p.id AND ins.status = 'failed') > 0";
    }
}

$sql = "SELECT p.id, p.name, p.client_name, p.city, p.project_type, p.address, p.phone, p.email, p.project_manager, p.scope_of_work, p.status, p.total_budget, p.invoice_number, p.start_date, p.end_date, p.notes, p.created_at,
        (SELECT COUNT(*) FROM permits pm WHERE pm.project_id = p.id) as permit_count,
        (SELECT COUNT(*) FROM permits pm WHERE pm.project_id = p.id AND pm.status = 'approved') as permits_approved,
        (SELECT COUNT(*) FROM permits pm WHERE pm.project_id = p.id AND pm.status IN ('not_started', 'submitted', 'in_review', 'correction_needed', 'resubmitted')) as permits_pending,
        (SELECT COALESCE(SUM(amount), 0) FROM vendor_payments WHERE project_id = p.id) AS money_out,
        (SELECT COALESCE(SUM(bid_amount), 0) FROM project_vendors WHERE project_id = p.id) AS total_bid
        FROM projects p";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY p.created_at DESC";

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
    $allProjects = $pdo->query("SELECT id, name, client_name, city, project_type, address, phone, email, project_manager, scope_of_work, status, total_budget, invoice_number, invoice_pdf, start_date, end_date, notes, created_at FROM projects ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
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
            $name         = trim($_POST['name'] ?? '');
            $client_name  = trim($_POST['client_name'] ?? '');
            $city         = $_POST['city'] ?? '';
            $project_type = $_POST['project_type'] ?? '';
            $address      = trim($_POST['address'] ?? '');
            $phone        = trim($_POST['phone'] ?? '');
            $email        = trim($_POST['email'] ?? '');
            $project_manager = $_POST['project_manager'] ?? '';
            $scope_of_work = trim($_POST['scope_of_work'] ?? '');
            $status       = $_POST['status'] ?? 'Signed';
            $budget       = $_POST['total_budget'] !== '' ? (float)$_POST['total_budget'] : 0;
            $invoice_num  = trim($_POST['invoice_number'] ?? '');
            $start_date   = $_POST['start_date'] ?? null;
            $end_date     = $_POST['end_date'] ?? null;
            $notes        = trim($_POST['notes'] ?? '');

            if ($name === '') {
                $message = "Project name is required.";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO projects 
                        (name, client_name, city, project_type, address, phone, email, 
                         project_manager, scope_of_work, status, total_budget, invoice_number, 
                         start_date, end_date, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $name,
                        $client_name ?: null,
                        $city ?: null,
                        $project_type ?: null,
                        $address ?: null,
                        $phone ?: null,
                        $email ?: null,
                        $project_manager ?: null,
                        $scope_of_work ?: null,
                        $status,
                        $budget,
                        $invoice_num ?: null,
                        $start_date ?: null,
                        $end_date ?: null,
                        $notes ?: null
                    ]);
                    
                    // Audit log
                    audit_log('create', 'projects', $pdo->lastInsertId(), $name);
                    
                    // Auto-add project manager to project_team if user exists
                    $newProjectId = $pdo->lastInsertId();
                    if ($project_manager) {
                        $pmStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email LIKE ? LIMIT 1");
                        $pmStmt->execute([$project_manager, '%' . $project_manager . '%']);
                        $pmUser = $pmStmt->fetch(PDO::FETCH_ASSOC);
                        if ($pmUser) {
                            $teamStmt = $pdo->prepare("INSERT IGNORE INTO project_team (project_id, user_id, role) VALUES (?, ?, 'pm')");
                            $teamStmt->execute([$newProjectId, $pmUser['id']]);
                        }
                    }
                    
                    header("Location: projects.php?message=created");
                    exit;
                } catch (Exception $e) {
                    $message = "Error creating project: " . $e->getMessage();
                }
            }
        } // end create_project

        // Update project
        if ($action === 'update_project') {
            $id            = (int)($_POST['project_id'] ?? 0);
            $name          = trim($_POST['edit_name'] ?? '');
            $client_name   = trim($_POST['edit_client_name'] ?? '');
            $city          = $_POST['edit_city'] ?? '';
            $project_type  = $_POST['edit_project_type'] ?? '';
            $address       = trim($_POST['edit_address'] ?? '');
            $phone         = trim($_POST['edit_phone'] ?? '');
            $email         = trim($_POST['edit_email'] ?? '');
            $project_manager = $_POST['edit_project_manager'] ?? '';
            $scope_of_work = trim($_POST['edit_scope'] ?? '');
            $status        = $_POST['edit_status'] ?? 'Signed';
            $budget        = $_POST['edit_budget'] !== '' ? (float)$_POST['edit_budget'] : 0;
            $invoice_num   = trim($_POST['edit_invoice'] ?? '');
            $start_date    = $_POST['edit_start_date'] ?: null;
            $end_date      = $_POST['edit_end_date'] ?: null;
            $notes         = trim($_POST['edit_notes'] ?? '');

            if ($id <= 0 || $name === '') {
                $message = "Invalid project data.";
            } else {
                try {
                    // Get old values for audit
                    $oldStmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
                    $oldStmt->execute([$id]);
                    $oldData = $oldStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $stmt = $pdo->prepare("UPDATE projects SET 
                        name = ?, client_name = ?, city = ?, project_type = ?, address = ?, 
                        phone = ?, email = ?, project_manager = ?, scope_of_work = ?, 
                        status = ?, total_budget = ?, invoice_number = ?,
                        start_date = ?, end_date = ?, notes = ? WHERE id = ?");
                    $stmt->execute([
                        $name,
                        $client_name ?: null,
                        $city ?: null,
                        $project_type ?: null,
                        $address ?: null,
                        $phone ?: null,
                        $email ?: null,
                        $project_manager ?: null,
                        $scope_of_work ?: null,
                        $status,
                        $budget,
                        $invoice_num ?: null,
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
                            'city' => $city,
                            'project_type' => $project_type,
                            'address' => $address,
                            'phone' => $phone,
                            'email' => $email,
                            'project_manager' => $project_manager,
                            'scope_of_work' => $scope_of_work,
                            'status' => $status,
                            'total_budget' => $budget,
                            'invoice_number' => $invoice_num,
                            'start_date' => $start_date,
                            'end_date' => $end_date,
                            'notes' => $notes
                        ]
                    ]);
                    
                    // Auto-add/update project manager to project_team if user exists
                    if ($project_manager) {
                        $pmStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email LIKE ? LIMIT 1");
                        $pmStmt->execute([$project_manager, '%' . $project_manager . '%']);
                        $pmUser = $pmStmt->fetch(PDO::FETCH_ASSOC);
                        if ($pmUser) {
                            $teamStmt = $pdo->prepare("INSERT IGNORE INTO project_team (project_id, user_id, role) VALUES (?, ?, 'pm')");
                            $teamStmt->execute([$id, $pmUser['id']]);
                        }
                    }
                    
                    header("Location: projects.php?message=updated");
                    exit;
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
                    
                    header("Location: projects.php?message=deleted");
                    exit;
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
                    
                    header("Location: projects.php?message=bulk_deleted");
                    exit;
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
    <div class="stat-label">Total Agreement Amount</div>
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

<!-- Filters - Collapsible -->
<div style="margin-top: 20px;">
  <button type="button" class="btn btn-secondary" onclick="document.getElementById('filters-panel').classList.toggle('hidden'); this.querySelector('i').classList.toggle('fa-chevron-down'); this.querySelector('i').classList.toggle('fa-chevron-up');">
    <i class="fa-solid fa-chevron-down"></i> Filters
    <?php if (!empty($filters['search']) || !empty($filters['status']) || !empty($filters['city']) || !empty($filters['manager']) || !empty($filters['permit_status']) || !empty($filters['inspection_status'])): ?>
    <span style="background: var(--primary); color: white; border-radius: 50%; width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; margin-left: 6px;">
      <?php echo (int)(!empty($filters['search'])) + (int)(!empty($filters['status'])) + (int)(!empty($filters['city'])) + (int)(!empty($filters['manager'])) + (int)(!empty($filters['permit_status'])) + (int)(!empty($filters['inspection_status'])); ?>
    </span>
    <?php endif; ?>
  </button>
</div>

<div id="filters-panel" class="card filter-bar hidden" style="margin-top: 10px; padding: 12px 16px;">
  <form method="get" class="filter-form">
    <div class="filter-group">
      <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" placeholder="Search projects..." class="filter-input">
      
      <!-- Status Filter -->
      <select name="status" class="filter-select">
        <option value="">All Statuses</option>
        <option value="Signed" <?php echo ($filters['status'] ?? '') === 'Signed' ? 'selected' : ''; ?>>Signed</option>
        <option value="Starting Soon" <?php echo ($filters['status'] ?? '') === 'Starting Soon' ? 'selected' : ''; ?>>Starting Soon</option>
        <option value="Active" <?php echo ($filters['status'] ?? '') === 'Active' ? 'selected' : ''; ?>>Active</option>
        <option value="Waiting on Permit" <?php echo ($filters['status'] ?? '') === 'Waiting on Permit' ? 'selected' : ''; ?>>Waiting on Permit</option>
        <option value="Waiting on Materials" <?php echo ($filters['status'] ?? '') === 'Waiting on Materials' ? 'selected' : ''; ?>>Waiting on Materials</option>
        <option value="On Hold" <?php echo ($filters['status'] ?? '') === 'On Hold' ? 'selected' : ''; ?>>On Hold</option>
        <option value="Completed" <?php echo ($filters['status'] ?? '') === 'Completed' ? 'selected' : ''; ?>>Completed</option>
        <option value="Cancelled" <?php echo ($filters['status'] ?? '') === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
      </select>
      
      <!-- City Filter -->
      <select name="city" class="filter-select">
        <option value="">All Cities</option>
        <?php foreach ($cityOptions as $city): ?>
        <option value="<?php echo htmlspecialchars($city); ?>" <?php echo ($filters['city'] ?? '') === $city ? 'selected' : ''; ?>><?php echo htmlspecialchars($city); ?></option>
        <?php endforeach; ?>
      </select>
      
      <!-- Project Manager Filter -->
      <select name="manager" class="filter-select">
        <option value="">All Managers</option>
        <?php foreach ($managerOptions as $pm): ?>
        <option value="<?php echo htmlspecialchars($pm); ?>" <?php echo ($filters['manager'] ?? '') === $pm ? 'selected' : ''; ?>><?php echo htmlspecialchars($pm); ?></option>
        <?php endforeach; ?>
      </select>
      
      <!-- Permit Status Filter -->
      <select name="permit_status" class="filter-select">
        <option value="">All Permits</option>
        <option value="none" <?php echo ($filters['permit_status'] ?? '') === 'none' ? 'selected' : ''; ?>>No Permits</option>
        <option value="pending" <?php echo ($filters['permit_status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Permits Pending</option>
        <option value="approved" <?php echo ($filters['permit_status'] ?? '') === 'approved' ? 'selected' : ''; ?>>Permits Approved</option>
        <option value="rejected" <?php echo ($filters['permit_status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Permits Rejected</option>
      </select>
      
      <!-- Inspection Status Filter -->
      <select name="inspection_status" class="filter-select">
        <option value="">All Inspections</option>
        <option value="none" <?php echo ($filters['inspection_status'] ?? '') === 'none' ? 'selected' : ''; ?>>No Inspections</option>
        <option value="pending" <?php echo ($filters['inspection_status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Inspections Pending</option>
        <option value="passed" <?php echo ($filters['inspection_status'] ?? '') === 'passed' ? 'selected' : ''; ?>>Inspections Passed</option>
        <option value="failed" <?php echo ($filters['inspection_status'] ?? '') === 'failed' ? 'selected' : ''; ?>>Inspections Failed</option>
      </select>
      
      <button type="submit" class="btn btn-sm"><i class="fa-solid fa-filter"></i></button>
      <?php if (!empty($filters['search']) || !empty($filters['status']) || !empty($filters['city']) || !empty($filters['manager']) || !empty($filters['permit_status']) || !empty($filters['inspection_status'])): ?>
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
        <div class="card-subtitle">Click a row for details, click Edit for modifications</div>
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
        <?php 
          $edit_roles = ['admin', 'pm'];
          if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $edit_roles)): 
        ?>
        <div class="bulk-actions" style="margin-bottom:10px;min-height:32px;">
          <button type="button" id="bulk-delete-projects-btn" class="btn btn-danger" style="opacity:0.6;cursor:not-allowed;">
            <i class="fa-solid fa-trash"></i> Delete Selected
          </button>
        </div>
        <?php endif; ?>
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
                <th>Status</th>
                <th>Permits</th>
                <th>Money In</th>
                <th>Money Out</th>
                <th>Profit</th>
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
                  <td colspan="11">
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
                  <?php $moneyIn = (float)($p['total_budget'] ?? 0); $moneyOut = (float)($p['money_out'] ?? 0); $isNonProfitable = $moneyIn > 0 && $moneyOut > ($moneyIn * 0.6); ?>
                  <tr class="project-row" <?php echo $isNonProfitable ? 'title="⚠️ Non Profitable: costs exceed 60% of budget"' : ''; ?>
                      data-id="<?php echo (int)$p['id']; ?>"
                      data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>"
                      data-client="<?php echo htmlspecialchars($p['client_name'] ?? '', ENT_QUOTES); ?>"
                      data-city="<?php echo htmlspecialchars($p['city'] ?? '', ENT_QUOTES); ?>"
                      data-project-type="<?php echo htmlspecialchars($p['project_type'] ?? '', ENT_QUOTES); ?>"
                      data-address="<?php echo htmlspecialchars($p['address'] ?? '', ENT_QUOTES); ?>"
                      data-phone="<?php echo htmlspecialchars($p['phone'] ?? '', ENT_QUOTES); ?>"
                      data-email="<?php echo htmlspecialchars($p['email'] ?? '', ENT_QUOTES); ?>"
                      data-project-manager="<?php echo htmlspecialchars($p['project_manager'] ?? '', ENT_QUOTES); ?>"
                      data-scope="<?php echo htmlspecialchars($p['scope_of_work'] ?? '', ENT_QUOTES); ?>"
                      data-status="<?php echo htmlspecialchars($p['status'] ?? '', ENT_QUOTES); ?>"
                      data-budget="<?php echo htmlspecialchars($p['total_budget'] ?? '', ENT_QUOTES); ?>"
                      data-invoice="<?php echo htmlspecialchars($p['invoice_number'] ?? '', ENT_QUOTES); ?>"
                      data-start="<?php echo htmlspecialchars($p['start_date'] ?? '', ENT_QUOTES); ?>"
                      data-end="<?php echo htmlspecialchars($p['end_date'] ?? '', ENT_QUOTES); ?>"
                      data-notes="<?php echo htmlspecialchars($p['notes'] ?? '', ENT_QUOTES); ?>">
                    <?php 
                      $edit_roles = ['admin', 'pm'];
                      if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $edit_roles)): 
                    ?>
                    <td><input type="checkbox" class="project-checkbox" name="project_ids[]" value="<?php echo (int)$p['id']; ?>"></td>
                    <?php endif; ?>
                    <td>
                        <a href="project-detail.php?id=<?php echo (int)$p['id']; ?>" target="_blank" title="Open full page">
                            <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                            <i class="fa-solid fa-external-link-alt" style="font-size: 10px; margin-left: 4px;"></i>
                        </a>
                    </td>
                    <td>
                      <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $p['status'] ?? 'signed')); ?>">
                        <?php echo htmlspecialchars($p['status'] ?? ''); ?>
                      </span>
                    </td>
                    <td>
                      <?php 
                        $permitCount = (int)($p['permit_count'] ?? 0);
                        $pending = (int)($p['permits_pending'] ?? 0);
                        $approved = (int)($p['permits_approved'] ?? 0);
                        if ($permitCount === 0): ?>
                        <span class="text-muted">—</span>
                        <?php else: ?>
                        <span title="<?php echo $pending; ?> pending, <?php echo $approved; ?> approved">
                          <?php if ($pending > 0): ?>
                          <span class="status-badge status-warning"><?php echo $pending; ?> pending</span>
                          <?php endif; ?>
                          <?php if ($approved > 0): ?>
                          <span class="status-badge status-success" style="margin-left:4px;"><?php echo $approved; ?> OK</span>
                          <?php endif; ?>
                          <?php if ($pending === 0 && $approved === 0): ?>
                          <span class="status-badge status-info"><?php echo $permitCount; ?></span>
                          <?php endif; ?>
                        </span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php
                        $moneyIn = (float)($p['total_budget'] ?? 0);
                        echo $moneyIn > 0 ? '$' . number_format($moneyIn, 2) : '—';
                      ?>
                    </td>
                    <td>
                      <?php
                        $moneyOut = (float)($p['money_out'] ?? 0);
                        $isNonProfitable = $moneyIn > 0 && $moneyOut > ($moneyIn * 0.6);
                        echo $moneyOut > 0 ? '$' . number_format($moneyOut, 2) : '—';
                      ?>
                    </td>
                    <td>
                      <?php
                        $profit = $moneyIn - $moneyOut;
                        $profitClass = $profit > 0 ? 'text-success' : ($profit < 0 ? 'text-danger' : '');
                        $profitDisplay = $moneyIn > 0 ? '<span class="' . $profitClass . '">$' . number_format($profit, 2) . '</span>' : '—';
                        echo $isNonProfitable ? '<span style="color:#dc2626;font-weight:bold;" title="Non Profitable: costs exceed 60%">⚠️ ' . $profitDisplay . '</span>' : $profitDisplay;
                      ?>
                    </td>
                    <?php 
                      $edit_roles = ['admin', 'pm'];
                      if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $edit_roles)): 
                    ?>
                    <td style="text-align:right;">
                      <button type="button" class="btn-secondary edit-project-btn" style="padding:4px 10px;font-size:12px;border-radius:999px;"
                          data-id="<?php echo (int)$p['id']; ?>"
                          data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>"
                          data-client="<?php echo htmlspecialchars($p['client_name'] ?? '', ENT_QUOTES); ?>"
                          data-city="<?php echo htmlspecialchars($p['city'] ?? '', ENT_QUOTES); ?>"
                          data-project-type="<?php echo htmlspecialchars($p['project_type'] ?? '', ENT_QUOTES); ?>"
                          data-address="<?php echo htmlspecialchars($p['address'] ?? '', ENT_QUOTES); ?>"
                          data-phone="<?php echo htmlspecialchars($p['phone'] ?? '', ENT_QUOTES); ?>"
                          data-email="<?php echo htmlspecialchars($p['email'] ?? '', ENT_QUOTES); ?>"
                          data-project-manager="<?php echo htmlspecialchars($p['project_manager'] ?? '', ENT_QUOTES); ?>"
                          data-scope="<?php echo htmlspecialchars($p['scope_of_work'] ?? '', ENT_QUOTES); ?>"
                          data-status="<?php echo htmlspecialchars($p['status'] ?? '', ENT_QUOTES); ?>"
                          data-budget="<?php echo htmlspecialchars($p['total_budget'] ?? '', ENT_QUOTES); ?>"
                          data-invoice="<?php echo htmlspecialchars($p['invoice_number'] ?? '', ENT_QUOTES); ?>"
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
      <button type="button" class="modal-tab" data-tab="vendors">Vendors</button>
      <button type="button" class="modal-tab" data-tab="team">Team</button>
      <button type="button" class="modal-tab" data-tab="permits">Permits</button>
      <button type="button" class="modal-tab" data-tab="inspections">Inspections</button>
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
      
      <!-- VENDORS TAB -->
      <div id="tab-vendors" class="tab-pane">
        <div style="margin-bottom: 16px;">
          <div class="flex gap-2" style="display: flex; gap: 8px; align-items: center;">
            <select id="assign-vendor-select" class="form-control" style="flex: 1;">
              <option value="">-- Select vendor --</option>
            </select>
            <input type="number" id="assign-vendor-bid" class="form-control" placeholder="Bid $" style="width: 120px;" step="0.01" min="0">
            <button type="button" id="assign-vendor-btn" class="btn btn-primary btn-sm">
              <i class="fa-solid fa-plus"></i> Assign
            </button>
          </div>
        </div>
        <div id="project-vendors-list" class="vendors-list">
          <div class="empty-state">No vendors assigned to this project.</div>
        </div>
      </div>
      
      <!-- TEAM TAB -->
      <div id="tab-team" class="tab-pane">
        <div style="margin-bottom: 16px;">
          <div class="flex gap-2" style="display: flex; gap: 8px; align-items: center;">
            <select id="assign-user-select" class="form-control" style="flex: 1;">
              <option value="">-- Select user --</option>
            </select>
            <input type="text" id="assign-user-role" class="form-control" placeholder="Role (e.g., PM)" style="width: 150px;">
            <button type="button" id="assign-user-btn" class="btn btn-primary btn-sm">
              <i class="fa-solid fa-plus"></i> Assign
            </button>
          </div>
        </div>
        <div id="project-team-list" class="team-list">
          <div class="empty-state">No team members assigned to this project.</div>
        </div>
      </div>
      
      <!-- PERMITS TAB -->
      <div id="tab-permits" class="tab-pane">
        <div id="detail-project-permits">
          <div class="empty-state">Click to load permits...</div>
        </div>
      </div>
      
      <!-- INSPECTIONS TAB -->
      <div id="tab-inspections" class="tab-pane">
        <div id="detail-project-inspections">
          <div class="empty-state">Click to load inspections...</div>
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
  <div class="modal-content" style="max-width: 700px;">
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
        <label for="edit-client">Client / Homeowner</label>
        <input type="text" name="edit_client_name" id="edit-client">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="edit-city">City</label>
          <input type="text" name="edit_city" id="edit-city" list="project-city-list" placeholder="Select or type city">
        </div>

        <div class="form-group">
          <label for="edit-project-type">Project Type</label>
          <input type="text" name="edit_project_type" id="edit-project-type" list="project-type-list" placeholder="Select or type type">
        </div>
      </div>

      <div class="form-group">
        <label for="edit-address">Property Address</label>
        <input type="text" name="edit_address" id="edit-address">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="edit-phone">Phone</label>
          <input type="tel" name="edit_phone" id="edit-phone">
        </div>

        <div class="form-group">
          <label for="edit-email">Email</label>
          <input type="email" name="edit_email" id="edit-email">
        </div>
      </div>

      <div class="form-group">
        <label for="edit-project-manager">Project Manager</label>
        <select name="edit_project_manager" id="edit-project-manager">
          <option value="">Select manager</option>
          <?php foreach ($usersList as $user): ?>
          <option value="<?php echo htmlspecialchars($user['username']); ?>">
            <?php echo htmlspecialchars($user['username']); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="edit-scope">Scope of Work</label>
        <textarea name="edit_scope" id="edit-scope" rows="2"></textarea>
      </div>

      <div class="form-group">
        <label for="edit-status">Status</label>
        <select name="edit_status" id="edit-status">
          <option value="Signed">Signed</option>
          <option value="Starting Soon">Starting Soon</option>
          <option value="Active">Active</option>
          <option value="Waiting on Permit">Waiting on Permit</option>
          <option value="Waiting on Materials">Waiting on Materials</option>
          <option value="On Hold">On Hold</option>
          <option value="Completed">Completed</option>
          <option value="Cancelled">Cancelled</option>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="edit-budget">Total Agreement Amount</label>
          <input type="number" step="0.01" name="edit_budget" id="edit-budget" placeholder="0.00">
        </div>

        <div class="form-group">
          <label for="edit-invoice">Invoice #</label>
          <input type="text" name="edit_invoice" id="edit-invoice">
        </div>
        
        <div class="form-group">
          <label for="edit-invoice-pdf">Invoice PDF</label>
          <input type="file" name="edit_invoice_pdf" id="edit-invoice-pdf" accept=".pdf">
          <input type="hidden" name="edit_invoice_pdf_existing" id="edit-invoice-pdf-existing" value="">
          <div id="current-invoice-pdf" class="text-muted" style="font-size: 12px; margin-top: 4px;"></div>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="edit-start">Start Date</label>
          <input type="date" name="edit_start_date" id="edit-start">
        </div>

        <div class="form-group">
          <label for="edit-end">Target Completion</label>
          <input type="date" name="edit_end_date" id="edit-end">
        </div>
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

<!-- Datalists for city/project_type autocomplete -->
<datalist id="project-city-list">
    <?php 
    $defaultCities = ['Phoenix', 'Scottsdale', 'Tempe', 'Chandler', 'Mesa', 'Gilbert', 'Peoria', 'Glendale', 'Surprise', 'Avondale', 'Goodyear', 'Queen Creek'];
    $allCities = array_unique(array_merge($defaultCities, $cityOptions));
    foreach ($allCities as $city): 
    ?>
    <option value="<?php echo htmlspecialchars($city); ?>">
    <?php endforeach; ?>
</datalist>

<datalist id="project-type-list">
    <?php 
    $defaultTypes = ['Kitchen', 'Bathroom', 'Addition', 'Roofing', 'Flooring', 'Windows', 'Doors', 'HVAC', 'Electrical', 'Plumbing', 'Painting', 'Landscaping', 'Pool', 'Garage', 'ADU'];
    $allTypes = array_unique(array_merge($defaultTypes, $projectTypeOptions));
    foreach ($allTypes as $type): 
    ?>
    <option value="<?php echo htmlspecialchars($type); ?>">
    <?php endforeach; ?>
</datalist>

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
        <label for="create-client">Client / Homeowner</label>
        <input type="text" name="client_name" id="create-client" placeholder="Client or homeowner name">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="create-city">City</label>
          <input type="text" name="city" id="create-city" list="project-city-list" placeholder="Select or type city">
        </div>
        
        <div class="form-group">
          <label for="create-project-type">Project Type</label>
          <input type="text" name="project_type" id="create-project-type" list="project-type-list" placeholder="Select or type type">
        </div>
      </div>

      <div class="form-group">
        <label for="create-address">Property Address</label>
        <input type="text" name="address" id="create-address" placeholder="Street address">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="create-phone">Phone</label>
          <input type="tel" name="phone" id="create-phone" placeholder="(555) 555-5555">
        </div>

        <div class="form-group">
          <label for="create-email">Email</label>
          <input type="email" name="email" id="create-email" placeholder="email@example.com">
        </div>
      </div>

      <div class="form-group">
        <label for="create-project-manager">Project Manager</label>
        <select name="project_manager" id="create-project-manager">
          <option value="">Select manager</option>
          <?php foreach ($usersList as $user): ?>
          <option value="<?php echo htmlspecialchars($user['username']); ?>">
            <?php echo htmlspecialchars($user['username']); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="create-scope">Scope of Work</label>
        <textarea name="scope_of_work" id="create-scope" rows="3" placeholder="Describe the scope of work..."></textarea>
      </div>

      <div class="form-group">
        <label for="create-status">Status</label>
        <select name="status" id="create-status">
          <option value="Signed">Signed</option>
          <option value="Starting Soon">Starting Soon</option>
          <option value="Active">Active</option>
          <option value="Waiting on Permit">Waiting on Permit</option>
          <option value="Waiting on Materials">Waiting on Materials</option>
          <option value="On Hold">On Hold</option>
          <option value="Completed">Completed</option>
          <option value="Cancelled">Cancelled</option>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="create-budget">Total Agreement Amount</label>
          <input type="number" step="0.01" name="total_budget" id="create-budget" placeholder="0.00" min="0">
        </div>

        <div class="form-group">
          <label for="create-invoice">Invoice #</label>
          <input type="text" name="invoice_number" id="create-invoice" placeholder="INV-001">
        </div>
        
        <div class="form-group">
          <label for="create-invoice-pdf">Invoice PDF</label>
          <input type="file" name="invoice_pdf" id="create-invoice-pdf" accept=".pdf">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="create-start">Start Date</label>
          <input type="date" name="start_date" id="create-start">
        </div>

        <div class="form-group">
          <label for="create-end">Target Completion</label>
          <input type="date" name="end_date" id="create-end">
        </div>
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

  // Auto-open create modal if create=1 in URL
  if (createModal && window.location.search.includes('create=1')) {
    createModal.style.display = 'flex';
  }
  
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
    var createNameInput = document.getElementById('create-name');
    if (createNameInput) {
      createNameInput.addEventListener('blur', function() {
        var formGroup = this.closest('.form-group');
        if (!this.value.trim() && this.value.length > 0) {
          formGroup.classList.add('error');
        } else {
          formGroup.classList.remove('error');
        }
      });
    }
  }

  // Edit project modal
  var editModal = document.getElementById('edit-project-modal');
  var editCloseBtn = document.getElementById('edit-project-close');
  var editForm = document.getElementById('edit-project-form');
  var editDeleteBtn = document.getElementById('edit-project-delete');

  document.querySelectorAll('.edit-project-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      var editProjectId = document.getElementById('edit-project-id');
      var editName = document.getElementById('edit-name');
      var editClient = document.getElementById('edit-client');
      var editCity = document.getElementById('edit-city');
      var editProjectType = document.getElementById('edit-project-type');
      var editAddress = document.getElementById('edit-address');
      var editPhone = document.getElementById('edit-phone');
      var editEmail = document.getElementById('edit-email');
      var editProjectManager = document.getElementById('edit-project-manager');
      var editScope = document.getElementById('edit-scope');
      var editStatus = document.getElementById('edit-status');
      var editBudget = document.getElementById('edit-budget');
      var editInvoice = document.getElementById('edit-invoice');
      var editStart = document.getElementById('edit-start');
      var editEnd = document.getElementById('edit-end');
      var editNotes = document.getElementById('edit-notes');
      
      if (editProjectId) editProjectId.value = btn.getAttribute('data-id') || '';
      if (editName) editName.value = btn.getAttribute('data-name') || '';
      if (editClient) editClient.value = btn.getAttribute('data-client') || '';
      if (editCity) editCity.value = btn.getAttribute('data-city') || '';
      if (editProjectType) editProjectType.value = btn.getAttribute('data-project-type') || '';
      if (editAddress) editAddress.value = btn.getAttribute('data-address') || '';
      if (editPhone) editPhone.value = btn.getAttribute('data-phone') || '';
      if (editEmail) editEmail.value = btn.getAttribute('data-email') || '';
      if (editProjectManager) editProjectManager.value = btn.getAttribute('data-project-manager') || '';
      if (editScope) editScope.value = btn.getAttribute('data-scope') || '';
      if (editStatus) editStatus.value = btn.getAttribute('data-status') || 'Signed';
      if (editBudget) editBudget.value = btn.getAttribute('data-budget') || '';
      if (editInvoice) editInvoice.value = btn.getAttribute('data-invoice') || '';
      if (editStart) editStart.value = btn.getAttribute('data-start') || '';
      if (editEnd) editEnd.value = btn.getAttribute('data-end') || '';
      if (editNotes) editNotes.value = btn.getAttribute('data-notes') || '';
      if (editModal) editModal.style.display = 'flex';
    });
  });

  if (editCloseBtn) editCloseBtn.addEventListener('click', function(e) { e.preventDefault(); if (editModal) editModal.style.display = 'none'; });
  if (editModal) editModal.addEventListener('click', function(e) { if (e.target === editModal && editModal) editModal.style.display = 'none'; });

  if (editDeleteBtn && editForm) {
    editDeleteBtn.addEventListener('click', function() {
      if (confirm('Delete this project? This cannot be undone.')) {
        var editProjectId = document.getElementById('edit-project-id');
        tableAction.value = 'delete_project';
        if (editProjectId) {
          var deleteInput = document.createElement('input');
          deleteInput.type = 'hidden';
          deleteInput.name = 'project_id';
          deleteInput.value = editProjectId.value;
          tableForm.appendChild(deleteInput);
        }
        tableForm.submit();
      }
    });
  }

  // Reset tabs to Overview when closing modal
  function resetTabsToOverview() {
    document.querySelectorAll('.modal-tab').forEach(function(t) { t.classList.remove('active'); });
    document.querySelectorAll('.tab-pane').forEach(function(p) { p.classList.remove('active'); });
    var overviewTab = document.querySelector('.modal-tab[data-tab="overview"]');
    var overviewPane = document.getElementById('tab-overview');
    if (overviewTab) overviewTab.classList.add('active');
    if (overviewPane) overviewPane.classList.add('active');
  }

  // ESC closes modal
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      if (createModal) createModal.style.display = 'none';
      if (editModal) editModal.style.display = 'none';
      if (detailModal) {
        resetTabsToOverview();
        detailModal.style.display = 'none';
      }
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
    row.addEventListener('click', function(e) {
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
        var address = row.getAttribute('data-address') || '';
        var city = row.getAttribute('data-city') || '';
        var type = row.getAttribute('data-project-type') || '';
        var scope = row.getAttribute('data-scope') || '';
        var phone = row.getAttribute('data-phone') || '';
        var email = row.getAttribute('data-email') || '';
        var pm = row.getAttribute('data-project-manager') || '';
        var invoice = row.getAttribute('data-invoice') || '';
        
        var html = '<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">';
        html += '<div><strong>Status:</strong><br><span class="status-badge status-' + (status || 'planned').toLowerCase().replace(' ', '-') + '">' + (status || '-') + '</span></div>';
        html += '<div><strong>Project Type:</strong><br>' + (type || '-') + '</div>';
        html += '<div><strong>Client:</strong><br>' + (client || '-') + '</div>';
        html += '<div><strong>Project Manager:</strong><br>' + (pm || '-') + '</div>';
        html += '<div style="grid-column: span 2;"><strong>Property Address:</strong><br>' + (address || '-') + (city ? ', ' + city : '') + '</div>';
        html += '<div><strong>Phone:</strong><br>' + (phone || '-') + '</div>';
        html += '<div><strong>Email:</strong><br>' + (email || '-') + '</div>';
        html += '<div style="grid-column: span 2;"><strong>Scope of Work:</strong><br>' + (scope || '-') + '</div>';
        html += '<div><strong>Budget:</strong><br>' + (budget ? '$' + Number(budget).toLocaleString(undefined, {minimumFractionDigits: 2}) : '-') + '</div>';
        html += '<div><strong>Invoice #:</strong><br>' + (invoice || '-') + '</div>';
        html += '<div><strong>Start Date:</strong><br>' + (start || '-') + '</div>';
        html += '<div><strong>Target End:</strong><br>' + (end || '-') + '</div>';
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

  if (detailClose) detailClose.addEventListener('click', function(e) { e.preventDefault(); if (detailModal) { resetTabsToOverview(); detailModal.style.display = 'none'; } });
  if (detailModal) detailModal.addEventListener('click', function(e) { if (e.target === detailModal && detailModal) { resetTabsToOverview(); detailModal.style.display = 'none'; } });

  // Tab switching
  document.querySelectorAll('.modal-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
      var tabId = tab.getAttribute('data-tab');
      
      // Update active tab button
      document.querySelectorAll('.modal-tab').forEach(function(t) { t.classList.remove('active'); });
      tab.classList.add('active');
      
      // Show corresponding content
      document.querySelectorAll('.tab-pane').forEach(function(pane) { pane.classList.remove('active'); });
      var targetTab = document.getElementById('tab-' + tabId);
      if (targetTab) targetTab.classList.add('active');
    });
  });

// Edit button from detail modal
  if (detailEditBtn) {
    detailEditBtn.addEventListener('click', function() {
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
        
        var editProjectId = document.getElementById('edit-project-id');
        var editName = document.getElementById('edit-name');
        var editClient = document.getElementById('edit-client');
        var editStatus = document.getElementById('edit-status');
        var editBudget = document.getElementById('edit-budget');
        var editStart = document.getElementById('edit-start');
        var editEnd = document.getElementById('edit-end');
        var editNotes = document.getElementById('edit-notes');
        
        if (editProjectId) editProjectId.value = currentRow.getAttribute('data-id') || '';
        if (editName) editName.value = currentRow.getAttribute('data-name') || '';
        if (editClient) editClient.value = currentRow.getAttribute('data-client') || '';
        if (editStatus) editStatus.value = currentRow.getAttribute('data-status') || 'Planned';
        if (editBudget) editBudget.value = currentRow.getAttribute('data-budget') || '';
        if (editStart) editStart.value = currentRow.getAttribute('data-start') || '';
        if (editEnd) editEnd.value = currentRow.getAttribute('data-end') || '';
        if (editNotes) editNotes.value = currentRow.getAttribute('data-notes') || '';
        
        if (editModal) editModal.style.display = 'flex';
      }
    });
  }
  
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
  
  // ============ VENDORS & TEAM (OPTIMIZED) ============
  
  // Cache for select options (vendors and users don't change often)
  var cache = {
    vendors: null,
    users: null,
    projectVendors: {},
    projectTeam: {}
  };
  
  // Load available vendors for select (with cache)
  function loadVendorsOptions() {
    if (cache.vendors) {
      populateVendorSelect(cache.vendors);
      return Promise.resolve();
    }
    return fetch('api/vendors.php')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success && data.vendors) {
          cache.vendors = data.vendors;
          populateVendorSelect(data.vendors);
        }
      });
  }
  
  function populateVendorSelect(vendors) {
    var select = document.getElementById('assign-vendor-select');
    select.innerHTML = '<option value="">-- Select vendor --</option>';
    vendors.forEach(function(v) {
      var opt = document.createElement('option');
      opt.value = v.id;
      opt.textContent = v.name;
      select.appendChild(opt);
    });
  }
  
  // Load available users for select (with cache)
  function loadUsersOptions() {
    if (cache.users) {
      populateUserSelect(cache.users);
      return Promise.resolve();
    }
    return fetch('api/users.php')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success && data.users) {
          cache.users = data.users;
          populateUserSelect(data.users);
        }
      });
  }
  
  function populateUserSelect(users) {
    var select = document.getElementById('assign-user-select');
    select.innerHTML = '<option value="">-- Select user --</option>';
    users.forEach(function(u) {
      var opt = document.createElement('option');
      opt.value = u.id;
      opt.textContent = u.username + ' (' + u.role + ')';
      select.appendChild(opt);
    });
  }
  
  // Load vendors assigned to project (with cache per project)
  function loadProjectVendors() {
    if (!currentProjectId) return;
    
    // Return cached if exists
    if (cache.projectVendors[currentProjectId]) {
      renderProjectVendors(cache.projectVendors[currentProjectId]);
      return Promise.resolve();
    }
    
    return fetch('api/project_vendors.php?project_id=' + currentProjectId)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success && data.vendors) {
          cache.projectVendors[currentProjectId] = data.vendors;
          renderProjectVendors(data.vendors);
        } else {
          cache.projectVendors[currentProjectId] = [];
          document.getElementById('project-vendors-list').innerHTML = '<div class="empty-state">No vendors assigned to this project.</div>';
        }
      });
  }
  
  function renderProjectVendors(vendors) {
    var container = document.getElementById('project-vendors-list');
    if (vendors.length > 0) {
      var html = '<table class="table table-striped"><thead><tr><th>Vendor</th><th>Total Bid</th><th>Total Paid</th><th>Action</th></tr></thead><tbody>';
      var totalBid = 0;
      var totalPaid = 0;
      vendors.forEach(function(v) {
        var assignedDate = v.assigned_at ? v.assigned_at.split(' ')[0] : '-';
        var bidAmount = v.bid_amount ? parseFloat(v.bid_amount) : 0;
        var paidAmount = v.total_paid ? parseFloat(v.total_paid) : 0;
        totalBid += bidAmount;
        totalPaid += paidAmount;
        var bidDisplay = bidAmount > 0 ? '$' + bidAmount.toLocaleString(undefined, {minimumFractionDigits: 2}) : '-';
        var paidDisplay = paidAmount > 0 ? '$' + paidAmount.toLocaleString(undefined, {minimumFractionDigits: 2}) : '$0.00';
        html += '<tr><td>' + (v.vendor_name || 'Vendor #' + v.vendor_id) + '</td>';
        html += '<td><span class="editable-bid" data-id="' + v.id + '" data-bid="' + bidAmount + '" style="cursor:pointer;">' + bidDisplay + '</span></td>';
        html += '<td>' + paidDisplay + '</td>';
        html += '<td><button class="btn btn-danger btn-sm" onclick="removeVendor(' + v.id + ')">Remove</button></td></tr>';
      });
      html += '</tbody><tfoot><tr><td><strong>Total</strong></td><td><strong>$' + totalBid.toLocaleString(undefined, {minimumFractionDigits: 2}) + '</strong></td><td><strong>$' + totalPaid.toLocaleString(undefined, {minimumFractionDigits: 2}) + '</strong></td><td></td></tr></tfoot></table>';
      container.innerHTML = html;
      
      // Add click handlers for editable bid amounts
      container.querySelectorAll('.editable-bid').forEach(function(el) {
        el.addEventListener('click', function() {
          var id = this.dataset.id;
          var currentBid = this.dataset.bid || 0;
          var newBid = prompt('Enter bid amount for this vendor:', currentBid);
          if (newBid !== null && !isNaN(parseFloat(newBid))) {
            updateVendorBidAmount(id, parseFloat(newBid));
          }
        });
      });
    } else {
      container.innerHTML = '<div class="empty-state">No vendors assigned to this project.</div>';
    }
  }
  
  function updateVendorBidAmount(id, bidAmount) {
    fetch('api/project_vendors.php', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id, bid_amount: bidAmount })
    }).then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        loadProjectVendors();
      } else {
        alert('Failed to update bid amount: ' + data.error);
      }
    });
  }
  
  function updateVendorPaidAmount(id, amount) {
    fetch('api/project_vendors.php', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id, paid_amount: parseFloat(amount) || 0 })
    }).then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.success) {
        alert('Failed to update: ' + data.error);
      }
    });
  }
  
  // Load team members assigned to project (with cache per project)
  function loadProjectTeam() {
    if (!currentProjectId) return;
    
    // Return cached if exists
    if (cache.projectTeam[currentProjectId]) {
      renderProjectTeam(cache.projectTeam[currentProjectId]);
      return Promise.resolve();
    }
    
    return fetch('api/project_team.php?project_id=' + currentProjectId)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success && data.team) {
          cache.projectTeam[currentProjectId] = data.team;
          renderProjectTeam(data.team);
        } else {
          cache.projectTeam[currentProjectId] = [];
          document.getElementById('project-team-list').innerHTML = '<div class="empty-state">No team members assigned to this project.</div>';
        }
      });
  }
  
  function renderProjectTeam(team) {
    var container = document.getElementById('project-team-list');
    if (team.length > 0) {
      var html = '<table class="table table-striped"><thead><tr><th>User</th><th>Role</th><th>Assigned</th><th>Action</th></tr></thead><tbody>';
      team.forEach(function(t) {
        html += '<tr><td>' + (t.username || 'User #' + t.user_id) + '</td>';
        html += '<td>' + (t.assigned_role || t.user_role || 'PM') + '</td>';
        html += '<td>' + t.assigned_at + '</td>';
        html += '<td><button class="btn btn-danger btn-sm" onclick="removeTeamMember(' + t.id + ')">Remove</button></td></tr>';
      });
      html += '</tbody></table>';
      container.innerHTML = html;
    } else {
      container.innerHTML = '<div class="empty-state">No team members assigned to this project.</div>';
    }
  }
  
  // Preload options once when page loads
  loadVendorsOptions();
  loadUsersOptions();
  
  // Track which tabs have been loaded
  var loadedTabs = { vendors: false, team: false, timeline: false, permits: false, inspections: false };
  
  // Load data when Vendors or Team tab is clicked (only first time)
  document.querySelectorAll('.modal-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
      var tabId = tab.getAttribute('data-tab');
      if (tabId === 'vendors' && !loadedTabs.vendors) {
        loadedTabs.vendors = true;
        loadProjectVendors();
      } else if (tabId === 'team' && !loadedTabs.team) {
        loadedTabs.team = true;
        loadProjectTeam();
      } else if (tabId === 'timeline' && !loadedTabs.timeline) {
        loadedTabs.timeline = true;
        loadProjectTimeline();
      } else if (tabId === 'permits' && !loadedTabs.permits) {
        loadedTabs.permits = true;
        loadProjectPermits();
      } else if (tabId === 'inspections' && !loadedTabs.inspections) {
        loadedTabs.inspections = true;
        loadProjectInspections();
      }
    });
  });
  
  // Reset cache when opening different project
  var lastProjectId = null;
  function resetProjectCache(projectId) {
    if (lastProjectId !== projectId) {
      lastProjectId = projectId;
      cache.projectVendors[projectId] = null;
      cache.projectTeam[projectId] = null;
      loadedTabs.vendors = false;
      loadedTabs.team = false;
      loadedTabs.timeline = false;
      loadedTabs.permits = false;
      loadedTabs.inspections = false;
    }
  }
  
  // Load Timeline (audit log for this project)
  function loadProjectTimeline() {
    if (!currentProjectId) return;
    
    var container = document.getElementById('detail-project-timeline');
    if (!container) return;
    
    container.innerHTML = '<div class="loading">Loading timeline...</div>';
    
    fetch('api/project_timeline.php?project_id=' + currentProjectId)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success && data.timeline && data.timeline.length > 0) {
          var html = '<div class="timeline">';
          data.timeline.forEach(function(item) {
            var icon = '';
            if (item.action_type === 'create') icon = '<i class="fa-solid fa-plus-circle text-success"></i>';
            else if (item.action_type === 'update') icon = '<i class="fa-solid fa-pen text-warning"></i>';
            else if (item.action_type === 'delete') icon = '<i class="fa-solid fa-trash text-danger"></i>';
            else icon = '<i class="fa-solid fa-circle text-info"></i>';
            
            html += '<div class="timeline-item">';
            html += '<div class="timeline-date">' + item.created_at + '</div>';
            html += '<div class="timeline-content">' + icon + ' <strong>' + (item.username || 'System') + '</strong> ' + item.action_type + 'd ' + item.entity_type;
            if (item.entity_name) html += ': "' + item.entity_name + '"';
            html += '</div></div>';
          });
          html += '</div>';
          container.innerHTML = html;
        } else {
          container.innerHTML = '<div class="empty-state">No activity recorded yet.</div>';
        }
      })
      .catch(function() {
        container.innerHTML = '<div class="error">Failed to load timeline.</div>';
      });
  }
  
  // Load Permits for this project
  function loadProjectPermits() {
    if (!currentProjectId) return;
    
    var container = document.getElementById('detail-project-permits');
    if (!container) return;
    
    container.innerHTML = '<div class="loading">Loading permits...</div>';
    
    fetch('api/permits.php?project_id=' + currentProjectId)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success && data.permits && data.permits.length > 0) {
          var html = '<table class="table table-striped"><thead><tr><th>City</th><th>Status</th><th>Submitted By</th><th>Permit #</th><th>Submission</th><th>Actions</th></tr></thead><tbody>';
          data.permits.forEach(function(p) {
            var statusClass = p.status === 'approved' ? 'status-success' : p.status === 'rejected' ? 'status-danger' : 'status-warning';
            html += '<tr>';
            html += '<td>' + (p.city || '-') + '</td>';
            html += '<td><span class="status-badge ' + statusClass + '">' + (p.status || 'not_started') + '</span></td>';
            html += '<td>' + (p.submitted_by || '-') + '</td>';
            html += '<td>' + (p.permit_number || '-') + '</td>';
            html += '<td>' + (p.submission_date || '-') + '</td>';
            html += '<td><a href="permits.php?project_id=' + currentProjectId + '" class="btn btn-sm btn-secondary">View</a></td>';
            html += '</tr>';
          });
          html += '</tbody></table>';
          container.innerHTML = html;
        } else {
          container.innerHTML = '<div class="empty-state">No permits for this project. <a href="permits.php?project_id=' + currentProjectId + '">Add Permit</a></div>';
        }
      })
      .catch(function() {
        container.innerHTML = '<div class="error">Failed to load permits.</div>';
      });
  }
  
  // Load Inspections for this project
  function loadProjectInspections() {
    if (!currentProjectId) return;
    
    var container = document.getElementById('detail-project-inspections');
    if (!container) return;
    
    container.innerHTML = '<div class="loading">Loading inspections...</div>';
    
    fetch('api/inspections.php?project_id=' + currentProjectId)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success && data.inspections && data.inspections.length > 0) {
          var html = '<table class="table table-striped"><thead><tr><th>Type</th><th>Status</th><th>Scheduled</th><th>Requested By</th><th>Actions</th></tr></thead><tbody>';
          data.inspections.forEach(function(i) {
            var statusClass = i.status === 'passed' ? 'status-success' : i.status === 'failed' ? 'status-danger' : 'status-warning';
            html += '<tr>';
            html += '<td>' + (i.inspection_type || '-') + '</td>';
            html += '<td><span class="status-badge ' + statusClass + '">' + (i.status || 'not_scheduled') + '</span></td>';
            html += '<td>' + (i.scheduled_date || '-') + '</td>';
            html += '<td>' + (i.requested_by || '-') + '</td>';
            html += '<td><a href="inspections.php?project_id=' + currentProjectId + '" class="btn btn-sm btn-secondary">View</a></td>';
            html += '</tr>';
          });
          html += '</tbody></table>';
          container.innerHTML = html;
        } else {
          container.innerHTML = '<div class="empty-state">No inspections for this project. <a href="inspections.php?project_id=' + currentProjectId + '">Add Inspection</a></div>';
        }
      })
      .catch(function() {
        container.innerHTML = '<div class="error">Failed to load inspections.</div>';
      });
  }
  
  // Assign vendor to project
  window.assignVendor = function() {
    var select = document.getElementById('assign-vendor-select');
    var vendorId = select.value;
    if (!vendorId || !currentProjectId) {
      alert('Select a vendor first');
      return;
    }
    fetch('api/project_vendors.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ project_id: currentProjectId, vendor_id: vendorId })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        // Update cache and re-render
        if (!cache.projectVendors[currentProjectId]) cache.projectVendors[currentProjectId] = [];
        cache.projectVendors[currentProjectId].push(data);
        loadProjectVendors();
        select.value = '';
      } else {
        alert(data.error || 'Error assigning vendor');
      }
    });
  };
  
  // Remove vendor from project
  window.removeVendor = function(id) {
    if (!confirm('Remove this vendor from the project?')) return;
    fetch('api/project_vendors.php', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        // Update cache and re-render
        if (cache.projectVendors[currentProjectId]) {
          cache.projectVendors[currentProjectId] = cache.projectVendors[currentProjectId].filter(function(v) { return v.id !== id; });
        }
        loadProjectVendors();
      }
    });
  };
  
  // Assign user to project
  window.assignTeamMember = function() {
    var select = document.getElementById('assign-user-select');
    var roleInput = document.getElementById('assign-user-role');
    var userId = select.value;
    var role = roleInput.value || 'pm';
    if (!userId || !currentProjectId) {
      alert('Select a user first');
      return;
    }
    fetch('api/project_team.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ project_id: currentProjectId, user_id: userId, role: role })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        // Update cache and re-render
        if (!cache.projectTeam[currentProjectId]) cache.projectTeam[currentProjectId] = [];
        cache.projectTeam[currentProjectId].push(data);
        loadProjectTeam();
        select.value = '';
        roleInput.value = '';
      } else {
        alert(data.error || 'Error assigning team member');
      }
    });
  };
  
  // Remove team member from project
  window.removeTeamMember = function(id) {
    if (!confirm('Remove this team member from the project?')) return;
    fetch('api/project_team.php', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        // Update cache and re-render
        if (cache.projectTeam[currentProjectId]) {
          cache.projectTeam[currentProjectId] = cache.projectTeam[currentProjectId].filter(function(t) { return t.id !== id; });
        }
        loadProjectTeam();
      }
    });
  };
  
  // Button event listeners
  var assignVendorBtn = document.getElementById('assign-vendor-btn');
  if (assignVendorBtn) assignVendorBtn.addEventListener('click', assignVendor);
  
  var assignUserBtn = document.getElementById('assign-user-btn');
  if (assignUserBtn) assignUserBtn.addEventListener('click', assignTeamMember);
  
  // Update cache when opening modal
  if (detailModal) {
    var observer = new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        if (mutation.attributeName === 'style' && detailModal.style.display !== 'none') {
          var detailNameText = detailName ? detailName.textContent : '';
          document.querySelectorAll('.project-row').forEach(function(row) {
            if (row.getAttribute('data-name') === detailNameText) {
              var newProjectId = row.getAttribute('data-id');
              resetProjectCache(newProjectId);
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