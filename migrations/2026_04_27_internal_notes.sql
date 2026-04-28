-- Internal Notes System
-- Run this migration to add internal notes functionality

CREATE TABLE IF NOT EXISTS internal_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    from_user_id INT NOT NULL,
    to_user_ids JSON NOT NULL COMMENT 'Array of user IDs who should see this note',
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project (project_id),
    INDEX idx_from_user (from_user_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;