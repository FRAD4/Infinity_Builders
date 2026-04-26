<?php
/**
 * inspections.php - Inspections management page
 */

$pageTitle = 'Inspections';
$currentPage = 'inspections';

require_once 'partials/init.php';

// Role-based access
$userRole = $_SESSION['user_role'] ?? 'user';
$allowedRoles = ['admin', 'pm', 'accounting', 'estimator'];
if (!in_array($userRole, $allowedRoles, true)) {
    http_response_code(403);
    die('Access denied. Only project team members can access this page.');
}

// Handle success messages from redirects
$message = '';
if (isset($_GET['message'])) {
    $messages = [
        'created' => 'Inspection created successfully.',
        'updated' => 'Inspection updated successfully.',
        'deleted' => 'Inspection deleted successfully.'
    ];
    $message = $messages[$_GET['message']] ?? '';
}

// Generate CSRF token for forms
require_once 'includes/security.php';
$csrf_token = csrf_token_generate();

// Handle filters
$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'status' => $_GET['status'] ?? '',
    'city' => $_GET['city'] ?? '',
    'project_id' => $_GET['project_id'] ?? '',
    'permit_id' => $_GET['permit_id'] ?? ''
];

// Get all projects for dropdown
$projectsList = [];
try {
    $stmt = $pdo->query("SELECT id, name, city, project_manager, status FROM projects ORDER BY name ASC");
    $projectsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $projectsList = [];
}

