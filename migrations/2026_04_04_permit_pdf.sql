-- Add permit_pdf column to permits table
ALTER TABLE permits ADD COLUMN permit_pdf VARCHAR(255) AFTER permit_number;
