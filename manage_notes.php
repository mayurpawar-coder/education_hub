<?php
require_once 'config/functions.php';
requireTeacher();

$pageTitle = 'Manage Notes';

/* --- Handle POST delete action --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    $deleteId = (int) $_POST['id'];
    $role = $_SESSION['user_role'] ?? 'student';
    $userId = $_SESSION['user_id'] ?? 0;

    $stmt = $conn->prepare("SELECT * FROM notes WHERE id = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $result = $stmt->get_result();
    $noteToDelete = $result->fetch_assoc();
    $stmt->close();

    if (!$noteToDelete) {
        $error = 'Note not found.';
    } elseif ($role !== 'admin' && $noteToDelete['uploaded_by'] != $userId) {
        $error = 'Unauthorized to delete this note.';
    } else {
        if (!empty($noteToDelete['file_path']) && file_exists($noteToDelete['file_path'])) {
            @unlink($noteToDelete['file_path']);
        }

        $stmt = $conn->prepare("DELETE FROM notes WHERE id = ?");
        $stmt->bind_param("i", $deleteId);

        if ($stmt->execute()) {
            $success = 'Note deleted successfully.';
        } else {
            $error = 'Failed to delete note.';
        }
        $stmt->close();
    }
}

/* Query all notes */
$role = $_SESSION['user_role'] ?? 'student';
$userId = $_SESSION['user_id'] ?? 0;

if ($role === 'admin') {
    $sql = "SELECT n.*, s.name AS subject_name, s.color AS subject_color, u.name AS uploader
            FROM notes n
            JOIN subjects s ON n.subject_id = s.id
            LEFT JOIN users u ON n.uploaded_by = u.id
            ORDER BY n.created_at DESC";
    $notesRes = $conn->query($sql);
} else {
    $stmt = $conn->prepare("SELECT n.*, s.name AS subject_name, s.color AS subject_color, u.name AS uploader
                            FROM notes n
                            JOIN subjects s ON n.subject_id = s.id
                            LEFT JOIN users u ON n.uploaded_by = u.id
                            WHERE n.uploaded_by = ?
                            ORDER BY n.created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $notesRes = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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
                                    <td><?= htmlspecialchars($note['title'] ?? '') ?></td>
                                    <td>
                                        <span style="background: <?= htmlspecialchars($note['subject_color'] ?? '#ccc') ?>20; 
                                                     color: <?= htmlspecialchars($note['subject_color'] ?? '#000') ?>; 
                                                     padding:4px 8px; 
                                                     border-radius:12px; 
                                                     font-size:12px;">
                                            <?= htmlspecialchars($note['subject_name'] ?? '') ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($note['uploader'] ?? 'Unknown') ?></td>
                                    <td><?= intval($note['downloads'] ?? 0) ?></td>
                                    <td><?= isset($note['created_at']) ? formatDate($note['created_at']) : '' ?></td>
                                    <td>
                                        <a class="btn btn-sm" href="download_notes.php?id=<?= intval($note['id']) ?>">Download</a>
                                        <a class="btn btn-sm" href="upload_notes.php?edit=<?= intval($note['id']) ?>">Edit</a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this note?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= intval($note['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="padding:28px; text-align:center;">
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