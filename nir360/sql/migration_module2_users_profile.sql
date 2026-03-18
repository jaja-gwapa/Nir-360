-- Module 2: User Information Management – extend users table
-- Run once after schema.sql. If columns already exist, skip or run the statements you need.

USE nir360;

-- Add profile photo path (stored under uploads/profile/)
ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL DEFAULT NULL AFTER updated_at;

-- Add location fields for map pin
ALTER TABLE users ADD COLUMN latitude DECIMAL(10, 8) NULL DEFAULT NULL AFTER profile_photo;
ALTER TABLE users ADD COLUMN longitude DECIMAL(11, 8) NULL DEFAULT NULL AFTER latitude;
ALTER TABLE users ADD COLUMN location_address TEXT NULL DEFAULT NULL AFTER longitude;
