-- Allow admin to disable live tracking for a report (reporter can no longer see responder location).
-- Default 1 = tracking enabled when responder is dispatched.
-- Run once; if column already exists, skip or run: ALTER TABLE incident_reports DROP COLUMN tracking_enabled; then re-run.
ALTER TABLE incident_reports
  ADD COLUMN tracking_enabled TINYINT(1) NOT NULL DEFAULT 1
  COMMENT '1 = reporter can track responder; 0 = admin disabled tracking';