// Get all permits for dropdown (linked to projects)
$permitsList = [];
try {
    $stmt = $pdo->query("SELECT p.id, p.permit_number, p.city, p.project_id, pr.name as project_name 
                         FROM permits p 
                         LEFT JOIN projects pr ON p.project_id = pr.id 
                         ORDER BY p.created_at DESC");
    $permitsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $permitsList = [];
}

// Build query for inspections with joins
$where = ['1=1'];
$params = [];

if (!empty($filters['search'])) {
    $where[] = "(i.inspection_type LIKE ? OR i.city LIKE ? OR pr.name LIKE ?)";
    $searchTerm = '%' . $filters['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($filters['status']) && $filters['status'] !== 'all') {
    $where[] = "i.status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['city']) && $filters['city'] !== 'all') {
    $where[] = "i.city = ?";
    $params[] = $filters['city'];
}

if (!empty($filters['project_id'])) {
    $where[] = "i.project_id = ?";
    $params[] = $filters['project_id'];
}

if (!empty($filters['permit_id'])) {
    $where[] = "i.permit_id = ?";
    $params[] = $filters['permit_id'];
}

$sql = "SELECT i.*, pr.name as project_name, pr.city as project_city, pr.project_manager,
               p.permit_number as permit_number
        FROM inspections i 
        LEFT JOIN projects pr ON i.project_id = pr.id
        LEFT JOIN permits p ON i.permit_id = p.id";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY i.scheduled_date DESC, i.created_at DESC";

$inspections = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $inspections = [];
}

// Calculate stats
$stats = [
    'total' => count($inspections),
    'not_scheduled' => 0,
    'requested' => 0,
    'scheduled' => 0,
    'completed' => 0,
    'passed' => 0,
    'failed' => 0,
    'reinspection_needed' => 0
];

$statusMap = [
    'not_scheduled' => 'not_scheduled',
    'requested' => 'requested',
    'scheduled' => 'scheduled',
    'completed' => 'completed',
    'passed' => 'passed',
    'failed' => 'failed',
    'reinspection_needed' => 'reinspection_needed'
];

foreach ($inspections as $i) {
    $mapped = $statusMap[$i['status']] ?? 'not_scheduled';
    if (isset($stats[$mapped])) {
        $stats[$mapped]++;
    }
}

// Get unique cities for filter
$cities = [];
foreach ($projectsList as $pr) {
    if (!empty($pr['city'])) {
        $cities[$pr['city']] = true;
    }
}
$cities = array_keys($cities);
sort($cities);

// Get users for requester dropdown
$usersList = [];
try {
    $stmt = $pdo->query("SELECT id, username FROM users ORDER BY username");
    $usersList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $usersList = [];
}

// Status options
$statusOptions = [
    'not_scheduled' => 'Not Scheduled',
    'requested' => 'Requested',
    'scheduled' => 'Scheduled',
    'completed' => 'Completed',
    'passed' => 'Passed',
    'failed' => 'Failed',
    'reinspection_needed' => 'Reinspection Needed'
];

// Inspection types
$inspectionTypes = [
    'Framing',
    'Electrical',
    'Plumbing',
    'HVAC',
    'Building',
    'Fire Safety',
    'ADA Compliance',
    'Final',
    'Other'
];

// City options
$cityOptions = ['Scottsdale', 'Phoenix', 'Tempe', 'Chandler', 'Mesa', 'Gilbert', 'Peoria', 'Glendale', 'Surprise', 'Avondale', 'Goodyear', 'Queen Creek'];
?>
<?php require_once 'partials/header.php'; ?>

<!-- Page Content -->
<div class="page-content">
    
    <!-- Stats Cards -->
    <div class="stats-grid" style="margin-bottom: 24px;">
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--primary-color);">
                <i class="fa-solid fa-clipboard-check"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Inspections</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #6b7280;">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['not_scheduled']; ?></div>
                <div class="stat-label">Not Scheduled</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #3b82f6;">
                <i class="fa-solid fa-paper-plane"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['requested']; ?></div>
                <div class="stat-label">Requested</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #8b5cf6;">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['scheduled']; ?></div>
                <div class="stat-label">Scheduled</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #10b981;">
                <i class="fa-solid fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['passed']; ?></div>
                <div class="stat-label">Passed</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #ef4444;">
                <i class="fa-solid fa-times-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['failed']; ?></div>
                <div class="stat-label">Failed</div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity / Changes Log -->
    <div class="card" style="margin-top: 20px;">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h3><i class="fa-solid fa-clock-rotate-left"></i> Recent Activity</h3>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="loadRecentChanges()">
                <i class="fa-solid fa-refresh"></i>
            </button>
        </div>
        <div class="card-body" style="padding: 12px;">
            <div id="recent-changes-list" style="max-height: 200px; overflow-y: auto;">
                <div class="text-center text-muted" style="padding: 20px;">
                    <i class="fa-solid fa-circle-notch fa-spin"></i> Loading...
                </div>
            </div>
        </div>
    </div>
    
    <!-- Message -->
    <?php if ($message): ?>
    <div class="alert alert-success" style="margin-bottom: 20px;">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <!-- Filters - Collapsible -->
    <div style="margin-top: 20px;">
      <button type="button" class="btn btn-secondary" onclick="document.getElementById('inspections-filters-panel').classList.toggle('hidden'); this.querySelector('i').classList.toggle('fa-chevron-down'); this.querySelector('i').classList.toggle('fa-chevron-up');">
        <i class="fa-solid fa-chevron-down"></i> Filters
        <?php if (!empty($filters['search']) || $filters['status'] !== 'all' || !empty($filters['city']) || !empty($filters['project_id'])): ?>
        <span style="background: var(--primary); color: white; border-radius: 50%; width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; margin-left: 6px;">
          <?php echo (int)(!empty($filters['search'])) + (int)($filters['status'] !== 'all') + (int)(!empty($filters['city'])) + (int)(!empty($filters['project_id'])); ?>
        </span>
        <?php endif; ?>
      </button>
    </div>

    <div id="inspections-filters-panel" class="card filter-bar hidden" style="margin-top: 10px; padding: 12px 16px;">
        <div class="filters-left">
            <!-- Search -->
            <div class="filter-search">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search inspections..." 
                       value="<?php echo htmlspecialchars($filters['search']); ?>">
            </div>
            
            <!-- Status Filter -->
            <select id="statusFilter" class="filter-select">
                <option value="all">All Statuses</option>
                <?php foreach ($statusOptions as $val => $label): ?>
                <option value="<?php echo $val; ?>" <?php echo $filters['status'] === $val ? 'selected' : ''; ?>>
                    <?php echo $label; ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <!-- City Filter -->
            <select id="cityFilter" class="filter-select">
                <option value="all">All Cities</option>
                <?php foreach ($cityOptions as $city): ?>
                <option value="<?php echo $city; ?>" <?php echo $filters['city'] === $city ? 'selected' : ''; ?>>
                    <?php echo $city; ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <!-- Project Filter -->
            <select id="projectFilter" class="filter-select">
                <option value="">All Projects</option>
                <?php foreach ($projectsList as $pr): ?>
                <option value="<?php echo $pr['id']; ?>" <?php echo $filters['project_id'] == $pr['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($pr['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Header + Add Button -->
    <div class="card" style="margin-top: 20px;">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
          <div>
            <h3>All Inspections</h3>
          </div>
          <div>
            <?php if (in_array($userRole, ['admin', 'pm', 'accounting', 'estimator'])): ?>
            <button class="btn btn-primary" onclick="openCreateModal()">
                <i class="fa-solid fa-plus"></i>
                Add Inspection
            </button>
            <?php endif; ?>
          </div>
        </div>
    </div>
    
    <!-- Inspections Table -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Type</th>
                    <th>City</th>
                    <th>Status</th>
                    <th>Scheduled</th>
                    <th>Requested By</th>
                    <th>Permit #</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="inspectionsTableBody">
                <?php if (empty($inspections)): ?>
                <tr>
                    <td colspan="8" class="empty-state">
                        <i class="fa-solid fa-clipboard-check"></i>
                        <p>No inspections found</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($inspections as $inspection): ?>
                <tr data-id="<?php echo $inspection['id']; ?>"
                    data-project-id="<?php echo $inspection['project_id']; ?>"
                    data-project-name="<?php echo htmlspecialchars($inspection['project_name'] ?? ''); ?>"
                    data-permit-id="<?php echo $inspection['permit_id']; ?>"
                    data-inspection-type="<?php echo htmlspecialchars($inspection['inspection_type'] ?? ''); ?>"
                    data-city="<?php echo htmlspecialchars($inspection['city'] ?? ''); ?>"
                    data-status="<?php echo $inspection['status']; ?>"
                    data-requested-by="<?php echo htmlspecialchars($inspection['requested_by'] ?? ''); ?>"
                    data-date-requested="<?php echo $inspection['date_requested'] ?? ''; ?>"
                    data-scheduled-date="<?php echo $inspection['scheduled_date'] ?? ''; ?>"
                    data-inspector-name="<?php echo htmlspecialchars($inspection['inspector_name'] ?? ''); ?>"
                    data-inspector-notes="<?php echo htmlspecialchars($inspection['inspector_notes'] ?? ''); ?>"
                    data-reinspection-needed="<?php echo $inspection['reinspection_needed']; ?>"
                    data-reinspection-date="<?php echo $inspection['reinspection_date'] ?? ''; ?>"
                    data-notes="<?php echo htmlspecialchars($inspection['notes'] ?? ''); ?>">
                    <td>
                        <?php if (!empty($inspection['project_name'])): ?>
                        <a href="projects.php?open=<?php echo $inspection['project_id']; ?>" class="project-link">
                            <?php echo htmlspecialchars($inspection['project_name']); ?>
                        </a>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($inspection['inspection_type'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($inspection['city'] ?? '—'); ?></td>
                    <td>
                        <?php 
                        $statusClass = [
                            'not_scheduled' => 'status-not_scheduled',
                            'requested' => 'status-requested',
                            'scheduled' => 'status-scheduled',
                            'completed' => 'status-completed',
                            'passed' => 'status-passed',
                            'failed' => 'status-failed',
                            'reinspection_needed' => 'status-reinspection_needed'
                        ];
                        $statusLabel = $statusOptions[$inspection['status']] ?? 'Unknown';
                        ?>
                        <span class="status-badge <?php echo $statusClass[$inspection['status']] ?? ''; ?>">
                            <?php echo $statusLabel; ?>
                        </span>
                    </td>
                    <td><?php echo $inspection['scheduled_date'] ? date('m/d/Y', strtotime($inspection['scheduled_date'])) : '—'; ?></td>
                    <td><?php echo htmlspecialchars($inspection['requested_by'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($inspection['permit_number'] ?? '—'); ?></td>
                    <td>
                        <div class="table-actions">
                            <button class="btn-icon" title="Edit" onclick="openEditModal(<?php echo $inspection['id']; ?>)">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <?php if ($userRole === 'admin'): ?>
                            <button class="btn-icon btn-danger" title="Delete" onclick="deleteInspection(<?php echo $inspection['id']; ?>)">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="inspectionModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Add Inspection</h2>
            <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        
        <form id="inspectionForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" id="inspectionId" name="inspection_id" value="">
            
            <div class="modal-body">
                <!-- Project Selection -->
                <div class="form-group">
                    <label for="projectId">Project *</label>
                    <select id="projectId" name="project_id" required>
                        <option value="">Select a project...</option>
                        <?php foreach ($projectsList as $pr): ?>
                        <option value="<?php echo $pr['id']; ?>" data-city="<?php echo htmlspecialchars($pr['city'] ?? ''); ?>">
                            <?php echo htmlspecialchars($pr['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Inspection Type -->
                <div class="form-group">
                    <label for="inspectionType">Inspection Type *</label>
                    <select id="inspectionType" name="inspection_type" required>
                        <option value="">Select type...</option>
                        <?php foreach ($inspectionTypes as $type): ?>
                        <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Permit Link (optional) -->
                <div class="form-group">
                    <label for="permitId">Linked Permit (optional)</label>
                    <select id="permitId" name="permit_id">
                        <option value="">No permit linked</option>
                        <?php foreach ($permitsList as $perm): ?>
                        <option value="<?php echo $perm['id']; ?>" data-project-id="<?php echo $perm['project_id']; ?>">
                            <?php echo htmlspecialchars($perm['permit_number'] ?? 'Permit #' . $perm['id']); ?> 
                            - <?php echo htmlspecialchars($perm['project_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- City -->
                <div class="form-group">
                    <label for="inspectionCity">City *</label>
                    <input type="text" id="inspectionCity" name="city" list="inspection-city-list" placeholder="Select or type city" required>
                    <datalist id="inspection-city-list">
                        <?php foreach ($cityOptions as $city): ?>
                        <option value="<?php echo $city; ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                
                <!-- Status -->
                <div class="form-group">
                    <label for="inspectionStatus">Status</label>
                    <select id="inspectionStatus" name="status">
                        <option value="not_scheduled" selected>Not Scheduled</option>
                        <?php foreach ($statusOptions as $val => $label): ?>
                        <?php if ($val !== 'not_scheduled'): ?>
                        <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Requested By -->
                <div class="form-group">
                    <label for="requestedBy">Requested By</label>
                    <select id="requestedBy" name="requested_by">
                        <option value="">Select person...</option>
                        <?php foreach ($usersList as $user): ?>
                        <option value="<?php echo htmlspecialchars($user['username']); ?>">
                            <?php echo htmlspecialchars($user['username']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Date Requested -->
                <div class="form-group">
                    <label for="dateRequested">Date Requested</label>
                    <input type="date" id="dateRequested" name="date_requested">
                </div>
                
                <!-- Scheduled Date -->
                <div class="form-group">
                    <label for="scheduledDate">Scheduled Date</label>
                    <input type="date" id="scheduledDate" name="scheduled_date">
                </div>
                
                <!-- Inspector Name -->
                <div class="form-group">
                    <label for="inspectorName">Inspector Name</label>
                    <input type="text" id="inspectorName" name="inspector_name" placeholder="Name of inspector...">
                </div>
                
                <!-- Inspector Notes -->
                <div class="form-group">
                    <label for="inspectorNotes">Inspector Notes</label>
                    <textarea id="inspectorNotes" name="inspector_notes" rows="3" placeholder="Notes from inspector..."></textarea>
                </div>
                
                <!-- Reinspection Needed -->
                <div class="form-group">
                    <label>Reinspection Needed?</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="reinspection_needed" value="no" checked> No
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="reinspection_needed" value="yes"> Yes
                        </label>
                    </div>
                </div>
                
                <!-- Reinspection Date -->
                <div class="form-group">
                    <label for="reinspectionDate">Reinspection Date</label>
                    <input type="date" id="reinspectionDate" name="reinspection_date">
                </div>
                
                <!-- Notes -->
                <div class="form-group">
                    <label for="inspectionNotes">Notes</label>
                    <textarea id="inspectionNotes" name="notes" rows="3" placeholder="Additional notes..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">Create Inspection</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h2>Delete Inspection</h2>
            <button type="button" class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete this inspection? This action cannot be undone.</p>
            <input type="hidden" id="deleteInspectionId" value="">
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
            <button type="button" class="btn btn-danger" onclick="confirmDelete()">Delete</button>
        </div>
    </div>
</div>

<script>
// Filter handling
const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const cityFilter = document.getElementById('cityFilter');
const projectFilter = document.getElementById('projectFilter');

function applyFilters() {
    const params = new URLSearchParams();
    if (searchInput.value) params.set('search', searchInput.value);
    if (statusFilter.value !== 'all') params.set('status', statusFilter.value);
    if (cityFilter.value !== 'all') params.set('city', cityFilter.value);
    if (projectFilter.value) params.set('project_id', projectFilter.value);
    window.location.href = 'inspections.php' + (params.toString() ? '?' + params.toString() : '');
}

searchInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') applyFilters(); });
statusFilter.addEventListener('change', applyFilters);
cityFilter.addEventListener('change', applyFilters);
projectFilter.addEventListener('change', applyFilters);

// Modal functions
const modal = document.getElementById('inspectionModal');
const deleteModal = document.getElementById('deleteModal');
const form = document.getElementById('inspectionForm');
const projectSelect = document.getElementById('projectId');
const permitSelect = document.getElementById('permitId');

// Filter permits dropdown based on selected project
projectSelect.addEventListener('change', function() {
    const selectedProjectId = this.value;
    const selectedOption = this.options[this.selectedIndex];
    const projectCity = selectedOption ? selectedOption.dataset.city : '';
    const citySelect = document.getElementById('inspectionCity');
    const permitOptions = permitSelect.querySelectorAll('option');
    
    // Auto-fill city from project
    if (projectCity && citySelect) {
        // Check if the city exists in the dropdown, if not add it temporarily
        let cityExists = false;
        for (let i = 0; i < citySelect.options.length; i++) {
            if (citySelect.options[i].value === projectCity) {
                cityExists = true;
                break;
            }
        }
        if (cityExists) {
            citySelect.value = projectCity;
        }
    }
    
    // Filter permits dropdown
    permitOptions.forEach(function(option) {
        if (option.value === '') return;
        const permitProjectId = option.dataset.projectId;
        if (!selectedProjectId || permitProjectId === selectedProjectId) {
            option.style.display = '';
        } else {
            option.style.display = 'none';
        }
    });
    
    // Reset permit selection when project changes
    permitSelect.value = '';
});

function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Add Inspection';
    document.getElementById('submitBtn').textContent = 'Create Inspection';
    form.reset();
    document.getElementById('inspectionId').value = '';
    
    // Show all permits when creating new inspection
    const permitOptions = permitSelect.querySelectorAll('option');
    permitOptions.forEach(function(option) {
        option.style.display = '';
    });
    
    modal.classList.add('active');
}

function openEditModal(id) {
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (!row) return;
    
    document.getElementById('modalTitle').textContent = 'Edit Inspection';
    document.getElementById('submitBtn').textContent = 'Save Changes';
    document.getElementById('inspectionId').value = id;
    
    // Fill form from data attributes
    document.getElementById('projectId').value = row.dataset.projectId || '';
    document.getElementById('inspectionType').value = row.dataset.inspectionType || '';
    document.getElementById('permitId').value = row.dataset.permitId || '';
    document.getElementById('inspectionCity').value = row.dataset.city || '';
    document.getElementById('inspectionStatus').value = row.dataset.status || 'not_scheduled';
    document.getElementById('requestedBy').value = row.dataset.requestedBy || '';
    document.getElementById('dateRequested').value = row.dataset.dateRequested || '';
    document.getElementById('scheduledDate').value = row.dataset.scheduledDate || '';
    document.getElementById('inspectorName').value = row.dataset.inspectorName || '';
    document.getElementById('inspectorNotes').value = row.dataset.inspectorNotes || '';
    document.querySelector(`input[name="reinspection_needed"][value="${row.dataset.reinspectionNeeded || 'no'}"]`).checked = true;
    document.getElementById('reinspectionDate').value = row.dataset.reinspectionDate || '';
    document.getElementById('inspectionNotes').value = row.dataset.notes || '';
    
    // Trigger permit filter based on selected project
    projectSelect.dispatchEvent(new Event('change'));
    
    modal.classList.add('active');
}

function closeModal() {
    modal.classList.remove('active');
}

// Delete functions
function deleteInspection(id) {
    document.getElementById('deleteInspectionId').value = id;
    deleteModal.classList.add('active');
}

function closeDeleteModal() {
    deleteModal.classList.remove('active');
}

async function confirmDelete() {
    const id = document.getElementById('deleteInspectionId').value;
    
    try {
        const response = await fetch('api/inspections.php?id=' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-Token': '<?php echo $csrf_token; ?>' }
        });
        
        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to delete inspection'));
        }
    } catch (e) {
        alert('Error deleting inspection');
    }
    
    closeDeleteModal();
}

// Form submission
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    const originalBtnText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Saving...';
    
    const id = document.getElementById('inspectionId').value;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    // Convert radio values
    data.reinspection_needed = data.reinspection_needed || 'no';
    
    // Convert numeric fields
    if (data.project_id) data.project_id = parseInt(data.project_id);
    if (data.permit_id && data.permit_id !== '') data.permit_id = parseInt(data.permit_id);
    if (data.inspector_name === '') data.inspector_name = null;
    
    const url = 'api/inspections.php';
    const method = id ? 'PUT' : 'POST';
    
    if (id) data.id = parseInt(id);
    
    try {
        const response = await fetch(url, {
            method: method,
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?php echo $csrf_token; ?>'
            },
            body: JSON.stringify(data)
        });
        
        const text = await response.text();
        
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalBtnText;
            alert('Server error: ' + text.substring(0, 500));
            return;
        }
        
        if (result.success) {
            location.reload();
        } else {
            alert('Error: ' + (result.error || 'Failed to save inspection'));
            submitBtn.disabled = false;
            submitBtn.textContent = originalBtnText;
        }
    } catch (e) {
        alert('Error saving inspection: ' + e.message);
        submitBtn.disabled = false;
        submitBtn.textContent = originalBtnText;
    }
    
    closeModal();
});

// Load recent changes/activity log
async function loadRecentChanges() {
    const list = document.getElementById('recent-changes-list');
    if (!list) return;
    
    list.innerHTML = '<div class="text-center text-muted" style="padding: 20px;"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading...</div>';
    
    try {
        const response = await fetch('api/inspections.php?recent_changes=1');
        const text = await response.text();
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            list.innerHTML = '<div class="text-center text-danger" style="padding: 20px;">Server Error</div>';
            return;
        }
        
        if (data.success && data.changes && data.changes.length > 0) {
            let html = '<table style="width: 100%; font-size: 13px;"><thead><tr><th>Date</th><th>Inspection</th><th>Project</th><th>Change</th><th>By</th><th>Note</th></tr></thead><tbody>';
            
            data.changes.forEach(function(change) {
                html += '<tr style="border-bottom: 1px solid var(--border-color);">';
                const date = new Date(change.changed_at);
                html += '<td style="padding: 8px 4px; white-space: nowrap;">' + 
                        (date.getMonth()+1) + '/' + date.getDate() + ' ' + 
                        date.getHours().toString().padStart(2,'0') + ':' + 
                        date.getMinutes().toString().padStart(2,'0') + '</td>';
                html += '<td style="padding: 8px 4px;">' + (change.inspection_type || '-') + '</td>';
                html += '<td style="padding: 8px 4px;">' + (change.project_name || '-') + '</td>';
                const statusClass = getInspectionStatusClass(change.new_status);
                html += '<td style="padding: 8px 4px;"><span class="status-badge ' + statusClass + '">' + 
                        (change.old_status || 'New') + ' → ' + change.new_status + '</span></td>';
                html += '<td style="padding: 8px 4px;">' + (change.username || '-') + '</td>';
                html += '<td style="padding: 8px 4px; color: var(--text-muted);">' + (change.note || '-') + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            list.innerHTML = html;
        } else {
            list.innerHTML = '<div class="text-center text-muted" style="padding: 20px;">No recent activity</div>';
        }
    } catch (e) {
        list.innerHTML = '<div class="text-center text-danger" style="padding: 20px;">Failed to load</div>';
    }
}

function getInspectionStatusClass(status) {
    const classes = {
        'not_scheduled': 'status-not_scheduled',
        'requested': 'status-requested',
        'scheduled': 'status-scheduled',
        'completed': 'status-completed',
        'passed': 'status-passed',
        'failed': 'status-failed',
        'reinspection_needed': 'status-reinspection_needed'
    };
    return classes[status] || 'status-pending';
}

// Load on page ready
document.addEventListener('DOMContentLoaded', function() {
    loadRecentChanges();
    
    // Auto-open create modal if create=1 in URL
    if (window.location.search.includes('create=1')) {
        openCreateModal();
    }
});

// Close modals on outside click
window.onclick = function(e) {
    if (e.target === modal) closeModal();
    if (e.target === deleteModal) closeDeleteModal();
};
</script>

<?php require_once 'partials/footer.php'; ?>
