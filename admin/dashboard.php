<?php
/**
 * ============================================================
 * Education Hub - Admin Dashboard (admin/dashboard.php)
 * ============================================================
 * 
 * PURPOSE:
 *   Overview panel for administrators showing platform-wide statistics,
 *   quick action buttons, recent users, uploaded notes, and questions.
 * 
 * ACCESS: Admin only (requireAdmin)
 * 
 * HOW IT WORKS:
 *   1. requireAdmin() blocks non-admin users
 *   2. Runs COUNT(*) queries to get totals for users, subjects, notes, etc.
 *   3. Queries recent users, all notes, and all questions for display
 *   4. Shows stat cards, quick actions, recent users table
 *   5. Shows uploaded notes and quiz questions tables (admin can see ALL)
 * 
 * CSS: ../assets/css/style.css (admin-stats, stats-grid, card, table)
 * ============================================================
 */

require_once '../config/functions.php';
requireAdmin(); // Only admins can access

$pageTitle = 'Admin Dashboard';

/* === Count queries for stat cards === */
$totalUsers = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$totalStudents = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'student'")->fetch_assoc()['c'];
$totalTeachers = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'teacher'")->fetch_assoc()['c'];
$totalSubjects = $conn->query("SELECT COUNT(*) as c FROM subjects")->fetch_assoc()['c'];
$totalNotes = $conn->query("SELECT COUNT(*) as c FROM notes")->fetch_assoc()['c'];
$totalQuestions = $conn->query("SELECT COUNT(*) as c FROM questions")->fetch_assoc()['c'];
$totalQuizzes = $conn->query("SELECT COUNT(*) as c FROM quiz_results")->fetch_assoc()['c'];

/* Recent users (last 5 registered) */
$recentUsers = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");

/* All uploaded notes with uploader name and subject */
$allNotes = $conn->query("
    SELECT n.*, u.name as uploader_name, s.name as subject_name, s.color as subject_color
    FROM notes n
    JOIN users u ON n.uploaded_by = u.id
    JOIN subjects s ON n.subject_id = s.id
    ORDER BY n.created_at DESC
    LIMIT 10
");

/* All questions with creator name and subject */
$allQuestions = $conn->query("
    SELECT q.*, u.name as creator_name, s.name as subject_name
    FROM questions q
    JOIN users u ON q.created_by = u.id
    JOIN subjects s ON q.subject_id = s.id
    ORDER BY q.created_at DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Education Hub</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="layout">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include '../includes/header.php'; ?>

            <section>
                <!-- === Platform Statistics (Row 1) === -->
                <div class="admin-stats">
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-value"><?= $totalUsers ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">🎓</div>
                        <div class="stat-value"><?= $totalStudents ?></div>
                        <div class="stat-label">Students</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-icon">👨‍🏫</div>
                        <div class="stat-value"><?= $totalTeachers ?></div>
                        <div class="stat-label">Teachers</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-icon">📚</div>
                        <div class="stat-value"><?= $totalSubjects ?></div>
                        <div class="stat-label">Subjects</div>
                    </div>
                </div>

                <!-- === Platform Statistics (Row 2) === -->
                <div class="stats-grid" style="margin-bottom: 32px;">
                    <div class="stat-card">
                        <div class="stat-icon">📝</div>
                        <div class="stat-value"><?= $totalNotes ?></div>
                        <div class="stat-label">Notes Uploaded</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">❓</div>
                        <div class="stat-value"><?= $totalQuestions ?></div>
                        <div class="stat-label">Quiz Questions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📊</div>
                        <div class="stat-value"><?= $totalQuizzes ?></div>
                        <div class="stat-label">Quizzes Taken</div>
                    </div>
                </div>

                <!-- === Quick Actions === -->
                <div class="card" style="margin-bottom: 32px;">
                    <div class="card-header">
                        <h3 class="card-title">⚡ Admin Actions</h3>
                    </div>
                    <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <a href="users.php" class="btn btn-primary">👥 Manage Users</a>
                        <a href="subjects.php" class="btn btn-secondary">📚 Manage Subjects</a>
                        <a href="../teacher_performance.php" class="btn btn-secondary">📊 Student Performance</a>
                        <a href="../upload_notes.php" class="btn btn-secondary">📤 Upload Notes</a>
                        <a href="../manage_questions.php" class="btn btn-secondary">➕ Add Questions</a>
                    </div>
                </div>

                <!-- === Recent Users Table === -->
                <div class="card" style="margin-bottom: 32px;">
                    <div class="card-header">
                        <h3 class="card-title">👥 Recent Users</h3>
                        <a href="users.php" class="btn btn-sm btn-secondary">View All</a>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($user = $recentUsers->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <span style="text-transform: capitalize; color: <?= $user['role'] === 'admin' ? 'var(--danger)' : ($user['role'] === 'teacher' ? 'var(--success)' : 'var(--primary)') ?>; font-weight: 600;">
                                            <?= $user['role'] ?>
                                        </span>
                                    </td>
                                    <td><?= formatDate($user['created_at']) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- === All Uploaded Notes (Admin can see everything) === -->
                <div class="card" style="margin-bottom: 32px;">
                    <div class="card-header">
                        <h3 class="card-title">📝 All Uploaded Notes</h3>
                    </div>
                    <?php if ($allNotes->num_rows > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Subject</th>
                                    <th>Uploaded By</th>
                                    <th>Downloads</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($note = $allNotes->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($note['title']) ?></strong></td>
                                    <td>
                                        <span style="background: <?= $note['subject_color'] ?>20; color: <?= $note['subject_color'] ?>; padding: 4px 12px; border-radius: 20px; font-size: 12px;">
                                            <?= htmlspecialchars($note['subject_name']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($note['uploader_name']) ?></td>
                                    <td><strong>⬇️ <?= $note['downloads'] ?></strong></td>
                                    <td><?= formatDate($note['created_at']) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p style="text-align: center; color: var(--text-muted); padding: 24px;">No notes uploaded yet.</p>
                    <?php endif; ?>
                </div>

                <!-- === All Quiz Questions (Admin can see all) === -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">❓ All Quiz Questions</h3>
                    </div>
                    <?php if ($allQuestions->num_rows > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Question</th>
                                    <th>Subject</th>
                                    <th>Created By</th>
                                    <th>Correct</th>
                                    <th>Difficulty</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($q = $allQuestions->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars(substr($q['question_text'], 0, 60)) ?>...</td>
                                    <td><?= htmlspecialchars($q['subject_name']) ?></td>
                                    <td><?= htmlspecialchars($q['creator_name']) ?></td>
                                    <td><strong style="color: var(--success);"><?= $q['correct_answer'] ?></strong></td>
                                    <td style="text-transform: capitalize;"><?= $q['difficulty'] ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p style="text-align: center; color: var(--text-muted); padding: 24px;">No questions added yet.</p>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
