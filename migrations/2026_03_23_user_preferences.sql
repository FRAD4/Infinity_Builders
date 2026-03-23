-- User Preferences Table
-- For dashboard personalization

CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    preference_key VARCHAR(100) NOT NULL,
    preference_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_preference (user_id, preference_key),
    INDEX idx_user_id (user_id)
);

-- Default preferences for new users
-- preference_key options:
-- - dashboard_layout: 'default' | 'compact' | 'expanded'
-- - dashboard_theme: 'system' | 'light' | 'dark'
-- - show_charts: 1 | 0
-- - show_recent_projects: 1 | 0
-- - show_recent_vendors: 1 | 0
-- - show_recent_payments: 1 | 0
-- - default_page: 'dashboard' | 'projects' | 'vendors' | 'reports'
-- - notifications_enabled: 1 | 0
-- - email_alerts: 1 | 0

-- Insert default preferences for existing users
INSERT INTO user_preferences (user_id, preference_key, preference_value)
SELECT id, 'dashboard_layout', 'default'
FROM users
WHERE id NOT IN (SELECT user_id FROM user_preferences WHERE preference_key = 'dashboard_layout');

INSERT INTO user_preferences (user_id, preference_key, preference_value)
SELECT id, 'dashboard_theme', 'system'
FROM users
WHERE id NOT IN (SELECT user_id FROM user_preferences WHERE preference_key = 'dashboard_theme');

INSERT INTO user_preferences (user_id, preference_key, preference_value)
SELECT id, 'show_charts', '1'
FROM users
WHERE id NOT IN (SELECT user_id FROM user_preferences WHERE preference_key = 'show_charts');

INSERT INTO user_preferences (user_id, preference_key, preference_value)
SELECT id, 'show_recent_projects', '1'
FROM users
WHERE id NOT IN (SELECT user_id FROM user_preferences WHERE preference_key = 'show_recent_projects');

INSERT INTO user_preferences (user_id, preference_key, preference_value)
SELECT id, 'show_recent_vendors', '1'
FROM users
WHERE id NOT IN (SELECT user_id FROM user_preferences WHERE preference_key = 'show_recent_vendors');

INSERT INTO user_preferences (user_id, preference_key, preference_value)
SELECT id, 'show_recent_payments', '1'
FROM users
WHERE id NOT IN (SELECT user_id FROM user_preferences WHERE preference_key = 'show_recent_payments');
