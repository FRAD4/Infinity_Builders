-- Add new fields to permits table
-- Based on new requirements for Infinity Builders

ALTER TABLE permits ADD COLUMN permit_required ENUM('yes', 'no') DEFAULT 'yes' AFTER permit_type;
ALTER TABLE permits ADD COLUMN submitted_by VARCHAR(255) AFTER status COMMENT 'Person responsible: Lucas Martelli, Azul Ortelli, Nicolas Ortiz';
ALTER TABLE permits ADD COLUMN permit_number VARCHAR(100) AFTER submitted_by;
ALTER TABLE permits ADD COLUMN corrections_required ENUM('yes', 'no') DEFAULT 'no' AFTER permit_number;
ALTER TABLE permits ADD COLUMN corrections_due_date DATE AFTER corrections_required;
