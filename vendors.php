<?php
/**
 * vendors.php - Vendors & Subcontractors admin page
 */

$pageTitle = 'Vendors';
$currentPage = 'vendors';

require_once 'partials/init.php';

// Role-based access: require at least 'pm' role to access vendors
$userRole = $_SESSION['user_role'] ?? 'user';
$allowedRoles = ['admin', 'pm', 'accounting'];
if (!in_array($userRole, $allowedRoles, true)) {
    http_response_code(403);
    die('Access denied. Only project team members can access this page.');
}

$message = '';

// Generate CSRF token for forms
require_once 'includes/security.php';
$csrf_token = csrf_token_generate();

// Get all active projects for payment dropdown
$projectsForPayment = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM projects WHERE status NOT IN ('Completed', 'Cancelled') ORDER BY name");
    $projectsForPayment = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get all projects for assign project dropdown
$allProjects = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM projects ORDER BY name ASC");
    $allProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Handle direct open from search (open=vendor_id)
$openVendorId = $_GET['open'] ?? null;

// Also handle name filter
$filterName = trim($_GET['name'] ?? '');

// If opening directly, search by that vendor name
$vendorSearchTerm = '';
if ($openVendorId) {
    // Get vendor name to filter by
    try {
        $stmt = $pdo->prepare("SELECT name FROM vendors WHERE id = ?");
        $stmt->execute([$openVendorId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $vendorSearchTerm = $row['name'];
        }
    } catch (Exception $e) {}
} elseif ($filterName) {
    $vendorSearchTerm = $filterName;
}

$uploadDirRel = 'uploads/vendor_docs';
$uploadDirAbs = __DIR__ . '/' . $uploadDirRel;

if (!is_dir($uploadDirAbs)) {
    @mkdir($uploadDirAbs, 0775, true);
}

function format_phone_us($raw) {
    $digits = preg_replace('/\D+/', '', (string)$raw);
    if (strlen($digits) === 10) {
        $area = substr($digits, 0, 3);
        $mid  = substr($digits, 3, 3);
        $last = substr($digits, 6, 4);
        return "($area) $mid-$last";
    }
    return trim($raw);
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!csrf_token_validate($submitted_token)) {
        $message = "Invalid request. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_vendor') {
            $name  = trim($_POST['name'] ?? '');
            $type  = $_POST['type'] ?? 'Subcontractor';
            $trade = trim($_POST['trade'] ?? '');
            $phone = $_POST['phone'] !== '' ? format_phone_us($_POST['phone']) : '';
            $email = trim($_POST['email'] ?? '');

            if ($name === '') {
                $message = "Vendor name is required.";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO vendors (name, type, trade, phone, email) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $type, $trade ?: null, $phone ?: null, $email ?: null]);
                    $message = "Vendor added successfully.";
                } catch (Exception $e) {
                    $message = "Error adding vendor: " . $e->getMessage();
                }
            }
        } // end add_vendor

        if ($action === 'update_vendor') {
            $id    = (int)($_POST['vendor_id'] ?? 0);
            $name  = trim($_POST['edit_name'] ?? '');
            $type  = $_POST['edit_type'] ?? 'Subcontractor';
            $trade = trim($_POST['edit_trade'] ?? '');
            $phone = $_POST['edit_phone'] !== '' ? format_phone_us($_POST['edit_phone']) : '';
            $email = trim($_POST['edit_email'] ?? '');

            if ($id <= 0 || $name === '') {
                $message = "Invalid vendor data.";
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE vendors SET name = ?, type = ?, trade = ?, phone = ?, email = ? WHERE id = ?");
                    $stmt->execute([$name, $type, $trade ?: null, $phone ?: null, $email ?: null, $id]);
                    $message = "Vendor updated.";
                } catch (Exception $e) {
                    $message = "Error updating vendor: " . $e->getMessage();
                }
            }
        } // end update_vendor

        if ($action === 'delete_vendor') {
            $id = (int)($_POST['vendor_id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM vendors WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = "Vendor deleted.";
                } catch (Exception $e) {
                    $message = "Error deleting vendor: " . $e->getMessage();
                }
            }
        } // end delete_vendor

        if ($action === 'bulk_delete') {
            $ids = array_filter(array_map('intval', (array)($_POST['vendor_ids'] ?? [])), fn($v) => $v > 0);
            if (!empty($ids)) {
                try {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmt = $pdo->prepare("DELETE FROM vendors WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                    $message = count($ids) . " vendor(s) deleted.";
                } catch (Exception $e) {
                    $message = "Error deleting vendors: " . $e->getMessage();
                }
            }
        } // end bulk_delete

        if ($action === 'upload_vendor_doc') {
            $vendorId = (int)($_POST['vendor_id'] ?? 0);
            $label    = trim($_POST['doc_label'] ?? '');

            if ($vendorId <= 0 || $label === '') {
                $message = "Invalid vendor or document name.";
            } elseif (!isset($_FILES['doc_file']) || $_FILES['doc_file']['error'] !== UPLOAD_ERR_OK) {
                $message = "File upload failed.";
            } else {
                $file    = $_FILES['doc_file'];
                $ext     = pathinfo($file['name'], PATHINFO_EXTENSION);
                $safeExt = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
                $stored  = 'v' . $vendorId . '_' . time() . '_' . bin2hex(random_bytes(4)) . ($safeExt ? '.' . $safeExt : '');
                $target  = $uploadDirAbs . '/' . $stored;
                $relPath = $uploadDirRel . '/' . $stored;

                if (!move_uploaded_file($file['tmp_name'], $target)) {
                    $message = "Could not save uploaded file.";
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO vendor_documents (vendor_id, label, file_path, mime_type) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$vendorId, $label, $relPath, $file['type'] ?? null]);
                        $message = "Document uploaded.";
                    } catch (Exception $e) {
                        @unlink($target);
                        $message = "Error saving document: " . $e->getMessage();
                    }
                }
            }
        } // end upload_vendor_doc

        if ($action === 'send_email') {
            $to      = trim($_POST['email_to'] ?? '');
            $subject = trim($_POST['email_subject'] ?? '');
            $body    = trim($_POST['email_body'] ?? '');
            
            if (empty($to) || empty($subject) || empty($body)) {
                $message = "All email fields are required.";
            } else {
                require_once 'includes/email.php';
                $result = send_email($to, $subject, $body);
                
                if ($result['status'] === 'sent') {
                    $message = "Email sent successfully to $to";
                } else {
                    $message = "Failed to send email: " . ($result['error_message'] ?? 'Unknown error');
                }
            }
        } // end send_email

        if ($action === 'add_payment') {
            $vendorId = (int)($_POST['payment_vendor_id'] ?? 0);
            $projectId = (int)($_POST['payment_project_id'] ?? 0);
            $amount = $_POST['payment_amount'] ?? '';
            $paidDate = $_POST['payment_date'] ?? date('Y-m-d');
            $description = trim($_POST['payment_description'] ?? '');
            
            // Handle invoice file upload
            $invoicePath = null;
            if (!empty($_FILES['payment_invoice']['name'])) {
                $uploadDir = __DIR__ . '/uploads/vendor_invoices/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = basename($_FILES['payment_invoice']['name']);
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
                
                if (in_array($fileExt, $allowedExts)) {
                    $newFileName = uniqid('invoice_') . '.' . $fileExt;
                    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $newFileName;
                    
                    if (move_uploaded_file($_FILES['payment_invoice']['tmp_name'], $targetPath)) {
                        $invoicePath = 'uploads/vendor_invoices/' . $newFileName;
                    } else {
                        $message = "Error uploading invoice file.";
                    }
                } else {
                    $message = "Invalid file type. Allowed: PDF, JPG, PNG.";
                }
            }
            
            if ($vendorId <= 0) {
                $message = "Invalid vendor.";
            } elseif ($projectId <= 0) {
                $message = "Please select a project.";
            } elseif ($amount === '' || (float)$amount <= 0) {
                $message = "Valid amount is required.";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO vendor_payments (vendor_id, project_id, amount, paid_date, description, invoice_path) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$vendorId, $projectId, (float)$amount, $paidDate, $description ?: null, $invoicePath]);
                    $message = "Payment recorded.";
                } catch (Exception $e) {
                    $message = "Error recording payment: " . $e->getMessage();
                }
            }
        } // end add_payment

        if ($action === 'assign_project_bid') {
            $vendorId = (int)($_POST['bid_vendor_id'] ?? 0);
            $projectId = (int)($_POST['bid_project_id'] ?? 0);
            $bidAmount = floatval($_POST['bid_amount'] ?? 0);
            
            if ($vendorId <= 0 || $projectId <= 0) {
                $message = "Invalid vendor or project.";
            } elseif ($bidAmount <= 0) {
                $message = "Valid bid amount is required.";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO project_vendors (vendor_id, project_id, bid_amount, assigned_at)
                        VALUES (?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE bid_amount = VALUES(bid_amount), assigned_at = NOW()
                    ");
                    $stmt->execute([$vendorId, $projectId, $bidAmount]);
                    $message = "Vendor assigned to project successfully.";
                } catch (Exception $e) {
                    $message = "Error assigning project: " . $e->getMessage();
                }
            }
        } // end assign_project_bid
    } // end CSRF validado
}

