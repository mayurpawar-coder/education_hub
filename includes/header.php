<?php
/**
 * ============================================================
 * Education Hub - Header Component (includes/header.php)
 * ============================================================
 * 
 * PURPOSE:
 *   Displays the top header bar on every page with:
 *   - Dynamic page title (set by $pageTitle variable in each page)
 *   - User's name and role badge
 *   - User avatar with initials (e.g., "RK" for Raj Kumar)
 * 
 * HOW IT WORKS:
 *   1. Gets current user data from database via getCurrentUser()
 *   2. Extracts first letter of each word in name to create initials
 *   3. Displays the header with flexbox layout
 * 
 * INCLUDED IN: Every page via <?php include 'includes/header.php'; ?>
 * CSS: Styled in style.css (.header, .user-info, .user-avatar)
 * ============================================================
 */

/* Get user data from database */
$user = getCurrentUser();

/* Generate avatar initials from user's name */
/* Example: "Raj Kumar" → "RK" */
$initials = '';
if ($user) {
    $names = explode(' ', $user['name']);  // Split name by spaces
    foreach ($names as $name) {
        $initials .= strtoupper($name[0]); // Take first letter of each word
    }
    $initials = substr($initials, 0, 2);   // Limit to 2 characters
}
?>

<!-- Page header bar: title on left, user info on right -->
<header class="header">
    <!-- Dynamic page title (set by each page's $pageTitle variable) -->
    <h1><?= $pageTitle ?? 'Dashboard' ?></h1>

    <!-- User info section: name, role badge, avatar circle -->
    <div class="header-right">
        <div class="user-info">
            <div class="user-details">
                <div class="user-name"><?= htmlspecialchars($user['name'] ?? 'User') ?></div>
                <div class="user-role"><?= ucfirst(htmlspecialchars($user['role'] ?? 'student')) ?></div>
            </div>
            <!-- Circular avatar with gradient background showing initials -->
            <div class="user-avatar"><?= $initials ?></div>
        </div>
    </div>
</header>
