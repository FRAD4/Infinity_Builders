<?php
/**
 * permits.php - Permits management page
 */

$pageTitle = 'Permits';
$currentPage = 'permits';

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
        'created' => 'Permit created successfully.',
        'updated' => 'Permit updated successfully.',
        'deleted' => 'Permit deleted successfully.'
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
    'project_id' => $_GET['project_id'] ?? ''
];

// Get all projects for dropdown
$projectsList = [];
try {
    $stmt = $pdo->query("SELECT id, name, city, project_manager, status FROM projects ORDER BY name ASC");
    $projectsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $projectsList = [];
}

// Build query for permits with project join
$where = ['1=1'];
$params = [];

if (!empty($filters['search'])) {
    $where[] = "(p.permit_number LIKE ? OR p.submitted_by LIKE ? OR pr.name LIKE ?)";
    $searchTerm = '%' . $filters['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($filters['status']) && $filters['status'] !== 'all') {
    $where[] = "p.status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['city']) && $filters['city'] !== 'all') {
    $where[] = "p.city = ?";
    $params[] = $filters['city'];
}

if (!empty($filters['project_id'])) {
    $where[] = "p.project_id = ?";
    $params[] = $filters['project_id'];
}

$sql = "SELECT p.*, pr.name as project_name, pr.city as project_city, pr.project_manager 
        FROM permits p 
        LEFT JOIN projects pr ON p.project_id = pr.id";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY p.created_at DESC";

$permits = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $permits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $permits = [];
}

// Calculate stats
$stats = [
    'total' => count($permits),
    'not_started' => 0,
    'submitted' => 0,
    'in_review' => 0,
    'correction_needed' => 0,
    'approved' => 0,
    'rejected' => 0
];

$statusMap = [
    'not_started' => 'not_started',
    'submitted' => 'submitted',
    'in_review' => 'in_review',
    'correction_needed' => 'correction_needed',
    'resubmitted' => 'submitted',
    'approved' => 'approved',
    'rejected' => 'rejected'
];

