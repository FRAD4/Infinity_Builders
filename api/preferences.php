<?php
/**
 * api/preferences.php - Save/load dashboard preferences
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../partials/init.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $preferenceKey = $input['preference_key'] ?? '';
    $preferenceValue = $input['preference_value'] ?? '';
    
    $userId = $_SESSION['user_id'] ?? 0;
    
    if ($userId > 0 && $preferenceKey) {
        // Direct query instead of using function (avoid global $pdo issues)
        $stmt = $pdo->prepare("
            INSERT INTO user_preferences (user_id, preference_key, preference_value)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value)
        ");
        $success = $stmt->execute([$userId, $preferenceKey, $preferenceValue]);
        
        if ($success) {
            echo json_encode(['success' => true, 'preference_key' => $preferenceKey, 'preference_value' => $preferenceValue]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save preference']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid user or preference key']);
    }
    exit;
}

if ($method === 'GET') {
    $userId = $_SESSION['user_id'] ?? 0;
    
    if ($userId > 0) {
        $prefs = get_all_user_preferences($userId);
        echo json_encode(['success' => true, 'preferences' => $prefs]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid method']);