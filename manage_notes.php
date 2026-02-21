<?php
require_once 'config/functions.php';
requireTeacher();

$pageTitle = 'Manage Notes';

/* --- Handle POST delete action --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    $deleteId = (int)$_POST['id'];
    $role = $_SESSION['user_role'] ?? 'student';
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

/* Query all notes (teachers see their own, admin sees all) */
$role = $_SESSION['user_role'] ?? 'student';
$userId = $_SESSION['user_id'];

if ($role === 'admin') {
    $notesRes = $conn->query("SELECT n.*, s.name AS subject_name, s.color AS subject_color, u.name AS uploader FROM notes n JOIN subjects s ON n.subject_id = s.id LEFT JOIN users u ON n.uploaded_by = u.id ORDER BY n.created_at DESC");
} else {
    $notesRes = $conn->query("SELECT n.*, s.name AS subject_name, s.color AS subject_color, u.name AS uploader FROM notes n JOIN subjects s ON n.subject_id = s.id LEFT JOIN users u ON n.uploaded_by = u.id WHERE n.uploaded_by = $userId ORDER BY n.created_at DESC");
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Notes - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/manage_notes.css">
</head>
<body>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include 'includes/header.php'; ?>

            <section>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">All Notes</h3>
                        <a href="upload_notes.php" class="btn btn-sm btn-primary">Upload New</a>
                    </div>

                    <?php if ($notesRes && $notesRes->num_rows > 0): ?>
                        <div style="overflow:auto;">
                            <table class="table" style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr style="text-align:left; border-bottom:1px solid #eee;">
                                        <th>Title</th>
                                        <th>Subject</th>
                                        <th>Uploader</th>
                                        <th>Downloads</th>
                                        <th>Uploaded</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($note = $notesRes->fetch_assoc()): ?>
                                        <tr style="border-bottom:1px solid #fafafa;">
                                            <td><?= htmlspecialchars($note['title']) ?></td>
                                            <td><span style="background: <?= $note['subject_color'] ?>20; color: <?= $note['subject_color'] ?>; padding:4px 8px; border-radius:12px; font-size:12px;"><?= htmlspecialchars($note['subject_name']) ?></span></td>
                                            <td><?= htmlspecialchars($note['uploader'] ?? 'Unknown') ?></td>
                                            <td><?= intval($note['downloads']) ?></td>
                                            <td><?= formatDate($note['created_at']) ?></td>
                                            <td>
                                                                        <a class="btn btn-sm" href="download_notes.php?id=<?= $note['id'] ?>">Download</a>
                                                                        <a class="btn btn-sm" href="upload_notes.php?edit=<?= $note['id'] ?>">Edit</a>
                                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this note?');">
                                                                            <input type="hidden" name="action" value="delete">
                                                                            <input type="hidden" name="id" value="<?= $note['id'] ?>">
                                                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                                                        </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div style="padding:28px; text-align:center; color:var(--text-muted);">
                            <p>No notes found.</p>
                            <a href="upload_notes.php" class="btn btn-primary">Upload Notes</a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
