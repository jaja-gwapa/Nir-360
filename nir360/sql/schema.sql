-- NIR360 MySQL Schema (PHP 8 + MySQL)
-- Run this to create the database and tables.

CREATE DATABASE IF NOT EXISTS nir360 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nir360;

-- Users (from Create Account + OTP verified)
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  mobile VARCHAR(20) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('civilian', 'authority', 'admin') NOT NULL DEFAULT 'civilian',
  is_phone_verified TINYINT(1) NOT NULL DEFAULT 0,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
  government_id_path VARCHAR(512) NULL,
  province VARCHAR(100) NOT NULL DEFAULT 'Negros Occidental',
  city VARCHAR(100) NOT NULL DEFAULT 'Bago City',
  barangay VARCHAR(255) NULL,
  purok VARCHAR(100) NULL,
  street_address VARCHAR(512) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_username (username),
  INDEX idx_email (email),
  INDEX idx_mobile (mobile),
  INDEX idx_role (role)
) ENGINE=InnoDB;

-- OTP (server-side; DEV MODE no SMS)
CREATE TABLE otp_codes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  code CHAR(6) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_expires (user_id, expires_at)
) ENGINE=InnoDB;

-- Profiles (full name, address, city, barangay, purok, street_address, emergency contact)
CREATE TABLE profiles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  full_name VARCHAR(255) NOT NULL,
  birthdate DATE NULL,
  address TEXT NOT NULL,
  province VARCHAR(100) NOT NULL DEFAULT 'Negros Occidental',
  barangay VARCHAR(255) NOT NULL,
  city VARCHAR(100) NOT NULL DEFAULT 'Bago City',
  purok VARCHAR(100) NULL,
  street_address TEXT NULL,
  emergency_contact_name VARCHAR(255) NOT NULL,
  emergency_contact_mobile VARCHAR(20) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Government ID uploads (paths outside web root)
CREATE TABLE id_uploads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  side ENUM('front', 'back') NOT NULL,
  file_path VARCHAR(512) NOT NULL,
  mime_type VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY one_per_side (user_id, side)
) ENGINE=InnoDB;

-- OCR extraction results + comparison
CREATE TABLE ocr_results (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  id_upload_id INT UNSIGNED NOT NULL,
  extracted_name VARCHAR(255) NULL,
  extracted_birthdate DATE NULL,
  extracted_address TEXT NULL,
  name_match TINYINT(1) NULL COMMENT '1=match, 0=mismatch, NULL=not compared',
  birthdate_match TINYINT(1) NULL,
  address_match TINYINT(1) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (id_upload_id) REFERENCES id_uploads(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Admin verification fallback (approve/reject with reason)
CREATE TABLE verification_reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NULL COMMENT 'Set when reviewed',
  status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  reason TEXT NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_status (status)
) ENGINE=InnoDB;

-- CSRF tokens (optional table; can use session instead)
-- We use session for CSRF in PHP

-- Sessions table if using database sessions (optional)
-- CREATE TABLE sessions (id VARCHAR(128) PRIMARY KEY, data BLOB, expires INT);
