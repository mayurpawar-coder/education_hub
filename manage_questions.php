<?php
/**
 * ============================================================
 * Education Hub - Manage Questions (manage_questions.php)
 * ============================================================
 * 
 * PURPOSE:
 *   Teachers add new multiple-choice quiz questions per subject.
 *   Shows a form to add questions and lists recently added ones.
 * 
 * ACCESS: Teachers and Admins only (requireTeacher)
 * 
 * HOW IT WORKS:
 *   1. Gets all subjects for the dropdown
 *   2. On form POST:
 *      a. Validates all fields (question, 4 options, correct answer)
 *      b. INSERTs into questions table with subject_id and created_by
 *      c. Shows success/error message
 *   3. Queries recent questions by this teacher for display
 * 
 * QUESTION FORMAT:
 *   - question_text: The question string
 *   - option_a/b/c/d: Four answer choices
 *   - correct_answer: 'A', 'B', 'C', or 'D'
 *   - difficulty: easy, medium, or hard
 *   - created_by: User ID of the teacher who added it
 * 
 * CSS: assets/css/style.css (card, form-group, table)
 * ============================================================
 */

require_once 'config/functions.php';
requireTeacher();

$pageTitle = 'Manage Questions';
$success = '';
$error = '';

/* --- Handle Bulk Upload --- */
if (isset($_POST['bulk_upload'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $fileName = $_FILES['excel_file']['name'];
        $fileTmp = $_FILES['excel_file']['tmp_name'];
        $fileType = $_FILES['excel_file']['type'];

        // Check file type (allow CSV for now, Excel would need library)
        $allowedTypes = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        if (!in_array($fileType, $allowedTypes) && !str_ends_with(strtolower($fileName), '.csv')) {
            $error = 'Please upload a CSV or Excel file';
        } else {
            // Process CSV file
            $handle = fopen($fileTmp, 'r');
            if ($handle !== FALSE) {
                $header = fgetcsv($handle); // Skip header row
                $successCount = 0;
                $errorCount = 0;
                $errors = [];

                while (($data = fgetcsv($handle)) !== FALSE) {
                    if (count($data) >= 8) {
                        $questionText = trim($data[0]);
                        $optionA = trim($data[1]);
                        $optionB = trim($data[2]);
                        $optionC = trim($data[3]);
                        $optionD = trim($data[4]);
                        $correctAnswer = strtoupper(trim($data[5]));
                        $subjectId = (int)trim($data[6]);
                        $year = trim($data[7]);
                        $semester = isset($data[8]) ? (int)trim($data[8]) : 1;
                        $difficulty = isset($data[9]) ? trim($data[9]) : 'medium';

                        // Validate data
                        if (empty($questionText) || empty($optionA) || empty($optionB) || 
                            empty($optionC) || empty($optionD) || !in_array($correctAnswer, ['A', 'B', 'C', 'D'])) {
                            $errorCount++;
                            continue;
                        }

                        // Insert question
                        $stmt = $conn->prepare("INSERT INTO questions (subject_id, question_text, option_a, option_b, option_c, option_d, correct_answer, difficulty, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("isssssssi", $subjectId, $questionText, $optionA, $optionB, $optionC, $optionD, $correctAnswer, $difficulty, $createdBy);
                        
                        if ($stmt->execute()) {
                            $successCount++;
                        } else {
                            $errorCount++;
                        }
                        $stmt->close();
                    } else {
                        $errorCount++;
                    }
                }
                fclose($handle);

                if ($successCount > 0) {
                    $success = "Bulk upload completed! Successfully added $successCount questions.";
                    if ($errorCount > 0) {
                        $success .= " $errorCount questions failed to import.";
                    }
                } else {
                    $error = "No questions were successfully imported. Please check your file format.";
                }
            } else {
                $error = "Failed to read the uploaded file";
            }
        }
    } else {
        $error = "Please select a file to upload";
    }
}

/* --- Handle Template Download --- */
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="question_template.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'subject_id', 'year', 'semester', 'difficulty']);
    fputcsv($output, ['What is 2+2?', '3', '4', '5', '6', 'B', '1', 'FY', '1', 'easy']);
    fputcsv($output, ['What does HTML stand for?', 'HyperText Markup Language', 'High Tech Modern Language', 'Home Tool Markup Language', 'Hyper Transfer Markup Language', 'A', '9', 'SY', '4', 'medium']);
    fclose($output);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $questionText = sanitize($_POST['question_text'] ?? '');
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $optionA = sanitize($_POST['option_a'] ?? '');
    $optionB = sanitize($_POST['option_b'] ?? '');
    $optionC = sanitize($_POST['option_c'] ?? '');
    $optionD = sanitize($_POST['option_d'] ?? '');
    $correctAnswer = strtoupper(sanitize($_POST['correct_answer'] ?? ''));
    $difficulty = sanitize($_POST['difficulty'] ?? 'medium');
    $createdBy = $_SESSION['user_id'];

    /* Validate all fields are filled */
    if (empty($questionText) || empty($subjectId) || empty($optionA) || empty($optionB) || empty($optionC) || empty($optionD) || empty($correctAnswer)) {
        $error = 'Please fill in all required fields';
    } elseif (!in_array($correctAnswer, ['A', 'B', 'C', 'D'])) {
        $error = 'Invalid correct answer';
    } else {
        /* INSERT question into database */
        $stmt = $conn->prepare("INSERT INTO questions (subject_id, question_text, option_a, option_b, option_c, option_d, correct_answer, difficulty, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssssi", $subjectId, $questionText, $optionA, $optionB, $optionC, $optionD, $correctAnswer, $difficulty, $createdBy);

        if ($stmt->execute()) {
            $success = 'Question added successfully!';
        } else {
            $error = 'Failed to add question';
        }
        $stmt->close();
    }
}

/* Get recent questions by this teacher */
$userId = $_SESSION['user_id'];
$myQuestions = $conn->query("
    SELECT q.*, s.name as subject_name 
    FROM questions q 
    JOIN subjects s ON q.subject_id = s.id 
    WHERE q.created_by = $userId 
    ORDER BY q.created_at DESC 
    LIMIT 20
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Questions - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'includes/header.php'; ?>

            <section>
                <!-- === Bulk Upload Section === -->
                <div class="card" style="margin-bottom: 32px;">
                    <h3 style="margin-bottom: 24px;">📊 Bulk Question Upload</h3>

                    <div style="margin-bottom: 20px;">
                        <p style="color: var(--text-muted); margin-bottom: 16px;">
                            Upload multiple questions at once using Excel or CSV format. Download the template to see the required format.
                        </p>

                        <a href="?download_template=1" class="btn btn-secondary" style="margin-right: 12px;">
                            📥 Download Template
                        </a>

                        <div style="display: inline-block; background: var(--surface-light); padding: 12px; border-radius: 8px; margin-top: 12px;">
                            <strong>Required Columns:</strong> question_text, option_a, option_b, option_c, option_d, correct_option, subject_id, year, semester
                        </div>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="excel_file">📎 Select Excel/CSV File *</label>
                            <input type="file" id="excel_file" name="excel_file" accept=".csv,.xlsx,.xls" required>
                            <small style="color: var(--text-muted);">
                                Supported formats: CSV, Excel (.xlsx, .xls) - Max 5MB
                            </small>
                        </div>

                        <button type="submit" name="bulk_upload" class="btn btn-primary">
                            🚀 Upload Questions
                        </button>
                    </form>
                </div>

                <!-- === Add Question Form === -->
                <div class="card" style="margin-bottom: 32px;">
                    <h3 style="margin-bottom: 24px;">➕ Add New Question</h3>

                    <?php if ($error): ?><?= showAlert($error, 'error') ?><?php endif; ?>
                    <?php if ($success): ?><?= showAlert($success, 'success') ?><?php endif; ?>

                    <form method="POST">
                        <!-- Subject and Difficulty selection (side by side) -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label for="subject_id">📖 Subject *</label>
                                <select id="subject_id" name="subject_id" required>
                                    <option value="">Select Subject</option>
                                    <?php 
                                    $subjects->data_seek(0);
                                    while ($subject = $subjects->fetch_assoc()): 
                                    ?>
                                    <option value="<?= $subject['id'] ?>"><?= htmlspecialchars($subject['name']) ?> (<?= $subject['year'] ?> Sem <?= $subject['semester'] ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="difficulty">📊 Difficulty</label>
                                <select id="difficulty" name="difficulty">
                                    <option value="easy">Easy</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="hard">Hard</option>
                                </select>
                            </div>
                        </div>

                        <!-- Question text -->
                        <div class="form-group">
                            <label for="question_text">❓ Question *</label>
                            <textarea id="question_text" name="question_text" rows="3" placeholder="Enter the question..." required></textarea>
                        </div>

                        <!-- 4 Options (2x2 grid) -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label for="option_a">🅰️ Option A *</label>
                                <input type="text" id="option_a" name="option_a" placeholder="Option A" required>
                            </div>
                            <div class="form-group">
                                <label for="option_b">🅱️ Option B *</label>
                                <input type="text" id="option_b" name="option_b" placeholder="Option B" required>
                            </div>
                            <div class="form-group">
                                <label for="option_c">©️ Option C *</label>
                                <input type="text" id="option_c" name="option_c" placeholder="Option C" required>
                            </div>
                            <div class="form-group">
                                <label for="option_d">🅳 Option D *</label>
                                <input type="text" id="option_d" name="option_d" placeholder="Option D" required>
                            </div>
                        </div>

                        <!-- Correct answer selector -->
                        <div class="form-group">
                            <label for="correct_answer">✅ Correct Answer *</label>
                            <select id="correct_answer" name="correct_answer" required>
                                <option value="">Select Correct Answer</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">➕ Add Question</button>
                    </form>
                </div>

                <!-- === My Recent Questions Table === -->
                <div class="card">
                    <h3 style="margin-bottom: 24px;">📋 My Recent Questions</h3>

                    <?php if ($myQuestions->num_rows > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Question</th>
                                    <th>Subject</th>
                                    <th>Difficulty</th>
                                    <th>Correct</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($q = $myQuestions->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars(substr($q['question_text'], 0, 60)) ?>...</td>
                                    <td><?= htmlspecialchars($q['subject_name']) ?></td>
                                    <td style="text-transform: capitalize;"><?= $q['difficulty'] ?></td>
                                    <td><strong style="color: var(--success);"><?= $q['correct_answer'] ?></strong></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p style="text-align: center; color: var(--text-muted);">No questions added yet.</p>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
