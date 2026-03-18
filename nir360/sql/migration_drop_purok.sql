-- Remove purok column from users and profiles
USE nir360;

ALTER TABLE users DROP COLUMN purok;
ALTER TABLE profiles DROP COLUMN purok;
