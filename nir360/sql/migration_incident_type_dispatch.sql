-- Add incident_type and dispatch fields for suggestions and admin confirm/edit before dispatch
-- Run each ALTER separately; skip any that already exist to avoid duplicate column errors.
USE nir360;

-- incident_type: from user form (fire, flood, earthquake, etc.)
ALTER TABLE incident_reports
  ADD COLUMN incident_type VARCHAR(80) NULL DEFAULT NULL COMMENT 'User-selected incident type' AFTER description;

-- Dispatch: admin-confirmed (or edited) equipment and counts when assigning
ALTER TABLE incident_reports
  ADD COLUMN dispatch_equipment TEXT NULL DEFAULT NULL COMMENT 'Equipment needed (admin confirmed/edited)' AFTER status;
ALTER TABLE incident_reports
  ADD COLUMN dispatch_responders TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Number of responders (admin confirmed/edited)' AFTER dispatch_equipment;
ALTER TABLE incident_reports
  ADD COLUMN dispatch_ambulances TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Number of ambulances (admin confirmed/edited)' AFTER dispatch_responders;
