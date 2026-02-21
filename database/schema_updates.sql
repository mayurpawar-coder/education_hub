-- Database Schema Updates for Education Hub
-- Run these queries in phpMyAdmin or MySQL Workbench

-- Add mobile number, status, and profile photo fields to users table
ALTER TABLE users
ADD COLUMN mobile VARCHAR(15) DEFAULT NULL AFTER email,
ADD COLUMN status ENUM('pending', 'approved') DEFAULT 'approved' AFTER role,
ADD COLUMN profile_photo_path VARCHAR(255) DEFAULT NULL AFTER status;

-- Update existing users to have approved status
UPDATE users SET status = 'approved' WHERE role IN ('admin', 'student');
UPDATE users SET status = 'pending' WHERE role = 'teacher';

-- Create uploads/profile directory structure (run this in terminal/cmd)
-- mkdir -p uploads/profile
-- chmod 755 uploads/profile

-- Insert demo mobile numbers for existing users
UPDATE users SET mobile = '+919876543210' WHERE email = 'admin@educationhub.com';
UPDATE users SET mobile = '+919876543211' WHERE email = 'teacher@test.com';
UPDATE users SET mobile = '+919876543212' WHERE email = 'raj@test.com';

-- Create table for bulk question uploads (optional tracking)
CREATE TABLE IF NOT EXISTS bulk_upload_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    uploaded_by INT NOT NULL,
    total_questions INT DEFAULT 0,
    successful_uploads INT DEFAULT 0,
    failed_uploads INT DEFAULT 0,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Sample data for testing
-- You can uncomment these to test with sample profile photos
-- UPDATE users SET profile_photo_path = 'uploads/profile/admin_default.jpg' WHERE email = 'admin@educationhub.com';
-- UPDATE users SET profile_photo_path = 'uploads/profile/teacher_default.jpg' WHERE email = 'teacher@test.com';
-- UPDATE users SET profile_photo_path = 'uploads/profile/student_default.jpg' WHERE email = 'raj@test.com';
