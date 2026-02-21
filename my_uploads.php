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
    ORDER BY s.year, s.semester, n.created_at DESC
");

// Group notes by year -> semester for structured display
$groupedNotes = [];
while ($row = $myNotes->fetch_assoc()) {
    $y = $row['year'] ?? 'Other';
    $sem = 'Sem ' . ($row['semester'] ?? '0');
    if (!isset($groupedNotes[$y])) $groupedNotes[$y] = [];
    if (!isset($groupedNotes[$y][$sem])) $groupedNotes[$y][$sem] = [];
    $groupedNotes[$y][$sem][] = $row;
}
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

                    <?php
                    // Calculate total count from grouped notes
                    $totalCount = 0;
                    foreach ($groupedNotes as $y => $sems) {
                        foreach ($sems as $s => $notesArr) {
                            $totalCount += count($notesArr);
                        }
                    }
                    ?>

                    <?php if ($totalCount > 0): ?>
                        <?php foreach ($groupedNotes as $year => $sems): ?>
                            <div style="margin-bottom: 18px;">
                                <h4 style="margin:12px 0;"><?= htmlspecialchars($year) ?></h4>
                                <?php foreach ($sems as $semester => $notesArr): ?>
                                    <div style="margin-left: 8px; margin-bottom: 12px;">
                                        <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
                                            <span class="year-badge" style="font-weight:600;"><?= htmlspecialchars($semester) ?></span>
                                        </div>
                                        <div class="notes-row" style="display:flex; flex-wrap:wrap; gap:12px;">
                                            <?php foreach ($notesArr as $note): ?>
                                                <div class="note-card" style="width:320px; border:1px solid #eee; padding:12px; border-radius:8px; background:#fff;">
                                                    <div style="display:flex; justify-content:space-between; align-items:start; gap:8px;">
                                                        <div>
                                                            <strong><?= htmlspecialchars($note['title']) ?></strong>
                                                            <div style="margin-top:6px;">
                                                                <span style="background: <?= $note['subject_color'] ?>20; color: <?= $note['subject_color'] ?>; padding: 4px 10px; border-radius:16px; font-size:12px;">
                                                                    <?= htmlspecialchars($note['subject_name']) ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div style="text-align:right; font-size:13px; color:var(--text-muted);">
                                                            <div>⬇️ <?= $note['downloads'] ?></div>
                                                            <div style="margin-top:8px;"><?= formatDate($note['created_at']) ?></div>
                                                        </div>
                                                    </div>
                                                    <div style="margin-top:10px; display:flex; gap:8px;">
                                                        <a class="btn btn-sm" href="download_notes.php?id=<?= $note['id'] ?>">Download</a>
                                                        <a class="btn btn-sm" href="manage_notes.php?edit=<?= $note['id'] ?>">Edit</a>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
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
