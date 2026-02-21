<?php
/**
 * ============================================================
 * Education Hub - Upload Notes (upload_notes.php)
 * ============================================================
 * 
 * PURPOSE:
 *   Teachers upload PDF study notes for students.
 *   Includes Year/Semester/Subject selection and file upload.
 * 
 * ACCESS: Teachers and Admins only (requireTeacher)
 * 
 * HOW IT WORKS:
 *   1. Gets all subjects from database for dropdown
 *   2. On form POST:
 *      a. Validates title and subject are filled
 *      b. If file uploaded: moves to uploads/notes/ directory
 *      c. INSERTs new record into notes table
 *      d. Shows success/error message
 *   3. JavaScript handles:
 *      - Year/Semester button toggles
 *      - Dynamic subject dropdown filtering
 *      - Drag & drop file upload area
 * 
 * FILE UPLOAD:
 *   - Accepted: .pdf, .doc, .docx, .txt, .ppt, .pptx
 *   - Saved to: uploads/notes/ with timestamp prefix
 *   - move_uploaded_file() moves from PHP temp to final location
 * 
 * CSS: assets/css/style.css + assets/css/upload_notes.css
 * ============================================================
 */

require_once 'config/functions.php';
requireTeacher();

$pageTitle = 'Upload Notes';
$success = '';
$error = '';

/* --- Editing support: if ?edit=ID, load existing note to prefill form --- */
$editing = false;
$existingFile = '';
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $row = $conn->query("SELECT * FROM notes WHERE id = $editId");
    if ($row && $row->num_rows) {
        $noteRow = $row->fetch_assoc();
        $editing = true;
        $editId = (int)$noteRow['id'];
        $title = htmlspecialchars($noteRow['title']);
        $content = htmlspecialchars($noteRow['content']);
        $subjectId = (int)$noteRow['subject_id'];
        $existingFile = $noteRow['file_path'];
    } else {
        $error = 'Note not found for editing.';
    }
}

/* Get subjects for the dropdown (all years/semesters) */
$subjects = $conn->query("SELECT * FROM subjects ORDER BY year, semester, name");

