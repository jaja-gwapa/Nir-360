-- Migration: Face verification – store selfie path and support verified status
-- Run on existing nir360 database.

USE nir360;

ALTER TABLE users
  ADD COLUMN selfie_path VARCHAR(512) NULL AFTER government_id_path;
