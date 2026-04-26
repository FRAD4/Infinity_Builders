-- Inspections table for Infinity Builders
-- Tracks inspections linked to permits

CREATE TABLE IF NOT EXISTS inspections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    permit_id INT,
    inspection_type VARCHAR(100) COMMENT 'Framing, Electrical, Plumbing, Final, etc.',
    city VARCHAR(100),
    requested_by VARCHAR(255) COMMENT 'Person requesting: Lucas Martelli, Azul Ortelli, Nicolas Ortiz',
    date_requested DATE,
    scheduled_date DATE,
    status ENUM('not_scheduled', 'requested', 'scheduled', 'completed', 'passed', 'failed', 'reinspection_needed') DEFAULT 'not_scheduled',
    inspector_notes TEXT,
    reinspection_needed ENUM('yes', 'no') DEFAULT 'no',
    reinspection_date DATE,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (permit_id) REFERENCES permits(id) ON DELETE SET NULL,
    INDEX idx_project_id (project_id),
    INDEX idx_permit_id (permit_id),
    INDEX idx_status (status)
);
