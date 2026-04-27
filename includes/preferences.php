<?php
/**
 * User Preferences Helper
 * Dashboard personalization functions
 * Uses PDO to match the rest of the project
 */

global $pdo;

/**
 * Get a single user preference
 * @param int $userId User ID
 * @param string $key Preference key
 * @return string|null Value or null if not set
 */
function get_user_preference(int $userId, string $key): ?string {
    global $pdo;
    $stmt = $pdo->prepare("SELECT preference_value FROM user_preferences WHERE user_id = ? AND preference_key = ?");
    $stmt->execute([$userId, $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['preference_value'] ?? null;
}

/**
 * Get all preferences for a user
 * @param int $userId User ID
 * @return array Key => Value pairs
 */
function get_all_user_preferences(int $userId): array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT preference_key, preference_value FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$userId]);
    $prefs = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $prefs[$row['preference_key']] = $row['preference_value'];
    }
    return $prefs;
}

/**
 * Set a user preference
 * @param int $userId User ID
 * @param string $key Preference key
 * @param string $value Preference value
 * @return bool Success
 */
function set_user_preference(int $userId, string $key, string $value): bool {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO user_preferences (user_id, preference_key, preference_value)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value)
    ");
    return $stmt->execute([$userId, $key, $value]);
}

/**
 * Set multiple preferences at once
 * @param int $userId User ID
 * @param array $prefs Array of key => value
 * @return bool Success
 */
function set_user_preferences(int $userId, array $prefs): bool {
    global $pdo;
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
        INSERT INTO user_preferences (user_id, preference_key, preference_value)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value)
    ");
    
    foreach ($prefs as $key => $value) {
        $stmt->execute([$userId, $key, $value]);
    }
    $pdo->commit();
    return true;
}

/**
 * Delete a user preference
 * @param int $userId User ID
 * @param string $key Preference key
 * @return bool Success
 */
function delete_user_preference(int $userId, string $key): bool {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM user_preferences WHERE user_id = ? AND preference_key = ?");
    return $stmt->execute([$userId, $key]);
}

/**
 * Get dashboard preferences with defaults
 * @param int $userId User ID
 * @return array Complete preferences with defaults
 */
function get_dashboard_preferences(int $userId): array {
    $defaults = [
        'dashboard_layout' => 'default',
        'dashboard_theme' => 'system',
        'show_charts' => '1',
        'show_recent_projects' => '1',
        'show_recent_vendors' => '1',
        'show_recent_payments' => '1',
        'show_recent_activity' => '1',
        'dashboard_show_projects' => '1',
        'dashboard_show_permits' => '1',
        'dashboard_show_financial' => '1',
        'dashboard_show_activity' => '1',
        'dashboard_time_filter' => '30d',
        'dashboard_date_filter' => 'all',
        'default_page' => 'dashboard',
        'notifications_enabled' => '1',
        'email_alerts' => '1'
    ];
    
    if ($userId <= 0) {
        return $defaults;
    }
    
    $userPrefs = get_all_user_preferences($userId);
    return array_merge($defaults, $userPrefs);
}
