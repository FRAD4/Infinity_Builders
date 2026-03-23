<?php
/**
 * Security Helpers for Infinity Builders
 * Phase 2: CSRF, Password Hashing, RBAC
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate CSRF token and store in session
 */
function csrf_token_generate(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from form submission
 */
function csrf_token_validate(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token for form embedding
 */
function csrf_token_field(): string {
    $token = csrf_token_generate();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Validate request is POST with CSRF token
 */
function require_post_with_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die('Method not allowed');
    }
    
    $token = $_POST['csrf_token'] ?? $_POST['X-CSRF-Token'] ?? '';
    if (!csrf_token_validate($token)) {
        http_response_code(403);
        die('Invalid CSRF token');
    }
}

/**
 * Require specific role for page access
 */
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

/**
 * Hash password with bcrypt
 */
function hash_password(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password with migration support
 * Returns: true = valid, false = invalid, 'migrate' = valid but needs migration
 */
function verify_password(string $password, string $storedHash, string $algo): bool|string {
    if ($algo === 'bcrypt') {
        return password_verify($password, $storedHash);
    }
    
    // SHA256 legacy - check and flag for migration
    if ($algo === 'sha256' || $algo === 'sha256_migration_pending') {
        if (hash('sha256', $password) === $storedHash) {
            return 'migrate'; // Signal to migrate on login
        }
        return false;
    }
    
    return false;
}

/**
 * Regenerate session ID to prevent fixation
 */
function secure_session_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_regenerate_id(true);
}

/**
 * Check if current user has a specific role
 */
function has_role(string $role): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Convenience: check if current user is admin
 */
function is_admin(): bool {
    return has_role('admin');
}
