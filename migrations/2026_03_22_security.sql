-- Infinity Builders Security Hardening Migration
-- Run this on your database (MySQL)

-- Add role column
ALTER TABLE users ADD COLUMN role ENUM('admin','user') NOT NULL DEFAULT 'user';

-- Add password algorithm tracking (includes sha256_migration_pending for batch migration)
ALTER TABLE users ADD COLUMN password_algo ENUM('sha256','bcrypt','sha256_migration_pending') NOT NULL DEFAULT 'sha256';

-- Add bcrypt hash field (nullable for migration period)
ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL;

-- Index for faster lookups
ALTER TABLE users ADD INDEX idx_password_algo (password_algo);

-- Migration: Set existing users to sha256 (they'll migrate on first login)
UPDATE users SET password_algo = 'sha256' WHERE password_algo IS NULL OR password_algo = '';
