<?php
/**
 * Education Hub - Sidebar Component
 * Clean version without Live Quiz
 */

$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['user_role'] ?? 'student';

// Get base path dynamically
$basePath = '';
if (strpos($_SERVER['PHP_SELF'], '/includes/') !== false || 
    strpos($_SERVER['PHP_SELF'], '/admin/') !== false ||
    strpos($_SERVER['PHP_SELF'], '/auth/') !== false) {
    $basePath = '../';
}
?>

<aside class="sidebar">
    <div class="sidebar-logo">
        <span class="logo-icon">📚</span>
        <h2>Education Hub</h2>
    </div>
    
    <nav class="sidebar-nav">
        <a href="<?= $role === 'admin' ? $basePath . 'admin/dashboard.php' : $basePath . 'dashboard.php' ?>" 
           class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <span class="icon">🏠</span>
            <span>Dashboard</span>
        </a>
        
        <a href="<?= $basePath ?>search_notes.php" class="nav-link <?= $currentPage === 'search_notes.php' ? 'active' : '' ?>">
            <span class="icon">📝</span>
            <span>Search Notes</span>
        </a>
        
        <a href="<?= $basePath ?>quiz.php" class="nav-link <?= $currentPage === 'quiz.php' ? 'active' : '' ?>">
            <span class="icon">❓</span>
            <span>Take Quiz</span>
        </a>
        
        <a href="<?= $basePath ?>performance.php" class="nav-link <?= $currentPage === 'performance.php' ? 'active' : '' ?>">
            <span class="icon">📊</span>
            <span>Performance</span>
        </a>
        
        <?php if ($role === 'teacher' || $role === 'admin'): ?>
        <a href="<?= $basePath ?>upload_notes.php" class="nav-link <?= $currentPage === 'upload_notes.php' ? 'active' : '' ?>">
            <span class="icon">📤</span>
            <span>Upload Notes</span>
        </a>
        
        <a href="<?= $basePath ?>manage_questions.php" class="nav-link <?= $currentPage === 'manage_questions.php' ? 'active' : '' ?>">
            <span class="icon">➕</span>
            <span>Manage Questions</span>
        </a>
        <?php endif; ?>
        
        <?php if ($role === 'admin'): ?>
        <a href="<?= $basePath ?>admin/users.php" class="nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>">
            <span class="icon">👥</span>
            <span>Manage Users</span>
        </a>
        
        <a href="<?= $basePath ?>admin/subjects.php" class="nav-link <?= $currentPage === 'subjects.php' ? 'active' : '' ?>">
            <span class="icon">📚</span>
            <span>Manage Subjects</span>
        </a>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
        <a href="<?= $basePath ?>auth/logout.php" class="nav-link">
            <span class="icon">🚪</span>
            <span>Logout</span>
        </a>
    </div>
</aside>
