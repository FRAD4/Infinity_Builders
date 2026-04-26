-- Permits table for Infinity Builders
-- Tracks permit status per project

CREATE TABLE IF NOT EXISTS permits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    city VARCHAR(100) NOT NULL COMMENT 'City: Scottsdale, Phoenix, Chandler, Tempe, etc.',
    permit_required ENUM('yes', 'no') DEFAULT 'yes',
    status ENUM('not_started', 'submitted', 'in_review', 'correction_needed', 'resubmitted', 'approved', 'rejected') DEFAULT 'not_started',
    submitted_by VARCHAR(255) COMMENT 'Person responsible: Lucas Martelli, Azul Ortelli, Nicolas Ortiz',
    submission_date DATE,
    permit_number VARCHAR(100),
    corrections_required ENUM('yes', 'no') DEFAULT 'no',
    corrections_due_date DATE,
    approval_date DATE,
    notes TEXT,
    internal_comments TEXT COMMENT 'Internal team notes (not visible externally)',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_id (project_id),
    INDEX idx_status (status),
    INDEX idx_city (city)
);
