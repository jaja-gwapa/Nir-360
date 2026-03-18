-- Add only location columns to users (fixes "Unknown column 'province'" on registration).
-- Run in phpMyAdmin on database nir360. If any line says "Duplicate column name", skip that line.
USE nir360;

ALTER TABLE users ADD COLUMN province VARCHAR(100) NOT NULL DEFAULT 'Negros Occidental';
ALTER TABLE users ADD COLUMN city VARCHAR(100) NOT NULL DEFAULT 'Bago City';
ALTER TABLE users ADD COLUMN barangay VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN street_address VARCHAR(512) NULL;
