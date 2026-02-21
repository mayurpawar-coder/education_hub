<?php
/**
 * Database Migration Script
 * Run this to add missing columns to the users table
 */

// Database connection
require_once 'config/functions.php';

// SQL to add missing columns
$sql = "
ALTER TABLE users
ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved' AFTER role,
ADD COLUMN mobile VARCHAR(20) AFTER status,
ADD COLUMN profile_image VARCHAR(255) AFTER mobile;
";

try {
    // Execute the migration
    if ($conn->query($sql) === TRUE) {
        echo "<h2 style='color: green;'>✅ Migration Successful!</h2>";
        echo "<p>The following columns have been added to the users table:</p>";
        echo "<ul>";
        echo "<li><strong>status</strong> - User approval status (pending/approved/rejected)</li>";
        echo "<li><strong>mobile</strong> - Mobile phone number (optional)</li>";
        echo "<li><strong>profile_image</strong> - Path to profile image file</li>";
        echo "</ul>";
        echo "<p><strong>You can now use the profile editing and registration features!</strong></p>";
        echo "<p><a href='dashboard.php'>Go to Dashboard</a></p>";
    } else {
        echo "<h2 style='color: red;'>❌ Migration Failed</h2>";
        echo "<p>Error: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Migration Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
