-- ========================================
-- Migration: Full DB sync for production
-- Run this to add all missing columns and tables
-- Date: 2026-04-08
-- ========================================

-- ========================================
-- USERS TABLE - Add missing columns
-- ========================================
-- Note: role may already exist from 2026_03_22_security.sql

-- Add full_name column to users (safe - ignores if exists)
ALTER TABLE users ADD COLUMN full_name VARCHAR(255) AFTER email;

-- Update role to include more roles if needed
-- (This may fail if the enum already has these values, that's OK)
-- ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'pm', 'accounting', 'estimator', 'viewer', 'user') DEFAULT 'user';


-- ========================================
-- PROJECTS TABLE - Add new fields
-- ========================================

-- Add city column
ALTER TABLE projects ADD COLUMN city VARCHAR(100) AFTER description;

-- Add project_type column
ALTER TABLE projects ADD COLUMN project_type VARCHAR(100) AFTER city;

-- Add address column
ALTER TABLE projects ADD COLUMN address VARCHAR(500) AFTER project_type;

-- Add phone column
ALTER TABLE projects ADD COLUMN phone VARCHAR(50) AFTER address;

-- Add email column
ALTER TABLE projects ADD COLUMN email VARCHAR(255) AFTER phone;

-- Add scope_of_work column
ALTER TABLE projects ADD COLUMN scope_of_work TEXT AFTER email;

-- Add project_manager column
ALTER TABLE projects ADD COLUMN project_manager VARCHAR(255) AFTER scope_of_work;

-- Add invoice_number column
ALTER TABLE projects ADD COLUMN invoice_number VARCHAR(100) AFTER total_budget;

-- Add invoice_path column
ALTER TABLE projects ADD COLUMN invoice_path VARCHAR(500) AFTER invoice_number;

-- Add invoice_pdf column (for PDF uploads)
ALTER TABLE projects ADD COLUMN invoice_pdf VARCHAR(500) AFTER invoice_path;

-- Add indexes for new fields
ALTER TABLE projects ADD INDEX idx_city (city);
ALTER TABLE projects ADD INDEX idx_project_type (project_type);
ALTER TABLE projects ADD INDEX idx_project_manager (project_manager);


-- ========================================
-- PERMITS TABLE - Add missing columns
-- ========================================

-- Add internal_comments column
ALTER TABLE permits ADD COLUMN internal_comments TEXT AFTER notes;

-- Add permit_pdf column
ALTER TABLE permits ADD COLUMN permit_pdf VARCHAR(500) AFTER internal_comments;


-- ========================================
-- INSPECTIONS TABLE - Add inspector_name
-- ========================================

-- Add inspector_name column
ALTER TABLE inspections ADD COLUMN inspector_name VARCHAR(255) AFTER scheduled_date;


-- ========================================
-- NEW TABLES
-- ========================================

-- Create permit_status_history table
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

-- Create project_team table
CREATE TABLE IF NOT EXISTS project_team (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    role VARCHAR(100) DEFAULT 'member',
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_project_user (project_id, user_id),
    INDEX idx_project_id (project_id),
    INDEX idx_user_id (user_id)
);

-- Create project_vendors table
CREATE TABLE IF NOT EXISTS project_vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    vendor_id INT NOT NULL,
    scope_of_work TEXT,
    contract_amount DECIMAL(12,2) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'active',
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    UNIQUE KEY unique_project_vendor (project_id, vendor_id),
    INDEX idx_project_id (project_id),
    INDEX idx_vendor_id (vendor_id)
);

-- Create inspection_status_history table
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
