<?php
/**
 * Education Hub - Helper Functions
 */

require_once __DIR__ . '/database.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check user role
function hasRole($role) {
    if (!isLoggedIn()) return false;
    return $_SESSION['user_role'] === $role;
}

// Check if admin
function isAdmin() {
    return hasRole('admin');
}

// Check if teacher
function isTeacher() {
    return hasRole('teacher');
}

// Check if student
function isStudent() {
    return hasRole('student');
}

// Get base URL for redirects
function getBasePath() {
    $scriptPath = $_SERVER['PHP_SELF'];
    if (strpos($scriptPath, '/admin/') !== false || 
        strpos($scriptPath, '/auth/') !== false ||
        strpos($scriptPath, '/includes/') !== false) {
        return '../';
    }
    return '';
}

// Redirect function
function redirect($url) {
    header("Location: $url");
    exit();
}

// Protect route - redirect to login if not authenticated
function requireLogin() {
    if (!isLoggedIn()) {
        $basePath = getBasePath();
        redirect($basePath . 'auth/login.php');
    }
}

// Protect admin routes
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        $basePath = getBasePath();
        redirect($basePath . 'dashboard.php?error=unauthorized');
    }
}

// Protect teacher routes
function requireTeacher() {
    requireLogin();
    if (!isTeacher() && !isAdmin()) {
        $basePath = getBasePath();
        redirect($basePath . 'dashboard.php?error=unauthorized');
    }
}

// Sanitize input
function sanitize($input) {
    global $conn;
    return htmlspecialchars(strip_tags(trim($conn->real_escape_string($input))));
}

// Display alert message
function showAlert($message, $type = 'info') {
    $bgColor = [
        'success' => '#10b981',
        'error' => '#ef4444',
        'warning' => '#f59e0b',
        'info' => '#0099ff'
    ];
    $bg = $bgColor[$type] ?? $bgColor['info'];
    
    return "<div class='alert alert-$type' style='background: $bg; color: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px;'>$message</div>";
}

// Get current user data
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    global $conn;
    $userId = $_SESSION['user_id'];
    $result = $conn->query("SELECT * FROM users WHERE id = $userId");
    return $result->fetch_assoc();
}

// Format date
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Get user stats
function getUserStats($userId) {
    global $conn;
    
    $stats = [
        'total_quizzes' => 0,
        'avg_score' => 0,
        'total_notes' => 0,
        'subjects_studied' => 0
    ];
    
    // Total quizzes taken
    $result = $conn->query("SELECT COUNT(*) as count, AVG(percentage) as avg FROM quiz_results WHERE user_id = $userId");
    $row = $result->fetch_assoc();
    $stats['total_quizzes'] = $row['count'];
    $stats['avg_score'] = round($row['avg'] ?? 0, 1);
    
    // Notes downloaded (for students) or uploaded (for teachers)
    $role = $_SESSION['user_role'];
    if ($role === 'teacher' || $role === 'admin') {
        $result = $conn->query("SELECT COUNT(*) as count FROM notes WHERE uploaded_by = $userId");
    } else {
        $result = $conn->query("SELECT COUNT(*) as count FROM notes");
    }
    $stats['total_notes'] = $result->fetch_assoc()['count'];
    
    // Subjects studied
    $result = $conn->query("SELECT COUNT(DISTINCT subject_id) as count FROM quiz_results WHERE user_id = $userId");
    $stats['subjects_studied'] = $result->fetch_assoc()['count'];
    
    return $stats;
}
?>
