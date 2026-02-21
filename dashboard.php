<?php
/**
 * ============================================================
 * Education Hub - Student/Teacher Dashboard (dashboard.php)
 * ============================================================
 * 
 * PURPOSE:
 *   Main dashboard for students and teachers after login.
 *   Shows statistics cards, quick action buttons, and subject list.
 * 
 * HOW IT WORKS:
 *   1. requireLogin() ensures only authenticated users see this page
 *   2. getUserStats() fetches quiz count, avg score, notes count
 *   3. Queries all subjects from database for the subject grid
 *   4. Displays stat cards with emoji icons and values
 *   5. Shows quick action buttons (Search Notes, Take Quiz, etc.)
 *   6. Lists all subjects with "View Notes" and "Take Quiz" links
 * 
 * ROLE DIFFERENCES:
 *   - Students see "Notes Available" count
 *   - Teachers see "Notes Uploaded" count
 *   - Teachers get extra quick action: "Upload Notes"
 * 
 * CSS: assets/css/style.css (stats-grid, stat-card, subjects-grid)
 * ============================================================
 */

require_once 'config/functions.php';
requireLogin();  // Redirect to login if not authenticated

$pageTitle = 'Dashboard';
$user = getCurrentUser();                    // Get user data from DB
$stats = getUserStats($_SESSION['user_id']); // Get quiz statistics

/* Query all subjects with note counts ordered by name for the subject grid */
$subjectsQuery = "
    SELECT s.*, COUNT(n.id) as note_count
    FROM subjects s
    LEFT JOIN notes n ON s.id = n.subject_id
    GROUP BY s.id
    ORDER BY s.year, s.semester, s.name
";
$subjects = $conn->query($subjectsQuery);

/* Group subjects by year and semester for organized display */
$groupedSubjects = [];
while ($subject = $subjects->fetch_assoc()) {
    $key = $subject['year'] . ' - Semester ' . $subject['semester'];
    if (!isset($groupedSubjects[$key])) {
        $groupedSubjects[$key] = [];
    }
    $groupedSubjects[$key][] = $subject;
}

/* Get notes for a specific subject if requested */
$selectedSubject = null;
$subjectNotes = [];
if (isset($_GET['subject_id']) && is_numeric($_GET['subject_id'])) {
    $subjectId = (int)$_GET['subject_id'];
    $selectedSubject = $conn->query("SELECT * FROM subjects WHERE id = $subjectId")->fetch_assoc();

    if ($selectedSubject) {
        $subjectNotes = $conn->query("
            SELECT n.*, u.name as uploader_name
            FROM notes n
            JOIN users u ON n.uploaded_by = u.id
            WHERE n.subject_id = $subjectId
            ORDER BY n.created_at DESC
        ");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        function showSubjectNotes(subjectId) {
            // Redirect to dashboard with subject_id parameter to show notes
            window.location.href = 'dashboard.php?subject_id=' + subjectId;
        }

        function hideSubjectNotes() {
            // Remove subject_id parameter to hide notes section
            const url = new URL(window.location);
            url.searchParams.delete('subject_id');
            window.location.href = url.toString();
        }

        // Show notes section if subject is selected
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('subject_id')) {
                document.getElementById('subject-notes-section').style.display = 'block';
                // Scroll to notes section
                document.getElementById('subject-notes-section').scrollIntoView({ behavior: 'smooth' });
            }
        });
    </script>
