-- Role-based dashboards: admin, responder, user + incident reports
USE nir360;

-- 1. Change role enum: civilian -> user, authority -> responder, admin -> admin
-- Step 1: expand enum to include old values, then update, then shrink (MySQL requirement)
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'responder', 'user', 'civilian', 'authority') NOT NULL DEFAULT 'user';
UPDATE users SET role = 'user' WHERE role = 'civilian';
UPDATE users SET role = 'responder' WHERE role = 'authority';
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'responder', 'user') NOT NULL DEFAULT 'user';

-- 2. Incident reports (Public Reporter submits; Admin views all; Responder gets dispatched)
CREATE TABLE IF NOT EXISTS incident_reports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL COMMENT 'User who submitted (role=user)',
  assigned_to INT UNSIGNED NULL COMMENT 'Responder user_id when assigned',
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  latitude DECIMAL(10,8) NULL COMMENT 'Incident latitude from map selection',
  longitude DECIMAL(11,8) NULL COMMENT 'Incident longitude from map selection',
  severity ENUM('low', 'medium', 'high') NULL DEFAULT NULL COMMENT 'Incident seriousness level selected by user',
  status ENUM('pending', 'dispatched', 'resolved') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_user (user_id),
  INDEX idx_assigned (assigned_to),
  INDEX idx_status (status),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
