<?php
/**
 * Education Hub - Search Notes Page
 */

require_once 'config/functions.php';
requireLogin();

$pageTitle = 'Search Notes';

// Get subjects for filter
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name");

// Handle search
$searchQuery = sanitize($_GET['search'] ?? '');
$subjectFilter = (int)($_GET['subject'] ?? 0);

$sql = "SELECT n.*, s.name as subject_name, s.color as subject_color, u.name as uploader_name 
        FROM notes n 
        JOIN subjects s ON n.subject_id = s.id 
        JOIN users u ON n.uploaded_by = u.id 
        WHERE 1=1";

if ($searchQuery) {
    $sql .= " AND (n.title LIKE '%$searchQuery%' OR n.content LIKE '%$searchQuery%')";
}

if ($subjectFilter) {
    $sql .= " AND n.subject_id = $subjectFilter";
}

$sql .= " ORDER BY n.created_at DESC";
$notes = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Notes - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <section>
                <!-- Search Bar -->
                <form method="GET" class="search-bar">
                    <input type="text" name="search" class="search-input" 
                           placeholder="Search notes by title or keyword..." 
                           value="<?= htmlspecialchars($searchQuery) ?>">
                    
                    <select name="subject" class="filter-select">
                        <option value="">All Subjects</option>
                        <?php 
                        $subjects->data_seek(0);
                        while ($subject = $subjects->fetch_assoc()): 
                        ?>
                        <option value="<?= $subject['id'] ?>" <?= $subjectFilter == $subject['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($subject['name']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">🔍 Search</button>
                </form>
                
                <!-- Notes Grid -->
                <div class="notes-grid">
                    <?php if ($notes->num_rows > 0): ?>
                        <?php while ($note = $notes->fetch_assoc()): ?>
                        <div class="note-card">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                                <span style="background: <?= $note['subject_color'] ?>20; color: <?= $note['subject_color'] ?>; padding: 4px 12px; border-radius: 20px; font-size: 12px;">
                                    <?= htmlspecialchars($note['subject_name']) ?>
                                </span>
                            </div>
                            <h3 class="note-title"><?= htmlspecialchars($note['title']) ?></h3>
                            <div class="note-meta">
                                <span>📤 <?= htmlspecialchars($note['uploader_name']) ?></span>
                                <span>📅 <?= formatDate($note['created_at']) ?></span>
                                <span>⬇️ <?= $note['downloads'] ?> downloads</span>
                            </div>
                            <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 16px;">
                                <?= htmlspecialchars(substr($note['content'], 0, 100)) ?>...
                            </p>
                            <a href="download_notes.php?id=<?= $note['id'] ?>" class="btn btn-sm btn-primary">📥 Download</a>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="card" style="grid-column: 1/-1; text-align: center; padding: 48px;">
                            <p style="color: var(--text-muted);">No notes found. Try adjusting your search.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
