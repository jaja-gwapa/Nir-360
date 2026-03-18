-- Add username column to users (unique, required for new registrations).
-- Run this in phpMyAdmin or MySQL if you get: Unknown column 'username' in 'where clause'

USE nir360;

-- Add column (nullable first so existing rows don't break)
ALTER TABLE users ADD COLUMN username VARCHAR(50) NULL AFTER id;

-- Set a default for any existing users so we can enforce NOT NULL
UPDATE users SET username = CONCAT('user_', id) WHERE username IS NULL;

-- Enforce NOT NULL and UNIQUE
ALTER TABLE users MODIFY COLUMN username VARCHAR(50) NOT NULL;
ALTER TABLE users ADD UNIQUE KEY unique_username (username);
