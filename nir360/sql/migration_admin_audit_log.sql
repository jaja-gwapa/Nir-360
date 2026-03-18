-- Admin audit log: assignments, status changes, user management (timestamps + admin identity for accountability).
-- Run in phpMyAdmin (select database nir360, then SQL tab).

USE nir360;

CREATE TABLE IF NOT EXISTS admin_audit_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NOT NULL,
  admin_email VARCHAR(255) NULL COMMENT 'Stored at time of action for record',
  action_type VARCHAR(64) NOT NULL COMMENT 'e.g. report_assign, report_confirm_resolved, user_deactivate, user_activate, user_reset_password, user_create, announcement_create',
  target_type VARCHAR(32) NULL COMMENT 'e.g. report, user, announcement',
  target_id INT UNSIGNED NULL,
  details JSON NULL COMMENT 'Extra context: old/new values, identifiers',
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_admin_created (admin_id, created_at),
  INDEX idx_action_created (action_type, created_at),
  INDEX idx_target (target_type, target_id),
  INDEX idx_created (created_at),
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
