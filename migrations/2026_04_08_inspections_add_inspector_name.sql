-- Add inspector_name column to inspections
-- This allows tracking which inspector performed the inspection

ALTER TABLE inspections 
ADD COLUMN inspector_name VARCHAR(255) AFTER scheduled_date;

-- Create inspection status history table
-- Tracks all status changes for inspections

CREATE TABLE IF NOT EXISTS inspection_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inspection_id INT NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    changed_by INT NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    note TEXT,
    FOREIGN KEY (inspection_id) REFERENCES inspections(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_inspection_id (inspection_id),
    INDEX idx_changed_at (changed_at)
);
