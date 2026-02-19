<?php
/**
 * ============================================================
 * Education Hub - Logout Handler (auth/logout.php)
 * ============================================================
 * 
 * PURPOSE:
 *   Ends the user's session and redirects to login page.
 * 
 * HOW IT WORKS:
 *   1. session_start() - Resume current session
 *   2. session_destroy() - Delete all session data (user_id, user_role, etc.)
 *   3. Redirect to login.php
 * 
 * USAGE: Called when user clicks "Logout" in sidebar
 * ============================================================
 */

session_start();
session_destroy();
header('Location: login.php');
exit();
?>