// Load vendors
$vendors = [];
try {
    // Filter by name if searching
    if (!empty($vendorSearchTerm)) {
        $stmt = $pdo->prepare("
            SELECT id, name, type, trade, phone, email, created_at 
            FROM vendors 
            WHERE name LIKE ?
            ORDER BY name ASC
        ");
        $stmt->execute(['%' . $vendorSearchTerm . '%']);
        $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("SELECT id, name, type, trade, phone, email, created_at FROM vendors ORDER BY name ASC");
        $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $message = "Vendors table not found. Check schema.";
}

// Load documents grouped by vendor
$docsByVendor = [];
try {
    $stmt = $pdo->query("SELECT id, vendor_id, label, file_path, mime_type, uploaded_at FROM vendor_documents ORDER BY uploaded_at DESC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $vid = (int)$row['vendor_id'];
        if (!isset($docsByVendor[$vid])) $docsByVendor[$vid] = [];
        $docsByVendor[$vid][] = $row;
    }
} catch (Exception $e) {}

// Load payments summary (by year)
$paymentsByVendor = [];
try {
    $stmt = $pdo->query("SELECT vendor_id, YEAR(paid_date) AS yr, SUM(amount) AS total FROM vendor_payments GROUP BY vendor_id, yr ORDER BY yr DESC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $vid = (int)$row['vendor_id'];
        if (!isset($paymentsByVendor[$vid])) $paymentsByVendor[$vid] = [];
        $paymentsByVendor[$vid][] = ['yr' => (int)$row['yr'], 'total' => (float)$row['total']];
    }
} catch (Exception $e) {}

// Load detailed payments for modal display
$paymentsDetailByVendor = [];
try {
    $stmt = $pdo->query("
        SELECT vp.id, vp.vendor_id, vp.project_id, vp.amount, vp.paid_date, vp.description, vp.invoice_path, p.name as project_name 
        FROM vendor_payments vp 
        LEFT JOIN projects p ON vp.project_id = p.id 
        ORDER BY vp.paid_date DESC 
        LIMIT 100
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $vid = (int)$row['vendor_id'];
        if (!isset($paymentsDetailByVendor[$vid])) $paymentsDetailByVendor[$vid] = [];
        $paymentsDetailByVendor[$vid][] = $row;
    }
} catch (Exception $e) {}

// Load vendor projects for modal display
$vendorProjectsByVendor = [];
try {
    $stmt = $pdo->query("
        SELECT pv.id, pv.vendor_id, pv.project_id, p.name as project_name, pv.bid_amount, pv.assigned_at,
            (SELECT COALESCE(SUM(amount), 0) FROM vendor_payments WHERE project_id = pv.project_id AND vendor_id = pv.vendor_id) as total_paid
        FROM project_vendors pv
        JOIN projects p ON pv.project_id = p.id
        ORDER BY pv.assigned_at DESC
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $vid = (int)$row['vendor_id'];
        if (!isset($vendorProjectsByVendor[$vid])) $vendorProjectsByVendor[$vid] = [];
        $vendorProjectsByVendor[$vid][] = $row;
    }
} catch (Exception $e) {}

// Calculate vendor metrics
$vendorStats = [
    'total' => count($vendors),
    'by_type' => [],
    'total_paid' => 0,
    'total_paid_this_year' => 0,
    'top_vendors' => []
];

foreach ($vendors as $v) {
    $type = $v['type'] ?? 'Other';
    if (!isset($vendorStats['by_type'][$type])) {
        $vendorStats['by_type'][$type] = 0;
    }
    $vendorStats['by_type'][$type]++;
}

// Get total payments
try {
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM vendor_payments");
    $vendorStats['total_paid'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM vendor_payments WHERE YEAR(paid_date) = YEAR(CURDATE())");
    $vendorStats['total_paid_this_year'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    // Top vendors by payment
    $stmt = $pdo->query("
        SELECT v.id, v.name, v.type, COALESCE(SUM(p.amount), 0) as total_paid 
        FROM vendors v 
        LEFT JOIN vendor_payments p ON v.id = p.vendor_id 
        GROUP BY v.id 
        ORDER BY total_paid DESC 
        LIMIT 5
    ");
    $vendorStats['top_vendors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

require_once 'partials/header.php';
?>

<!-- Header removed - now handled by partials/header.php -->

<?php if ($message): ?>
  <div class="alert"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- Vendor Stats Cards -->
<div class="stats-grid">
  <div class="stat-card stat-card-blue">
    <div class="stat-value"><?php echo $vendorStats['total']; ?></div>
    <div class="stat-label">Total Vendors</div>
  </div>
  
  <?php foreach ($vendorStats['by_type'] as $type => $count): ?>
  <div class="stat-card stat-card-green">
    <div class="stat-value"><?php echo $count; ?></div>
    <div class="stat-label"><?php echo htmlspecialchars($type); ?>s</div>
  </div>
  <?php endforeach; ?>
  
  <div class="stat-card stat-card-green">
    <div class="stat-value">$<?php echo number_format($vendorStats['total_paid'] / 1000, 0); ?>K</div>
    <div class="stat-label">Total Paid</div>
  </div>
  
  <div class="stat-card stat-card-blue">
    <div class="stat-value">$<?php echo number_format($vendorStats['total_paid_this_year'] / 1000, 0); ?>K</div>
    <div class="stat-label">Paid This Year</div>
  </div>
</div>

<!-- Top Vendors by Payments -->
<?php if (!empty($vendorStats['top_vendors'])): ?>
<div class="card" style="margin-bottom: 20px;">
  <div class="card-header">
    <h3><i class="fa-solid fa-trophy" style="color: var(--warning);"></i> Top Vendors by Payments</h3>
  </div>
  
  <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-top: 12px;">
    <?php foreach ($vendorStats['top_vendors'] as $i => $v): ?>
      <?php if ($v['total_paid'] > 0): ?>
      <div style="flex: 1; min-width: 180px; background: var(--bg-elevated); padding: 12px 16px; border-radius: 8px;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
          <span style="font-size: 20px; font-weight: 700; color: <?php echo $i === 0 ? 'var(--warning)' : 'var(--text-muted)'; ?>">
            #<?php echo $i + 1; ?>
          </span>
          <strong><?php echo htmlspecialchars($v['name']); ?></strong>
        </div>
        <div style="font-size: 18px; font-weight: 600; color: var(--success);">
          $<?php echo number_format($v['total_paid'], 0); ?>
        </div>
        <div class="small-text" style="color: var(--text-muted);">
          <?php echo htmlspecialchars($v['type'] ?? ''); ?>
        </div>
      </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="grid">
  <!-- Vendors Table -->
  <div class="card" style="grid-column: span 2;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
      <div>
        <h3>Vendors</h3>
        <div class="card-subtitle">Click a row for vendor details & documents.</div>
      </div>
      <button type="button" id="open-create-vendor-modal" class="btn">
        <i class="fa-solid fa-plus"></i> Add Vendor
      </button>
    </div>

    <div class="table-wrapper">
      <form method="post" id="vendor-table-form">
        <input type="hidden" name="action" id="vendor-table-action" value="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <div class="bulk-actions" style="margin-bottom:10px;min-height:32px;">
          <button type="button" id="bulk-delete-btn" class="btn btn-danger" style="opacity:0.6;cursor:not-allowed;display:none;">
            <i class="fa-solid fa-trash"></i> Delete Selected
          </button>
        </div>
        <table>
          <thead>
            <tr>
              <th style="width:32px;"><input type="checkbox" id="select-all-vendors"></th>
              <th>Name</th>
              <th>Type</th>
              <th>Trade / Specialty</th>
              <th>Contact</th>
              <th>Added</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($vendors)): ?>
              <tr>
                <td colspan="7">
                  <div class="empty-state">
                    <div class="empty-state-icon">👷</div>
                    <h3>No vendors yet</h3>
                    <p>Start building your vendor directory</p>
                    <?php if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'pm'])): ?>
                    <button type="button" class="btn" id="open-create-vendor-modal-empty">
                      <i class="fa-solid fa-plus"></i> Add Vendor
                    </button>
                    <script>
                      document.getElementById('open-create-vendor-modal-empty')?.addEventListener('click', function() {
                        document.getElementById('open-create-vendor-modal')?.click();
                      });
                    </script>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($vendors as $v): ?>
                <tr class="vendor-row"
                    data-id="<?php echo (int)$v['id']; ?>"
                    data-name="<?php echo htmlspecialchars($v['name'], ENT_QUOTES); ?>"
                    data-type="<?php echo htmlspecialchars($v['type'], ENT_QUOTES); ?>"
                    data-trade="<?php echo htmlspecialchars($v['trade'] ?? '', ENT_QUOTES); ?>"
                    data-phone="<?php echo htmlspecialchars($v['phone'] ?? '', ENT_QUOTES); ?>"
                    data-email="<?php echo htmlspecialchars($v['email'] ?? '', ENT_QUOTES); ?>"
                    data-created="<?php echo htmlspecialchars($v['created_at'] ?? '', ENT_QUOTES); ?>">
                  <td><input type="checkbox" class="vendor-checkbox" name="vendor_ids[]" value="<?php echo (int)$v['id']; ?>"></td>
                  <td><strong><?php echo htmlspecialchars($v['name']); ?></strong></td>
                  <td><?php echo htmlspecialchars($v['type']); ?></td>
                  <td><?php echo htmlspecialchars($v['trade'] ?? ''); ?></td>
                  <td>
                    <div class="small-text">
                      <?php echo htmlspecialchars($v['phone'] ?? ''); ?><br>
                      <?php echo htmlspecialchars($v['email'] ?? ''); ?>
                    </div>
                  </td>
                  <td class="small-text"><?php echo htmlspecialchars($v['created_at'] ?? ''); ?></td>
                  <td style="text-align:right;">
                    <button type="button" class="btn-secondary edit-vendor-btn" style="padding:4px 10px;font-size:12px;border-radius:999px;"
                        data-id="<?php echo (int)$v['id']; ?>"
                        data-name="<?php echo htmlspecialchars($v['name'], ENT_QUOTES); ?>"
                        data-type="<?php echo htmlspecialchars($v['type'], ENT_QUOTES); ?>"
                        data-trade="<?php echo htmlspecialchars($v['trade'] ?? '', ENT_QUOTES); ?>"
                        data-phone="<?php echo htmlspecialchars($v['phone'] ?? '', ENT_QUOTES); ?>"
                        data-email="<?php echo htmlspecialchars($v['email'] ?? '', ENT_QUOTES); ?>">
                      Edit
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </form>
    </div>
  </div>
</div>

<!-- CREATE VENDOR MODAL -->
<div id="create-vendor-modal" class="modal-overlay">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Add Vendor</h2>
      <button type="button" id="create-vendor-close" class="modal-close">&times;</button>
    </div>

    <form method="post">
      <input type="hidden" name="action" value="add_vendor">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

      <div class="form-group">
        <label>Vendor / Company Name *</label>
        <input type="text" name="name" required>
      </div>

      <div class="form-group">
        <label>Type</label>
        <select name="type">
          <option value="Subcontractor">Subcontractor</option>
          <option value="Supplier">Supplier</option>
          <option value="Consultant">Consultant</option>
          <option value="Other">Other</option>
        </select>
      </div>

      <div class="form-group">
        <label>Trade / Specialty</label>
        <input type="text" name="trade" placeholder="e.g. Framing, Electrical, Roofing">
      </div>

      <div class="form-group">
        <label>Phone</label>
        <input type="text" name="phone" placeholder="4804654654">
      </div>

      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email">
      </div>

      <div class="modal-actions">
        <button type="submit" class="btn btn-primary">Save Vendor</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT VENDOR MODAL -->
<div id="edit-vendor-modal" class="modal-overlay">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Edit Vendor</h2>
      <button type="button" id="edit-vendor-close" class="modal-close">&times;</button>
    </div>

    <form method="post" id="edit-vendor-form">
      <input type="hidden" name="action" id="edit-action" value="update_vendor">
      <input type="hidden" name="vendor_id" id="edit-vendor-id">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

      <div class="form-group">
        <label for="edit-name">Vendor / Company Name *</label>
        <input type="text" name="edit_name" id="edit-name" required>
      </div>

      <div class="form-group">
        <label for="edit-type">Type</label>
        <select name="edit_type" id="edit-type">
          <option value="Subcontractor">Subcontractor</option>
          <option value="Supplier">Supplier</option>
          <option value="Consultant">Consultant</option>
          <option value="Other">Other</option>
        </select>
      </div>

      <div class="form-group">
        <label for="edit-trade">Trade / Specialty</label>
        <input type="text" name="edit_trade" id="edit-trade">
      </div>

      <div class="form-group">
        <label for="edit-phone">Phone</label>
        <input type="text" name="edit_phone" id="edit-phone">
      </div>

      <div class="form-group">
        <label for="edit-email">Email</label>
        <input type="email" name="edit_email" id="edit-email">
      </div>

      <div class="modal-actions">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <button type="button" id="edit-vendor-delete" class="btn btn-danger-outline">Delete Vendor</button>
      </div>
    </form>
  </div>
</div>

<!-- VENDOR DETAIL & DOCUMENTS MODAL -->
<div id="vendor-detail-modal" class="modal-overlay">
  <div class="modal-content modal-large">
    <div class="modal-header">
      <div>
        <h2 id="detail-name">Vendor Details</h2>
        <div id="detail-sub" class="modal-subtitle"></div>
      </div>
      <button type="button" id="vendor-detail-close" class="modal-close">&times;</button>
    </div>

    <!-- Tabs -->
    <div class="modal-tabs">
      <button type="button" class="modal-tab active" data-tab="info">Info</button>
      <button type="button" class="modal-tab" data-tab="documents">Documents</button>
      <button type="button" class="modal-tab" data-tab="payments">Payments Log</button>
      <button type="button" class="modal-tab" data-tab="projects-bids">Projects & Bids</button>
    </div>

    <!-- Tab Content -->
    <div class="modal-tab-content">
      <!-- Info Tab -->
      <div id="tab-info" class="tab-pane active">
        <div class="modal-grid">
          <div class="card">
            <div class="card-header">
              <h3>Info</h3>
              <button type="button" class="btn btn-small" id="send-email-btn">Send Email</button>
            </div>
            <div class="card-subtitle">Contact, trade &amp; payment summary.</div>
            <div class="card-body">
              <div id="detail-info" class="detail-info"></div>
            </div>
          </div>

          <!-- ADD PAYMENT CARD -->
          <?php 
            $payment_roles = ['admin', 'pm', 'accounting'];
            if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $payment_roles)): 
          ?>
          <div class="card">
            <div class="card-header"><h3>Record Payment</h3></div>
            <div class="card-subtitle">Add a payment for this vendor.</div>
            <div class="card-body">
              <form method="post" class="modal-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_payment">
                <input type="hidden" name="payment_vendor_id" id="payment-vendor-id">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="form-group">
                  <label>Project *</label>
                  <select name="payment_project_id" required>
                    <option value="">Select project</option>
                    <?php foreach ($projectsForPayment as $proj): ?>
                    <option value="<?php echo (int)$proj['id']; ?>">
                      <?php echo htmlspecialchars($proj['name']); ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label>Amount *</label>
                  <input type="number" step="0.01" name="payment_amount" placeholder="0.00" required>
                </div>
                <div class="form-group">
                  <label>Date</label>
                  <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>">
                </div>
            <div class="form-group">
              <label>Description</label>
              <input type="text" name="payment_description" placeholder="e.g. Invoice #12345 - Phase 1">
            </div>
            <div class="form-group">
              <label>Invoice (PDF/Image)</label>
              <input type="file" name="payment_invoice" accept=".pdf,.jpg,.jpeg,.png">
            </div>
            <button type="submit" class="btn btn-primary">Record Payment</button>
              </form>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Documents Tab -->
      <div id="tab-documents" class="tab-pane">
        <div class="card">
          <div class="card-header"><h3>Documents</h3></div>
          <div class="card-subtitle">Contracts, W-9s, forms and other vendor files.</div>
          <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="modal-form">
              <input type="hidden" name="action" value="upload_vendor_doc">
              <input type="hidden" name="vendor_id" id="detail-vendor-id">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
              <div class="form-group">
                <label>Document name *</label>
                <input type="text" name="doc_label" placeholder="e.g. Master Subcontract 2025" required>
              </div>
              <div class="form-group">
                <label>File *</label>
                <input type="file" name="doc_file" required>
              </div>
              <button type="submit" class="btn btn-primary">Upload Document</button>
            </form>

            <div id="vendor-docs-list"></div>
          </div>
        </div>
      </div>

      <!-- Payments Log Tab -->
      <div id="tab-payments" class="tab-pane">
        <div class="card">
          <div class="card-header"><h3>Payments Log</h3></div>
          <div class="card-subtitle">All payments for this vendor.</div>
          <div class="card-body">
            <div id="vendor-payments-list"></div>
          </div>
        </div>
      </div>

      <!-- Projects & Bids Tab -->
      <div id="tab-projects-bids" class="tab-pane">
        <div class="card">
          <div class="card-header"><h3>Assign to Project</h3></div>
          <div class="card-body">
            <form method="post" class="modal-form" id="assign-project-form">
              <input type="hidden" name="action" value="assign_project_bid">
              <input type="hidden" name="bid_vendor_id" id="bid-vendor-id">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
              <div class="form-group">
                <label>Project *</label>
                <select name="bid_project_id" required>
                  <option value="">Select project</option>
                  <?php foreach ($allProjects as $proj): ?>
                  <option value="<?php echo (int)$proj['id']; ?>">
                    <?php echo htmlspecialchars($proj['name']); ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Bid Amount *</label>
                <input type="number" step="0.01" name="bid_amount" placeholder="0.00" required>
              </div>
              <button type="submit" class="btn btn-primary">Assign to Project</button>
            </form>
          </div>
        </div>
        <div class="card" style="margin-top: 16px;">
          <div class="card-header"><h3>Assigned Projects</h3></div>
          <div class="card-body" id="vendor-projects-list">
            <!-- Loaded via JS -->
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- DOCUMENT PREVIEW MODAL -->
<div id="doc-preview-modal" class="modal-overlay modal-dark">
  <div id="doc-preview-content" class="modal-content modal-document">
    <div class="modal-header">
      <div id="doc-preview-title">Document</div>
      <div class="modal-header-actions">
        <a id="doc-download-link" href="#" download class="btn btn-small">Download</a>
        <button id="doc-print-btn" type="button" class="btn btn-small btn-secondary">Print</button>
        <button id="doc-preview-close" type="button" class="modal-close">&times;</button>
      </div>
    </div>

    <div class="doc-preview-container">
      <div class="doc-preview-frame">
        <iframe id="doc-preview-frame" src=""></iframe>
      </div>
    </div>
  </div>
</div>

<!-- SEND EMAIL MODAL -->
<div id="email-modal" class="modal-overlay">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Send Email to Vendor</h2>
      <button type="button" id="email-modal-close" class="modal-close">&times;</button>
    </div>
    
    <form method="post">
      <input type="hidden" name="action" value="send_email">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
      
      <div class="form-group">
        <label for="email-to">To *</label>
        <input type="email" name="email_to" id="email-to" required placeholder="vendor@example.com">
      </div>
      
      <div class="form-group">
        <label for="email-subject">Subject *</label>
        <input type="text" name="email_subject" id="email-subject" required placeholder="Email subject">
      </div>
      
      <div class="form-group">
        <label for="email-body">Message *</label>
        <textarea name="email_body" id="email-body" required rows="6" placeholder="Write your message here..."></textarea>
      </div>
      
      <div class="modal-actions">
        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-paper-plane"></i> Send Email
        </button>
        <button type="button" class="btn btn-secondary" id="email-modal-cancel">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
(function() {
  // Bulk delete checkboxes
  var selectAll  = document.getElementById('select-all-vendors');
  var checkboxes = document.querySelectorAll('.vendor-checkbox');
  var bulkBtn    = document.getElementById('bulk-delete-btn');
  var tableForm  = document.getElementById('vendor-table-form');
  var tableAction = document.getElementById('vendor-table-action');

  function updateBulkButtonState() {
    var anyChecked = Array.from(checkboxes).some(function(cb) { return cb.checked; });
    if (bulkBtn) {
      bulkBtn.style.opacity = anyChecked ? '1' : '0.6';
      bulkBtn.style.cursor  = anyChecked ? 'pointer' : 'not-allowed';
    }
  }

  if (selectAll) {
    selectAll.addEventListener('change', function() {
      checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
      updateBulkButtonState();
    });
  }

  checkboxes.forEach(function(cb) {
    cb.addEventListener('change', updateBulkButtonState);
  });

  if (bulkBtn) {
    bulkBtn.addEventListener('click', function() {
      if (!Array.from(checkboxes).some(function(cb) { return cb.checked; })) return;
      if (confirm('Delete all selected vendors? This cannot be undone.')) {
        tableAction.value = 'bulk_delete';
        tableForm.submit();
      }
    });
  }

  // Create vendor modal
  var createModal = document.getElementById('create-vendor-modal');
  var createCloseBtn = document.getElementById('create-vendor-close');
  var openCreateBtn = document.getElementById('open-create-vendor-modal');

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

  // Edit vendor modal
  var editModal    = document.getElementById('edit-vendor-modal');
  var editCloseBtn = document.getElementById('edit-vendor-close');
  var editForm     = document.getElementById('edit-vendor-form');
  var editDeleteBtn = document.getElementById('edit-vendor-delete');

  document.querySelectorAll('.edit-vendor-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      var editVendorId = document.getElementById('edit-vendor-id');
      var editName = document.getElementById('edit-name');
      var editType = document.getElementById('edit-type');
      var editTrade = document.getElementById('edit-trade');
      var editPhone = document.getElementById('edit-phone');
      var editEmail = document.getElementById('edit-email');
      var editAction = document.getElementById('edit-action');
      
      if (editVendorId) editVendorId.value = btn.getAttribute('data-id') || '';
      if (editName) editName.value = btn.getAttribute('data-name') || '';
      if (editType) editType.value = btn.getAttribute('data-type') || '';
      if (editTrade) editTrade.value = btn.getAttribute('data-trade') || '';
      if (editPhone) editPhone.value = btn.getAttribute('data-phone') || '';
      if (editEmail) editEmail.value = btn.getAttribute('data-email') || '';
      if (editAction) editAction.value = 'update_vendor';
      if (editModal) editModal.style.display = 'flex';
    });
  });

  if (editCloseBtn) editCloseBtn.addEventListener('click', function(e) { e.preventDefault(); if (editModal) editModal.style.display = 'none'; });
  if (editModal) editModal.addEventListener('click', function(e) { if (e.target === editModal && editModal) editModal.style.display = 'none'; });

  if (editDeleteBtn && editForm) {
    editDeleteBtn.addEventListener('click', function() {
      if (confirm('Delete this vendor?')) {
        var editAction = document.getElementById('edit-action');
        if (editAction) editAction.value = 'delete_vendor';
        editForm.submit();
      }
    });
  }

  // Vendor detail & documents modal
  var detailModal    = document.getElementById('vendor-detail-modal');
  var detailClose    = document.getElementById('vendor-detail-close');
  var detailName     = document.getElementById('detail-name');
  var detailSub      = document.getElementById('detail-sub');
  var detailInfo     = document.getElementById('detail-info');
  var detailVendorId = document.getElementById('detail-vendor-id');
  var docsList       = document.getElementById('vendor-docs-list');
  var paymentsList   = document.getElementById('vendor-payments-list');

  var docsByVendor     = <?php echo json_encode($docsByVendor, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
  var paymentsByVendor = <?php echo json_encode($paymentsByVendor, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
  var paymentsDetailByVendor = <?php echo json_encode($paymentsDetailByVendor, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
  var vendorProjectsByVendor = <?php echo json_encode($vendorProjectsByVendor, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

  document.querySelectorAll('.vendor-row').forEach(function(row) {
    row.addEventListener('click', function() {
      var id      = row.getAttribute('data-id');
      var name    = row.getAttribute('data-name') || '';
      var type    = row.getAttribute('data-type') || '';
      var trade   = row.getAttribute('data-trade') || '';
      var phone   = row.getAttribute('data-phone') || '';
      var email   = row.getAttribute('data-email') || '';
      var created = row.getAttribute('data-created') || '';

      if (detailName) detailName.textContent = name || 'Vendor Details';
      if (detailSub) detailSub.textContent = (trade ? trade + ' • ' : '') + type;
      if (detailVendorId) detailVendorId.value = id;
      
      // Also set payment vendor ID
      var paymentVendorId = document.getElementById('payment-vendor-id');
      if (paymentVendorId) paymentVendorId.value = id;

      // Set bid vendor ID for projects-bids tab
      var bidVendorId = document.getElementById('bid-vendor-id');
      if (bidVendorId) bidVendorId.value = id;

      if (detailInfo) {
        var html = '<div><strong>Type:</strong> ' + (type || '-') + '</div>';
        html += '<div><strong>Trade:</strong> ' + (trade || '-') + '</div>';
        html += '<div><strong>Phone:</strong> ' + (phone || '-') + '</div>';
        html += '<div><strong>Email:</strong> ' + (email || '-') + '</div>';
        html += '<div><strong>Added:</strong> ' + (created || '-') + '</div>';

        var payRows = paymentsByVendor[id] || [];
        if (payRows.length) {
          html += '<div style="margin-top:8px;"><strong>Paid to date (by year):</strong>';
          html += '<div style="margin-top:2px;">';
          payRows.forEach(function(p) {
            html += '<div>' + p.yr + ': $' + Number(p.total || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + '</div>';
          });
          html += '</div></div>';
        } else {
          html += '<div style="margin-top:8px;"><strong>Paid to date:</strong> $0.00</div>';
        }
        detailInfo.innerHTML = html;
      }

      if (docsList) {
        var docs = docsByVendor[id] || [];
        if (!docs.length) {
          docsList.innerHTML = '<div class="muted">No documents uploaded yet for this vendor.</div>';
        } else {
          var listHtml = '<ul style="list-style:none;padding:0;margin:0;">';
          docs.forEach(function(d) {
            listHtml += '<li style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;gap:8px;">';
            listHtml += '<div><div>' + (d.label || 'Document') + '</div>';
            if (d.uploaded_at) listHtml += '<div class="small-text" style="opacity:0.7;font-size:12px;">' + d.uploaded_at + '</div>';
            listHtml += '</div>';
            listHtml += '<button type="button" class="btn-secondary doc-view-btn" data-path="' + encodeURI(d.file_path || '#') + '" data-label="' + (d.label || 'Document').replace(/"/g, '&quot;') + '">View</button>';
            listHtml += '</li>';
          });
          listHtml += '</ul>';
          docsList.innerHTML = listHtml;
        }
      }

      // Render payment history
      if (paymentsList) {
        var payments = paymentsDetailByVendor[id] || [];
        if (!payments.length) {
          paymentsList.innerHTML = '<div class="muted">No payments recorded yet.</div>';
        } else {
          var payHtml = '<table class="table table-striped" style="font-size: 13px;"><thead><tr><th>Date</th><th>Project</th><th>Amount</th><th>Description</th><th>Invoice</th></tr></thead><tbody>';
          payments.forEach(function(p) {
            payHtml += '<tr>';
            payHtml += '<td>' + (p.paid_date || '-') + '</td>';
            payHtml += '<td>' + (p.project_name || 'General') + '</td>';
            payHtml += '<td>$' + Number(p.amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2}) + '</td>';
            payHtml += '<td>' + (p.description || '-') + '</td>';
            if (p.invoice_path) {
              // Use same resolution as vendor_docs - just the relative path
              var basePath = window.location.pathname.replace(/[^/]*$/, '');
              payHtml += '<td><a href="' + basePath + p.invoice_path + '" target="_blank" class="btn btn-small btn-secondary">View</a></td>';
            } else {
              payHtml += '<td>-</td>';
            }
            payHtml += '</tr>';
          });
          payHtml += '</tbody></table>';
          paymentsList.innerHTML = payHtml;
        }
      }

      // Render vendor projects list
      var projectsList = document.getElementById('vendor-projects-list');
      if (projectsList) {
        var projects = vendorProjectsByVendor[id] || [];
        if (!projects.length) {
          projectsList.innerHTML = '<div class="muted">No projects assigned yet.</div>';
        } else {
          var projHtml = '<table class="table table-striped" style="font-size: 13px;"><thead><tr><th>Project</th><th>Bid Amount</th><th>Total Paid</th><th>Assigned</th></tr></thead><tbody>';
          projects.forEach(function(pj) {
            projHtml += '<tr>';
            projHtml += '<td>' + (pj.project_name || '-') + '</td>';
            projHtml += '<td>$' + Number(pj.bid_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2}) + '</td>';
            projHtml += '<td>$' + Number(pj.total_paid || 0).toLocaleString(undefined, {minimumFractionDigits: 2}) + '</td>';
            projHtml += '<td>' + (pj.assigned_at || '-') + '</td>';
            projHtml += '</tr>';
          });
          projHtml += '</tbody></table>';
          projectsList.innerHTML = projHtml;
        }
      }

      if (detailModal) detailModal.style.display = 'flex';
    });
  });

  // Modal tabs logic
  document.querySelectorAll('#vendor-detail-modal .modal-tab').forEach(function(tabBtn) {
    tabBtn.addEventListener('click', function() {
      var tabId = this.getAttribute('data-tab');
      
      // Update active tab button
      document.querySelectorAll('#vendor-detail-modal .modal-tab').forEach(function(b) { b.classList.remove('active'); });
      this.classList.add('active');
      
      // Show correct tab pane
      document.querySelectorAll('#vendor-detail-modal .tab-pane').forEach(function(pane) { pane.classList.remove('active'); });
      document.getElementById('tab-' + tabId).classList.add('active');
    });
  });

  if (detailClose) detailClose.addEventListener('click', function(e) { e.preventDefault(); if (detailModal) detailModal.style.display = 'none'; });
  if (detailModal) detailModal.addEventListener('click', function(e) { if (e.target === detailModal && detailModal) detailModal.style.display = 'none'; });

  // Document preview modal
  var docModal   = document.getElementById('doc-preview-modal');
  var docFrame   = document.getElementById('doc-preview-frame');
  var docTitle   = document.getElementById('doc-preview-title');
  var docDlLink  = document.getElementById('doc-download-link');
  var docPrint   = document.getElementById('doc-print-btn');
  var docClose   = document.getElementById('doc-preview-close');
  var docContent = document.getElementById('doc-preview-content');

  if (docsList) {
    docsList.addEventListener('click', function(e) {
      var btn = e.target.closest('.doc-view-btn');
      if (!btn) return;
      var path = btn.getAttribute('data-path');
      if (path && path !== '#') {
        var url = path;
        if (path.toLowerCase().endsWith('.pdf') && url.indexOf('#') === -1) url += '#page=1&zoom=page-fit';
        if (docFrame) docFrame.src = url;
        if (docTitle) docTitle.textContent = btn.getAttribute('data-label') || 'Document';
        if (docDlLink) docDlLink.href = path;
        if (docModal) docModal.style.display = 'flex';
      }
    });
  }

  if (docClose) docClose.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); if (docModal) docModal.style.display = 'none'; if (docFrame) docFrame.src = ''; });
  if (docModal) docModal.addEventListener('click', function(e) { if (!docContent || !docContent.contains(e.target)) { if (docModal) docModal.style.display = 'none'; if (docFrame) docFrame.src = ''; } });
  if (docPrint && docFrame) {
    docPrint.addEventListener('click', function() {
      try { docFrame.contentWindow.focus(); docFrame.contentWindow.print(); } catch(err) { alert('Use the browser print dialog after opening the document.'); }
    });
  }

  // ESC closes modals
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      if (createModal) createModal.style.display = 'none';
      if (editModal) editModal.style.display = 'none';
      if (detailModal) detailModal.style.display = 'none';
      if (docModal) docModal.style.display = 'none';
      if (emailModal) emailModal.style.display = 'none';
    }
  });

  // Email modal
  var emailModal = document.getElementById('email-modal');
  var emailModalClose = document.getElementById('email-modal-close');
  var emailModalCancel = document.getElementById('email-modal-cancel');
  var sendEmailBtn = document.getElementById('send-email-btn');

  if (sendEmailBtn) {
    sendEmailBtn.addEventListener('click', function() {
      // Get email from vendor being viewed - stored in data属性
      var detailInfo = document.getElementById('detail-info');
      var emailTo = document.getElementById('email-to');
      var emailSubject = document.getElementById('email-subject');
      
      // Parse vendor email from detail-info HTML
      var vendorEmail = '';
      if (detailInfo && detailInfo.innerHTML) {
        var emailMatch = detailInfo.innerHTML.match(/<div><strong>Email:<\/strong>\s*(.+?)<\/div>/);
        if (emailMatch && emailMatch[1] && emailMatch[1] !== '-') {
          vendorEmail = emailMatch[1].trim();
        }
      }
      
      if (emailTo) emailTo.value = vendorEmail;
      if (emailSubject) emailSubject.value = '';
      
      if (emailModal) emailModal.style.display = 'flex';
    });
  }

  if (emailModalClose) {
    emailModalClose.addEventListener('click', function() {
      if (emailModal) emailModal.style.display = 'none';
    });
  }

  if (emailModalCancel) {
    emailModalCancel.addEventListener('click', function() {
      if (emailModal) emailModal.style.display = 'none';
    });
  }

  if (emailModal) {
    emailModal.addEventListener('click', function(e) {
      if (e.target === emailModal) {
        emailModal.style.display = 'none';
      }
    });
  }
  
  // Auto-open vendor from search
  var openVendorId = '<?php echo $openVendorId; ?>';
  if (openVendorId) {
    var row = document.querySelector('.vendor-row[data-id="' + openVendorId + '"]');
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
})();
</script>

<?php require_once 'partials/footer.php'; ?>
