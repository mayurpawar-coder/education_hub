<?php
/**
 * ============================================================
 * Education Hub - Manage Users (admin/users.php)
 * ============================================================
 * 
 * PURPOSE:
 *   Admin can view, edit roles, and delete users.
 * 
 * HOW IT WORKS:
 *   1. DELETE: ?delete=5 → deletes user with ID 5 (prevents self-delete)
 *   2. ROLE CHANGE: POST with user_id + new_role → updates role in DB
 *   3. Lists all users with role dropdowns and delete buttons
 * 
 * SECURITY:
 *   - requireAdmin() blocks non-admin users
 *   - Cannot delete your own account
 *   - Role dropdown disabled for current admin user
 * 
 * CSS: ../assets/css/style.css (card, table)
 * ============================================================
 */

require_once '../config/functions.php';
requireAdmin();

$pageTitle = 'Manage Users';
$success = '';
$error = '';

/* --- Handle user deletion via URL parameter --- */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    /* Prevent admin from deleting themselves */
    if ($deleteId !== $_SESSION['user_id']) {
        $conn->query("DELETE FROM users WHERE id = $deleteId");
        $success = 'User deleted successfully';
    } else {
        $error = 'Cannot delete your own account';
    }
}

/* --- Handle approve/reject actions for teacher requests --- */
if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $uid = (int)$_GET['approve'];
    $conn->query("UPDATE users SET status = 'approved' WHERE id = $uid");
    $success = 'User approved successfully';
}
if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $uid = (int)$_GET['reject'];
    $conn->query("UPDATE users SET status = 'rejected' WHERE id = $uid");
    $success = 'User rejected successfully';
}

/* --- Handle role change via POST form --- */
if (isset($_POST['change_role'])) {
    $userId = (int)$_POST['user_id'];
    $newRole = sanitize($_POST['new_role']);

    if (in_array($newRole, ['student', 'teacher', 'admin'])) {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $newRole, $userId);
        $stmt->execute();
        $success = 'User role updated successfully';
    }
}

/* Get all users */
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Education Hub</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="layout">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include '../includes/header.php'; ?>

            <section>
                <?php if ($error): ?><?= showAlert($error, 'error') ?><?php endif; ?>
                <?php if ($success): ?><?= showAlert($success, 'success') ?><?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">👥 All Users (<?= $users->num_rows ?>)</h3>
                    </div>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Mobile</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td style="display:flex; align-items:center; gap:10px;">
                                        <?php if (!empty($user['profile_image']) && file_exists(__DIR__ . '/../' . $user['profile_image'])): ?>
                                            <img src="<?= '../' . $user['profile_image'] ?>" style="width:36px; height:36px; border-radius:50%; object-fit:cover;">
                                        <?php else: ?>
                                            <div style="width:36px; height:36px; border-radius:50%; background:#eee; display:flex; align-items:center; justify-content:center; font-weight:600;"><?= substr(strtoupper($user['name']),0,2) ?></div>
                                        <?php endif; ?>
                                        <strong><?= htmlspecialchars($user['name']) ?></strong>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($user['mobile'] ?? '-') ?>
                                    </td>
                                    <td>
                                        <!-- Inline role change form: dropdown auto-submits on change -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <select name="new_role" onchange="this.form.submit()" 
                                                    style="padding: 6px 10px; border-radius: 6px; background: var(--surface-light); color: var(--text); border: 1px solid var(--border); font-size: 12px;"
                                                    <?= $user['id'] === $_SESSION['user_id'] ? 'disabled' : '' ?>>
                                                <option value="student" <?= $user['role'] === 'student' ? 'selected' : '' ?>>👨‍🎓 Student</option>
                                                <option value="teacher" <?= $user['role'] === 'teacher' ? 'selected' : '' ?>>👩‍🏫 Teacher</option>
                                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>🛡️ Admin</option>
                                            </select>
                                            <input type="hidden" name="change_role" value="1">
                                        </form>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($user['status'] ?? 'approved') ?>
                                    </td>
                                    <td><?= formatDate($user['created_at']) ?></td>
                                    <td>
                                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                        <?php if (($user['role'] ?? '') === 'teacher' && ($user['status'] ?? '') === 'pending'): ?>
                                            <a href="?approve=<?= $user['id'] ?>" class="btn btn-sm btn-success">✅ Approve</a>
                                            <a href="?reject=<?= $user['id'] ?>" class="btn btn-sm btn-warning">❌ Reject</a>
                                        <?php endif; ?>
                                        <a href="?delete=<?= $user['id'] ?>" 
                                           onclick="return confirm('Are you sure you want to delete this user?')"
                                           class="btn btn-sm btn-danger">🗑️ Delete</a>
                                        <?php if (!empty($user['mobile'])): ?>
                                            <a href="tel:<?= htmlspecialchars($user['mobile']) ?>" class="btn btn-sm" style="margin-left:6px;">📞 Call</a>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 12px;">Current User</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
