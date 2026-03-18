-- Draft reports, user notifications, SMS log
USE nir360;

-- 1. Allow 'draft' status on incident_reports (server-side created_at/updated_at already in use)
ALTER TABLE incident_reports
  MODIFY COLUMN status ENUM('draft', 'pending', 'dispatched', 'resolved') NOT NULL DEFAULT 'pending';

-- 2. User notifications (submission confirmation, etc.)
CREATE TABLE IF NOT EXISTS user_notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  report_id INT UNSIGNED NULL,
  type VARCHAR(64) NOT NULL DEFAULT 'submission_confirmation',
  title VARCHAR(255) NOT NULL,
  body TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (report_id) REFERENCES incident_reports(id) ON DELETE SET NULL,
  INDEX idx_user_read (user_id, read_at),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. SMS log (queued/sent/failed) when sending to responder on assign
CREATE TABLE IF NOT EXISTS sms_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id INT UNSIGNED NOT NULL,
  recipient_user_id INT UNSIGNED NOT NULL,
  recipient_phone VARCHAR(20) NOT NULL,
  message_body TEXT NOT NULL,
  status ENUM('queued', 'sent', 'failed') NOT NULL DEFAULT 'queued',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  error_message VARCHAR(512) NULL,
  FOREIGN KEY (report_id) REFERENCES incident_reports(id) ON DELETE CASCADE,
  FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_report (report_id),
  INDEX idx_recipient (recipient_user_id),
  INDEX idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
