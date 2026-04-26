<?php
/**
 * Save Preference AJAX Handler
 * Receives POST request to save a single dashboard preference
 */

require_once __DIR__ . '/../partials/init.php';
require_once __DIR__ . '/preferences.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;

if ($userId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$key = $_POST['key'] ?? '';
$value = $_POST['value'] ?? '';

if (empty($key)) {
    echo json_encode(['success' => false, 'error' => 'No key provided']);
    exit;
}

// Validate key against allowed preferences
$allowedKeys = [
    'dashboard_show_projects',
    'dashboard_show_permits', 
    'dashboard_show_financial',
    'show_charts',
    'show_recent_activity',
    'show_recent_projects',
    'show_recent_vendors',
    'show_recent_payments',
    'dashboard_theme',
    'dashboard_layout'
];

if (!in_array($key, $allowedKeys)) {
    echo json_encode(['success' => false, 'error' => 'Invalid key']);
    exit;
}

if (set_user_preference($userId, $key, $value)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}