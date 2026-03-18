-- Fix: Add missing columns to incident_reports (severity, address, incident_type if absent).
-- Run in phpMyAdmin: select database nir360, open SQL tab, run the whole script.
-- If you get "Duplicate column 'X'", that column already exists – comment out that ALTER and run again.
USE nir360;

-- 1. Severity (required for listByReporter and report tracking)
ALTER TABLE incident_reports
  ADD COLUMN severity ENUM('low', 'medium', 'high') NULL DEFAULT NULL COMMENT 'Incident seriousness level selected by user' AFTER longitude;

-- 2. Address (for map/location display) – run only if 1 succeeded or severity already existed
ALTER TABLE incident_reports
  ADD COLUMN address TEXT NULL COMMENT 'Readable address from reverse geocoding (Nominatim)' AFTER longitude;

-- 3. Incident type (for dispatch suggestions and SMS)
ALTER TABLE incident_reports
  ADD COLUMN incident_type VARCHAR(80) NULL DEFAULT NULL COMMENT 'User-selected incident type' AFTER description;
