-- Remove selfie_path from users (face recognition was removed from the app).
-- Run once in phpMyAdmin or MySQL: USE nir360; then run the ALTER below.

USE nir360;

ALTER TABLE users DROP COLUMN selfie_path;
