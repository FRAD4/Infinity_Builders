-- Notifications Table for Internal Notes
-- Run this migration to add notifications functionality

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(20) DEFAULT 'info' COMMENT 'info, warning, danger, success',
    title VARCHAR(255) NOT NULL,
    message TEXT,
    url VARCHAR(255) DEFAULT '',
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME DEFAULT NULL,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cleanup old read notifications (run periodically)
-- DELETE FROM notifications WHERE is_read = 1 AND read_at < DATE_SUB(NOW(), INTERVAL 30 DAY);