foreach ($permits as $p) {
    $mapped = $statusMap[$p['status']] ?? 'not_started';
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

// Get users for submitter dropdown
$usersList = [];
try {
    $stmt = $pdo->query("SELECT id, username FROM users ORDER BY username");
    $usersList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $usersList = [];
}

// Status options
$statusOptions = [
    'not_started' => 'Not Started',
    'submitted' => 'Submitted',
    'in_review' => 'In Review',
    'correction_needed' => 'Correction Needed',
    'resubmitted' => 'Resubmitted',
    'approved' => 'Approved',
    'rejected' => 'Rejected'
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
                <i class="fa-solid fa-file-contract"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Permits</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #6b7280;">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['not_started']; ?></div>
                <div class="stat-label">Not Started</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #3b82f6;">
                <i class="fa-solid fa-paper-plane"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['submitted']; ?></div>
                <div class="stat-label">Submitted</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #f59e0b;">
                <i class="fa-solid fa-search"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['in_review']; ?></div>
                <div class="stat-label">In Review</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #ef4444;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['correction_needed']; ?></div>
                <div class="stat-label">Correction Needed</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #10b981;">
                <i class="fa-solid fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['approved']; ?></div>
                <div class="stat-label">Approved</div>
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
      <button type="button" class="btn btn-secondary" onclick="document.getElementById('permits-filters-panel').classList.toggle('hidden'); this.querySelector('i').classList.toggle('fa-chevron-down'); this.querySelector('i').classList.toggle('fa-chevron-up');">
        <i class="fa-solid fa-chevron-down"></i> Filters
        <?php if (!empty($filters['search']) || $filters['status'] !== 'all' || !empty($filters['city']) || !empty($filters['project_id'])): ?>
        <span style="background: var(--primary); color: white; border-radius: 50%; width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; margin-left: 6px;">
          <?php echo (int)(!empty($filters['search'])) + (int)($filters['status'] !== 'all') + (int)(!empty($filters['city'])) + (int)(!empty($filters['project_id'])); ?>
        </span>
        <?php endif; ?>
      </button>
    </div>

    <div id="permits-filters-panel" class="card filter-bar hidden" style="margin-top: 10px; padding: 12px 16px;">
        <div class="filters-left">
            <!-- Search -->
            <div class="filter-search">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search permits..." 
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
            <h3>All Permits</h3>
          </div>
          <div>
            <?php if (in_array($userRole, ['admin', 'pm', 'accounting', 'estimator'])): ?>
            <button class="btn btn-primary" onclick="openCreateModal()">
                <i class="fa-solid fa-plus"></i>
                Add Permit
            </button>
            <?php endif; ?>
          </div>
        </div>
    </div>
    
    <!-- Permits Table -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>City</th>
                    <th>Status</th>
                    <th>Submitted By</th>
                    <th>Permit #</th>
                    <th>PDF</th>
                    <th>Submission Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="permitsTableBody">
                <?php if (empty($permits)): ?>
                <tr>
                    <td colspan="7" class="empty-state">
                        <i class="fa-solid fa-file-contract"></i>
                        <p>No permits found</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($permits as $permit): ?>
                <tr data-id="<?php echo $permit['id']; ?>"
                    data-project-id="<?php echo $permit['project_id']; ?>"
                    data-project-name="<?php echo htmlspecialchars($permit['project_name'] ?? ''); ?>"
                    data-city="<?php echo htmlspecialchars($permit['city'] ?? ''); ?>"
                    data-status="<?php echo $permit['status']; ?>"
                    data-permit-required="<?php echo $permit['permit_required']; ?>"
                    data-submitted-by="<?php echo htmlspecialchars($permit['submitted_by'] ?? ''); ?>"
                    data-permit-number="<?php echo htmlspecialchars($permit['permit_number'] ?? ''); ?>"
                    data-permit-pdf="<?php echo htmlspecialchars($permit['permit_pdf'] ?? ''); ?>"
                    data-corrections-required="<?php echo $permit['corrections_required']; ?>"
                    data-corrections-due-date="<?php echo $permit['corrections_due_date'] ?? ''; ?>"
                    data-submission-date="<?php echo $permit['submission_date'] ?? ''; ?>"
                    data-approval-date="<?php echo $permit['approval_date'] ?? ''; ?>"
                    data-notes="<?php echo htmlspecialchars($permit['notes'] ?? ''); ?>"
                    data-internal-comments="<?php echo htmlspecialchars($permit['internal_comments'] ?? ''); ?>">
                    <td>
                        <?php if (!empty($permit['project_name'])): ?>
                        <a href="projects.php?open=<?php echo $permit['project_id']; ?>" class="project-link">
                            <?php echo htmlspecialchars($permit['project_name']); ?>
                        </a>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($permit['city'] ?? '—'); ?></td>
                    <td>
                        <?php 
                        $statusClass = [
                            'not_started' => 'status-not_started',
                            'submitted' => 'status-submitted',
                            'in_review' => 'status-in_review',
                            'correction_needed' => 'status-correction_needed',
                            'resubmitted' => 'status-resubmitted',
                            'approved' => 'status-approved',
                            'rejected' => 'status-rejected'
                        ];
                        $statusLabel = $statusOptions[$permit['status']] ?? 'Unknown';
                        ?>
                        <span class="status-badge <?php echo $statusClass[$permit['status']] ?? ''; ?>">
                            <?php echo $statusLabel; ?>
                        </span>
                        <?php 
                        // Flag overdue permits (30+ days since submission, not approved/rejected)
                        $isOverdue = false;
                        if ($permit['submission_date'] && !in_array($permit['status'], ['approved', 'rejected'])) {
                            $submitted = new DateTime($permit['submission_date']);
                            $now = new DateTime();
                            $days = $now->diff($submitted)->days;
                            if ($days > 30) $isOverdue = true;
                        }
                        if ($isOverdue): ?>
                        <span class="status-badge status-danger" style="margin-left: 4px;" title="Overdue (<?php echo $days; ?> days)">⚠️</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($permit['submitted_by'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($permit['permit_number'] ?? '—'); ?></td>
                    <td>
                        <?php if (!empty($permit['permit_pdf'])): ?>
                        <a href="uploads/permits/<?php echo htmlspecialchars($permit['permit_pdf']); ?>" target="_blank" title="View PDF">
                            <i class="fa-solid fa-file-pdf text-danger"></i>
                        </a>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $permit['submission_date'] ? date('m/d/Y', strtotime($permit['submission_date'])) : '—'; ?></td>
                    <td>
                        <?php 
                        // Show permit age (days since submission)
                        if ($permit['submission_date'] && !in_array($permit['status'], ['approved', 'rejected', 'not_started'])) {
                            $submitted = new DateTime($permit['submission_date']);
                            $now = new DateTime();
                            $days = $now->diff($submitted)->days;
                            echo $days . ' days';
                        } else {
                            echo '—';
                        }
                        ?>
                    </td>
                    <td>
                        <div class="table-actions">
                            <button class="btn-icon" title="Edit" onclick="openEditModal(<?php echo $permit['id']; ?>)">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <?php if ($userRole === 'admin'): ?>
                            <button class="btn-icon btn-danger" title="Delete" onclick="deletePermit(<?php echo $permit['id']; ?>)">
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
<div id="permitModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Add Permit</h2>
            <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        
        <form id="permitForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" id="permitId" name="permit_id" value="">
            
            <div class="modal-body">
                <!-- Project Selection -->
                <div class="form-group">
                    <label for="projectId">Project *</label>
                    <select id="projectId" name="project_id" required>
                        <option value="">Select a project...</option>
                        <?php foreach ($projectsList as $pr): ?>
                        <option value="<?php echo $pr['id']; ?>">
                            <?php echo htmlspecialchars($pr['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- City -->
                <div class="form-group">
                    <label for="permitCity">City *</label>
                    <input type="text" id="permitCity" name="city" list="permit-city-list" placeholder="Select or type city" required>
                    <datalist id="permit-city-list">
                        <?php foreach ($cityOptions as $city): ?>
                        <option value="<?php echo $city; ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                
                <!-- Permit Required -->
                <div class="form-group">
                    <label>Permit Required?</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="permit_required" value="yes" checked onchange="togglePermitFields()"> Yes
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="permit_required" value="no" onchange="togglePermitFields()"> No
                        </label>
                    </div>
                </div>
                
                <!-- Permit Fields (shown when permit_required = yes) -->
                <div id="permit-fields">
                <!-- Status -->
                <div class="form-group">
                    <label for="permitStatus">Status</label>
                    <select id="permitStatus" name="status" value="not_started">
                        <option value="not_started" selected>Not Started</option>
                        <?php foreach ($statusOptions as $val => $label): ?>
                        <?php if ($val !== 'not_started'): ?>
                        <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Submitted By -->
                <div class="form-group">
                    <label for="submittedBy">Submitted By</label>
                    <select id="submittedBy" name="submitted_by">
                        <option value="">Select person...</option>
                        <?php foreach ($usersList as $user): ?>
                        <option value="<?php echo htmlspecialchars($user['username']); ?>">
                            <?php echo htmlspecialchars($user['username']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Permit Number -->
                <div class="form-group">
                    <label for="permitNumber">Permit Number</label>
                    <input type="text" id="permitNumber" name="permit_number" placeholder="e.g. PRM-2026-001">
                </div>
                
                <!-- Permit PDF -->
                <div class="form-group">
                    <label for="permitPdf">Permit PDF</label>
                    <input type="file" id="permitPdf" name="permit_pdf" accept=".pdf" accept-charset="utf-8">
                    <input type="hidden" id="permitPdfFilename" name="permit_pdf_filename" value="">
                    <div id="currentPermitPdf" class="text-muted" style="font-size: 12px; margin-top: 4px;"></div>
                </div>
                
                <!-- Submission Date -->
                <div class="form-group">
                    <label for="submissionDate">Submission Date</label>
                    <input type="date" id="submissionDate" name="submission_date">
                </div>
                
                <!-- Corrections Required -->
                <div class="form-group">
                    <label>Corrections Required?</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="corrections_required" value="no" checked> No
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="corrections_required" value="yes"> Yes
                        </label>
                    </div>
                </div>
                
                <!-- Corrections Due Date -->
                <div class="form-group">
                    <label for="correctionsDueDate">Corrections Due Date</label>
                    <input type="date" id="correctionsDueDate" name="corrections_due_date">
                </div>
                
                <!-- Approval Date -->
                <div class="form-group">
                    <label for="approvalDate">Approval Date</label>
                    <input type="date" id="approvalDate" name="approval_date">
                </div>
                
                <!-- Notes -->
                </div><!-- end permit-fields -->
                
                <!-- Status Note (for status changes) -->
                <div class="form-group">
                    <label for="statusNote">Status Change Note (optional)</label>
                    <input type="text" id="statusNote" name="status_note" placeholder="Reason for status change...">
                </div>
                
                <div class="form-group">
                    <label for="permitNotes">Notes</label>
                    <textarea id="permitNotes" name="notes" rows="3" placeholder="Additional notes..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="internalComments">Internal Comments</label>
                    <textarea id="internalComments" name="internal_comments" rows="2" placeholder="Internal team notes (not visible externally)..."></textarea>
                </div>
                
                <!-- Status History -->
                <div class="form-group" id="status-history-section" style="display: none;">
                    <label>Status History</label>
                    <div id="status-history-list" style="max-height: 150px; overflow-y: auto; border: 1px solid var(--border-color); padding: 8px; border-radius: 4px;"></div>
                </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">Create Permit</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h2>Delete Permit</h2>
            <button type="button" class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete this permit? This action cannot be undone.</p>
            <input type="hidden" id="deletePermitId" value="">
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
    window.location.href = 'permits.php' + (params.toString() ? '?' + params.toString() : '');
}

searchInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') applyFilters(); });
statusFilter.addEventListener('change', applyFilters);
cityFilter.addEventListener('change', applyFilters);
projectFilter.addEventListener('change', applyFilters);

// Toggle permit fields based on permit_required
function togglePermitFields() {
    const permitRequired = document.querySelector('input[name="permit_required"]:checked')?.value || 'yes';
    const permitFields = document.getElementById('permit-fields');
    if (permitFields) {
        permitFields.style.display = permitRequired === 'yes' ? 'block' : 'none';
    }
}

// Initial toggle on page load
document.addEventListener('DOMContentLoaded', togglePermitFields);

// Modal functions
const modal = document.getElementById('permitModal');
const deleteModal = document.getElementById('deleteModal');
const form = document.getElementById('permitForm');

// Test function
function testSubmit() {
    form.dispatchEvent(new Event('submit'));
}

function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Add Permit';
    document.getElementById('submitBtn').textContent = 'Create Permit';
    form.reset();
    document.getElementById('permitId').value = '';
    modal.classList.add('active');
}

function openEditModal(id) {
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (!row) return;
    
    document.getElementById('modalTitle').textContent = 'Edit Permit';
    document.getElementById('submitBtn').textContent = 'Save Changes';
    document.getElementById('permitId').value = id;
    
    // Fill form from data attributes
    document.getElementById('projectId').value = row.dataset.projectId || '';
    document.getElementById('permitCity').value = row.dataset.city || '';
    document.querySelector(`input[name="permit_required"][value="${row.dataset.permitRequired || 'yes'}"]`).checked = true;
    document.getElementById('permitStatus').value = row.dataset.status || 'not_started';
    document.getElementById('submittedBy').value = row.dataset.submittedBy || '';
    document.getElementById('permitNumber').value = row.dataset.permitNumber || '';
    document.getElementById('permitPdfFilename').value = row.dataset.permitPdf || '';
    
    // Show current PDF if exists
    const pdfDisplay = document.getElementById('currentPermitPdf');
    if (row.dataset.permitPdf) {
        pdfDisplay.innerHTML = '<i class="fa-solid fa-file-pdf"></i> Current: <a href="uploads/permits/' + row.dataset.permitPdf + '" target="_blank">' + row.dataset.permitPdf + '</a>';
    } else {
        pdfDisplay.textContent = '';
    }
    
    document.getElementById('submissionDate').value = row.dataset.submissionDate || '';
    document.querySelector(`input[name="corrections_required"][value="${row.dataset.correctionsRequired || 'no'}"]`).checked = true;
    document.getElementById('correctionsDueDate').value = row.dataset.correctionsDueDate || '';
    document.getElementById('approvalDate').value = row.dataset.approvalDate || '';
    document.getElementById('permitNotes').value = row.dataset.notes || '';
    document.getElementById('internalComments').value = row.dataset.internalComments || '';
    
    // Toggle fields visibility based on permit_required
    togglePermitFields();
    
    // Load status history
    loadStatusHistory(id);
    
    modal.classList.add('active');
}

function closeModal() {
    modal.classList.remove('active');
}

// Load status history for a permit
async function loadStatusHistory(permitId) {
    const section = document.getElementById('status-history-section');
    const list = document.getElementById('status-history-list');
    
    if (!section || !list) return;
    
    try {
        const response = await fetch('api/permits.php?history_for=' + permitId);
        const data = await response.json();
        
        if (data.success && data.history && data.history.length > 0) {
            let html = '<table style="width: 100%; font-size: 12px;"><tr><th>Date</th><th>From</th><th>To</th><th>By</th><th>Note</th></tr>';
            data.history.forEach(function(h) {
                html += '<tr>';
                html += '<td>' + h.changed_at + '</td>';
                html += '<td>' + (h.old_status || '-') + '</td>';
                html += '<td>' + h.new_status + '</td>';
                html += '<td>' + h.username + '</td>';
                html += '<td>' + (h.note || '-') + '</td>';
                html += '</tr>';
            });
            html += '</table>';
            list.innerHTML = html;
            section.style.display = 'block';
        } else {
            list.innerHTML = '<span class="text-muted">No status changes recorded yet.</span>';
            section.style.display = 'block';
        }
    } catch (e) {
        console.error('Failed to load status history:', e);
        section.style.display = 'none';
    }
}

// Delete functions
function deletePermit(id) {
    document.getElementById('deletePermitId').value = id;
    deleteModal.classList.add('active');
}

function closeDeleteModal() {
    deleteModal.classList.remove('active');
}

async function confirmDelete() {
    const id = document.getElementById('deletePermitId').value;
    
    try {
        const response = await fetch('api/permits.php?id=' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-Token': '<?php echo $csrf_token; ?>' }
        });
        
        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to delete permit'));
        }
    } catch (e) {
        alert('Error deleting permit');
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
    
    const id = document.getElementById('permitId').value;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    // Convert radio values
    data.permit_required = data.permit_required || 'yes';
    data.corrections_required = data.corrections_required || 'no';
    
    // Convert numeric fields
    if (data.project_id) data.project_id = parseInt(data.project_id);
    
    const url = id ? 'api/permits.php' : 'api/permits.php';
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
        
        // Try to parse as JSON
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            // Not JSON - show the actual error
            submitBtn.disabled = false;
            submitBtn.textContent = originalBtnText;
            alert('Server error: ' + text.substring(0, 500));
            return;
        }
        
        if (result.success) {
            // If there's a PDF file selected, upload it
            const permitId = result.permit_id || id;
            const pdfInput = document.getElementById('permitPdf');
            if (pdfInput && pdfInput.files.length > 0) {
                submitBtn.textContent = 'Uploading PDF...';
                await uploadPermitPdf(permitId, pdfInput.files[0]);
            }
            location.reload();
        } else {
            alert('Error: ' + (result.error || 'Failed to save permit'));
            submitBtn.disabled = false;
            submitBtn.textContent = originalBtnText;
        }
    } catch (e) {
        console.error('Error:', e);
        alert('Error saving permit: ' + e.message);
        submitBtn.disabled = false;
        submitBtn.textContent = originalBtnText;
    }
    
    closeModal();
});

// PDF upload function
async function uploadPermitPdf(permitId, file) {
    const formData = new FormData();
    formData.append('permit_id', permitId);
    formData.append('permit_pdf', file);
    
    const response = await fetch('api/permits.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': '<?php echo $csrf_token; ?>' },
        body: formData
    });
    
    const result = await response.json();
    if (!result.success) {
        console.error('PDF upload failed:', result.error);
    }
    return result;
}

