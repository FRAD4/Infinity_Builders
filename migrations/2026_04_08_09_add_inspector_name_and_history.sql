-- ========================================
-- Migration: Add inspector_name to inspections + inspection_status_history
-- Date: 2026-04-08
-- ========================================

-- Add inspector_name column to inspections (safe - ignores if exists)
ALTER TABLE inspections ADD COLUMN inspector_name VARCHAR(255) AFTER scheduled_date;

-- Create inspection_status_history table (tracks status changes)
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
