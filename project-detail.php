<?php
/**
 * project-detail.php - Full page view for a single project
 * Access via: project-detail.php?id=X
 */

$projectId = $_GET['id'] ?? null;

if (!$projectId) {
    die('Project ID required');
}

$pageTitle = 'Project Details';
$currentPage = 'projects';

require_once 'partials/init.php';

// Role-based access
$userRole = $_SESSION['user_role'] ?? 'viewer';
$allowedRoles = ['admin', 'pm', 'estimator', 'accounting'];
if (!in_array($userRole, $allowedRoles, true)) {
    http_response_code(403);
    die('Access denied');
}

// Get project data
try {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$project) {
        die('Project not found');
    }
} catch (Exception $e) {
    die('Error loading project');
}

// Get permits for this project
$permits = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM permits WHERE project_id = ? ORDER BY created_at DESC");
    $stmt->execute([$projectId]);
    $permits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get inspections for this project
$inspections = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM inspections WHERE project_id = ? ORDER BY scheduled_date DESC");
    $stmt->execute([$projectId]);
    $inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get documents for this project
$documents = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM project_documents WHERE project_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([$projectId]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get team members
$team = [];
try {
    $stmt = $pdo->prepare("
        SELECT pt.*, u.username, u.email, u.role as user_role
        FROM project_team pt
        LEFT JOIN users u ON pt.user_id = u.id
        WHERE pt.project_id = ?
    ");
    $stmt->execute([$projectId]);
    $team = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get vendors
$vendors = [];
try {
    $stmt = $pdo->prepare("
        SELECT pv.*, v.name, v.contact_name, v.email, v.phone
        FROM project_vendors pv
        LEFT JOIN vendors v ON pv.vendor_id = v.id
        WHERE pv.project_id = ?
    ");
    $stmt->execute([$projectId]);
    $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get timeline/activity
$activity = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, u.username
        FROM audit_log a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.entity_id = ? AND a.entity_type = 'projects'
        ORDER BY a.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$projectId]);
    $activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

require_once 'partials/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumbs">
    <a href="projects.php">← Back to Projects</a>
    <span class="separator">/</span>
    <span><?php echo htmlspecialchars($project['name']); ?></span>
</div>

<!-- Project Header -->
<div class="project-header">
    <div>
        <h1><?php echo htmlspecialchars($project['name']); ?></h1>
        <p class="text-secondary">
            <?php echo htmlspecialchars($project['client_name'] ?? 'No client'); ?> • 
            <?php echo htmlspecialchars($project['city'] ?? ''); ?>
        </p>
    </div>
    <div>
        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $project['status'] ?? 'signed')); ?>">
            <?php echo htmlspecialchars($project['status']); ?>
        </span>
    </div>
</div>

<!-- Overview Section -->
<div class="detail-section">
    <h2>Overview</h2>
    <div class="detail-grid">
        <div class="detail-item">
            <label>Client</label>
            <span><?php echo htmlspecialchars($project['client_name'] ?? '—'); ?></span>
        </div>
        <div class="detail-item">
            <label>Phone</label>
            <span><?php echo htmlspecialchars($project['phone'] ?? '—'); ?></span>
        </div>
        <div class="detail-item">
            <label>Email</label>
            <span><?php echo htmlspecialchars($project['email'] ?? '—'); ?></span>
        </div>
        <div class="detail-item">
            <label>Address</label>
            <span><?php echo htmlspecialchars($project['address'] ?? '—'); ?></span>
        </div>
        <div class="detail-item">
            <label>Project Type</label>
            <span><?php echo htmlspecialchars($project['project_type'] ?? '—'); ?></span>
        </div>
        <div class="detail-item">
            <label>Project Manager</label>
            <span><?php echo htmlspecialchars($project['project_manager'] ?? '—'); ?></span>
        </div>
        <div class="detail-item">
            <label>Budget</label>
            <span><?php echo $project['total_budget'] ? '$' . number_format($project['total_budget'], 2) : '—'; ?></span>
        </div>
        <div class="detail-item">
            <label>Invoice #</label>
            <span>
                <?php echo htmlspecialchars($project['invoice_number'] ?? '—'); ?>
                <?php if (!empty($project['invoice_pdf'])): ?>
                <a href="uploads/invoices/<?php echo htmlspecialchars($project['invoice_pdf']); ?>" target="_blank">
                    <i class="fa-solid fa-file-pdf text-danger"></i>
                </a>
                <?php endif; ?>
            </span>
        </div>
        <div class="detail-item">
            <label>Start Date</label>
            <span><?php echo $project['start_date'] ? date('m/d/Y', strtotime($project['start_date'])) : '—'; ?></span>
        </div>
        <div class="detail-item">
            <label>Target Completion</label>
            <span><?php echo $project['end_date'] ? date('m/d/Y', strtotime($project['end_date'])) : '—'; ?></span>
        </div>
    </div>
    
    <?php if (!empty($project['scope_of_work'])): ?>
    <div class="detail-item" style="margin-top: 16px;">
        <label>Scope of Work</label>
        <p><?php echo nl2br(htmlspecialchars($project['scope_of_work'])); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($project['notes'])): ?>
    <div class="detail-item" style="margin-top: 16px;">
        <label>Internal Notes</label>
        <p><?php echo nl2br(htmlspecialchars($project['notes'])); ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- Tabs -->
