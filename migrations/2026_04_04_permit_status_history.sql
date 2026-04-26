-- Permit status history table
-- Tracks all status changes for permits

CREATE TABLE IF NOT EXISTS permit_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permit_id INT NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    changed_by INT NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    note TEXT,
    FOREIGN KEY (permit_id) REFERENCES permits(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_permit_id (permit_id),
    INDEX idx_changed_at (changed_at)
);
