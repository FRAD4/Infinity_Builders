-- Add new fields to projects table
-- Based on new requirements for Infinity Builders

ALTER TABLE projects ADD COLUMN address VARCHAR(500) AFTER city;
ALTER TABLE projects ADD COLUMN phone VARCHAR(50) AFTER address;
ALTER TABLE projects ADD COLUMN email VARCHAR(255) AFTER phone;
ALTER TABLE projects ADD COLUMN scope_of_work TEXT AFTER email;
ALTER TABLE projects ADD COLUMN project_manager VARCHAR(255) AFTER scope_of_work;
ALTER TABLE projects ADD COLUMN project_type VARCHAR(100) AFTER project_manager COMMENT 'kitchen, bathroom, addition, roofing, flooring, etc.';
ALTER TABLE projects ADD COLUMN invoice_number VARCHAR(100) AFTER total_budget;
ALTER TABLE projects ADD COLUMN invoice_path VARCHAR(500) AFTER invoice_number;
