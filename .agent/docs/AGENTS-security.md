# Infinity Builders — Security Guide

> Guide for AI agents working on authentication, authorization, and security.

---

## Security Files

| File | Purpose |
|------|---------|
| `includes/security.php` | CSRF, auth, RBAC, password hashing |
| `includes/sanitize.php` | Input validation and sanitization |
| `includes/database.php` | MySQLi wrapper (legacy, unused) |
| `includes/audit.php` | Activity logging |

---

## CSRF Protection

### Generation

```php
require_once 'includes/security.php';
$csrf_token = csrf_token_generate();
```

```php
// Output: 64-character hex string stored in $_SESSION['csrf_token']
function csrf_token_generate(): string {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
```

### Validation

```php
$submitted_token = $_POST['csrf_token'] ?? '';
if (!csrf_token_validate($submitted_token)) {
    $message = "Invalid request. Please try again.";
    // Stop processing
}
```

```php
// Uses timing-safe comparison
function csrf_token_validate(?string $token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token ?? '');
}
```

### In Forms

```php
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
```

### Header for AJAX

```javascript
fetch('api/endpoint.php', {
    method: 'POST',
    headers: {
        'X-CSRF-Token': '<?php echo $csrf_token; ?>'
    },
    // ...
});
```

---

## Password Hashing

### Hashing (bcrypt)

```php
function hash_password(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}
```

### Verification with Migration

```php
function verify_password(string $password, string $storedHash, string $algo): bool|string {
    if ($algo === 'bcrypt') {
        return password_verify($password, $storedHash);
    }
    
    if ($algo === 'sha256' || $algo === 'sha256_migration_pending') {
        if (hash('sha256', $password) === $storedHash) {
            return 'migrate';  // Signal to upgrade to bcrypt
        }
        return false;
    }
    
    return false;
}
```

### Login with Auto-Migration

```php
$result = verify_password($password, $user['password_hash'], $user['password_algo']);

if ($result === true) {
    // Login success
} elseif ($result === 'migrate') {
    // Valid SHA256 password - upgrade to bcrypt
    $newHash = hash_password($password);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, password_algo = 'bcrypt' WHERE id = ?");
    $stmt->execute([$newHash, $user['id']]);
    // Continue with login
} else {
    // Invalid password
}
```

---

## Role-Based Access Control (RBAC)

### Roles

| Role | Permissions |
|------|-------------|
| `admin` | Full access, user management, all features |
| `pm` | Projects, reports, tasks, documents, team |
| `accounting` | Financial reports, payments, tasks |
| `estimator` | Tasks, documents, limited reports |
| `viewer` | Read-only access |

### Check Function

```php
function has_role(string $role): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}
```

### Admin Check

```php
function is_admin(): bool {
    return has_role('admin');
}
```

### Require Role (page-level restriction)

```php
function require_role(string $role): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
    
    $userRole = $_SESSION['user_role'] ?? 'user';
    if ($userRole !== $role) {
        http_response_code(403);
        die('Access denied');
    }
}

// Usage
require_role('admin');
```

### In Templates

```php
<?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
    <a href="users.php">Users</a>
    <a href="audit.php">Activity Log</a>
<?php endif; ?>

<?php 
$report_roles = ['admin', 'pm', 'accounting', 'estimator'];
if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $report_roles)): 
?>
    <a href="reports.php">Reports</a>
<?php endif; ?>
```

---

## Input Sanitization

### Sanitize Input

```php
function sanitize_input(mixed $value, string $type = 'string'): mixed {
    if ($value === null) return null;
    
    switch ($type) {
        case 'int':
            return filter_var($value, FILTER_VALIDATE_INT) !== false 
                ? (int)$value 
                : 0;
        
        case 'email':
            return filter_var(trim($value), FILTER_VALIDATE_EMAIL) 
                ? strtolower(trim($value)) 
                : '';
        
        case 'html':
            return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        
        case 'string':
        default:
            return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }
}
```

### Convenience Functions

```php
// From $_POST
sanitize_post('email', 'email');  // Returns sanitized email or ''
sanitize_post('id', 'int');       // Returns int or 0

// From $_GET
sanitize_get('search', 'string');
```

### Array Sanitization

```php
function sanitize_input_array(array $data, string $type = 'string'): array {
    return array_map(fn($v) => sanitize_input($v, $type), $data);
}
```

---

## Output Encoding

### Always Escape User Data

```php
// WRONG - XSS vulnerability
echo $userInput;

// CORRECT - Safe output
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');
```

### In HTML Attributes

```php
// Use ENT_QUOTES for attributes
echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
```

### In JavaScript

```php
// Escape for JS context
<script>
const name = <?php echo json_encode($name); ?>;
</script>
```

---

## SQL Injection Prevention

### Always Use Prepared Statements

```php
// WRONG - SQL injection vulnerability
$sql = "SELECT * FROM users WHERE id = " . $id;

// CORRECT - Parameterized query
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
```

### With LIKE Search

```php
// WRONG
$sql = "SELECT * FROM projects WHERE name LIKE '%$search%'";

// CORRECT
$sql = "SELECT * FROM projects WHERE name LIKE ?";
$searchParam = '%' . $search . '%';
$stmt = $pdo->prepare($sql);
$stmt->execute([$searchParam]);
```

### With IN Clause

```php
$ids = array_filter(array_map('intval', $ids), fn($v) => $v > 0);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "DELETE FROM projects WHERE id IN ($placeholders)";
$stmt = $pdo->prepare($sql);
$stmt->execute($ids);
```

---

## Session Security

### Secure Start

```php
function secure_session_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_regenerate_id(true);  // Prevent session fixation
}
```

### Session After Login

```php
// Regenerate session ID after successful login
session_regenerate_id(true);

$_SESSION['user_id'] = $user['id'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['username'] = $user['username'];
```

### Logout

```php
$_SESSION = array();
session_destroy();
```

---

## Audit Logging

### Log Action

```php
audit_log(
    $action,        // 'create', 'update', 'delete'
    $entity,        // 'projects', 'vendors', etc.
    $entity_id,     // ID of the affected record
    $entity_name,   // Display name
    $changes = null, // Array with old/new values for updates
    $user_id = null // Optional, uses $_SESSION['user_id'] if null
);
```

### Examples

```php
// Create
audit_log('create', 'projects', $pdo->lastInsertId(), $name);

// Update with changes
audit_log('update', 'projects', $id, $name, [
    'old' => $oldData,
    'new' => ['name' => $name, 'status' => $status]
]);

// Delete
audit_log('delete', 'projects', $id, $oldData['name']);
```

---

## Common Security Tasks

### Add CSRF to Form

```php
<?php
require_once 'includes/security.php';
$csrf_token = csrf_token_generate();
?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <!-- form fields -->
</form>
```

### Validate POST Request

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_token_validate($token)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
    // Continue processing
}
```

### Restrict Page to Role

```php
require_once 'partials/init.php';
require_role('admin');  // Dies if not admin
```

### Add Role Check to UI

```php
<?php if (is_admin()): ?>
    <a href="users.php">Manage Users</a>
<?php endif; ?>
```

---

## Known Security Issues

1. **Logout without CSRF**: Logout is a simple GET redirect, no CSRF protection
2. **Session-based rate limiting**: Resets when browser closes
3. **Hardcoded credentials**: DB passwords in config.php (dev only, should use env vars)

---

## Running Security Tests

```bash
php public_html/testing/full_test.php
```

Tests cover:
- CSRF token generation and validation
- Password hashing and verification
- RBAC functions
- Login flow with migration

---

> **Last Updated**: 2026-04-03
