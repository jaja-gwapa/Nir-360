-- Add location columns to PROFILES table (fixes "Unknown column 'province'" when profile is inserted after user).
-- Run in phpMyAdmin on database nir360. If you get "Duplicate column name", that column exists — skip that line.
USE nir360;

ALTER TABLE profiles ADD COLUMN province VARCHAR(100) NOT NULL DEFAULT 'Negros Occidental';
ALTER TABLE profiles ADD COLUMN city VARCHAR(100) NOT NULL DEFAULT 'Bago City';
ALTER TABLE profiles ADD COLUMN barangay VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE profiles ADD COLUMN street_address TEXT NULL;
