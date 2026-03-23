<?php
/**
 * Email Configuration - Infinity Builders
 * 
 * IMPORTANT: Add this file to .gitignore
 * 
 * Copy values from your email provider (SMTP)
 */

// SMTP Settings - Gmail
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.gmail.com');
    define('SMTP_PORT', 587); // TLS
    define('SMTP_USER', 'francoyolo16@gmail.com');        // <-- CAMBIAR
    define('SMTP_PASS', 'mgayhkcuowpocgyv');            // <-- CAMBIAR (contraseña de aplicación)
    define('SMTP_FROM', 'francoyolo16@gmail.com');       // <-- CAMBIAR
    define('SMTP_FROM_NAME', 'Infinity Builders');
    define('SMTP_SECURE', 'tls'); // TLS
}

// Database settings for email logging
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'infinity_builders');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}
