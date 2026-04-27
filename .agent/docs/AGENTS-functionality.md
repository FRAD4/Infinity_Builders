# Infinity Builders — Functionality Guide

> Guide for AI agents working on CRUD operations, forms, helpers, and business logic.

---

## File Structure

```
public_html/
├── Main Pages
│   ├── dashboard.php     → Personalized dashboard by role
│   ├── projects.php      → Project CRUD + bulk operations
│   ├── vendors.php       → Vendor management
│   ├── users.php         → User management (admin only)
│   ├── calendar.php      → FullCalendar timeline
│   ├── reports.php       → Analytics and charts
│   ├── audit.php         → Audit log viewer
│   └── settings.php      → User preferences
│
├── Includes (Helpers)
│   ├── security.php      → CSRF, auth, RBAC
│   ├── sanitize.php      → Input validation
│   ├── audit.php         → Activity logging
│   ├── email.php         → PHPMailer wrapper
│   ├── preferences.php   → User preferences
│   └── notifications.php → Alert helpers
│
└── Exports
    ├── export-projects.php
    ├── export-vendors.php
    ├── export-payments.php
    └── export-audit.php
```

---

## Page Structure Pattern

Every main page follows this template:

```php
<?php
// 1. Metadata
$pageTitle = 'Page Name';
$currentPage = 'page-name';

// 2. Bootstrap
require_once 'partials/init.php';

// 3. Access control
$userRole = $_SESSION['user_role'] ?? 'viewer';
$allowedRoles = ['admin', 'pm', 'estimator'];
if (!in_array($userRole, $allowedRoles, true)) {
    http_response_code(403);
    die('Access denied.');
}

// 4. CSRF
require_once 'includes/security.php';
$csrf_token = csrf_token_generate();

// 5. GET filters
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

// 6. POST handling (CRUD)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_item') {
        // Handle create
    } elseif ($action === 'update_item') {
        // Handle update
    } elseif ($action === 'delete_item') {
        // Handle delete
    }
}

// 7. Load data
$items = load_items($pdo, $search, $status);

// 8. Render
require_once 'partials/header.php';
?>
<!-- HTML content, modals, JavaScript -->
<?php require_once 'partials/footer.php'; ?>
```

---

## CRUD Operations

### Create

```php
if ($action === 'create_project') {
    $name = trim($_POST['name'] ?? '');
    
    if ($name === '') {
        $message = "Project name is required.";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO projects (name, client_name, status, total_budget, start_date, end_date)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $name,
            $_POST['client_name'] ?? '',
            $_POST['status'] ?? 'pending',
            $_POST['total_budget'] ?? 0,
            $_POST['start_date'] ?? null,
            $_POST['end_date'] ?? null
        ]);
        
        $newId = $pdo->lastInsertId();
        audit_log('create', 'projects', $newId, $name);
        
        header("Location: projects.php?message=created");
        exit;
    }
}
```

### Update

```php
if ($action === 'update_project') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    
    // Get old data for audit
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        UPDATE projects 
        SET name = ?, client_name = ?, status = ?, total_budget = ?, start_date = ?, end_date = ?, notes = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $name,
        $_POST['client_name'] ?? '',
        $_POST['status'] ?? 'pending',
        $_POST['total_budget'] ?? 0,
        $_POST['start_date'] ?? null,
        $_POST['end_date'] ?? null,
        $_POST['notes'] ?? '',
        $id
    ]);
    
    audit_log('update', 'projects', $id, $name, [
        'old' => $old,
        'new' => ['name' => $name, 'status' => $_POST['status']]
    ]);
    
    $message = "Project updated.";
}
```

### Delete

```php
if ($action === 'delete_project') {
    $id = (int)($_POST['id'] ?? 0);
    
    // Get name for audit
    $stmt = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    
    audit_log('delete', 'projects', $id, $project['name'] ?? 'Unknown');
    
    $message = "Project deleted.";
}
```

### Bulk Delete

```php
if ($action === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    $ids = array_filter(array_map('intval', $ids), fn($v) => $v > 0);
    
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        
        $message = count($ids) . " projects deleted.";
    }
}
```

---

## Modal-Based UI

### Create Modal

