-- Add verification_status to users (Pending, Verified, Rejected)
-- Run this if you already have the nir360 database.

USE nir360;

ALTER TABLE users
  ADD COLUMN verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending'
  AFTER is_verified;

-- Optional: sync existing is_verified to status
UPDATE users SET verification_status = IF(is_verified = 1, 'verified', 'pending');
