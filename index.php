<?php
/**
 * ============================================================
 * Education Hub - Entry Point (index.php)
 * ============================================================
 * 
 * PURPOSE:
 *   This is the first page loaded when user visits the application.
 *   It checks login status and redirects accordingly.
 * 
 * LOGIC:
 *   1. If user IS logged in:
 *      - Admin → redirect to admin/dashboard.php
 *      - Student/Teacher → redirect to dashboard.php
 *   2. If user is NOT logged in:
 *      - Redirect to auth/login.php
 * 
 * WHY: This ensures users always land on the appropriate page
 *      without seeing a blank index page.
 * ============================================================
 */

require_once 'config/functions.php';

if (isLoggedIn()) {
    /* Admin goes to admin dashboard, others go to student/teacher dashboard */
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('dashboard.php');
    }
} else {
    /* Not logged in → send to login page */
    redirect('auth/login.php');
}
?>
