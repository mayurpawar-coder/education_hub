<?php

/**
 * ============================================================
 * Education Hub - Sidebar Navigation (includes/sidebar.php)
 * ============================================================
 * 
 * PURPOSE:
 *   Fixed left sidebar with navigation links that change based on user role.
 * 
 * ROLE-BASED LINKS:
 *   ALL ROLES:
 *     - Dashboard, Search Notes, Practice Quiz
 * 
 *   STUDENT ONLY:
 *     - My Performance (own quiz history)
 * 
 *   TEACHER + ADMIN:
 *     - Student Performance (view all students by semester)
 *     - Upload Notes (upload PDFs for students)
 *     - Manage Questions (add quiz questions per subject)
 *     - My Uploads (view notes they uploaded)
 * 
 *   ADMIN ONLY:
 *     - Manage Users (view/edit/delete all users)
 *     - Manage Subjects (add/remove subjects by year/semester)
 * 
 * HOW IT WORKS:
 *   1. Gets current page filename to highlight active link
 *   2. Gets user role from session
 *   3. Calculates base path for links (../  for subdirectory pages)
 *   4. Renders nav links with active class on current page
 * 
 * CSS: Styled in style.css (.sidebar, .nav-link, .nav-link.active)
 * ============================================================
 */

/* Get current page name for active link highlighting */
$currentPage = basename($_SERVER['PHP_SELF']);

/* Get user role from session (defaults to student) */
$role = $_SESSION['user_role'] ?? 'student';

/* Calculate base path: pages in admin/ or auth/ need '../' prefix */
$basePath = '';
if (
    strpos($_SERVER['PHP_SELF'], '/admin/') !== false ||
    strpos($_SERVER['PHP_SELF'], '/auth/') !== false ||
    strpos($_SERVER['PHP_SELF'], '/includes/') !== false
) {
    $basePath = '../';
}
?>

<!-- Sidebar: Fixed left navigation panel with gradient background -->
<aside class="sidebar">

    <!-- === Logo Section === -->
    <div class="sidebar-logo">
        <span class="logo-icon">📚</span>
        <h2>Education Hub</h2>
        <!-- Role badge: shows which role is currently logged in -->
        <small style="color: var(--text-muted); font-size: 11px; display: block; margin-top: 4px;">
            <?= ucfirst($role) ?> Panel
        </small>
    </div>

    <!-- === Navigation Links === -->
    <nav class="sidebar-nav">

        <!-- Dashboard link (all roles) -->
        <!-- Admin goes to admin/dashboard.php, Teacher goes to dashboard.php, Student goes to quiz.php -->
        <?php
        $dashboardUrl = $basePath . 'quiz.php';
        if ($role === 'admin') $dashboardUrl = $basePath . 'admin/dashboard.php';
        if ($role === 'teacher') $dashboardUrl = $basePath . 'dashboard.php';
        ?>
        <a href="<?= $dashboardUrl ?>"
            class="nav-link <?= ($currentPage === 'dashboard.php' || ($currentPage === 'quiz.php' && $role === 'student')) ? 'active' : '' ?>">
            <span class="icon">🏠</span>
            <span>Dashboard</span>
        </a>

        <!-- Search Notes (students & teachers can search/download notes) -->
        <a href="<?= $basePath ?>search_notes.php" class="nav-link <?= $currentPage === 'search_notes.php' ? 'active' : '' ?>">
            <span class="icon">🔍</span>
            <span>Search Notes</span>
        </a>

        <!-- Practice Quiz (all roles can take quizzes) -->
        <a href="<?= $basePath ?>quiz.php" class="nav-link <?= $currentPage === 'quiz.php' ? 'active' : '' ?>">
            <span class="icon">🎯</span>
            <span>Practice Quiz</span>
        </a>

        <!-- Performance link: different destination based on role -->
        <!-- Students → own performance, Teachers/Admins → all students -->
        <a href="<?= ($role === 'teacher' || $role === 'admin') ? $basePath . 'teacher_performance.php' : $basePath . 'performance.php' ?>"
            class="nav-link <?= ($currentPage === 'performance.php' || $currentPage === 'teacher_performance.php') ? 'active' : '' ?>">
            <span class="icon">📊</span>
            <span><?= ($role === 'teacher' || $role === 'admin') ? 'Student Performance' : 'My Performance' ?></span>
        </a>

        <!-- ===== Teacher & Admin only links ===== -->
        <?php if ($role === 'teacher' || $role === 'admin'): ?>

            <!-- Notes Management: single entry for notes-related actions (teachers & admins) -->
            <a href="<?= $basePath ?>notes_management.php" class="nav-link <?= $currentPage === 'notes_management.php' ? 'active' : '' ?>">
                <span class="icon">🗂️</span>
                <span>Notes Management</span>
            </a>

            <!-- Manage Questions: add/edit quiz questions per subject -->
            <a href="<?= $basePath ?>manage_questions.php" class="nav-link <?= $currentPage === 'manage_questions.php' ? 'active' : '' ?>">
                <span class="icon">➕</span>
                <span>Manage Questions</span>
            </a>

        <?php endif; ?>

        <!-- ===== Admin only links ===== -->
        <?php if ($role === 'admin'): ?>

            <!-- Manage Users: view/edit all registered users -->
            <a href="<?= $basePath ?>admin/users.php" class="nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>">
                <span class="icon">👥</span>
                <span>Manage Users</span>
            </a>

            <!-- Manage Subjects: add/edit subjects by year/semester -->
            <a href="<?= $basePath ?>admin/subjects.php" class="nav-link <?= $currentPage === 'subjects.php' ? 'active' : '' ?>">
                <span class="icon">📚</span>
                <span>Manage Subjects</span>
            </a>

        <?php endif; ?>
    </nav>

    <!-- === Logout Button === -->
    <div class="sidebar-footer">
        <a href="<?= $basePath ?>auth/logout.php" class="nav-link">
            <span class="icon">🚪</span>
            <span>Logout</span>
        </a>
    </div>
</aside>