```php
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Create New Project</h3>
            <span class="close" onclick="closeModal('createModal')">&times;</span>
        </div>
        <form method="POST" id="createForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="create_project">
            
            <div class="form-group">
                <label>Project Name *</label>
                <input type="text" name="name" id="create-name" required>
            </div>
            
            <div class="form-group">
                <label>Client</label>
                <input type="text" name="client_name" id="create-client">
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="create-status">
                    <option value="pending">Pending</option>
                    <option value="active">Active</option>
                    <option value="on hold">On Hold</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>
```

### Edit Modal (Populated via data-* attributes)

```php
<tr class="project-row"
    data-id="<?php echo (int)$p['id']; ?>"
    data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>"
    data-client="<?php echo htmlspecialchars($p['client_name'] ?? '', ENT_QUOTES); ?>"
    data-status="<?php echo htmlspecialchars($p['status'] ?? '', ENT_QUOTES); ?>"
    data-budget="<?php echo htmlspecialchars($p['total_budget'] ?? '', ENT_QUOTES); ?>">
    <td><?php echo htmlspecialchars($p['name']); ?></td>
    <td><?php echo htmlspecialchars($p['client_name']); ?></td>
</tr>
```

```javascript
// JavaScript to populate edit modal
document.querySelectorAll('.project-row').forEach(row => {
    row.addEventListener('dblclick', function() {
        document.getElementById('edit-id').value = this.dataset.id;
        document.getElementById('edit-name').value = this.dataset.name;
        document.getElementById('edit-client').value = this.dataset.client;
        document.getElementById('edit-status').value = this.dataset.status;
        document.getElementById('edit-budget').value = this.dataset.budget;
        openModal('editModal');
    });
});
```

---

## Form Validation

### Server-Side Validation

```php
$errors = [];

// Required field
if (empty($name)) {
    $errors[] = "Project name is required.";
}

// Minimum length
if (strlen($username) < 3) {
    $errors[] = "Username must be at least 3 characters.";
}

// Email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format.";
}

// Numeric validation
if (!is_numeric($budget) || $budget < 0) {
    $errors[] = "Budget must be a positive number.";
}

// Duplicate check
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$username]);
if ($stmt->fetch()) {
    $errors[] = "Username already exists.";
}

// Display errors
if (!empty($errors)) {
    $message = implode("<br>", array_map('htmlspecialchars', $errors));
}
```

### Client-Side Validation

```javascript
createForm.addEventListener('submit', function(e) {
    const nameInput = document.getElementById('create-name');
    const formGroup = nameInput.closest('.form-group');
    
    formGroup.classList.remove('error');
    
    if (!nameInput.value.trim()) {
        e.preventDefault();
        formGroup.classList.add('error');
        nameInput.focus();
        return false;
    }
    
    return true;
});
```

---

## Helper Functions

### Security (includes/security.php)

| Function | Usage |
|----------|-------|
| `csrf_token_generate()` | Generate CSRF token |
| `csrf_token_validate($token)` | Validate token |
| `require_role($role)` | Restrict to role |
| `has_role($role)` | Check role |
| `is_admin()` | Check admin |
| `hash_password($password)` | bcrypt hash |
| `verify_password(...)` | Verify with migration |

### Sanitize (includes/sanitize.php)

```php
sanitize_input($value, 'string');  // XSS escape
sanitize_input($value, 'int');      // Validate int
sanitize_input($value, 'email');   // Validate email
sanitize_post('name', 'string');   // Sanitize POST
sanitize_get('id', 'int');         // Sanitize GET
```

### Audit (includes/audit.php)

```php
audit_log('create', 'projects', $id, $name);
audit_log('update', 'projects', $id, $name, ['old' => $old, 'new' => $new]);
audit_log('delete', 'projects', $id, $name);
```

### Email (includes/email.php)

```php
send_email($to, $subject, $body);
log_email($to, $subject, $body, 'sent');
```

### Preferences (includes/preferences.php)

```php
$theme = get_user_preference($userId, 'dashboard_theme');
$prefs = get_all_user_preferences($userId);
set_user_preference($userId, 'dashboard_theme', 'dark');
```

---

## Query Patterns

### Basic Select

