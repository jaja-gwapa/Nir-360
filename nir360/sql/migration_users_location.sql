-- Migration: Add city, barangay, purok, street_address to users table
-- Run on existing nir360 database.

USE nir360;

ALTER TABLE users
  ADD COLUMN city VARCHAR(100) NOT NULL DEFAULT 'Bago City' AFTER selfie_path,
  ADD COLUMN barangay VARCHAR(255) NULL AFTER city,
  ADD COLUMN purok VARCHAR(100) NULL AFTER barangay,
  ADD COLUMN street_address VARCHAR(512) NULL AFTER purok;
