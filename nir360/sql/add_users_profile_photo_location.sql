-- Add profile_photo and location columns to users (if missing).
-- Run in phpMyAdmin or MySQL client. If a column already exists, you'll get "Duplicate column"; skip that line or run the others.
-- Full fix: run each ALTER below one at a time.

USE nir360;

ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE users ADD COLUMN latitude DECIMAL(10, 8) NULL DEFAULT NULL;
ALTER TABLE users ADD COLUMN longitude DECIMAL(11, 8) NULL DEFAULT NULL;
ALTER TABLE users ADD COLUMN location_address TEXT NULL DEFAULT NULL;