// Load recent changes/activity log
async function loadRecentChanges() {
    const list = document.getElementById('recent-changes-list');
    if (!list) return;
    
    list.innerHTML = '<div class="text-center text-muted" style="padding: 20px;"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading...</div>';
    
    try {
        const response = await fetch('api/permits.php?recent_changes=1');
        
        const text = await response.text();
        
        // Try to parse as JSON
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            list.innerHTML = '<div class="text-center text-danger" style="padding: 20px;">Server Error: ' + text.substring(0, 200) + '</div>';
            return;
        }
        
        if (data.success && data.changes && data.changes.length > 0) {
            let html = '<table style="width: 100%; font-size: 13px;"><thead><tr><th>Date</th><th>Permit #</th><th>Project</th><th>Change</th><th>By</th><th>Note</th></tr></thead><tbody>';
            
            data.changes.forEach(function(change) {
                html += '<tr style="border-bottom: 1px solid var(--border-color);">';
                // Date
                const date = new Date(change.changed_at);
                html += '<td style="padding: 8px 4px; white-space: nowrap;">' + 
                        (date.getMonth()+1) + '/' + date.getDate() + ' ' + 
                        date.getHours().toString().padStart(2,'0') + ':' + 
                        date.getMinutes().toString().padStart(2,'0') + '</td>';
                // Permit number
                html += '<td style="padding: 8px 4px;">' + (change.permit_number || '-') + '</td>';
                // Project name
                html += '<td style="padding: 8px 4px;">' + (change.project_name || '-') + '</td>';
                // Status change
                const statusClass = getStatusClass(change.new_status);
                html += '<td style="padding: 8px 4px;"><span class="status-badge ' + statusClass + '">' + 
                        (change.old_status || 'New') + ' → ' + change.new_status + '</span></td>';
                // By
                html += '<td style="padding: 8px 4px;">' + (change.username || '-') + '</td>';
                // Note
                html += '<td style="padding: 8px 4px; color: var(--text-muted);">' + (change.note || '-') + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            list.innerHTML = html;
        } else {
            list.innerHTML = '<div class="text-center text-muted" style="padding: 20px;">No recent activity</div>';
        }
    } catch (e) {
        console.error('Failed to load recent changes:', e);
        list.innerHTML = '<div class="text-center text-danger" style="padding: 20px;">Failed to load</div>';
    }
}

function getStatusClass(status) {
    const classes = {
        'not_started': 'status-not_started',
        'submitted': 'status-submitted',
        'in_review': 'status-in_review',
        'correction_needed': 'status-correction_needed',
        'resubmitted': 'status-resubmitted',
        'approved': 'status-approved',
        'rejected': 'status-rejected'
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