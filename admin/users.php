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

/* Get all users, sorted with pending teachers first */
$users = $conn->query("SELECT * FROM users ORDER BY CASE WHEN status = 'pending' AND role = 'teacher' THEN 0 ELSE 1 END, created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Education Hub</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Status badges with theme colors */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-approved {
            background: var(--success-light);
            color: var(--success);
        }
        
        .status-pending {
            background: var(--warning-light);
            color: var(--warning);
        }
        
        .status-rejected {
            background: var(--danger-light);
            color: var(--danger);
        }
        
        /* User avatar styling */
        .user-avatar-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border);
            box-shadow: var(--shadow-sm);
        }
        
        .user-avatar-fallback {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 14px;
            box-shadow: var(--shadow-sm);
        }
        
        .user-name {
            font-weight: 600;
            color: var(--text);
        }
        
        /* Role dropdown styling */
        .role-dropdown {
            padding: 6px 10px;
            border-radius: var(--radius-sm);
            background: var(--surface-light);
            color: var(--text);
            border: 1px solid var(--border);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .role-dropdown:hover {
            border-color: var(--primary);
        }
        
        .role-dropdown:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        /* Action buttons styling */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            font-size: 11px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-approve {
            background: var(--success);
            color: white;
        }
        
        .btn-approve:hover {
            background: #047857;
            transform: translateY(-1px);
        }
        
        .btn-reject {
            background: var(--warning);
            color: white;
        }
        
        .btn-reject:hover {
            background: #b45309;
            transform: translateY(-1px);
        }
        
        .btn-delete {
            background: var(--danger);
            color: white;
        }
        
        .btn-delete:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }
        
        .btn-call {
            background: var(--primary);
            color: white;
        }
        
        .btn-call:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        /* Pending teacher row highlighting */
        .pending-teacher-row {
            background: var(--warning-light);
            border-left: 4px solid var(--warning);
        }
        
        .pending-teacher-row:hover {
            background: #fef3c7;
        }
        
        /* Table improvements */
        .table-container {
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: var(--primary-lighter);
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border);
        }
        
        td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        
        tr:hover {
            background: var(--primary-lighter);
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
                gap: 4px;
            }
            
            .btn-action {
                font-size: 10px;
                padding: 4px 8px;
            }
            
            .user-avatar-cell {
                flex-direction: column;
                gap: 8px;
                text-align: center;
            }
        }
    </style>
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
                        <small style="color: var(--text-muted);">Teachers with pending approval are highlighted</small>
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
                                <?php
                                $isPendingTeacher = ($user['role'] ?? '') === 'teacher' && ($user['status'] ?? '') === 'pending';
                                $rowClass = $isPendingTeacher ? 'pending-teacher-row' : '';
                                ?>
                                <tr class="<?= $rowClass ?>">
                                    <td><strong><?= $user['id'] ?></strong></td>
                                    <td>
                                        <div class="user-avatar-cell">
                                            <?php if (!empty($user['profile_image']) && file_exists(__DIR__ . '/../' . $user['profile_image'])): ?>
                                                <img src="<?= '../' . $user['profile_image'] ?>" alt="Profile" class="user-avatar-img">
                                            <?php else: ?>
                                                <div class="user-avatar-fallback"><?= substr(strtoupper($user['name']),0,2) ?></div>
                                            <?php endif; ?>
                                            <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?= !empty($user['mobile']) ? '📱 ' . htmlspecialchars($user['mobile']) : '<span style="color: var(--text-muted);">-</span>' ?>
                                    </td>
                                    <td>
                                        <!-- Inline role change form: dropdown auto-submits on change -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <select name="new_role" onchange="this.form.submit()" 
                                                    class="role-dropdown"
                                                    <?= $user['id'] === $_SESSION['user_id'] ? 'disabled' : '' ?>>
                                                <option value="student" <?= $user['role'] === 'student' ? 'selected' : '' ?>>👨‍🎓 Student</option>
                                                <option value="teacher" <?= $user['role'] === 'teacher' ? 'selected' : '' ?>>👩‍🏫 Teacher</option>
                                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>🛡️ Admin</option>
                                            </select>
                                            <input type="hidden" name="change_role" value="1">
                                        </form>
                                    </td>
                                    <td>
                                        <?php $status = $user['status'] ?? 'approved'; ?>
                                        <span class="status-badge status-<?= $status ?>">
                                            <?= htmlspecialchars(ucfirst($status)) ?>
                                        </span>
                                    </td>
                                    <td><?= formatDate($user['created_at']) ?></td>
                                    <td>
                                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                        <div class="action-buttons">
                                            <?php if ($isPendingTeacher): ?>
                                                <a href="?approve=<?= $user['id'] ?>" class="btn-action btn-approve" onclick="return confirm('Approve this teacher?')">✅ Approve</a>
                                                <a href="?reject=<?= $user['id'] ?>" class="btn-action btn-reject" onclick="return confirm('Reject this teacher?')">❌ Reject</a>
                                            <?php endif; ?>
                                            <a href="?delete=<?= $user['id'] ?>" 
                                               onclick="return confirm('Are you sure you want to delete this user?')"
                                               class="btn-action btn-delete">🗑️ Delete</a>
                                            <?php if (!empty($user['mobile'])): ?>
                                                <a href="tel:<?= htmlspecialchars($user['mobile']) ?>" class="btn-action btn-call">📞 Call</a>
                                            <?php endif; ?>
                                        </div>
                                        <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 12px; font-weight: 500;">Current User</span>
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
