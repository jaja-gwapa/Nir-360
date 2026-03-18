-- Rename incident_reports.reporter_id -> incident_reports.user_id
-- Run in phpMyAdmin (SQL tab) after selecting the `nir360` database.
--
-- IMPORTANT:
-- You must drop the FOREIGN KEY that references reporter_id before renaming the column.
-- The FK name can vary (often incident_reports_ibfk_1). If the DROP fails, run:
--   SHOW CREATE TABLE incident_reports;
-- then use the actual FK name in the DROP statement.

USE nir360;

-- 0) Optional: inspect current table definition
-- SHOW CREATE TABLE incident_reports;

-- 1) Drop foreign key on reporter_id (choose the one that exists; if first fails, try the next)
-- If both fail, use SHOW CREATE TABLE to find the correct FK name.
ALTER TABLE incident_reports DROP FOREIGN KEY incident_reports_ibfk_1;
-- ALTER TABLE incident_reports DROP FOREIGN KEY fk_incident_reports_reporter_id;

-- 2) Drop index on reporter_id (after dropping FK; MySQL uses it to enforce the FK)
ALTER TABLE incident_reports DROP INDEX idx_reporter;

-- 3) Rename the column
ALTER TABLE incident_reports CHANGE COLUMN reporter_id user_id INT UNSIGNED NOT NULL;

-- 4) Re-add FK + index with new name
ALTER TABLE incident_reports
  ADD CONSTRAINT fk_incident_reports_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  ADD INDEX idx_user (user_id);

