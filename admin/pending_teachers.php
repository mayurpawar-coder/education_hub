<?php
/**
 * ============================================================
 * Education Hub - Pending Teachers Approval (admin/pending_teachers.php)
 * ============================================================
 * 
 * PURPOSE:
 *   Allows admin to view and approve/decline teacher registration requests
 * 
 * SECURITY:
 *   - Admin-only access required
 *   - Prepared statements prevent SQL injection
 *   - CSRF protection on form actions
 * ============================================================
 */

require_once '../config/functions.php';

// Admin-only access
if (!isLoggedIn() || !isAdmin()) {
    redirect('../dashboard.php');
}

$message = '';
$error = '';

// Handle approve/decline actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $action = sanitize($_POST['action'] ?? '');
    
    if ($teacherId > 0 && in_array($action, ['approve', 'decline'])) {
        // Verify this is a pending teacher
        $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ? AND role = 'teacher' AND status = 'pending'");
        $stmt->bind_param("i", $teacherId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $teacher = $result->fetch_assoc();
            
            if ($action === 'approve') {
                // Approve teacher
                $stmt = $conn->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
                $stmt->bind_param("i", $teacherId);
                if ($stmt->execute()) {
                    $message = "Teacher {$teacher['name']} has been approved successfully.";
                } else {
                    $error = "Failed to approve teacher. Please try again.";
                }
            } else {
                // Decline teacher - delete the account
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $teacherId);
                if ($stmt->execute()) {
                    $message = "Teacher {$teacher['name']} has been declined and removed.";
                } else {
                    $error = "Failed to decline teacher. Please try again.";
                }
            }
        } else {
            $error = "Invalid teacher request.";
        }
        $stmt->close();
    }
}

// Get all pending teachers
$stmt = $conn->prepare("SELECT id, name, email, mobile, created_at FROM users WHERE role = 'teacher' AND status = 'pending' ORDER BY created_at DESC");
$stmt->execute();
$pendingTeachers = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Teachers - Education Hub</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .pending-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 24px;
            border: 1px solid var(--border);
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
        }
        
        .pending-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .pending-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
        }
        
        .pending-badge {
            background: var(--warning);
            color: white;
            padding: 4px 12px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 600;
        }
        
        .pending-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        
        .info-value {
            font-size: 14px;
            color: var(--text);
            font-weight: 500;
        }
        
        .pending-actions {
            display: flex;
            gap: 12px;
        }
        
        .btn-approve {
            background: var(--success);
            color: white;
        }
        
        .btn-decline {
            background: var(--danger);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        
        .empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="layout">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include '../includes/header.php'; ?>

            <section>
                <div class="card-header">
                    <h2 class="card-title">👨‍🏫 Pending Teacher Approvals</h2>
                </div>

                <?php if ($message): ?>
                    <?= showAlert($message, 'success') ?>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <?= showAlert($error, 'error') ?>
                <?php endif; ?>

                <?php if ($pendingTeachers->num_rows > 0): ?>
                    <?php while ($teacher = $pendingTeachers->fetch_assoc()): ?>
                        <div class="pending-card">
                            <div class="pending-header">
                                <div class="pending-title"><?= htmlspecialchars($teacher['name']) ?></div>
                                <div class="pending-badge">Pending Approval</div>
                            </div>
                            
                            <div class="pending-info">
                                <div class="info-item">
                                    <span class="info-label">📧 Email</span>
                                    <span class="info-value"><?= htmlspecialchars($teacher['email']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">📱 Mobile</span>
                                    <span class="info-value"><?= htmlspecialchars($teacher['mobile'] ?? 'Not provided') ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">📅 Applied On</span>
                                    <span class="info-value"><?= date('M j, Y', strtotime($teacher['created_at'])) ?></span>
                                </div>
                            </div>
                            
                            <div class="pending-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="teacher_id" value="<?= $teacher['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-approve" onclick="return confirm('Are you sure you want to approve this teacher?')">
                                        ✅ Approve
                                    </button>
                                </form>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="teacher_id" value="<?= $teacher['id'] ?>">
                                    <input type="hidden" name="action" value="decline">
                                    <button type="submit" class="btn btn-decline" onclick="return confirm('Are you sure you want to decline this teacher? This will permanently delete their account.')">
                                        ❌ Decline
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📋</div>
                        <h3>No Pending Teachers</h3>
                        <p>All teacher registration requests have been processed.</p>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
