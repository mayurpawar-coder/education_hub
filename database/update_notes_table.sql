-- Create note downloads tracking table
CREATE TABLE IF NOT EXISTS note_downloads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    user_id INT NOT NULL,
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_note_id (note_id),
    INDEX idx_user_id (user_id)
);

-- Note: Ensure notes table matches the expected structure.
-- The required columns:
-- id (INT PK AI)
-- title (VARCHAR 255)
-- description (TEXT)
-- file_name (VARCHAR 255)
-- original_name (VARCHAR 255)
-- file_size (INT)
-- file_type (VARCHAR 50)
-- subject_id (INT)
-- year (VARCHAR 10)
-- semester (INT)
-- created_by (INT)
-- download_count (INT default 0)
-- is_deleted (TINYINT default 0)
-- created_at (TIMESTAMP)
-- updated_at (TIMESTAMP)

-- Modify existing table structure if it exists
-- Rename uploaded_by to created_by and file_path to file_name if migrating from old version
/*
ALTER TABLE notes 
CHANGE file_path file_name VARCHAR(255) NOT NULL,
CHANGE uploaded_by created_by INT NOT NULL,
ADD COLUMN description TEXT AFTER title,
ADD COLUMN original_name VARCHAR(255) NOT NULL AFTER file_name,
ADD COLUMN file_size INT NOT NULL AFTER original_name,
ADD COLUMN file_type VARCHAR(50) NOT NULL AFTER file_size,
ADD COLUMN year VARCHAR(10) NOT NULL AFTER subject_id,
ADD COLUMN semester INT NOT NULL AFTER year,
ADD COLUMN download_count INT DEFAULT 0 AFTER created_by,
ADD COLUMN is_deleted TINYINT DEFAULT 0 AFTER download_count,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
*/

-- Alternatively, drop and recreate for fresh start
/*
DROP TABLE IF EXISTS notes;
CREATE TABLE IF NOT EXISTS notes (
id INT AUTO_INCREMENT PRIMARY KEY,
title VARCHAR(255) NOT NULL,
description TEXT,
file_name VARCHAR(255) NOT NULL,
original_name VARCHAR(255) NOT NULL,
file_size INT NOT NULL,
file_type VARCHAR(50) NOT NULL,
subject_id INT NOT NULL,
year VARCHAR(10) NOT NULL,
semester INT NOT NULL,
created_by INT NOT NULL,
download_count INT DEFAULT 0,
is_deleted TINYINT DEFAULT 0,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
INDEX idx_subject_id (subject_id),
INDEX idx_created_by (created_by)
);
*/