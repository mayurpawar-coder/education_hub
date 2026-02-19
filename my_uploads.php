<?php
/**
 * ============================================================
 * Education Hub - My Uploads (my_uploads.php)
 * ============================================================
 * 
 * PURPOSE:
 *   Shows teachers a list of all notes THEY have uploaded.
 *   Displays title, subject, downloads count, and upload date.
 * 
 * ACCESS: Teachers and Admins only (requireTeacher)
 * 
 * HOW IT WORKS:
 *   1. requireTeacher() ensures only teachers/admins access this page
 *   2. Queries notes table WHERE uploaded_by = current user's ID
 *   3. JOINs with subjects table to show subject name and color
 *   4. Displays notes in a card-based list with download stats
 * 
 * CSS: assets/css/style.css (card, table, note-card classes)
 * ============================================================
 */

require_once 'config/functions.php';
requireTeacher(); // Only teachers and admins

$pageTitle = 'My Uploads';
$userId = $_SESSION['user_id'];

/* Query notes uploaded by current user, join subjects for name/color */
$myNotes = $conn->query("
    SELECT n.*, s.name as subject_name, s.color as subject_color, s.year, s.semester
    FROM notes n
    JOIN subjects s ON n.subject_id = s.id
    WHERE n.uploaded_by = $userId
    ORDER BY n.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Uploads - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'includes/header.php'; ?>

            <section>
                <!-- Page hero section -->
                <div class="card" style="margin-bottom: 24px; text-align: center; padding: 32px;">
                    <h2 style="margin-bottom: 8px;">📄 My Uploaded Notes</h2>
                    <p style="color: var(--text-muted);">View and manage all notes you have uploaded</p>
                </div>

                <!-- Notes list -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">📁 Your Notes (<?= $myNotes->num_rows ?>)</h3>
                        <a href="upload_notes.php" class="btn btn-sm btn-primary">📤 Upload New</a>
                    </div>

                    <?php if ($myNotes->num_rows > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Subject</th>
                                    <th>Year / Sem</th>
                                    <th>Downloads</th>
                                    <th>Uploaded</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($note = $myNotes->fetch_assoc()): ?>
                                <tr>
                                    <!-- Note title -->
                                    <td><strong><?= htmlspecialchars($note['title']) ?></strong></td>
                                    <!-- Subject with color badge -->
                                    <td>
                                        <span style="background: <?= $note['subject_color'] ?>20; color: <?= $note['subject_color'] ?>; padding: 4px 12px; border-radius: 20px; font-size: 12px;">
                                            <?= htmlspecialchars($note['subject_name']) ?>
                                        </span>
                                    </td>
                                    <!-- Year and semester -->
                                    <td><?= $note['year'] ?> - Sem <?= $note['semester'] ?></td>
                                    <!-- Download count with icon -->
                                    <td><strong style="color: var(--primary);">⬇️ <?= $note['downloads'] ?></strong></td>
                                    <!-- Upload date formatted -->
                                    <td><?= formatDate($note['created_at']) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <!-- Empty state when no notes uploaded yet -->
                    <div style="text-align: center; padding: 48px; color: var(--text-muted);">
                        <div style="font-size: 48px; margin-bottom: 16px;">📭</div>
                        <p>You haven't uploaded any notes yet.</p>
                        <a href="upload_notes.php" class="btn btn-primary" style="margin-top: 16px;">📤 Upload Your First Note</a>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
