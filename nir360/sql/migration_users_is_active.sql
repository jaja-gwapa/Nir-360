-- Add is_active to users (admin can deactivate/activate accounts)
USE nir360;

ALTER TABLE users
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1
  AFTER role;
