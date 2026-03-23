# Phase 2 Security Hardening - Testing Plan
## Infinity Builders

---

## Prerequisites

1. Local XAMPP MySQL running
2. Database `infinity_builders` initialized
3. Test users created (see below)

### Test Users Required

| Email | Password | Role | Password Algo |
|-------|----------|------|---------------|
| admin@infinity.com | admin123 | admin | sha256 (for migration test) |
| user@test.com | user123 | user | bcrypt (already migrated) |

---

## Test Suite

### 1. CSRF Protection

#### 1.1 Login Form CSRF Token
**Steps:**
1. Open browser to `http://localhost/Infinity%20Builders/public_html/index.php`
2. View page source, find hidden input `csrf_token`
3. Verify token value is present (32+ hex chars)

**Expected:** Token exists in form ✅

#### 1.2 Invalid CSRF Rejection
**Steps:**
1. Submit login form with tampered/empty `csrf_token`
2. Observe error message

**Expected:** Request rejected with "Invalid request" message ✅

---

### 2. RBAC (Role-Based Access Control)

#### 2.1 Admin Can Access Users Page
**Steps:**
1. Login as `admin@infinity.com` / `admin123`
2. Navigate to `users.php`
3. Verify user management page loads

**Expected:** Admin sees user management interface ✅

#### 2.2 Non-Admin Blocked from Users Page
**Steps:**
1. Login as `user@test.com` / `user123`
2. Try to access `users.php` directly

**Expected:** Access denied (403 or redirect to dashboard) ✅

#### 2.3 Admin Menu Visibility
**Steps:**
1. Login as `user@test.com`
2. Verify "Users" menu item is NOT visible in sidebar

**Expected:** Users link hidden for non-admin ✅

---

### 3. Password Migration

#### 3.1 SHA256 to bcrypt Migration (Auto on Login)
**Steps:**
1. Check current `password_algo` for admin user:
   ```sql
   SELECT email, password_algo FROM users WHERE email = 'admin@infinity.com';
   ```
2. Should show `sha256`
3. Login as `admin@infinity.com` / `admin123`
4. Check `password_algo` again:
   ```sql
   SELECT email, password_algo, password_hash IS NOT NULL as has_bcrypt FROM users WHERE email = 'admin@infinity.com';
   ```
5. Should show `bcrypt` with non-null `password_hash`

**Expected:** `password_algo` changed to `bcrypt`, bcrypt hash stored in `password_hash` ✅

#### 3.2 Subsequent Login Uses bcrypt
**Steps:**
1. Logout and login again as `admin@infinity.com`
2. Verify login succeeds using bcrypt (not SHA256)

**Expected:** Login works, bcrypt verification used ✅

#### 3.3 Migration Script Dry-Run
**Steps:**
```bash
cd C:/xampp/htdocs/Infinity\ Builders/public_html
php scripts/migrate_passwords.php
```

**Expected:** Shows preview of users to migrate (if any) without applying changes ✅

#### 3.4 Migration Script Live Mode
**Steps:**
```bash
php scripts/migrate_passwords.php --live
```

**Expected:** Marks users with `password_algo = 'sha256_migration_pending'` ✅

---

### 4. Input Sanitization

#### 4.1 XSS Prevention in Forms
**Steps:**
1. Login as admin
2. Try to input `<script>alert('xss')</script>` in any text field
3. Submit form

**Expected:** Script tags are escaped/not executed ✅

---

## Quick Test Commands

```bash
# Check password migration status
mysql -u root infinity_builders -e "SELECT email, password_algo, password_hash IS NOT NULL as has_bcrypt FROM users;"

# Reset admin to sha256 for re-testing
mysql -u root infinity_builders -e "UPDATE users SET password_algo='sha256', password_hash=NULL WHERE email='admin@infinity.com';"

# Run migration script
cd C:/xampp/htdocs/Infinity\ Builders/public_html
php scripts/migrate_passwords.php --dry-run
php scripts/migrate_passwords.php --live
```

---

## Test Results Checklist

| Test | Description | Result |
|------|-------------|--------|
| 1.1 | Login form has CSRF token | ☐ |
| 1.2 | Invalid CSRF rejected | ☐ |
| 2.1 | Admin accesses users.php | ☐ |
| 2.2 | Non-admin blocked from users.php | ☐ |
| 2.3 | Admin menu hidden for user | ☐ |
| 3.1 | SHA256 auto-migrates on login | ☐ |
| 3.2 | bcrypt login works | ☐ |
| 3.3 | Migration script dry-run | ☐ |
| 3.4 | Migration script live | ☐ |
| 4.1 | XSS prevention | ☐ |

---

## Troubleshooting

### Login fails with "Invalid email or password"
- Check database connection in `config.local.php`
- Verify user exists: `SELECT * FROM users WHERE email='admin@infinity.com';`
- Check password is SHA256: `SELECT password FROM users WHERE email='admin@infinity.com';`

### CSRF token missing
- Ensure `partials/init.php` is included at top of page
- Check `includes/security.php` has `csrf_token_generate()` called on page load

### Migration not happening
- Verify `password_algo = 'sha256'` for the user
- Check `login.php` line 75-77 uses correct hash column
