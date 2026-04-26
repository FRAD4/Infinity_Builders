-- Add internal_comments column to permits table
-- Run this to add internal comments field to existing database

ALTER TABLE permits 
ADD COLUMN internal_comments TEXT COMMENT 'Internal team notes (not visible externally)' 
AFTER notes;
