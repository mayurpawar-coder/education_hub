-- Migration: add user fields for mobile, status, and profile_image
-- Run this once in the education_hub database (phpMyAdmin or mysql CLI)

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS `status` ENUM('pending','approved','rejected') DEFAULT 'approved' AFTER `role`,
  ADD COLUMN IF NOT EXISTS `mobile` VARCHAR(20) DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `profile_image` VARCHAR(255) DEFAULT NULL AFTER `mobile`;

-- Notes:
-- 1) Teacher registrations will use 'pending' status and require admin approval.
-- 2) Profile images are stored under /uploads/profile/ and the DB column stores the relative file path.
