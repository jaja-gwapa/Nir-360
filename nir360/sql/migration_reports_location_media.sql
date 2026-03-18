-- Add report location and media support for existing incident_reports table
USE nir360;

ALTER TABLE incident_reports
  ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,8) NULL COMMENT 'Incident latitude from map selection' AFTER description,
  ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) NULL COMMENT 'Incident longitude from map selection' AFTER latitude,
  ADD COLUMN IF NOT EXISTS severity ENUM('low', 'medium', 'high') NULL DEFAULT NULL COMMENT 'Incident seriousness level selected by user' AFTER longitude;

CREATE INDEX idx_incident_location ON incident_reports (latitude, longitude);

CREATE TABLE IF NOT EXISTS incident_report_media (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id INT UNSIGNED NOT NULL,
  media_type ENUM('image', 'video') NOT NULL,
  file_path VARCHAR(512) NOT NULL,
  mime_type VARCHAR(64) NOT NULL,
  original_name VARCHAR(255) NULL,
  file_size INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (report_id) REFERENCES incident_reports(id) ON DELETE CASCADE,
  INDEX idx_report_media_report (report_id),
  INDEX idx_report_media_type (media_type),
  INDEX idx_report_media_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