```php
$stmt = $pdo->query("SELECT * FROM projects ORDER BY created_at DESC");
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### With Filter

```php
$sql = "SELECT * FROM projects WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND name LIKE ?";
    $params[] = '%' . $search . '%';
}

if (!empty($status)) {
    $sql .= " AND status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### With Join

```php
$stmt = $pdo->prepare("
    SELECT p.*, u.username as created_by_name
    FROM projects p
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);
```

### Aggregation

```php
$stmt = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM projects 
    GROUP BY status
");
$stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

---

## Export to CSV

### Pattern

```php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="projects.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Name', 'Client', 'Status', 'Budget']);

foreach ($projects as $p) {
    fputcsv($output, [
        $p['id'],
        $p['name'],
        $p['client_name'],
        $p['status'],
        $p['total_budget']
    ]);
}

fclose($output);
exit;
```

---

## Common Tasks

### Add New Form Field

1. Add field to Create modal in HTML
2. Add field to Edit modal in HTML
3. Add to POST handler in PHP
4. Add to SQL INSERT/UPDATE

### Add New CRUD Operation

1. Add action value to form: `<input type="hidden" name="action" value="action_name">`
2. Add handler in POST block
3. Add modal HTML
4. Add JavaScript to populate modal

### Add Filter

1. Add to GET parameters in PHP block
2. Add filter form in HTML (or use URL params)
3. Apply in SQL query

---

## Project Statuses

When creating or updating projects, use these statuses:

```php
// In forms
<select name="status">
    <option value="Signed">Signed</option>
    <option value="Starting Soon">Starting Soon</option>
    <option value="Active">Active</option>
    <option value="Waiting on Permit">Waiting on Permit</option>
    <option value="Waiting on Materials">Waiting on Materials</option>
    <option value="On Hold">On Hold</option>
    <option value="Completed">Completed</option>
    <option value="Cancelled">Cancelled</option>
</select>
```

### Permit Statuses

```php
<select name="status">
    <option value="pending_submission">Pending Submission</option>
    <option value="waiting_approval">Waiting Approval</option>
    <option value="approved">Approved</option>
    <option value="rejected">Rejected</option>
    <option value="expired">Expired</option>
</select>
```

### Inspection Statuses

```php
<select name="status">
    <option value="not_scheduled">Not Scheduled</option>
    <option value="requested">Requested</option>
    <option value="scheduled">Scheduled</option>
    <option value="completed">Completed</option>
    <option value="passed">Passed</option>
    <option value="failed">Failed</option>
    <option value="reinspection_needed">Reinspection Needed</option>
</select>
```

---

## Project Cities

```php
<select name="city">
    <option value="Phoenix">Phoenix</option>
    <option value="Scottsdale">Scottsdale</option>
    <option value="Tempe">Tempe</option>
    <option value="Chandler">Chandler</option>
    <option value="Mesa">Mesa</option>
    <option value="Gilbert">Gilbert</option>
    <option value="Peoria">Peoria</option>
    <option value="Glendale">Glendale</option>
    <option value="Surprise">Surprise</option>
    <option value="Avondale">Avondale</option>
    <option value="Goodyear">Goodyear</option>
</select>
```

## Project Types

```php
<select name="project_type">
    <option value="Kitchen">Kitchen</option>
    <option value="Bathroom">Bathroom</option>
    <option value="Addition">Addition</option>
    <option value="Roofing">Roofing</option>
    <option value="Flooring">Flooring</option>
    <option value="Painting">Painting</option>
    <option value="HVAC">HVAC</option>
    <option value="Electrical">Electrical</option>
    <option value="Plumbing">Plumbing</option>
    <option value="Full Remodel">Full Remodel</option>
    <option value="Other">Other</option>
</select>
```

## Project Managers

```php
<select name="project_manager">
    <option value="Regev Cohen">Regev Cohen</option>
    <option value="Yossi Dror">Yossi Dror</option>
    <option value="Carmel Cohen">Carmel Cohen</option>
    <option value="Lucas Martelli">Lucas Martelli</option>
    <option value="Azul Ortelli">Azul Ortelli</option>
    <option value="Nicolas Ortiz">Nicolas Ortiz</option>
</select>
```

---

> **Last Updated**: 2026-04-03