/* --- Handle POST: Process file upload form --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $content = sanitize($_POST['content'] ?? '');
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $uploadedBy = $_SESSION['user_id'];

    if (empty($title) || empty($subjectId)) {
        $error = 'Please fill in all required fields';
    } else {
        $filePath = null;

        /* Handle file upload if a file was selected */
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/notes/';
            /* Create directory if it doesn't exist */
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            /* Add timestamp prefix to filename to avoid duplicates */
            $fileName = time() . '_' . basename($_FILES['file']['name']);
            $filePath = $uploadDir . $fileName;

            /* Move file from PHP temp location to our uploads folder */
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $filePath)) {
                $error = 'Failed to upload file';
            }
        }

        if (empty($error)) {
            if (!empty($_POST['edit_id'])) {
                // Update existing note
                $editId = (int)$_POST['edit_id'];
                // If a new file uploaded, remove old file
                if ($filePath && !empty($existingFile) && file_exists($existingFile)) {
                    @unlink($existingFile);
                } elseif (!$filePath && !empty($existingFile)) {
                    // Keep old file if no new file uploaded
                    $filePath = $existingFile;
                }
                $stmt = $conn->prepare("UPDATE notes SET title = ?, content = ?, file_path = ?, subject_id = ? WHERE id = ?");
                $stmt->bind_param("sssii", $title, $content, $filePath, $subjectId, $editId);
                if ($stmt->execute()) {
                    $success = 'Note updated successfully!';
                } else {
                    $error = 'Failed to update note';
                }
                $stmt->close();
            } else {
                /* INSERT note into database */
                $stmt = $conn->prepare("INSERT INTO notes (title, content, file_path, subject_id, uploaded_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssii", $title, $content, $filePath, $subjectId, $uploadedBy);

                if ($stmt->execute()) {
                    $success = 'Note uploaded successfully!';
                } else {
                    $error = 'Failed to save note';
                }
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Notes - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/upload_notes.css">
</head>
<body>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'includes/header.php'; ?>

            <section>
                <!-- Hero banner -->
                <div class="upload-hero">
                    <h1>📤 Upload Notes</h1>
                    <p>Share study materials with students</p>
                </div>

                <div class="upload-container">
                    <!-- Alert messages -->
                    <?php if ($error): ?><?= showAlert($error, 'error') ?><?php endif; ?>
                    <?php if ($success): ?><?= showAlert($success, 'success') ?><?php endif; ?>

                    <!-- Upload form with enctype for file uploads -->
                    <form method="POST" enctype="multipart/form-data" class="upload-form">
                        <div class="form-grid">
                            <!-- Note title input -->
                            <div class="form-group full-width">
                                <label for="title">📝 Title *</label>
                                <input type="text" id="title" name="title" placeholder="Enter note title" required>
                            </div>

                            <!-- Year selector buttons (FY/SY/TY) -->
                            <div class="form-group">
                                <label>📅 Year</label>
                                <div class="year-selector">
                                    <button type="button" class="year-btn active" data-year="FY">FY</button>
                                    <button type="button" class="year-btn" data-year="SY">SY</button>
                                    <button type="button" class="year-btn" data-year="TY">TY</button>
                                </div>
                            </div>

                            <!-- Semester selector buttons (dynamic based on year) -->
                            <div class="form-group">
                                <label>📚 Semester</label>
                                <div class="semester-selector">
                                    <button type="button" class="sem-btn active" data-sem="1">Sem 1</button>
                                    <button type="button" class="sem-btn" data-sem="2">Sem 2</button>
                                </div>
                            </div>

                            <!-- Subject dropdown (filtered by JS based on year/semester) -->
                            <div class="form-group full-width">
                                <label for="subject_id">📖 Subject *</label>
                                <select id="subject_id" name="subject_id" required>
                                    <option value="">Select Subject</option>
                                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                                    <option value="<?= $subject['id'] ?>" 
                                            data-year="<?= $subject['year'] ?>" 
                                            data-sem="<?= $subject['semester'] ?>">
                                        <?= htmlspecialchars($subject['name']) ?> (<?= $subject['year'] ?> - Sem <?= $subject['semester'] ?>)
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <!-- Description/content textarea -->
                            <div class="form-group full-width">
                                <label for="content">📋 Description/Content</label>
                                <textarea id="content" name="content" rows="5" placeholder="Enter note description or content..."></textarea>
                            </div>

                            <!-- File upload area with drag & drop -->
                            <div class="form-group full-width">
                                <label>📁 Upload File (PDF, DOC, etc.)</label>
                                <div class="file-upload-area" id="dropzone">
                                    <div class="upload-icon">📄</div>
                                    <p>Drag & drop your file here or <span class="browse-link">browse</span></p>
                                    <span class="file-types">Supports: PDF, DOC, DOCX, TXT, PPT, PPTX</span>
                                    <input type="file" id="file" name="file" accept=".pdf,.doc,.docx,.txt,.ppt,.pptx" hidden>
                                    <div class="selected-file" id="selectedFile"></div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-upload">📤 Upload Note</button>
                    </form>
                </div>
            </section>
        </main>
    </div>

    <!-- === JavaScript: Year/Semester Filtering + Drag & Drop === -->
    <script>
        /* --- Year/Semester button logic --- */
        /* When user clicks FY/SY/TY, update semester buttons and filter subjects */
        const yearBtns = document.querySelectorAll('.year-btn');
        const semBtns = document.querySelectorAll('.sem-btn');
        const subjectSelect = document.getElementById('subject_id');
        const options = subjectSelect.querySelectorAll('option');

        /* Map: which semesters belong to each year */
        const semestersByYear = { 'FY': [1, 2], 'SY': [3, 4], 'TY': [5, 6] };
        let selectedYear = 'FY';
        let selectedSem = 1;

        /* Update semester buttons when year changes */
        function updateSemesterButtons() {
            const sems = semestersByYear[selectedYear];
            semBtns.forEach((btn, index) => {
                btn.textContent = 'Sem ' + sems[index];
                btn.dataset.sem = sems[index];
            });
            selectedSem = sems[0];
            semBtns[0].classList.add('active');
            semBtns[1].classList.remove('active');
            filterSubjects();
        }

        /* Show only subjects matching selected year + semester */
        function filterSubjects() {
            options.forEach(opt => {
                if (!opt.value) { opt.style.display = 'block'; return; }
                const show = opt.dataset.year === selectedYear && parseInt(opt.dataset.sem) === selectedSem;
                opt.style.display = show ? 'block' : 'none';
            });
            subjectSelect.value = '';
        }

        /* Year button click handlers */
        yearBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                yearBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                selectedYear = btn.dataset.year;
                updateSemesterButtons();
            });
        });

        /* Semester button click handlers */
        semBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                semBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                selectedSem = parseInt(btn.dataset.sem);
                filterSubjects();
            });
        });

        /* --- Drag & Drop file upload --- */
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('file');
        const selectedFile = document.getElementById('selectedFile');

        /* Click dropzone to open file picker */
        dropzone.addEventListener('click', () => fileInput.click());

        /* Drag over: add visual feedback */
        dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));

        /* Drop: assign file to input */
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                showSelectedFile(e.dataTransfer.files[0]);
            }
        });

        /* Show selected filename */
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length) showSelectedFile(fileInput.files[0]);
        });

        function showSelectedFile(file) {
            selectedFile.innerHTML = `<span>📎 ${file.name}</span>`;
            selectedFile.style.display = 'block';
        }

        /* Initial filter on page load */
        filterSubjects();
    </script>
</body>
</html>
