-- Add UNIQUE constraint to users.mobile (contact number).
-- Run after normalizing existing mobile values to digits-only if needed.

USE nir360;

-- Normalize existing mobile values to digits-only (strip +, spaces, dashes)
UPDATE users SET mobile = REPLACE(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), '-', '') WHERE mobile LIKE '%+%' OR mobile LIKE '% %' OR mobile LIKE '%-%';

-- Add UNIQUE
ALTER TABLE users ADD UNIQUE KEY unique_mobile (mobile);
