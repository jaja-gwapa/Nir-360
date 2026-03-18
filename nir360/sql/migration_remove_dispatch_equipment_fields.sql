-- Remove admin dispatch/equipment recommendation fields from incident_reports
-- Columns removed:
--   dispatch_equipment, dispatch_responders, dispatch_ambulances
--
-- Run in phpMyAdmin (SQL tab) after selecting the `nir360` database.
USE nir360;

ALTER TABLE incident_reports
  DROP COLUMN dispatch_equipment,
  DROP COLUMN dispatch_responders,
  DROP COLUMN dispatch_ambulances;

