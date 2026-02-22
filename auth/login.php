<?php

/**
 * ============================================================
 * Education Hub - Login Page (auth/login.php)
 * ============================================================
 * 
 * PURPOSE:
 *   Authenticates users with email and password.
 *   Sets session variables on successful login.
 * 
 * HOW IT WORKS:
 *   1. If already logged in → redirect to dashboard
 *   2. On form POST:
 *      a. Sanitize email input
 *      b. Query users table for matching email (prepared statement)
 *      c. Verify password using password_verify() (bcrypt)
 *      d. If valid → set session variables → redirect by role
 *      e. If invalid → show error message
 *   3. Displays login form with demo credentials for testing
 * 
 * SECURITY:
 *   - Prepared statement prevents SQL injection
 *   - password_verify() compares against bcrypt hash
 *   - Session stores user_id, user_name, user_email, user_role
 * 
 * CSS: Uses ../assets/css/style.css (auth-page, auth-card classes)
 * ============================================================
 */

require_once '../config/functions.php';

$error = '';

/* --- If already logged in, skip login page --- */
if (isLoggedIn()) {
    redirect('../dashboard.php');
}

/* --- Handle POST: Process login form submission --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    /* Validate: both fields must be filled */
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        /* Query database for user with this email (prepared statement for security) */
        $stmt = $conn->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);  // "s" = string parameter
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            /* Verify entered password against stored bcrypt hash */
            if (password_verify($password, $user['password'])) {
                /* Check if teacher is approved */
                if ($user['role'] === 'teacher' && ($user['status'] ?? '') === 'pending') {
                    $error = 'Your teacher account is pending admin approval. Please wait for approval.';
                } else {
                    /* SUCCESS: Set session variables for authentication */
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];

                    /* Redirect based on role: admin → admin dashboard, others → student/teacher dashboard */
                    if ($user['role'] === 'admin') {
                        redirect('../admin/dashboard.php');
                    } else {
                        redirect('../dashboard.php');
                    }
                }
            } else {
                $error = 'Invalid email or password';
            }
        } else {
            $error = 'Invalid email or password';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Education Hub</title>
    <!-- Main stylesheet contains auth-page and auth-card styles -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="auth-page">
    <!-- Centered login container -->
    <div class="auth-container">
        <div class="auth-card">
            <!-- Logo and title -->
            <div class="auth-header">
                <div class="logo">
                    <span class="logo-icon">📚</span>
                    <h1>Education Hub</h1>
                </div>
                <p>Sign in to your account</p>
            </div>

            <!-- Error message (shown only if login fails) -->
            <?php if ($error): ?>
                <?= showAlert($error, 'error') ?>
            <?php endif; ?>

            <!-- Login form: sends POST to this same page -->
            <form method="POST" class="auth-form">
                <!-- Email input field -->
                <div class="form-group">
                    <label for="email">📧 Email Address</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <!-- Password input field -->
                <div class="form-group">
                    <label for="password">🔒 Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required
                        value="">
                </div>

                <!-- Submit button with gradient background -->
                <button type="submit" class="btn btn-primary btn-block">🔑 Sign In</button>
            </form>

            <!-- Link to registration page -->
            <div class="auth-footer">
                <p>Don't have an account? <a href="register.php">Sign up</a></p>
            </div>

            <!-- Demo credentials for testing all 3 roles -->

        </div>
    </div>
</body>

</html>