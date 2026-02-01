<?php
/**
 * Education Hub - Search Notes Page with Year/Semester Filter
 */

require_once 'config/functions.php';
requireLogin();

$pageTitle = 'Search Notes';

// Get subjects for filter
$subjects = $conn->query("SELECT * FROM subjects ORDER BY year, semester, name");

// Handle filters
$searchQuery = sanitize($_GET['search'] ?? '');
$subjectFilter = (int)($_GET['subject'] ?? 0);
$yearFilter = sanitize($_GET['year'] ?? '');
$semesterFilter = (int)($_GET['semester'] ?? 0);

$sql = "SELECT n.*, s.name as subject_name, s.color as subject_color, s.year, s.semester, u.name as uploader_name 
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

if ($yearFilter) {
    $sql .= " AND s.year = '$yearFilter'";
}

if ($semesterFilter) {
    $sql .= " AND s.semester = $semesterFilter";
}

$sql .= " ORDER BY s.year, s.semester, n.created_at DESC";
$notes = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Notes - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/search_notes.css">
</head>
<body>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <section>
                <!-- Hero Section -->
                <div class="notes-hero">
                    <h1>📚 Study Materials</h1>
                    <p>Find notes by year, semester, or subject</p>
                </div>
                
                <!-- Year/Semester Tabs -->
                <div class="year-tabs">
                    <a href="?year=" class="year-tab <?= empty($yearFilter) ? 'active' : '' ?>">All Years</a>
                    <a href="?year=FY" class="year-tab <?= $yearFilter === 'FY' ? 'active' : '' ?>">
                        <span class="year-badge fy">FY</span> First Year
                    </a>
                    <a href="?year=SY" class="year-tab <?= $yearFilter === 'SY' ? 'active' : '' ?>">
                        <span class="year-badge sy">SY</span> Second Year
                    </a>
                    <a href="?year=TY" class="year-tab <?= $yearFilter === 'TY' ? 'active' : '' ?>">
                        <span class="year-badge ty">TY</span> Third Year
                    </a>
                </div>
                
                <!-- Semester Filter -->
                <?php if ($yearFilter): ?>
                <div class="semester-tabs">
                    <?php 
                    $semesters = [
                        'FY' => [1, 2],
                        'SY' => [3, 4],
                        'TY' => [5, 6]
                    ];
                    $availableSems = $semesters[$yearFilter] ?? [];
                    ?>
                    <a href="?year=<?= $yearFilter ?>" class="semester-tab <?= !$semesterFilter ? 'active' : '' ?>">All Semesters</a>
                    <?php foreach ($availableSems as $sem): ?>
                    <a href="?year=<?= $yearFilter ?>&semester=<?= $sem ?>" 
                       class="semester-tab <?= $semesterFilter == $sem ? 'active' : '' ?>">
                        Semester <?= $sem ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Search Bar -->
                <form method="GET" class="search-bar modern-search">
                    <input type="hidden" name="year" value="<?= htmlspecialchars($yearFilter) ?>">
                    <input type="hidden" name="semester" value="<?= $semesterFilter ?>">
                    
                    <div class="search-input-wrapper">
                        <span class="search-icon">🔍</span>
                        <input type="text" name="search" class="search-input" 
                               placeholder="Search notes by title or keyword..." 
                               value="<?= htmlspecialchars($searchQuery) ?>">
                    </div>
                    
                    <select name="subject" class="filter-select modern-select">
                        <option value="">All Subjects</option>
                        <?php 
                        $subjects->data_seek(0);
                        while ($subject = $subjects->fetch_assoc()): 
                            // Filter subjects based on year/semester selection
                            if ($yearFilter && $subject['year'] !== $yearFilter) continue;
                            if ($semesterFilter && $subject['semester'] != $semesterFilter) continue;
                        ?>
                        <option value="<?= $subject['id'] ?>" <?= $subjectFilter == $subject['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($subject['name']) ?> (Sem <?= $subject['semester'] ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                    
                    <button type="submit" class="btn btn-primary btn-search">Search</button>
                </form>
                
                <!-- Notes Grid -->
                <div class="notes-grid">
                    <?php if ($notes->num_rows > 0): ?>
                        <?php while ($note = $notes->fetch_assoc()): ?>
                        <div class="note-card modern-card">
                            <div class="note-header">
                                <span class="subject-badge" style="background: <?= $note['subject_color'] ?>20; color: <?= $note['subject_color'] ?>;">
                                    <?= htmlspecialchars($note['subject_name']) ?>
                                </span>
                                <span class="semester-badge"><?= $note['year'] ?> - Sem <?= $note['semester'] ?></span>
                            </div>
                            <h3 class="note-title"><?= htmlspecialchars($note['title']) ?></h3>
                            <div class="note-meta">
                                <span>📤 <?= htmlspecialchars($note['uploader_name']) ?></span>
                                <span>📅 <?= formatDate($note['created_at']) ?></span>
                                <span>⬇️ <?= $note['downloads'] ?> downloads</span>
                            </div>
                            <p class="note-content">
                                <?= htmlspecialchars(substr($note['content'], 0, 100)) ?>...
                            </p>
                            <a href="download_notes.php?id=<?= $note['id'] ?>" class="btn btn-sm btn-primary btn-download">
                                📥 Download
                            </a>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">📭</div>
                            <h3>No notes found</h3>
                            <p>Try adjusting your filters or search query</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
