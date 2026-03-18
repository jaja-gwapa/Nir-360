-- Migration: Bago City location fields + government_id_path
-- Run this on existing nir360 database (e.g. in phpMyAdmin or mysql CLI).

USE nir360;

-- Profiles: add city (fixed Bago City), purok, street_address
ALTER TABLE profiles
  ADD COLUMN city VARCHAR(100) NOT NULL DEFAULT 'Bago City' AFTER barangay,
  ADD COLUMN purok VARCHAR(100) NULL AFTER city,
  ADD COLUMN street_address TEXT NULL AFTER purok;

-- Users: store path to front government ID for quick reference
ALTER TABLE users
  ADD COLUMN government_id_path VARCHAR(512) NULL AFTER verification_status;
