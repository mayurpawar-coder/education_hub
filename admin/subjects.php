<?php
/**
 * Education Hub - Manage Subjects (Admin Only)
 */

require_once '../config/functions.php';
requireAdmin();

$pageTitle = 'Manage Subjects';
$success = '';
$error = '';

// Handle subject deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    $conn->query("DELETE FROM subjects WHERE id = $deleteId");
    $success = 'Subject deleted successfully';
}

// Handle add subject
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

// Get all subjects with stats
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
                <?php if ($error): ?>
                    <?= showAlert($error, 'error') ?>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <?= showAlert($success, 'success') ?>
                <?php endif; ?>
                
                <!-- Add Subject Form -->
                <div class="card" style="margin-bottom: 32px;">
                    <h3 style="margin-bottom: 24px;">Add New Subject</h3>
                    
                    <form method="POST">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto auto; gap: 20px; align-items: end;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="name">Subject Name *</label>
                                <input type="text" id="name" name="name" placeholder="e.g., Biology" required>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="description">Description</label>
                                <input type="text" id="description" name="description" placeholder="Brief description">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="year">Year</label>
                                <select id="year" name="year">
                                    <option value="FY">First Year (FY)</option>
                                    <option value="SY">Second Year (SY)</option>
                                    <option value="TY">Third Year (TY)</option>
                                </select>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="semester">Semester</label>
                                <select id="semester" name="semester">
                                    <option value="1">Sem 1</option>
                                    <option value="2">Sem 2</option>
                                    <option value="3">Sem 3</option>
                                    <option value="4">Sem 4</option>
                                    <option value="5">Sem 5</option>
                                    <option value="6">Sem 6</option>
                                </select>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="color">Color</label>
                                <input type="color" id="color" name="color" value="#0099ff" style="height: 42px; width: 60px;">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">➕ Add Subject</button>
                        </div>
                    </form>
                </div>
                
                <!-- Subjects List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">All Subjects (<?= $subjects->num_rows ?>)</h3>
                    </div>
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Year</th>
                                    <th>Semester</th>
                                    <th>Notes</th>
                                    <th>Questions</th>
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
                                           class="btn btn-sm btn-danger">Delete</a>
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
