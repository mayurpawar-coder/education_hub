<?php
require_once 'config/functions.php';
requireLogin();

$user = getCurrentUser();
$pageTitle = 'Edit Profile';
$error = '';
$success = '';

if (!$user) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? $user['name']);
    $mobile = sanitize($_POST['mobile'] ?? $user['mobile']);

    // Handle profile image upload
    $profileImagePath = $user['profile_image'] ?? null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowed = ['image/jpeg', 'image/png'];
        $fileType = $_FILES['profile_image']['type'];
        $fileSize = $_FILES['profile_image']['size'];
        if (!in_array($fileType, $allowed)) {
            $error = 'Profile image must be JPG or PNG';
        } elseif ($fileSize > 2 * 1024 * 1024) {
            $error = 'Profile image must be smaller than 2MB';
        } else {
            $uploadsDir = __DIR__ . '/uploads/profile/';
            if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
            $ext = $fileType === 'image/png' ? '.png' : '.jpg';
            $destName = time() . '_' . preg_replace('/[^a-zA-Z0-9-_\.]/', '', basename($_FILES['profile_image']['name']));
            $destPath = $uploadsDir . $destName;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $destPath)) {
                // Remove old image if exists
                if (!empty($profileImagePath) && file_exists(__DIR__ . '/' . $profileImagePath)) {
                    @unlink(__DIR__ . '/' . $profileImagePath);
                }
                $profileImagePath = 'uploads/profile/' . $destName;
            } else {
                $error = 'Failed to upload profile image';
            }
        }
    }

    if (empty($error)) {
        $stmt = $conn->prepare("UPDATE users SET name = ?, mobile = ?, profile_image = ? WHERE id = ?");
        $stmt->bind_param('sssi', $name, $mobile, $profileImagePath, $user['id']);
        if ($stmt->execute()) {
            $success = 'Profile updated successfully';
            // Refresh current user session info
            $_SESSION['user_name'] = $name;
            // Reload user data
            $user = getCurrentUser();
        } else {
            $error = 'Failed to update profile';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Profile - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include 'includes/header.php'; ?>

            <section>
                <div class="card" style="max-width:700px; margin:24px auto;">
                    <h3>Edit Profile</h3>

                    <?php if ($error): ?><?= showAlert($error, 'error') ?><?php endif; ?>
                    <?php if ($success): ?><?= showAlert($success, 'success') ?><?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" required value="<?= htmlspecialchars($user['name']) ?>">
                        </div>

                        <div class="form-group">
                            <label for="mobile">Mobile</label>
                            <input type="text" id="mobile" name="mobile" value="<?= htmlspecialchars($user['mobile'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="profile_image">Profile Image (jpg/png, ≤ 2MB)</label>
                            <input type="file" id="profile_image" name="profile_image" accept="image/png, image/jpeg">
                        </div>

                        <button class="btn btn-primary" type="submit">Save Changes</button>
                    </form>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
