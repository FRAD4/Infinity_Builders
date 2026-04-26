<?php
/**
 * init-api.php - Lightweight initialization for API endpoints
 * Includes session start for auth-dependent APIs
 */

require_once __DIR__ . '/../config/config.php';

// Start session if not started (needed for auth)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Use existing PDO from config
global $pdo;
if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new Exception('PDO not available');
}