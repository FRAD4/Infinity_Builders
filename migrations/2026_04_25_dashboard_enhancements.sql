-- Dashboard Enhancements - User Preferences
-- Phase 2: Add new preference keys for dashboard customization

-- New preference_keys:
-- - dashboard_show_charts: '1' | '0' (default: '1')
-- - dashboard_show_activity: '1' | '0' (default: '1')
-- - dashboard_show_stats: '1' | '0' (default: '1')
-- - dashboard_date_filter: 'this_week' | 'this_month' | 'this_quarter' | 'this_year' | 'all_time' (default: 'all_time')

INSERT INTO user_preferences (user_id, preference_key, preference_value)
SELECT id, 'dashboard_show_charts', '1'
FROM users
WHERE id NOT IN (SELECT user_id FROM user_preferences WHERE preference_key = 'dashboard_show_charts');

INSERT INTO user_preferences (user_id, preference_key, preference_value)
SELECT id, 'dashboard_show_activity', '1'
FROM users
WHERE id NOT IN (SELECT user_id FROM user_preferences WHERE preference_key = 'dashboard_show_activity');

INSERT INTO user_preferences (user_id, preference_key, preference_value)
SELECT id, 'dashboard_show_stats', '1'
FROM users
WHERE id NOT IN (SELECT user_id FROM user_preferences WHERE preference_key = 'dashboard_show_stats');

INSERT INTO user_preferences (user_id, preference_key, preference_value)
SELECT id, 'dashboard_date_filter', 'all_time'
FROM users
WHERE id NOT IN (SELECT user_id FROM user_preferences WHERE preference_key = 'dashboard_date_filter');