<div class="detail-tabs">
    <div class="tab-buttons">
        <button class="tab-btn active" data-tab="permits">Permits (<?php echo count($permits); ?>)</button>
        <button class="tab-btn" data-tab="inspections">Inspections (<?php echo count($inspections); ?>)</button>
        <button class="tab-btn" data-tab="documents">Documents (<?php echo count($documents); ?>)</button>
        <button class="tab-btn" data-tab="team">Team (<?php echo count($team); ?>)</button>
        <button class="tab-btn" data-tab="vendors">Vendors (<?php echo count($vendors); ?>)</button>
        <button class="tab-btn" data-tab="timeline">Timeline</button>
    </div>
    
    <!-- Permits Tab -->
    <div class="tab-content active" id="tab-permits">
        <?php if (empty($permits)): ?>
        <div class="empty-state">No permits for this project.</div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>City</th>
                    <th>Status</th>
                    <th>Submitted By</th>
                    <th>Permit #</th>
                    <th>Submission</th>
                    <th>Age</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($permits as $permit): ?>
                <tr>
                    <td><?php echo htmlspecialchars($permit['city'] ?? '—'); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $permit['status']; ?>">
                            <?php echo $permit['status']; ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($permit['submitted_by'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($permit['permit_number'] ?? '—'); ?></td>
                    <td><?php echo $permit['submission_date'] ? date('m/d/Y', strtotime($permit['submission_date'])) : '—'; ?></td>
                    <td>
                        <?php 
                        if ($permit['submission_date']) {
                            $days = (new DateTime())->diff(new DateTime($permit['submission_date']))->days;
                            echo $days . ' days';
                        } else {
                            echo '—';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <!-- Inspections Tab -->
    <div class="tab-content" id="tab-inspections">
        <?php if (empty($inspections)): ?>
        <div class="empty-state">No inspections for this project.</div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Scheduled</th>
                    <th>Requested By</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inspections as $insp): ?>
                <tr>
                    <td><?php echo htmlspecialchars($insp['inspection_type'] ?? '—'); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $insp['status']; ?>">
                            <?php echo $insp['status']; ?>
                        </span>
                    </td>
                    <td><?php echo $insp['scheduled_date'] ? date('m/d/Y', strtotime($insp['scheduled_date'])) : '—'; ?></td>
                    <td><?php echo htmlspecialchars($insp['requested_by'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <!-- Documents Tab -->
    <div class="tab-content" id="tab-documents">
        <?php if (empty($documents)): ?>
        <div class="empty-state">No documents for this project.</div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Label</th>
                    <th>Type</th>
                    <th>Uploaded</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $doc): ?>
                <tr>
                    <td>
                        <a href="api/documents.php?id=<?php echo $doc['id']; ?>" target="_blank">
                            <?php echo htmlspecialchars($doc['label']); ?>
                        </a>
                    </td>
                    <td><?php echo htmlspecialchars($doc['file_type'] ?? '—'); ?></td>
                    <td><?php echo date('m/d/Y', strtotime($doc['uploaded_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <!-- Team Tab -->
    <div class="tab-content" id="tab-team">
        <?php if (empty($team)): ?>
        <div class="empty-state">No team members assigned.</div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Assigned</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($team as $member): ?>
                <tr>
                    <td><?php echo htmlspecialchars($member['username'] ?? 'User #' . $member['user_id']); ?></td>
                    <td><?php echo htmlspecialchars($member['assigned_role'] ?? $member['user_role']); ?></td>
                    <td><?php echo date('m/d/Y', strtotime($member['assigned_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <!-- Vendors Tab -->
    <div class="tab-content" id="tab-vendors">
        <?php if (empty($vendors)): ?>
        <div class="empty-state">No vendors assigned.</div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Vendor</th>
                    <th>Contact</th>
                    <th>Phone</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vendors as $vendor): ?>
                <tr>
                    <td><?php echo htmlspecialchars($vendor['name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($vendor['contact_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($vendor['phone'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($vendor['email'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <!-- Timeline Tab -->
    <div class="tab-content" id="tab-timeline">
        <?php if (empty($activity)): ?>
        <div class="empty-state">No activity recorded.</div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activity as $act): ?>
                <tr>
                    <td><?php echo date('m/d/Y g:i a', strtotime($act['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($act['username'] ?? 'System'); ?></td>
                    <td><?php echo htmlspecialchars($act['action_type']); ?>d</td>
                    <td><?php echo htmlspecialchars($act['entity_name'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<style>
.project-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border-color);
}
.project-header h1 {
    margin: 0 0 8px 0;
    font-size: 28px;
}
.detail-section {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 24px;
}
.detail-section h2 {
    margin: 0 0 16px 0;
    font-size: 20px;
}
.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
}
.detail-item label {
    display: block;
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 4px;
    text-transform: uppercase;
}
.detail-item span {
    font-size: 15px;
}
.detail-tabs {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    overflow: hidden;
}
.tab-buttons {
    display: flex;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-secondary);
}
.tab-btn {
    padding: 12px 20px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 14px;
    color: var(--text-secondary);
    border-bottom: 2px solid transparent;
}
.tab-btn:hover {
    color: var(--text-primary);
}
.tab-btn.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
}
.tab-content {
    display: none;
    padding: 24px;
}
.tab-content.active {
    display: block;
}
.breadcrumbs {
    margin-bottom: 16px;
    font-size: 14px;
}
.breadcrumbs a {
    color: var(--primary-color);
    text-decoration: none;
}
.breadcrumbs .separator {
    margin: 0 8px;
    color: var(--text-secondary);
}
</style>

<script>
document.querySelectorAll('.tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
        document.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
    });
});
</script>

<?php require_once 'partials/footer.php'; ?>
