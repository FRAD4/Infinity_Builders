<?php
/**
 * Bootstrap file for PHPUnit tests
 * Loads autoloader and sets up test environment
 */

define('BASE_PATH', __DIR__);

// Autoload project files
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $prefix = 'InfinityBuilders\\Tests\\';
    $baseDir = __DIR__ . '/tests/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Also autoload includes for security tests
spl_autoload_register(function ($class) {
    $prefix = 'InfinityBuilders\\Includes\\';
    $baseDir = __DIR__ . '/includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Load security functions for tests that need them
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/sanitize.php';

// Start session for tests that need it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
