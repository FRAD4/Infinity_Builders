-- Add new fields to projects table
-- Based on new requirements for Infinity Builders

-- Add new columns to projects table
ALTER TABLE projects ADD COLUMN city VARCHAR(100) AFTER description;
ALTER TABLE projects ADD COLUMN project_type VARCHAR(100) AFTER city COMMENT 'kitchen, bathroom, addition, roofing, flooring, etc.';
ALTER TABLE projects ADD COLUMN address VARCHAR(500) AFTER project_type;
ALTER TABLE projects ADD COLUMN phone VARCHAR(50) AFTER address;
ALTER TABLE projects ADD COLUMN email VARCHAR(255) AFTER phone;
ALTER TABLE projects ADD COLUMN scope_of_work TEXT AFTER email;
ALTER TABLE projects ADD COLUMN project_manager VARCHAR(255) AFTER scope_of_work;
ALTER TABLE projects ADD COLUMN invoice_number VARCHAR(100) AFTER total_budget;
ALTER TABLE projects ADD COLUMN invoice_path VARCHAR(500) AFTER invoice_number;

-- Update status enum to include new statuses
ALTER TABLE projects MODIFY COLUMN status ENUM(
    'signed',
    'starting_soon',
    'active',
    'waiting_permit',
    'waiting_materials',
    'on_hold',
    'completed',
    'cancelled'
) DEFAULT 'signed';

-- Add indexes for new fields
ALTER TABLE projects ADD INDEX idx_city (city);
ALTER TABLE projects ADD INDEX idx_project_type (project_type);
ALTER TABLE projects ADD INDEX idx_project_manager (project_manager);
