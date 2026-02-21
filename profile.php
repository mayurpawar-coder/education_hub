<?php
require_once 'config/functions.php';
requireLogin();

$user = getCurrentUser();
$pageTitle = 'My Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include 'includes/header.php'; ?>

            <section>
                <?php if (!$user): ?>
                    <?= showAlert('User not found', 'error') ?>
                <?php else: ?>
                    <div class="card" style="max-width:900px; margin:24px auto;">
                        <div style="display:flex; gap:18px; align-items:center;">
                            <?php if (!empty($user['profile_image']) && file_exists(__DIR__ . '/' . $user['profile_image'])): ?>
                                <img src="<?= htmlspecialchars($user['profile_image']) ?>" alt="Avatar" style="width:120px; height:120px; border-radius:50%; object-fit:cover;">
                            <?php else: ?>
                                <img src="assets/images/default-avatar.svg" alt="Avatar" style="width:120px; height:120px; border-radius:50%; object-fit:cover; background:#eee;">
                            <?php endif; ?>

                            <div>
                                <h2 style="margin:0;"><?= htmlspecialchars($user['name']) ?></h2>
                                <div style="color:var(--text-muted); margin-top:6px;">Role: <?= ucfirst(htmlspecialchars($user['role'])) ?></div>
                                <?php if (!empty($user['mobile'])): ?>
                                    <div style="margin-top:8px;">
                                        <a href="tel:<?= htmlspecialchars($user['mobile']) ?>" class="btn">📞 Call Student</a>
                                        <span style="margin-left:10px; color:var(--text-muted);"><?= htmlspecialchars($user['mobile']) ?></span>
                                    </div>
                                <?php endif; ?>

                                <div style="margin-top:12px;">
                                    <a href="edit_profile.php" class="btn btn-primary">Edit Profile</a>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top:18px; color:var(--text-muted);">
                            <!-- Email intentionally hidden per requirements -->
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
