-- Update incident report tracking statuses to pending / dispatched / resolved.
USE nir360;

UPDATE incident_reports
SET status = 'dispatched'
WHERE status IN ('assigned', 'in_progress');

ALTER TABLE incident_reports
  MODIFY COLUMN status ENUM('pending', 'dispatched', 'resolved') NOT NULL DEFAULT 'pending';