</head>
<body>
    <div class="layout">
        <!-- Left sidebar navigation (role-based links) -->
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <!-- Top header bar with page title and user info -->
            <?php include 'includes/header.php'; ?>

            <?php if ($user['role'] === 'teacher' && ($user['status'] ?? 'approved') === 'pending'): ?>
                <?= showAlert('Your teacher registration is pending approval. Please wait for admin approval.', 'warning') ?>
            <?php endif; ?>

            <section class="dashboard-content">

                <!-- === Statistics Cards === -->
                <!-- Shows key numbers: quizzes taken, accuracy, notes, subjects -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">📝</div>
                        <div class="stat-value"><?= $stats['total_quizzes'] ?></div>
                        <div class="stat-label">Quizzes Taken</div>
                    </div>

                    <div class="stat-card success">
                        <div class="stat-icon">🎯</div>
                        <div class="stat-value"><?= $stats['avg_score'] ?>%</div>
                        <div class="stat-label">Average Score</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">📚</div>
                        <div class="stat-value"><?= $stats['total_notes'] ?></div>
                        <!-- Teachers see "Uploaded", students see "Available" -->
                        <div class="stat-label"><?= isTeacher() ? 'Notes Uploaded' : 'Notes Available' ?></div>
                    </div>

                    <div class="stat-card warning">
                        <div class="stat-icon">📖</div>
                        <div class="stat-value"><?= $stats['subjects_studied'] ?></div>
                        <div class="stat-label">Subjects Studied</div>
                    </div>
                </div>

                <!-- === Quick Actions === -->
                <!-- Buttons for fast navigation to key features -->
                <div class="card" style="margin-bottom: 32px;">
                    <div class="card-header">
                        <h3 class="card-title">⚡ Quick Actions</h3>
                    </div>
                    <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <a href="search_notes.php" class="btn btn-primary">📝 Search Notes</a>
                        <a href="quiz.php" class="btn btn-secondary">❓ Take Quiz</a>
                        <a href="performance.php" class="btn btn-secondary">📊 View Performance</a>
                        <!-- Only teachers and admins can upload notes -->
                        <?php if (isTeacher() || isAdmin()): ?>
                        <a href="upload_notes.php" class="btn btn-success">📤 Upload Notes</a>
                        <a href="manage_questions.php" class="btn btn-secondary">➕ Add Questions</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- === Subjects by Year & Semester === -->
                <div class="card" style="margin-bottom: 32px;">
                    <div class="card-header">
                        <h3 class="card-title">📚 Subjects by Year & Semester</h3>
                        <p style="color: var(--text-muted); margin: 4px 0 0 0; font-size: 14px;">Click on any subject to view available notes</p>
                    </div>

                    <!-- Year/Semester Tabs -->
                    <?php foreach ($groupedSubjects as $yearSem => $subjectsInGroup): ?>
                    <div class="year-semester-section" style="margin-bottom: 24px;">
                        <h4 style="color: var(--primary); font-weight: 700; margin-bottom: 16px; padding: 12px; background: var(--primary-lighter); border-radius: var(--radius-sm);">
                            🎓 <?= htmlspecialchars($yearSem) ?>
                        </h4>

                        <div class="subjects-grid" style="grid-template-columns: repeat(3, 1fr); gap: 20px;">
                            <?php foreach ($subjectsInGroup as $subject): ?>
                            <div class="subject-card" style="cursor: pointer;" onclick="showSubjectNotes(<?= $subject['id'] ?>)">
                                <div class="subject-header">
                                    <div class="subject-icon" style="background: <?= $subject['color'] ?>20; color: <?= $subject['color'] ?>;">
                                        📚
                                    </div>
                                    <h3 class="subject-name" style="font-size: 16px;"><?= htmlspecialchars($subject['name']) ?></h3>
                                </div>
                                <p class="subject-desc" style="font-size: 13px; margin-bottom: 8px;"><?= htmlspecialchars($subject['description']) ?></p>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                    <span style="font-size: 12px; color: var(--text-muted);">
                                        📝 <?= $subject['note_count'] ?> note<?= $subject['note_count'] != 1 ? 's' : '' ?> available
                                    </span>
                                    <span style="font-size: 11px; color: var(--text-light); background: var(--surface); padding: 2px 6px; border-radius: 4px;">
                                        <?= $subject['year'] ?> Sem <?= $subject['semester'] ?>
                                    </span>
                                </div>
                                <div class="subject-actions">
                                    <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); showSubjectNotes(<?= $subject['id'] ?>)">
                                        📖 View Notes
                                    </button>
                                    <a href="quiz.php?subject=<?= $subject['id'] ?>" class="btn btn-sm btn-secondary" onclick="event.stopPropagation()">
                                        🎯 Take Quiz
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- === Subject Notes Section === -->
                <div id="subject-notes-section" class="card" style="display: none; margin-bottom: 32px;">
                    <div class="card-header">
                        <h3 class="card-title" id="subject-notes-title">📖 Subject Notes</h3>
                        <button class="btn btn-sm" onclick="hideSubjectNotes()" style="margin-left: auto;">✕ Close</button>
                    </div>

                    <div id="subject-notes-content">
                        <!-- Notes will be loaded here via AJAX or page refresh -->
                        <?php if ($selectedSubject && $subjectNotes): ?>
                            <div class="notes-list" style="display: grid; gap: 16px;">
                                <?php while ($note = $subjectNotes->fetch_assoc()): ?>
                                <div class="note-item" style="padding: 16px; border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--surface-light);">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                                        <div>
                                            <h4 style="margin: 0; color: var(--text); font-size: 16px;"><?= htmlspecialchars($note['title']) ?></h4>
                                            <p style="margin: 4px 0 0 0; color: var(--text-muted); font-size: 12px;">
                                                Uploaded by <?= htmlspecialchars($note['uploader_name']) ?> •
                                                <?= formatDate($note['created_at']) ?> •
                                                Downloaded <?= $note['downloads'] ?> times
                                            </p>
                                        </div>
                                        <a href="download_notes.php?id=<?= $note['id'] ?>" class="btn btn-sm btn-success" style="margin-left: 12px;">
                                            � Download
                                        </a>
                                    </div>
                                    <?php if (!empty($note['content'])): ?>
                                        <div style="color: var(--text-muted); font-size: 14px; line-height: 1.5; margin-top: 8px;">
                                            <?= nl2br(htmlspecialchars(substr($note['content'], 0, 200))) ?>
                                            <?php if (strlen($note['content']) > 200): ?>...<?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php elseif ($selectedSubject): ?>
                            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                                <div style="font-size: 48px; margin-bottom: 16px;">📝</div>
                                <h4>No notes available</h4>
                                <p>Notes for this subject haven't been uploaded yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
