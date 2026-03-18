-- Add province column (Negros Occidental) to users and profiles.

USE nir360;

ALTER TABLE users ADD COLUMN province VARCHAR(100) NOT NULL DEFAULT 'Negros Occidental' AFTER selfie_path;
ALTER TABLE profiles ADD COLUMN province VARCHAR(100) NOT NULL DEFAULT 'Negros Occidental' AFTER address;
