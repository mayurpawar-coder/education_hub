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

/* Query all subjects ordered by name for the subject grid */
$subjects = $conn->query("SELECT * FROM subjects ORDER BY year, semester, name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="layout">
        <!-- Left sidebar navigation (role-based links) -->
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <!-- Top header bar with page title and user info -->
            <?php include 'includes/header.php'; ?>

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

                <!-- === Subjects Grid === -->
                <!-- Shows all subjects as cards with View Notes / Take Quiz buttons -->
                <h2 style="margin-bottom: 24px;">📚 Subjects</h2>
                <div class="subjects-grid">
                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                    <div class="subject-card">
                        <div class="subject-header">
                            <!-- Subject icon with color background -->
                            <div class="subject-icon" style="background: <?= $subject['color'] ?>20; color: <?= $subject['color'] ?>;">
                                📚
                            </div>
                            <h3 class="subject-name"><?= htmlspecialchars($subject['name']) ?></h3>
                        </div>
                        <!-- Year and semester badge -->
                        <p class="subject-desc"><?= $subject['year'] ?> - Semester <?= $subject['semester'] ?></p>
                        <p class="subject-desc"><?= htmlspecialchars($subject['description']) ?></p>
                        <!-- Action buttons: navigate to notes or quiz for this subject -->
                        <div class="subject-actions">
                            <a href="search_notes.php?subject=<?= $subject['id'] ?>" class="btn btn-sm btn-secondary">📖 View Notes</a>
                            <a href="quiz.php?subject=<?= $subject['id'] ?>" class="btn btn-sm btn-primary">🎯 Take Quiz</a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
