<?php
/**
 * Education Hub - Upload Notes (Teachers Only)
 */

require_once 'config/functions.php';
requireTeacher();

$pageTitle = 'Upload Notes';
$success = '';
$error = '';

// Get subjects grouped by year/semester
$subjects = $conn->query("SELECT * FROM subjects ORDER BY year, semester, name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $content = sanitize($_POST['content'] ?? '');
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $uploadedBy = $_SESSION['user_id'];
    
    if (empty($title) || empty($subjectId)) {
        $error = 'Please fill in all required fields';
    } else {
        $filePath = null;
        
        // Handle file upload
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/notes/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = time() . '_' . basename($_FILES['file']['name']);
            $filePath = $uploadDir . $fileName;
            
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $filePath)) {
                $error = 'Failed to upload file';
            }
        }
        
        if (empty($error)) {
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
                <div class="upload-hero">
                    <h1>📤 Upload Notes</h1>
                    <p>Share study materials with students</p>
                </div>
                
                <div class="upload-container">
                    <?php if ($error): ?>
                        <?= showAlert($error, 'error') ?>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <?= showAlert($success, 'success') ?>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data" class="upload-form">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="title">📝 Title *</label>
                                <input type="text" id="title" name="title" placeholder="Enter note title" required>
                            </div>
                            
                            <div class="form-group">
                                <label>📅 Year</label>
                                <div class="year-selector">
                                    <button type="button" class="year-btn active" data-year="FY">FY</button>
                                    <button type="button" class="year-btn" data-year="SY">SY</button>
                                    <button type="button" class="year-btn" data-year="TY">TY</button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>📚 Semester</label>
                                <div class="semester-selector">
                                    <button type="button" class="sem-btn active" data-sem="1">Sem 1</button>
                                    <button type="button" class="sem-btn" data-sem="2">Sem 2</button>
                                </div>
                            </div>
                            
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
                            
                            <div class="form-group full-width">
                                <label for="content">📋 Description/Content</label>
                                <textarea id="content" name="content" rows="5" placeholder="Enter note description or content..."></textarea>
                            </div>
                            
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
                        
                        <button type="submit" class="btn btn-primary btn-upload">
                            📤 Upload Note
                        </button>
                    </form>
                </div>
            </section>
        </main>
    </div>
    
    <script>
        // Year/Semester filter logic
        const yearBtns = document.querySelectorAll('.year-btn');
        const semBtns = document.querySelectorAll('.sem-btn');
        const subjectSelect = document.getElementById('subject_id');
        const options = subjectSelect.querySelectorAll('option');
        
        const semestersByYear = {
            'FY': [1, 2],
            'SY': [3, 4],
            'TY': [5, 6]
        };
        
        let selectedYear = 'FY';
        let selectedSem = 1;
        
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
        
        function filterSubjects() {
            options.forEach(opt => {
                if (!opt.value) {
                    opt.style.display = 'block';
                    return;
                }
                const year = opt.dataset.year;
                const sem = parseInt(opt.dataset.sem);
                if (year === selectedYear && sem === selectedSem) {
                    opt.style.display = 'block';
                } else {
                    opt.style.display = 'none';
                }
            });
            subjectSelect.value = '';
        }
        
        yearBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                yearBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                selectedYear = btn.dataset.year;
                updateSemesterButtons();
            });
        });
        
        semBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                semBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                selectedSem = parseInt(btn.dataset.sem);
                filterSubjects();
            });
        });
        
        // File upload drag & drop
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('file');
        const selectedFile = document.getElementById('selectedFile');
        
        dropzone.addEventListener('click', () => fileInput.click());
        
        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });
        
        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('dragover');
        });
        
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                showSelectedFile(e.dataTransfer.files[0]);
            }
        });
        
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length) {
                showSelectedFile(fileInput.files[0]);
            }
        });
        
        function showSelectedFile(file) {
            selectedFile.innerHTML = `<span>📎 ${file.name}</span>`;
            selectedFile.style.display = 'block';
        }
        
        // Initial filter
        filterSubjects();
    </script>
</body>
</html>
