# Infinity Builders — Testing Guide

> Guide for AI agents running tests, writing new tests, and understanding test coverage.

---

## Test Files Location

```
public_html/testing/
```

### Available Test Files

| File | Purpose |
|------|---------|
| `full_test.php` | Main test suite (all tests) |
| `phase3_test.php` | Phase 3 functionality tests |
| `css-redesign-test.md` | Test plan for CSS redesign |
| `PHASE_D_security_hardening_plan.md` | Security phase documentation |

---

## Running Tests

### Run Full Test Suite

```bash
php public_html/testing/full_test.php
```

### Run Specific Test Group

Tests are organized by category within `full_test.php`. You can run individual test functions by editing the file.

---

## Test Coverage

### Current Tests (full_test.php)

The test suite covers:

1. **CSRF Protection**
   - Token generation (64-character hex)
   - Valid token acceptance
   - Invalid token rejection
   - HTML field generation

2. **Password Functions**
   - bcrypt hashing (format `$2y$...`)
   - Password verification
   - Wrong password rejection
   - SHA256 verification with migration signal
   - Migration pending status handling

3. **RBAC Functions**
   - has_role() with different session states
   - is_admin() detection
   - Role comparison logic

4. **Database State**
   - Test user presence
   - Password algorithm column
   - Role assignments

5. **Login Flow**
   - Complete authentication process
   - Password verification with migration
   - Session establishment

---

## Test Structure Pattern

### Basic Test Function

```php
function test_csrf_generation() {
    require_once __DIR__ . '/../public_html/includes/security.php';
    
    // Generate token
    $token = csrf_token_generate();
    
    // Assertions
    if (strlen($token) !== 64) {
        return ['status' => 'fail', 'message' => 'Token should be 64 chars'];
    }
    
    if (!ctype_xdigit($token)) {
        return ['status' => 'fail', 'message' => 'Token should be hex'];
    }
    
    return ['status' => 'pass'];
}
```

### Test Runner

```php
$tests = [
    ['name' => 'CSRF Generation', 'fn' => 'test_csrf_generation'],
    ['name' => 'CSRF Validation', 'fn' => 'test_csrf_validation'],
    // ... more tests
];

foreach ($tests as $test) {
    $result = $test['fn']();
    echo $test['name'] . ': ' . $result['status'] . "\n";
    if ($result['status'] === 'fail') {
        echo "  → " . $result['message'] . "\n";
    }
}
```

---

## Writing New Tests

### Test Password Hashing

```php
function test_password_hashing() {
    require_once __DIR__ . '/../public_html/includes/security.php';
    
    $password = 'testpassword123';
    $hash = hash_password($password);
    
    // Check bcrypt format
    if (!str_starts_with($hash, '$2y$')) {
        return ['status' => 'fail', 'message' => 'Should produce bcrypt hash'];
    }
    
    // Check verification
    if (!password_verify($password, $hash)) {
        return ['status' => 'fail', 'message' => 'Should verify correct password'];
    }
    
    return ['status' => 'pass'];
}
```

### Test Role Checking

```php
function test_has_role() {
    require_once __DIR__ . '/../public_html/includes/security.php';
    
    // Start fresh session
    $_SESSION = [];
    
    // Test no role
    if (has_role('admin')) {
        return ['status' => 'fail', 'message' => 'Should return false with no session'];
    }
    
    // Test with role
    $_SESSION['user_role'] = 'admin';
    if (!has_role('admin')) {
        return ['status' => 'fail', 'message' => 'Should return true for admin'];
    }
    
    // Cleanup
    $_SESSION = [];
    
    return ['status' => 'pass'];
}
```

### Test Database Query

```php
function test_user_query() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT id, email, role FROM users LIMIT 1");
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['status' => 'fail', 'message' => 'No users found in database'];
        }
        
        if (empty($user['email'])) {
            return ['status' => 'fail', 'message' => 'User email is empty'];
        }
        
        return ['status' => 'pass'];
    } catch (Exception $e) {
        return ['status' => 'fail', 'message' => 'Query failed: ' . $e->getMessage()];
    }
}
```

---

## Common Test Scenarios

### Test Form Submission

```php
function test_form_csrf_protection() {
    // Simulate POST without token
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['action' => 'create'];
    
    $result = csrf_token_validate('');
    
    if ($result) {
        return ['status' => 'fail', 'message' => 'Should reject empty token'];
    }
    
    return ['status' => 'pass'];
}
```

### Test Input Sanitization

```php
function test_sanitize_input() {
    require_once __DIR__ . '/../public_html/includes/sanitize.php';
    
    // Test XSS prevention
    $dirty = '<script>alert("xss")</script>';
    $clean = sanitize_input($dirty, 'string');
    
    if (strpos($clean, '<script>') !== false) {
        return ['status' => 'fail', 'message' => 'Should escape script tags'];
    }
    
    // Test integer validation
    $int = sanitize_input('42', 'int');
    if ($int !== 42) {
        return ['status' => 'fail', 'message' => 'Should parse integer'];
    }
    
    return ['status' => 'pass'];
}
```

### Test API Endpoint

```php
function test_api_unauthorized() {
    // Simulate API call without session
    $_SESSION = [];
    
    // Would check: if (!isset($_SESSION['user_id'])) { ... }
    $authorized = isset($_SESSION['user_id']);
    
    if ($authorized) {
        return ['status' => 'fail', 'message' => 'Should not be authorized without session'];
    }
    
    return ['status' => 'pass'];
}
```

---

## Testing Best Practices

1. **Always clean up**: Reset `$_SESSION`, `$_POST`, etc. after tests
2. **Use meaningful names**: `test_` prefix for test functions
3. **Return structured results**: `['status' => 'pass'|'fail', 'message' => '...']`
4. **Test one thing**: Each test should verify one specific behavior
5. **Handle exceptions**: Try-catch around database operations

---

## Known Test Issues

1. **XAMPP mail() not configured**: Email tests show "failed" status (expected in dev)
2. **Session-based rate limiting**: Tests don't cover rate limiting (resets on browser close)
3. **Manual database setup**: Tests assume database is already created

---

## Test Database Setup

Before running tests, ensure:

```php
// In test file
require_once __DIR__ . '/../public_html/config.php';

// Check database exists
try {
    $stmt = $pdo->query("SELECT 1");
} catch (Exception $e) {
    die("Database not available: " . $e->getMessage());
}
```

---

> **Last Updated**: 2026-04-03
