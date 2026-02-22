<?php

/**
 * ============================================================
 * Education Hub - Helper Functions (functions.php)
 * ============================================================
 * 
 * PURPOSE:
 *   Contains all reusable helper functions used across the application.
 *   Every PHP page includes this file via require_once.
 * 
 * WHAT THIS FILE PROVIDES:
 *   - Authentication checks (isLoggedIn, hasRole, isAdmin, etc.)
 *   - Route protection (requireLogin, requireAdmin, requireTeacher)
 *   - Input sanitization (sanitize function - prevents XSS attacks)
 *   - Alert messages (showAlert - colored notification boxes)
 *   - User data retrieval (getCurrentUser, getUserStats)
 *   - Utility functions (redirect, formatDate, getBasePath)
 * 
 * HOW IT WORKS:
 *   This file first includes database.php to get the $conn object,
 *   then defines functions that check $_SESSION variables set during login.
 * 
 * SECURITY:
 *   - sanitize() strips tags and escapes HTML to prevent XSS
 *   - requireLogin() redirects unauthenticated users to login page
 *   - requireAdmin()/requireTeacher() enforce role-based access
 * ============================================================
 */

// Global Constants
const PASS_PERCENTAGE = 40;

/* Include database connection - gives us $conn and $db */
require_once __DIR__ . '/database.php';

// NOTE: Database schema changes are provided as a migration SQL file:
// See: database/20260221_add_user_fields.sql
// Run the SQL file manually (phpMyAdmin or mysql client) to add
// the following columns to `users`: `status`, `mobile`, `profile_image`.
// We avoid runtime ALTERs to keep migrations explicit and auditable.

/* ==================== AUTHENTICATION FUNCTIONS ==================== */

/**
 * isLoggedIn() - Check if user is authenticated
 * 
 * LOGIC: Returns true if 'user_id' exists in the PHP session.
 * The user_id is set in login.php after successful password verification.
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

/**
 * hasRole($role) - Check if logged-in user has a specific role
 * 
 * PARAMETERS: $role = 'student', 'teacher', or 'admin'
 * LOGIC: Compares the session's user_role with the given role string.
 * Returns false if user is not logged in.
 */
function hasRole($role)
{
    if (!isLoggedIn()) return false;
    return $_SESSION['user_role'] === $role;
}

/** Shortcut: Check if current user is admin */
function isAdmin()
{
    return hasRole('admin');
}

/** Shortcut: Check if current user is teacher */
function isTeacher()
{
    return hasRole('teacher');
}

/** Shortcut: Check if current user is student */
function isStudent()
{
    return hasRole('student');
}

/* ==================== NAVIGATION HELPERS ==================== */

/**
 * getBasePath() - Calculate the relative path for links
 * 
 * LOGIC:
 *   Pages in subdirectories (admin/, auth/) need '../' prefix
 *   to link back to root-level pages. This function detects the
 *   current directory and returns the correct prefix.
 * 
 * EXAMPLE:
 *   From admin/dashboard.php → returns '../'
 *   From dashboard.php → returns ''
 */
function getBasePath()
{
    $scriptPath = $_SERVER['PHP_SELF'];
    if (
        strpos($scriptPath, '/admin/') !== false ||
        strpos($scriptPath, '/auth/') !== false ||
        strpos($scriptPath, '/includes/') !== false
    ) {
        return '../';
    }
    return '';
}

/**
 * redirect($url) - Redirect user to another page
 * 
 * LOGIC: Sends HTTP Location header and stops script execution.
 * Always call exit() after header() to prevent code below from running.
 */
function redirect($url)
{
    header("Location: $url");
    exit();
}

/* ==================== ROUTE PROTECTION ==================== */

/**
 * requireLogin() - Protect a page from unauthenticated access
 * 
 * USAGE: Call at the top of any page that requires login.
 * LOGIC: If not logged in, redirects to auth/login.php
 */
function requireLogin()
{
    if (!isLoggedIn()) {
        $basePath = getBasePath();
        redirect($basePath . 'auth/login.php');
    }
}

/**
 * requireAdmin() - Protect admin-only pages
 * 
 * LOGIC: First checks login, then checks if role is 'admin'.
 * Non-admin users are redirected to dashboard with error message.
 */
function requireAdmin()
{
    requireLogin();
    if (!isAdmin()) {
        $basePath = getBasePath();
        if (isTeacher()) {
            redirect($basePath . 'dashboard.php?error=unauthorized');
        } else {
            redirect($basePath . 'quiz.php?error=unauthorized');
        }
    }
}

