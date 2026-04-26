<?php
/**
 * Rate Limiting for Login Attempts
 * Prevents brute force attacks by limiting login attempts
 * 
 * Usage: 
 *   // At login start
 *   rate_limit_check($email);
 *   
 *   // On failed login
 *   rate_limit_record($email);
 *   
 *   // On successful login  
 *   rate_limit_clear($email);
 */

define('RATE_LIMIT_MAX_ATTEMPTS', 5);
define('RATE_LIMIT_WINDOW', 900); // 15 minutes
define('RATE_LOCKOUT_DURATION', 1800); // 30 minutes

/**
 * Check if an account is locked due to too many failed attempts
 */
function rate_limit_check(string $email): bool
{
    global $pdo;
    
    // Clean old attempts first
    rate_limit_cleanup();
    
    $stmt = $pdo->prepare("
        SELECT attempts, locked_until 
        FROM login_attempts 
        WHERE email = ? AND locked_until > NOW()
    ");
    $stmt->execute([strtolower($email)]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && strtotime($result['locked_until']) > time()) {
        $remaining = strtotime($result['locked_until']) - time();
        throw new Exception("Account locked. Try again in " . ceil($remaining/60) . " minutes.");
    }
    
    return true;
}

/**
 * Record a failed login attempt
 */
function rate_limit_record(string $email): void
{
    global $pdo;
    
    $email = strtolower($email);
    $now = date('Y-m-d H:i:s');
    
    // Check if record exists
    $stmt = $pdo->prepare("SELECT attempts FROM login_attempts WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        $newAttempts = $existing['attempts'] + 1;
        
        // Lock if max attempts reached
        $lockedUntil = null;
        if ($newAttempts >= RATE_LIMIT_MAX_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', time() + RATE_LOCKOUT_DURATION);
        }
        
        $stmt = $pdo->prepare("
            UPDATE login_attempts 
            SET attempts = ?, last_attempt = ?, locked_until = ?
            WHERE email = ?
        ");
        $stmt->execute([$newAttempts, $now, $lockedUntil, $email]);
    } else {
        // First attempt
        $lockedUntil = (RATE_LIMIT_MAX_ATTEMPTS <= 1) ? date('Y-m-d H:i:s', time() + RATE_LOCKOUT_DURATION) : null;
        
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (email, attempts, first_attempt, last_attempt, locked_until)
            VALUES (?, 1, ?, ?, ?)
        ");
        $stmt->execute([$email, $now, $now, $lockedUntil]);
    }
    
    // Log failed attempt
    log_login_attempt($email, false);
}

/**
 * Clear rate limit on successful login
 */
function rate_limit_clear(string $email): void
{
    global $pdo;
    
    $email = strtolower($email);
    
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email = ?");
    $stmt->execute([$email]);
}

/**
 * Clean up old rate limit records
 */
function rate_limit_cleanup(): void
{
    global $pdo;
    
    // Remove records older than the window + lockout
    $cutoff = date('Y-m-d H:i:s', time() - (RATE_LIMIT_WINDOW + RATE_LOCKOUT_DURATION));
    
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE last_attempt < ? AND locked_until IS NULL");
    $stmt->execute([$cutoff]);
}

/**
 * Log login attempts to audit log
 */
function log_login_attempt(string $email, bool $success): void
{
    global $pdo;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt = $pdo->prepare("
        INSERT INTO audit_log (user_id, username, action_type, entity_type, entity_name, ip_address, user_agent)
        VALUES (NULL, ?, ?, 'login', ?, ?, ?)
    ");
    
    $username = $success ? 'SUCCESS' : 'FAILED';
    $entityName = $success ? "Login successful: $email" : "Login failed: $email";
    
    $stmt->execute([$username, $success ? 'login_success' : 'login_failed', $entityName, $ip, $userAgent]);
}

/**
 * Create login_attempts table if not exists
 */
function rate_limit_install(): void
{
    global $pdo;
    
    $sql = "
    CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        attempts INT DEFAULT 1,
        first_attempt DATETIME,
        last_attempt DATETIME,
        locked_until DATETIME,
        INDEX idx_email (email),
        INDEX idx_locked_until (locked_until)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    $pdo->exec($sql);
}
