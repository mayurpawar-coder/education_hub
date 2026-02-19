<?php
/**
 * ============================================================
 * Education Hub - Manage Subjects (admin/subjects.php)
 * ============================================================
 * 
 * PURPOSE:
 *   Admin can add new subjects and delete existing ones.
 *   Subjects are organized by Year (FY/SY/TY) and Semester (1-6).
 * 
 * HOW IT WORKS:
 *   1. DELETE: ?delete=5 → deletes subject with ID 5 (cascades notes/questions)
 *   2. ADD: POST with name, description, year, semester, color
 *      → INSERTs into subjects table
 *   3. Lists all subjects with notes/questions counts
 * 
 * CASCADE DELETE:
 *   When a subject is deleted, all related notes and questions
 *   are also deleted (ON DELETE CASCADE in SQL schema).
 * 
 * CSS: ../assets/css/style.css (card, form-group, table)
 * ============================================================
 */

require_once '../config/functions.php';
requireAdmin();

$pageTitle = 'Manage Subjects';
$success = '';
$error = '';

/* --- Handle subject deletion --- */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    $conn->query("DELETE FROM subjects WHERE id = $deleteId");
    $success = 'Subject deleted successfully';
}

/* --- Handle add subject form --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $color = sanitize($_POST['color'] ?? '#0099ff');
    $year = sanitize($_POST['year'] ?? 'FY');
    $semester = (int)($_POST['semester'] ?? 1);
    $createdBy = $_SESSION['user_id'];

    if (empty($name)) {
        $error = 'Subject name is required';
    } else {
        $stmt = $conn->prepare("INSERT INTO subjects (name, description, color, year, semester, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssii", $name, $description, $color, $year, $semester, $createdBy);

        if ($stmt->execute()) {
            $success = 'Subject added successfully';
        } else {
            $error = 'Failed to add subject';
        }
    }
}

/* Get all subjects with note/question counts */
$subjects = $conn->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM notes WHERE subject_id = s.id) as notes_count,
           (SELECT COUNT(*) FROM questions WHERE subject_id = s.id) as questions_count
    FROM subjects s 
    ORDER BY s.year, s.semester, s.name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects - Education Hub</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="layout">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include '../includes/header.php'; ?>

            <section>
                <?php if ($error): ?><?= showAlert($error, 'error') ?><?php endif; ?>
                <?php if ($success): ?><?= showAlert($success, 'success') ?><?php endif; ?>

                <!-- === Add Subject Form === -->
                <div class="card" style="margin-bottom: 32px;">
                    <h3 style="margin-bottom: 24px;">➕ Add New Subject</h3>

                    <form method="POST">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto auto; gap: 20px; align-items: end;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="name">📖 Subject Name *</label>
                                <input type="text" id="name" name="name" placeholder="e.g., Data Structures" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="description">📋 Description</label>
                                <input type="text" id="description" name="description" placeholder="Brief description">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="year">📅 Year</label>
                                <select id="year" name="year">
                                    <option value="FY">FY (First Year)</option>
                                    <option value="SY">SY (Second Year)</option>
                                    <option value="TY">TY (Third Year)</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="semester">📚 Semester</label>
                                <select id="semester" name="semester">
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?= $i ?>">Sem <?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="color">🎨 Color</label>
                                <input type="color" id="color" name="color" value="#0099ff" style="height: 42px; width: 60px;">
                            </div>
                            <button type="submit" class="btn btn-primary">➕ Add</button>
                        </div>
                    </form>
                </div>

                <!-- === Subjects List Table === -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">📚 All Subjects (<?= $subjects->num_rows ?>)</h3>
                    </div>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Year</th>
                                    <th>Semester</th>
                                    <th>📝 Notes</th>
                                    <th>❓ Questions</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($subject = $subjects->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div style="width: 12px; height: 12px; border-radius: 50%; background: <?= $subject['color'] ?>;"></div>
                                            <strong><?= htmlspecialchars($subject['name']) ?></strong>
                                        </div>
                                    </td>
                                    <td><?= $subject['year'] ?></td>
                                    <td>Sem <?= $subject['semester'] ?></td>
                                    <td><?= $subject['notes_count'] ?></td>
                                    <td><?= $subject['questions_count'] ?></td>
                                    <td>
                                        <a href="?delete=<?= $subject['id'] ?>" 
                                           onclick="return confirm('Delete this subject? All related notes and questions will also be deleted!')"
                                           class="btn btn-sm btn-danger">🗑️ Delete</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
