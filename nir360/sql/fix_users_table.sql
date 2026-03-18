-- Fix users table: add all columns required by NIR360 registration.
-- Run this in phpMyAdmin: select database nir360, open SQL tab, paste and run.
-- If you get "Duplicate column name", that column already exists — skip that line and run the rest.
USE nir360;

-- 1. Username (required for registration)
ALTER TABLE users ADD COLUMN username VARCHAR(50) NULL AFTER id;
UPDATE users SET username = CONCAT('user_', id) WHERE username IS NULL;
ALTER TABLE users MODIFY COLUMN username VARCHAR(50) NOT NULL;
ALTER TABLE users ADD UNIQUE KEY unique_username (username);

-- 2. Verification status (pending / verified / rejected)
ALTER TABLE users ADD COLUMN verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending' AFTER is_verified;

-- 3. Government ID path
ALTER TABLE users ADD COLUMN government_id_path VARCHAR(512) NULL AFTER verification_status;

-- 4. Location (Bago City)
ALTER TABLE users ADD COLUMN province VARCHAR(100) NOT NULL DEFAULT 'Negros Occidental' AFTER government_id_path;
ALTER TABLE users ADD COLUMN city VARCHAR(100) NOT NULL DEFAULT 'Bago City' AFTER province;
ALTER TABLE users ADD COLUMN barangay VARCHAR(255) NULL AFTER city;
ALTER TABLE users ADD COLUMN street_address VARCHAR(512) NULL AFTER barangay;
