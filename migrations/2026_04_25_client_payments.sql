-- ========================================
-- Migration: Create client_payments table
-- Date: 2026-04-25
-- Purpose: Track payments from client to Infinity Builders
-- ========================================

CREATE TABLE IF NOT EXISTS client_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_date DATE NOT NULL,
    description VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_id (project_id)
);