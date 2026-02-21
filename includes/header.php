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

// Base path for links
$basePath = getBasePath();
?>

<!-- Page header bar: title on left, user info on right -->
<header class="header">
    <!-- Optional back button (set $backUrl in page) -->
    <?php if (!empty($backUrl)): ?>
        <a href="<?= $basePath . $backUrl ?>" class="back-button" style="margin-right:12px; color:var(--text); text-decoration:none; font-weight:600;">← Back</a>
    <?php endif; ?>

    <!-- Dynamic page title (set by each page's $pageTitle variable) -->
    <h1><?= $pageTitle ?? 'Dashboard' ?></h1>

    <!-- User info section: name, role badge, avatar (image or initials) -->
    <div class="header-right">
        <div class="user-info" style="display:flex; align-items:center; gap:12px;">
            <div class="user-details" style="text-align:right;">
                <div class="user-name"><?= htmlspecialchars($user['name'] ?? 'User') ?></div>
                <div class="user-role"><?= ucfirst(htmlspecialchars($user['role'] ?? 'student')) ?></div>
            </div>

            <div class="avatar-container" style="position:relative;">
                <?php if (!empty($user['profile_image']) && file_exists(__DIR__ . '/../' . $user['profile_image'])): ?>
                    <img id="header-avatar" src="<?= $basePath . $user['profile_image'] ?>" alt="Avatar"
                         style="width:40px; height:40px; border-radius:50%; object-fit:cover; cursor:pointer;">
                <?php else: ?>
                    <div id="header-avatar" style="width:40px; height:40px; border-radius:50%; background:var(--surface); display:flex; align-items:center; justify-content:center; font-weight:600; cursor:pointer;">
                        <?= $initials ?>
                    </div>
                <?php endif; ?>

                <!-- Dropdown menu (hidden by default) -->
                <div id="avatar-dropdown" style="display:none; position:absolute; right:0; top:48px; background:#fff; border:1px solid var(--border); border-radius:8px; box-shadow:0 6px 18px rgba(0,0,0,0.08); min-width:160px; z-index:50;">
                    <a href="<?= $basePath ?>profile.php" style="display:block; padding:10px 12px; color:var(--text); text-decoration:none;">My Profile</a>
                    <a href="<?= $basePath ?>edit_profile.php" style="display:block; padding:10px 12px; color:var(--text); text-decoration:none;">Edit Profile</a>
                    <a href="<?= $basePath ?>auth/logout.php" style="display:block; padding:10px 12px; color:var(--text); text-decoration:none;">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function(){
            var avatar = document.getElementById('header-avatar');
            var dropdown = document.getElementById('avatar-dropdown');
            if (!avatar) return;
            avatar.addEventListener('click', function(e){
                e.stopPropagation();
                dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
            });
            document.addEventListener('click', function(){ dropdown.style.display = 'none'; });
        })();
    </script>
</header>
