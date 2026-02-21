<?php
// Database Schema Update Script
// Run this once to update the database for new features

require_once '../config/database.php';

// Add new columns to users table
$query1 = "ALTER TABLE users
ADD COLUMN mobile VARCHAR(15) DEFAULT NULL AFTER email,
ADD COLUMN status ENUM('pending', 'approved') DEFAULT 'approved' AFTER role,
ADD COLUMN profile_photo_path VARCHAR(255) DEFAULT NULL AFTER status";

if ($conn->query($query1) === TRUE) {
    echo "✓ Database schema updated successfully<br>";
} else {
    echo "Error updating schema: " . $conn->error . "<br>";
}

// Update existing users status
$conn->query("UPDATE users SET status = 'approved' WHERE role IN ('admin', 'student')");
$conn->query("UPDATE users SET status = 'pending' WHERE role = 'teacher'");

// Add demo mobile numbers
$conn->query("UPDATE users SET mobile = '+919876543210' WHERE email = 'admin@educationhub.com'");
$conn->query("UPDATE users SET mobile = '+919876543211' WHERE email = 'teacher@test.com'");
$conn->query("UPDATE users SET mobile = '+919876543212' WHERE email = 'raj@test.com'");

// Create uploads/profile directory
$profileDir = '../uploads/profile';
if (!file_exists($profileDir)) {
    mkdir($profileDir, 0755, true);
    echo "✓ Profile upload directory created<br>";
}

echo "<br>Database update completed successfully! 🎉";
?>
