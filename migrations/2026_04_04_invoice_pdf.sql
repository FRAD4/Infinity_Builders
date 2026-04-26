-- Add invoice_pdf column to projects table
ALTER TABLE projects ADD COLUMN invoice_pdf VARCHAR(255) AFTER invoice_number;
