<?php
/**
 * ============================================================
 * Education Hub - Registration Page (auth/register.php)
 * ============================================================
 * 
 * PURPOSE:
 *   Allows new users to create an account with name, email,
 *   password, and role selection (Student or Teacher).
 * 
 * HOW IT WORKS:
 *   1. If already logged in → redirect to dashboard
 *   2. On form POST:
 *      a. Validate all required fields
 *      b. Validate email format using filter_var()
 *      c. Check password length (minimum 6 characters)
 *      d. Confirm password matches confirmation field
 *      e. Check if email already exists in database
 *      f. Hash password using password_hash() with bcrypt
 *      g. INSERT new user into users table
 *      h. Show success → redirect to login after 2 seconds
 * 
 * SECURITY:
 *   - password_hash(PASSWORD_DEFAULT) uses bcrypt algorithm
 *   - Prepared statement prevents SQL injection on INSERT
 *   - Role restricted to 'student' or 'teacher' (no self-admin)
 * 
 * CSS: ../assets/css/style.css (auth-page, auth-card, form-group)
 * ============================================================
 */

require_once '../config/functions.php';

$error = '';
$success = '';

/* Already logged in → skip registration */
if (isLoggedIn()) {
    redirect('../dashboard.php');
}

/* --- Handle POST: Process registration form --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = sanitize($_POST['role'] ?? 'student');
    $mobile = sanitize($_POST['mobile'] ?? null);

    /* --- Validation Chain --- */
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (!in_array($role, ['student', 'teacher'])) {
        /* Only student/teacher can self-register; admin is created manually */
        $error = 'Invalid role selected';
    } else {
        /* Check if email already exists */
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $error = 'Email already registered';
        } else {
                /* Hash password with bcrypt (secure one-way hash) */
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                /* Determine initial status: teachers -> pending, students -> approved */
                $status = $role === 'teacher' ? 'pending' : 'approved';

                /* Handle optional profile image upload */
                $profileImagePath = null;
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $allowed = ['image/jpeg', 'image/png'];
                    $fileType = $_FILES['profile_image']['type'];
                    $fileSize = $_FILES['profile_image']['size'];
                    if (!in_array($fileType, $allowed)) {
                        $error = 'Profile image must be JPG or PNG';
                    } elseif ($fileSize > 2 * 1024 * 1024) { // 2MB limit
                        $error = 'Profile image must be smaller than 2MB';
                    } else {
                        $uploadsDir = __DIR__ . '/../uploads/profile/';
                        if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
                        $ext = $fileType === 'image/png' ? '.png' : '.jpg';
                        $destName = time() . '_' . preg_replace('/[^a-zA-Z0-9-_\.]/', '', basename($_FILES['profile_image']['name']));
                        $destPath = $uploadsDir . $destName;
                        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $destPath)) {
                            // Store relative path for DB
                            $profileImagePath = 'uploads/profile/' . $destName;
                        }
                    }
                }

                /* Insert new user into database (include mobile, status, profile_image)
                   Make sure the migration has been applied to add these columns */
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status, mobile, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssss", $name, $email, $hashedPassword, $role, $status, $mobile, $profileImagePath);

            if ($stmt->execute()) {
                if ($role === 'teacher') {
                    $success = 'Registration successful! Your teacher account is pending admin approval. You will be notified once approved. Redirecting to login...';
                } else {
                    $success = 'Registration successful! Redirecting to login...';
                }
                /* Auto-redirect to login page after 3 seconds */
                header("refresh:3;url=login.php");
            } else {
                $error = 'Registration failed. Please try again.';
            }
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
    <title>Register - Education Hub</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <!-- Logo and title -->
            <div class="auth-header">
                <div class="logo">
                    <span class="logo-icon">📚</span>
                    <h1>Education Hub</h1>
                </div>
                <p>Create your account</p>
            </div>

            <!-- Error/Success alerts -->
            <?php if ($error): ?>
                <?= showAlert($error, 'error') ?>
            <?php endif; ?>
            <?php if ($success): ?>
                <?= showAlert($success, 'success') ?>
            <?php endif; ?>

            <!-- Registration form -->
            <form method="POST" enctype="multipart/form-data" class="auth-form">
                <!-- Full Name -->
                <div class="form-group">
                    <label for="name">👤 Full Name</label>
                    <input type="text" id="name" name="name" placeholder="Enter your full name" required
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label for="email">📧 Email Address</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <!-- Mobile -->
                <div class="form-group">
                    <label for="mobile">📱 Mobile Number</label>
                    <input type="text" id="mobile" name="mobile" placeholder="e.g. +919876543210"
                           value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>">
                </div>

                <!-- Profile Image -->
                <div class="form-group">
                    <label for="profile_image">🖼️ Profile Image (optional)</label>
                    <input type="file" id="profile_image" name="profile_image" accept="image/png, image/jpeg">
                </div>

                <!-- Role selector: Student or Teacher only -->
                <div class="form-group">
                    <label for="role">🎓 I am a</label>
                    <select id="role" name="role" required>
                        <option value="student" <?= ($_POST['role'] ?? '') === 'student' ? 'selected' : '' ?>>👨‍🎓 Student</option>
                        <option value="teacher" <?= ($_POST['role'] ?? '') === 'teacher' ? 'selected' : '' ?>>👩‍🏫 Teacher</option>
                    </select>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password">🔒 Password</label>
                    <input type="password" id="password" name="password" placeholder="At least 6 characters" required>
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <label for="confirm_password">🔒 Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">✅ Create Account</button>
            </form>

            <!-- Link to login page -->
            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Sign in</a></p>
            </div>
        </div>
    </div>
</body>
</html>
