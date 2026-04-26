<?php
/**
 * Email Sender - Infinity Builders
 * Phase 3: Email Feature
 * 
 * Wrapper for sending emails with logging to emails_log table
 */

require_once __DIR__ . '/../config/config.email.php';

// Load Composer autoloader for PHPMailer
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email with logging
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML or plain text)
 * @return array ['status' => 'sent'|'failed', 'error_message' => string|null]
 */
function send_email(string $to, string $subject, string $body): array {
    $result = [
        'status' => 'failed',
        'error_message' => null
    ];
    
    // Validate inputs
    if (empty($to) || empty($subject) || empty($body)) {
        $result['error_message'] = 'Missing required fields: to, subject, or body';
        log_email($to, $subject, $body, 'failed', $result['error_message']);
        return $result;
    }
    
    // Validate email format
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $result['error_message'] = 'Invalid email address: ' . $to;
        log_email($to, $subject, $body, 'failed', $result['error_message']);
        return $result;
    }
    
    // Try PHPMailer first, then fallback to mail()
    $sent = false;
    $error_msg = null;
    
    // Check if PHPMailer is available (uses namespace PHPMailer\PHPMailer\PHPMailer)
    if (class_exists('PHPMailer\PHPMailer\Exception')) {
        try {
            $mail = new PHPMailer();
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->Port = SMTP_PORT;
            $mail->SMTPSecure = SMTP_SECURE;
            
            if (!empty(SMTP_USER)) {
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USER;
                $mail->Password = SMTP_PASS;
            }
            
            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML(true);
            
            $sent = $mail->send();
            if (!$sent) {
                $error_msg = $mail->ErrorInfo;
            }
        } catch (PHPMailer\PHPMailer\Exception $e) {
            $error_msg = $e->getMessage();
        }
    } else {
        // Fallback to PHP mail()
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM . '>',
            'Reply-To: ' . SMTP_FROM,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        $sent = @mail($to, $subject, $body, implode("\r\n", $headers));
        if (!$sent) {
            $error_msg = 'mail() function failed';
        }
    }
    
    // Update result
    if ($sent) {
        $result['status'] = 'sent';
    } else {
        $result['error_message'] = $error_msg ?? 'Unknown error';
    }
    
    // Log the attempt
    log_email($to, $subject, $body, $result['status'], $result['error_message']);
    
    return $result;
}

/**
 * Log email attempt to database
 * 
 * @param string $to Recipient
 * @param string $subject Subject
 * @param string $body Body
 * @param string $status 'sent' or 'failed'
 * @param string|null $error_message Error message if failed
 * @return bool Success of logging
 */
function log_email(string $to, string $subject, string $body, string $status, ?string $error_message = null): bool {
    try {
        // Sanitize inputs for logging (limit length)
        $to_log = substr($to, 0, 255);
        $subject_log = substr($subject, 0, 500);
        $body_log = substr($body, 0, 65535);
        $error_log = $error_message ? substr($error_message, 0, 1000) : null;
        
        // Try to use existing PDO from config.php, otherwise create new connection
        global $pdo;
        if (!isset($pdo) || $pdo === null) {
            require_once __DIR__ . '/../config/config.php';
        }
        
        // Check if emails_log table exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS emails_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                to_email VARCHAR(255) NOT NULL,
                subject VARCHAR(500),
                body TEXT,
                status VARCHAR(20) NOT NULL,
                error_message VARCHAR(1000),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_created (created_at)
            )
        ");
        
        $stmt = $pdo->prepare("
            INSERT INTO emails_log (to_email, subject, body, status, error_message) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$to_log, $subject_log, $body_log, $status, $error_log]);
        
        return true;
    } catch (Exception $e) {
        // Don't break the flow if logging fails
        error_log('Email log failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get email log history
 * 
 * @param int $limit Number of records to return
 * @param string|null $status Filter by status ('sent', 'failed', or null for all)
 * @return array Email log records
 */
function get_email_logs(int $limit = 50, ?string $status = null): array {
    try {
        require_once __DIR__ . '/../config/config.php';
        global $pdo;
        
        $sql = "SELECT * FROM emails_log";
        if ($status) {
            $sql .= " WHERE status = ?";
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $limit]);
        } else {
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$limit]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Get email logs failed: ' . $e->getMessage());
        return [];
    }
}
