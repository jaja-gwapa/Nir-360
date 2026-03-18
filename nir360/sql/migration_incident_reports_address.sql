-- Add address column for reverse-geocoded incident location (Nominatim display_name).
-- Run once: USE nir360; then run the ALTER below.

USE nir360;

ALTER TABLE incident_reports
  ADD COLUMN address TEXT NULL COMMENT 'Readable address from reverse geocoding (Nominatim)' AFTER longitude;
