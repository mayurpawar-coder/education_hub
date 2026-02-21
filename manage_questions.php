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

/* Get all subjects for the dropdown */
$subjects = $conn->query("SELECT * FROM subjects ORDER BY year, semester, name");

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
                        $createdBy = $_SESSION['user_id'];

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
                    <div class="card-header">
                        <h3 class="card-title">📊 Bulk Question Upload</h3>
                    </div>

                    <div style="padding: 24px; padding-top: 0;">
                        <div style="margin-bottom: 20px;">
                            <p style="color: var(--text-muted); margin-bottom: 16px;">
                                Upload multiple questions at once using Excel or CSV format. Download the template to see the required format.
                            </p>

                            <a href="?download_template=1" class="btn btn-secondary" style="margin-right: 12px;">
                                📥 Download Template
                            </a>

                            <div style="display: inline-block; background: var(--surface-light); padding: 12px; border-radius: var(--radius-sm); margin-top: 12px; border: 1px solid var(--border);">
                                <strong style="color: var(--text);">Required Columns:</strong> question_text, option_a, option_b, option_c, option_d, correct_option, subject_id, year, semester, difficulty
                            </div>
                        </div>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label for="excel_file" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text);">📎 Select Excel/CSV File *</label>
                                <input type="file" id="excel_file" name="excel_file" accept=".csv,.xlsx,.xls" required style="width: 100%; padding: 12px 16px; border: 2px solid var(--border); border-radius: var(--radius-md); background: var(--surface-light); color: var(--text); font: 14px/1.5 inherit; transition: var(--transition);">
                                <small style="color: var(--text-muted); margin-top: 4px; display: block;">
                                    Supported formats: CSV, Excel (.xlsx, .xls) - Max 5MB
                                </small>
                            </div>

                            <button type="submit" name="bulk_upload" class="btn btn-primary">
                                🚀 Upload Questions
                            </button>
                        </form>
                    </div>
                </div>

                <!-- === Add Question Form === -->
                <div class="card" style="margin-bottom: 32px;">
                    <div class="card-header">
                        <h3 class="card-title">➕ Add New Question</h3>
                    </div>

                    <?php if ($error): ?><?= showAlert($error, 'error') ?><?php endif; ?>
                    <?php if ($success): ?><?= showAlert($success, 'success') ?><?php endif; ?>

                    <form method="POST" style="padding: 24px; padding-top: 0;">
                        <!-- Subject and Difficulty selection (side by side) -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
                            <div class="form-group">
                                <label for="subject_id" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text);">📖 Subject *</label>
                                <select id="subject_id" name="subject_id" required style="width: 100%; padding: 12px 16px; border: 2px solid var(--border); border-radius: var(--radius-md); background: var(--surface-light); color: var(--text); font: 14px/1.5 inherit; transition: var(--transition);">
                                    <option value="">Select Subject</option>
                                    <?php
                                    $subjects->data_seek(0);
                                    while ($subject = $subjects->fetch_assoc()):
                                    ?>
                                    <option value="<?= $subject['id'] ?>">
                                        <?= htmlspecialchars($subject['name']) ?> 
                                        (<?php 
                                            switch($subject['year']) {
                                                case 'FY': echo 'First Year'; break;
                                                case 'SY': echo 'Second Year'; break;
                                                case 'TY': echo 'Third Year'; break;
                                                default: echo $subject['year']; break;
                                            }
                                        ?> Sem <?= $subject['semester'] ?>)
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="difficulty" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text);">📊 Difficulty</label>
                                <select id="difficulty" name="difficulty" style="width: 100%; padding: 12px 16px; border: 2px solid var(--border); border-radius: var(--radius-md); background: var(--surface-light); color: var(--text); font: 14px/1.5 inherit; transition: var(--transition);">
                                    <option value="easy">Easy</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="hard">Hard</option>
                                </select>
                            </div>
                        </div>

                        <!-- Question text -->
                        <div class="form-group" style="margin-bottom: 24px;">
                            <label for="question_text" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text);">❓ Question *</label>
                            <textarea id="question_text" name="question_text" rows="3" placeholder="Enter the question..." required style="width: 100%; padding: 12px 16px; border: 2px solid var(--border); border-radius: var(--radius-md); background: var(--surface-light); color: var(--text); font: 14px/1.5 inherit; transition: var(--transition); resize: vertical; min-height: 80px;"></textarea>
                        </div>

                        <!-- 4 Options (2x2 grid) -->
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 24px;">
                            <div class="form-group">
                                <label for="option_a" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text);">🅰️ Option A *</label>
                                <input type="text" id="option_a" name="option_a" placeholder="Option A" required style="width: 100%; padding: 12px 16px; border: 2px solid var(--border); border-radius: var(--radius-md); background: var(--surface-light); color: var(--text); font: 14px/1.5 inherit; transition: var(--transition);">
                            </div>
                            <div class="form-group">
                                <label for="option_b" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text);">🅱️ Option B *</label>
                                <input type="text" id="option_b" name="option_b" placeholder="Option B" required style="width: 100%; padding: 12px 16px; border: 2px solid var(--border); border-radius: var(--radius-md); background: var(--surface-light); color: var(--text); font: 14px/1.5 inherit; transition: var(--transition);">
                            </div>
                            <div class="form-group">
                                <label for="option_c" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text);">©️ Option C *</label>
                                <input type="text" id="option_c" name="option_c" placeholder="Option C" required style="width: 100%; padding: 12px 16px; border: 2px solid var(--border); border-radius: var(--radius-md); background: var(--surface-light); color: var(--text); font: 14px/1.5 inherit; transition: var(--transition);">
                            </div>
                            <div class="form-group">
                                <label for="option_d" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text);">🅳 Option D *</label>
                                <input type="text" id="option_d" name="option_d" placeholder="Option D" required style="width: 100%; padding: 12px 16px; border: 2px solid var(--border); border-radius: var(--radius-md); background: var(--surface-light); color: var(--text); font: 14px/1.5 inherit; transition: var(--transition);">
                            </div>
                        </div>

                        <!-- Correct answer selector -->
                        <div class="form-group" style="margin-bottom: 32px;">
                            <label for="correct_answer" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text);">✅ Correct Answer *</label>
                            <select id="correct_answer" name="correct_answer" required style="width: 100%; padding: 12px 16px; border: 2px solid var(--border); border-radius: var(--radius-md); background: var(--surface-light); color: var(--text); font: 14px/1.5 inherit; transition: var(--transition);">
                                <option value="">Select Correct Answer</option>
                                <option value="A">A - Option A</option>
                                <option value="B">B - Option B</option>
                                <option value="C">C - Option C</option>
                                <option value="D">D - Option D</option>
                            </select>
                        </div>

                        <!-- Submit Button with blue background -->
                        <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border);">
                            <button type="submit" class="btn btn-primary btn-block btn-lg" style="font-weight: 700; background: #1a56db !important;">
                                ➕ Add Question
                            </button>
                        </div>
                    </form>
                </div>

                <!-- === My Recent Questions Table === -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">📋 My Recent Questions</h3>
                    </div>

                    <div style="padding: 24px; padding-top: 0;">
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
                                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars(substr($q['question_text'], 0, 60)) ?>...</td>
                                        <td>
                                            <span style="background: var(--primary-lighter); color: var(--primary); padding: 4px 8px; border-radius: var(--radius-sm); font-size: 12px; font-weight: 600;">
                                                <?= htmlspecialchars($q['subject_name']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="text-transform: capitalize; padding: 4px 8px; border-radius: var(--radius-sm); font-size: 12px; font-weight: 600;
                                                <?php
                                                switch($q['difficulty']) {
                                                    case 'easy': echo 'background: var(--success-light); color: var(--success);'; break;
                                                    case 'medium': echo 'background: var(--warning-light); color: var(--warning);'; break;
                                                    case 'hard': echo 'background: var(--danger-light); color: var(--danger);'; break;
                                                }
                                                ?>">
                                                <?= $q['difficulty'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong style="color: var(--success); font-size: 16px; background: var(--success-light); padding: 6px 12px; border-radius: var(--radius-md); display: inline-block; min-width: 32px; text-align: center;">
                                                <?= $q['correct_answer'] ?>
                                            </strong>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                            <div style="font-size: 48px; margin-bottom: 16px;">❓</div>
                            <h4 style="margin-bottom: 8px; color: var(--text);">No questions added yet</h4>
                            <p style="margin-bottom: 20px;">Start by adding your first question above</p>
                            <a href="#add-question" class="btn btn-primary">➕ Add Your First Question</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
