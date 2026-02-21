<?php
require_once 'config/functions.php';
requireLogin();

$role = $_SESSION['user_role'] ?? 'student';
if (!in_array($role, ['teacher', 'admin'])) {
    // Students should use search_notes directly
    redirect('search_notes.php');
}

$tab = $_GET['tab'] ?? 'all';
$pageTitle = 'Notes Management';
$success = '';
$error = '';

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    $deleteId = (int)$_POST['id'];
    $userId = $_SESSION['user_id'];

    // Verify ownership or admin
    $q = $conn->query("SELECT * FROM notes WHERE id = $deleteId");
    $noteToDelete = $q ? $q->fetch_assoc() : null;
    if (!$noteToDelete) {
        $error = 'Note not found.';
    } elseif ($role !== 'admin' && $noteToDelete['uploaded_by'] != $userId) {
        $error = 'Unauthorized to delete this note.';
    } else {
        // Remove file from disk if exists
        if (!empty($noteToDelete['file_path']) && file_exists($noteToDelete['file_path'])) {
            @unlink($noteToDelete['file_path']);
        }
        $stmt = $conn->prepare("DELETE FROM notes WHERE id = ?");
        $stmt->bind_param('i', $deleteId);
        if ($stmt->execute()) {
            $success = 'Note deleted successfully.';
        } else {
            $error = 'Failed to delete note.';
        }
        $stmt->close();
    }
}

// Get all notes organized by year and semester
$role = $_SESSION['user_role'] ?? 'student';
$userId = $_SESSION['user_id'];

if ($role === 'admin') {
    $notesRes = $conn->query("SELECT n.*, s.name AS subject_name, s.color AS subject_color, s.year, s.semester, u.name AS uploader FROM notes n JOIN subjects s ON n.subject_id = s.id LEFT JOIN users u ON n.uploaded_by = u.id ORDER BY s.year DESC, s.semester DESC, s.name ASC, n.created_at DESC");
} else {
    $notesRes = $conn->query("SELECT n.*, s.name AS subject_name, s.color AS subject_color, s.year, s.semester, u.name AS uploader FROM notes n JOIN subjects s ON n.subject_id = s.id LEFT JOIN users u ON n.uploaded_by = u.id WHERE n.uploaded_by = $userId ORDER BY s.year DESC, s.semester DESC, s.name ASC, n.created_at DESC");
}

// Organize notes by year and semester
$organizedNotes = [];
while ($note = $notesRes->fetch_assoc()) {
    $year = $note['year'];
    $semester = $note['semester'];
    $organizedNotes[$year][$semester][] = $note;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes Management - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .year-section {
            margin-bottom: 32px;
        }
        
        .year-header {
            background: var(--gradient-primary);
            color: white;
            padding: 16px 24px;
            border-radius: var(--radius-lg);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--shadow-md);
        }
        
        .year-title {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
        }
        
        .semester-section {
            margin-bottom: 24px;
        }
        
        .semester-header {
            background: var(--surface);
            border: 1px solid var(--border);
            padding: 12px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .semester-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
            margin: 0;
        }
        
        .subject-section {
            margin-bottom: 20px;
        }
        
        .subject-header {
            background: var(--primary-lighter);
            padding: 12px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .subject-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .subject-badge {
            background: var(--primary);
            color: white;
            padding: 4px 12px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 600;
        }
        
        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }
        
        .note-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 16px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        
        .note-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .note-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
            line-height: 1.4;
        }
        
        .note-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            font-size: 12px;
            color: var(--text-muted);
        }
        
        .note-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 11px;
            border-radius: var(--radius-sm);
        }
        
        .empty-section {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }
        
        .upload-btn {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--gradient-primary);
            color: white;
            border: none;
            font-size: 24px;
            cursor: pointer;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
            z-index: 1000;
        }
        
        .upload-btn:hover {
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include 'includes/header.php'; ?>

            <section>
                <?php if ($error): ?><?= showAlert($error, 'error') ?><?php endif; ?>
                <?php if ($success): ?><?= showAlert($success, 'success') ?><?php endif; ?>

                <div class="page-header">
                    <h1>📚 Notes Management</h1>
                </div>

                <?php if (!empty($organizedNotes)): ?>
                    <?php foreach ($organizedNotes as $year => $semesters): ?>
                        <div class="year-section">
                            <div class="year-header">
                                <span style="font-size: 24px;">📅</span>
                                <h2 class="year-title">Year <?= $year ?></h2>
                                <span style="margin-left: auto; font-size: 14px; opacity: 0.9;">
                                    <?= array_sum(array_map('count', $semesters)) ?> notes
                                </span>
                            </div>

                            <?php foreach ($semesters as $semester => $subjects): ?>
                                <div class="semester-section">
                                    <div class="semester-header">
                                        <span style="font-size: 18px;">📖</span>
                                        <h3 class="semester-title">Semester <?= $semester ?></h3>
                                        <span style="margin-left: auto; font-size: 12px; color: var(--text-muted);">
                                            <?= count($subjects) ?> subjects
                                        </span>
                                    </div>

                                    <?php 
                                    // Group notes by subject
                                    $subjectsGrouped = [];
                                    foreach ($subjects as $note) {
                                        $subjectsGrouped[$note['subject_name']][] = $note;
                                    }
                                    ?>

                                    <?php foreach ($subjectsGrouped as $subjectName => $notes): ?>
                                        <div class="subject-section">
                                            <div class="subject-header">
                                                <div class="subject-title">
                                                    <span style="width: 12px; height: 12px; border-radius: 50%; background: <?= $notes[0]['subject_color'] ?>;"></span>
                                                    <?= htmlspecialchars($subjectName) ?>
                                                </div>
                                                <span class="subject-badge"><?= count($notes) ?> notes</span>
                                            </div>

                                            <div class="notes-grid">
                                                <?php foreach ($notes as $note): ?>
                                                    <div class="note-card">
                                                        <div class="note-title"><?= htmlspecialchars($note['title']) ?></div>
                                                        
                                                        <div class="note-meta">
                                                            <span>📤 <?= htmlspecialchars($note['uploader'] ?? 'Unknown') ?></span>
                                                            <span>⬇️ <?= intval($note['downloads']) ?></span>
                                                        </div>
                                                        
                                                        <div class="note-meta">
                                                            <span>📅 <?= formatDate($note['created_at']) ?></span>
                                                        </div>
                                                        
                                                        <div class="note-actions">
                                                            <a href="download_notes.php?id=<?= $note['id'] ?>" class="btn btn-primary btn-small">Download</a>
                                                            <?php if ($role === 'admin' || $note['uploaded_by'] == $userId): ?>
                                                                <a href="upload_notes.php?edit=<?= $note['id'] ?>" class="btn btn-secondary btn-small">Edit</a>
                                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this note?');">
                                                                    <input type="hidden" name="action" value="delete">
                                                                    <input type="hidden" name="id" value="<?= $note['id'] ?>">
                                                                    <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-section">
                        <div style="font-size: 48px; margin-bottom: 16px;">📭</div>
                        <h3>No Notes Found</h3>
                        <p>No notes have been uploaded yet. Start by uploading your first notes!</p>
                        <a href="upload_notes.php" class="btn btn-primary" style="margin-top: 16px;">Upload Notes</a>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Floating Upload Button -->
            <?php if (in_array($role, ['teacher', 'admin'])): ?>
                <a href="upload_notes.php" class="upload-btn" title="Upload New Notes">
                    +
                </a>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
