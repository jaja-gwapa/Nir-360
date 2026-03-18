-- Add 'awaiting_closure' status: when responder returns to base geofence, reports move here until admin/responder confirms Resolved.
-- Run in phpMyAdmin (select database nir360, then SQL tab).

ALTER TABLE incident_reports
  MODIFY COLUMN status ENUM('draft', 'pending', 'dispatched', 'awaiting_closure', 'resolved') NOT NULL DEFAULT 'pending';