/**
 * requireTeacher() - Protect teacher/admin-only pages
 * 
 * LOGIC: Allows both teachers and admins to access.
 * Students are redirected to dashboard with error message.
 */
function requireTeacher()
{
    requireLogin();
    if (!isTeacher() && !isAdmin()) {
        $basePath = getBasePath();
        // Redirect students to the quiz page if they try to access teacher-only pages
        redirect($basePath . 'quiz.php?error=unauthorized');
    }
}

/* ==================== INPUT SANITIZATION ==================== */

/**
 * sanitize($input) - Clean user input to prevent XSS attacks
 * 
 * LOGIC:
 *   1. trim() - Removes whitespace from start/end
 *   2. strip_tags() - Removes HTML/PHP tags
 *   3. real_escape_string() - Escapes special MySQL characters
 *   4. htmlspecialchars() - Converts special chars to HTML entities
 * 
 * USAGE: sanitize($_POST['name']) before using in queries or display
 */
function sanitize($input)
{
    global $conn;
    return htmlspecialchars(strip_tags(trim($conn->real_escape_string($input))));
}

/* ==================== UI HELPERS ==================== */

/**
 * showAlert($message, $type) - Display a colored alert box
 * 
 * PARAMETERS:
 *   $message = Text to display
 *   $type = 'success' (green), 'error' (red), 'warning' (orange), 'info' (blue)
 * 
 * RETURNS: HTML string for the alert div
 * USAGE: <?= showAlert('Saved!', 'success') ?>
 */
function showAlert($message, $type = 'info')
{
    $bgColor = [
        'success' => '#10b981',
        'error' => '#ef4444',
        'warning' => '#f59e0b',
        'info' => '#0099ff'
    ];
    $bg = $bgColor[$type] ?? $bgColor['info'];
    return "<div class='alert alert-$type' style='background: {$bg}20; color: $bg; padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; border: 1px solid {$bg}40; font-weight: 500;'>$message</div>";
}

/* ==================== USER DATA FUNCTIONS ==================== */

/**
 * getCurrentUser() - Get full user record from database
 * 
 * LOGIC: Uses the session's user_id to query the users table.
 * RETURNS: Associative array with all user fields, or null if not logged in.
 */
function getCurrentUser()
{
    if (!isLoggedIn()) return null;
    global $conn;
    $userId = $_SESSION['user_id'];
    $result = $conn->query("SELECT * FROM users WHERE id = $userId");
    return $result->fetch_assoc();
}

/**
 * formatDate($date) - Convert MySQL date to readable format
 * 
 * INPUT:  '2025-12-28 14:30:00'
 * OUTPUT: 'Dec 28, 2025'
 */
function formatDate($date)
{
    return date('M d, Y', strtotime($date));
}

/**
 * getUserStats($userId) - Get quiz statistics for a user
 * 
 * RETURNS array with:
 *   - total_quizzes: Number of quizzes taken
 *   - avg_score: Average percentage score
 *   - total_notes: Notes available (student) or uploaded (teacher)
 *   - subjects_studied: Unique subjects attempted in quizzes
 * 
 * LOGIC:
 *   1. Counts quiz_results rows for this user
 *   2. Calculates average percentage
 *   3. For teachers: counts notes they uploaded
 *      For students: counts total available notes
 *   4. Counts distinct subjects in quiz_results
 */
function getUserStats($userId)
{
    global $conn;

    $stats = [
        'total_quizzes' => 0,
        'avg_score' => 0,
        'total_notes' => 0,
        'subjects_studied' => 0
    ];

    /* Count quizzes taken and calculate average score */
    $result = $conn->query("SELECT COUNT(*) as count, AVG(score) as avg FROM quiz_sessions WHERE student_id = $userId AND status='completed'");
    $row = $result->fetch_assoc();
    $stats['total_quizzes'] = $row['count'];
    $stats['avg_score'] = round($row['avg'] ?? 0, 1);

    /* Count notes: uploaded (for teachers) or available (for students) */
    $role = $_SESSION['user_role'];
    if ($role === 'teacher' || $role === 'admin') {
        $result = $conn->query("SELECT COUNT(*) as count FROM notes WHERE created_by = $userId AND is_deleted = 0");
    } else {
        $result = $conn->query("SELECT COUNT(*) as count FROM notes WHERE is_deleted = 0");
    }
    $stats['total_notes'] = $result->fetch_assoc()['count'];

    /* Count unique subjects studied through quizzes */
    $result = $conn->query("SELECT COUNT(DISTINCT subject_id) as count FROM quiz_sessions WHERE student_id = $userId AND status='completed'");
    $stats['subjects_studied'] = $result->fetch_assoc()['count'];

    return $stats;
}
