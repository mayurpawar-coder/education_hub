<?php
/**
 * ============================================================
 * Education Hub - Search Notes (search_notes.php)
 * ============================================================
 *
 * PURPOSE:
 *   Browse and download study notes organized by year/semester,
 *   with note counts and inline notes display like dashboard.
 *
 * HOW IT WORKS:
 *   1. Groups subjects by year and semester
 *   2. Shows note count for each subject
 *   3. Click subject to view available notes
 *   4. Download notes directly from the interface
 *
 * CSS: assets/css/style.css + assets/css/search_notes.css
 * ============================================================
 */

require_once 'config/functions.php';
requireLogin();

$pageTitle = 'Search Notes';

/* Get all subjects for the dropdown filter */
$allSubjects = $conn->query("SELECT * FROM subjects ORDER BY year, semester, name");

/* --- Read filter values from URL --- */
$searchQuery = sanitize($_GET['search'] ?? '');
$subjectFilter = (int)($_GET['subject'] ?? 0);
$yearFilter = sanitize($_GET['year'] ?? '');
$semesterFilter = (int)($_GET['semester'] ?? 0);

/* Query all subjects with note counts ordered by year, semester, name */
/* Apply filters if specified */
$subjectsQuery = "
    SELECT s.*, COUNT(n.id) as note_count
    FROM subjects s
    LEFT JOIN notes n ON s.id = n.subject_id
";

/* Add WHERE conditions based on active filters */
$whereConditions = [];
if ($yearFilter) $whereConditions[] = "s.year = '$yearFilter'";
if ($semesterFilter) $whereConditions[] = "s.semester = $semesterFilter";
if ($subjectFilter) $whereConditions[] = "s.id = $subjectFilter";

if (!empty($whereConditions)) {
    $subjectsQuery .= " WHERE " . implode(" AND ", $whereConditions);
}

$subjectsQuery .= "
    GROUP BY s.id
    ORDER BY s.year, s.semester, s.name
";
$subjects = $conn->query($subjectsQuery);

/* Group subjects by year and semester for organized display */
$groupedSubjects = [];
while ($subject = $subjects->fetch_assoc()) {
    $key = $subject['year'] . ' - Semester ' . $subject['semester'];
    if (!isset($groupedSubjects[$key])) {
        $groupedSubjects[$key] = [];
    }
    $groupedSubjects[$key][] = $subject;
}

/* Get notes for a specific subject if requested */
$selectedSubject = null;
$subjectNotes = [];
$searchResults = [];
$showSearchResults = false;

if (isset($_GET['subject_id']) && is_numeric($_GET['subject_id'])) {
    $subjectId = (int)$_GET['subject_id'];
    $selectedSubject = $conn->query("SELECT * FROM subjects WHERE id = $subjectId")->fetch_assoc();

    if ($selectedSubject) {
        $subjectNotes = $conn->query("
            SELECT n.*, u.name as uploader_name
            FROM notes n
            JOIN users u ON n.uploaded_by = u.id
            WHERE n.subject_id = $subjectId
            ORDER BY n.created_at DESC
        ");
    }
} elseif ($searchQuery || $yearFilter || $semesterFilter || $subjectFilter) {
    // Perform search based on filters
    $searchSql = "SELECT n.*, s.name as subject_name, s.color as subject_color, s.year, s.semester, u.name as uploader_name
        FROM notes n
        JOIN subjects s ON n.subject_id = s.id
        JOIN users u ON n.uploaded_by = u.id
        WHERE 1=1";

    // Add search conditions
    if ($searchQuery) $searchSql .= " AND (n.title LIKE '%$searchQuery%' OR n.content LIKE '%$searchQuery%')";
    if ($subjectFilter) $searchSql .= " AND n.subject_id = $subjectFilter";
    if ($yearFilter) $searchSql .= " AND s.year = '$yearFilter'";
    if ($semesterFilter) $searchSql .= " AND s.semester = $semesterFilter";

    $searchSql .= " ORDER BY n.created_at DESC";
    $searchResults = $conn->query($searchSql);
    $showSearchResults = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Notes - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/search_notes.css">
    <script>
        function showSubjectNotes(subjectId) {
            // Redirect to search_notes with subject_id parameter to show notes
            window.location.href = 'search_notes.php?subject_id=' + subjectId;
        }

        function hideSubjectNotes() {
            // Remove subject_id parameter to hide notes section
            const url = new URL(window.location);
            url.searchParams.delete('subject_id');
            window.location.href = url.toString();
        }

        // Show notes section if subject is selected
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('subject_id')) {
                document.getElementById('subject-notes-section').style.display = 'block';
                // Scroll to notes section
                document.getElementById('subject-notes-section').scrollIntoView({ behavior: 'smooth' });
            }
        });
    </script>
</head>
<body>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'includes/header.php'; ?>

            <section>
                <!-- === Hero Section === -->
                <div class="notes-hero">
                    <h1>📚 Study Materials</h1>
                </div>

                <!-- === Search & Filter Section === -->
                <div class="card" style="margin-bottom: 32px;">
                    <div class="card-header">
                        <h3 class="card-title">🔍 Search & Filter Notes</h3>
                    </div>

                    <!-- Year Tabs -->
                    <div style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;">
                        <a href="search_notes.php" class="btn btn-sm <?= empty($yearFilter) ? 'btn-primary' : 'btn-secondary' ?>">All Years</a>
                        <a href="?year=FY<?= $semesterFilter ? '&semester=' . $semesterFilter : '' ?><?= $subjectFilter ? '&subject=' . $subjectFilter : '' ?>" class="btn btn-sm <?= $yearFilter === 'FY' ? 'btn-primary' : 'btn-secondary' ?>">
                            <span style="background: #3b82f6; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-right: 4px;">FY</span> First Year
                        </a>
                        <a href="?year=SY<?= $semesterFilter ? '&semester=' . $semesterFilter : '' ?><?= $subjectFilter ? '&subject=' . $subjectFilter : '' ?>" class="btn btn-sm <?= $yearFilter === 'SY' ? 'btn-primary' : 'btn-secondary' ?>">
                            <span style="background: #10b981; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-right: 4px;">SY</span> Second Year
                        </a>
                        <a href="?year=TY<?= $semesterFilter ? '&semester=' . $semesterFilter : '' ?><?= $subjectFilter ? '&subject=' . $subjectFilter : '' ?>" class="btn btn-sm <?= $yearFilter === 'TY' ? 'btn-primary' : 'btn-secondary' ?>">
                            <span style="background: #1a56db; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-right: 4px;">TY</span> Third Year
                        </a>
                    </div>

                    <!-- Semester Tabs (show only if year is selected) -->
                    <?php if ($yearFilter): ?>
                    <div style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;">
                        <?php
                        $semesters = ['FY' => [1, 2], 'SY' => [3, 4], 'TY' => [5, 6]];
                        $availableSems = $semesters[$yearFilter] ?? [];
                        ?>
                        <a href="?year=<?= $yearFilter ?><?= $subjectFilter ? '&subject=' . $subjectFilter : '' ?>" class="btn btn-sm <?= !$semesterFilter ? 'btn-primary' : 'btn-secondary' ?>">All Semesters</a>
                        <?php foreach ($availableSems as $sem): ?>
                        <a href="?year=<?= $yearFilter ?>&semester=<?= $sem ?><?= $subjectFilter ? '&subject=' . $subjectFilter : '' ?>" class="btn btn-sm <?= $semesterFilter == $sem ? 'btn-primary' : 'btn-secondary' ?>">
                            Semester <?= $sem ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Search Form -->
                    <form method="GET" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                        <!-- Preserve year/semester filters when searching -->
                        <input type="hidden" name="year" value="<?= htmlspecialchars($yearFilter) ?>">
                        <input type="hidden" name="semester" value="<?= $semesterFilter ?>">

                        <input type="text" name="search" placeholder="Search notes by title or content..."
                               value="<?= htmlspecialchars($searchQuery) ?>" style="flex: 1; min-width: 200px; padding: 8px 12px; border: 1px solid var(--border); border-radius: var(--radius-sm);">

                        <select name="subject" style="padding: 8px 12px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--surface);">
                            <option value="">All Subjects</option>
                            <?php
                            $allSubjects->data_seek(0);
                            while ($subject = $allSubjects->fetch_assoc()):
                                // Filter subjects based on year/semester if selected
                                if ($yearFilter && $subject['year'] !== $yearFilter) continue;
                                if ($semesterFilter && $subject['semester'] != $semesterFilter) continue;
                            ?>
                            <option value="<?= $subject['id'] ?>" <?= $subjectFilter == $subject['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($subject['name']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>

                        <button type="submit" class="btn btn-primary">🔍 Search</button>
                        <?php if ($searchQuery || $yearFilter || $semesterFilter || $subjectFilter): ?>
                        <a href="search_notes.php" class="btn btn-secondary">Clear Filters</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- === Search Results Section === -->
                <?php if ($showSearchResults): ?>
                <div class="card" style="margin-bottom: 32px;">
                    <div class="card-header">
                        <h3 class="card-title">🔍 Search Results</h3>
                        <p style="color: var(--text-muted); margin: 4px 0 0 0; font-size: 14px;">
                            <?php
                            $resultCount = $searchResults ? $searchResults->num_rows : 0;
                            echo $resultCount . ' note' . ($resultCount != 1 ? 's' : '') . ' found';
                            if ($searchQuery) echo ' for "' . htmlspecialchars($searchQuery) . '"';
                            ?>
                        </p>
                    </div>

                    <div style="padding: 20px;">
                        <?php if ($searchResults && $searchResults->num_rows > 0): ?>
                            <div style="display: grid; gap: 16px;">
                                <?php while ($note = $searchResults->fetch_assoc()): ?>
                                <div style="padding: 16px; border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--surface-light);">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                                        <div>
                                            <h4 style="margin: 0; color: var(--text); font-size: 16px;"><?= htmlspecialchars($note['title']) ?></h4>
                                            <p style="margin: 4px 0 0 0; color: var(--text-muted); font-size: 12px;">
                                                <span style="width: 12px; height: 12px; border-radius: 50%; background: <?= $note['subject_color'] ?>; display: inline-block; margin-right: 6px;"></span>
                                                <?= htmlspecialchars($note['subject_name']) ?> •
                                                Uploaded by <?= htmlspecialchars($note['uploader_name']) ?> •
                                                <?= formatDate($note['created_at']) ?> •
                                                Downloaded <?= intval($note['downloads']) ?> times
                                            </p>
                                        </div>
                                        <a href="download_notes.php?id=<?= $note['id'] ?>" class="btn btn-sm btn-success">
                                            📥 Download
                                        </a>
                                    </div>
                                    <?php if (!empty($note['content'])): ?>
                                        <div style="color: var(--text-muted); font-size: 14px; line-height: 1.5;">
                                            <?= nl2br(htmlspecialchars(substr($note['content'], 0, 200))) ?>
                                            <?php if (strlen($note['content']) > 200): ?>...<?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                                <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
                                <h4>No notes found</h4>
                                <p>Try adjusting your search criteria or browse subjects below.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- === Subjects by Year & Semester === -->
                <div class="card" style="margin-bottom: 32px;">
                    <div class="card-header">
                        <h3 class="card-title">📚 Browse Notes by Subject</h3>
                        <p style="color: var(--text-muted); margin: 4px 0 0 0; font-size: 14px;">Click on any subject to view available notes</p>
                    </div>

                    <!-- Year/Semester Tabs -->
                    <?php foreach ($groupedSubjects as $yearSem => $subjectsInGroup): ?>
                    <div class="year-semester-section" style="margin-bottom: 24px;">
                        <h4 style="color: var(--primary); font-weight: 700; margin-bottom: 16px; padding: 12px; background: var(--primary-lighter); border-radius: var(--radius-sm);">
                            🎓 <?= htmlspecialchars($yearSem) ?>
                        </h4>

                        <div class="subjects-grid" style="grid-template-columns: repeat(3, 1fr); gap: 20px;">
                            <?php foreach ($subjectsInGroup as $subject): ?>
                            <div class="subject-card" style="cursor: pointer;" onclick="showSubjectNotes(<?= $subject['id'] ?>)">
                                <div class="subject-header">
                                    <div class="subject-icon" style="background: <?= $subject['color'] ?>20; color: <?= $subject['color'] ?>;">
                                        📚
                                    </div>
                                    <h3 class="subject-name" style="font-size: 16px;"><?= htmlspecialchars($subject['name']) ?></h3>
                                </div>
                                <p class="subject-desc" style="font-size: 13px; margin-bottom: 8px;"><?= htmlspecialchars($subject['description']) ?></p>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                    <span style="font-size: 12px; color: var(--text-muted);">
                                        📝 <?= $subject['note_count'] ?> note<?= $subject['note_count'] != 1 ? 's' : '' ?> available
                                    </span>
                                    <span style="font-size: 11px; color: var(--text-light); background: var(--surface); padding: 2px 6px; border-radius: 4px;">
                                        <?= $subject['year'] ?> Sem <?= $subject['semester'] ?>
                                    </span>
                                </div>
                                <div class="subject-actions">
                                    <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); showSubjectNotes(<?= $subject['id'] ?>)">
                                        📖 View Notes
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- === Subject Notes Section === -->
                <div id="subject-notes-section" class="card" style="display: none; margin-bottom: 32px;">
                    <div class="card-header">
                        <h3 class="card-title" id="subject-notes-title">📖 Subject Notes</h3>
                        <button class="btn btn-sm" onclick="hideSubjectNotes()" style="margin-left: auto;">✕ Close</button>
                    </div>

                    <div id="subject-notes-content">
                        <!-- Notes will be loaded here via page refresh -->
                        <?php if ($selectedSubject && $subjectNotes): ?>
                            <div class="notes-list" style="display: grid; gap: 16px;">
                                <?php while ($note = $subjectNotes->fetch_assoc()): ?>
                                <div class="note-item" style="padding: 16px; border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--surface-light);">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                                        <div>
                                            <h4 style="margin: 0; color: var(--text); font-size: 16px;"><?= htmlspecialchars($note['title']) ?></h4>
                                            <p style="margin: 4px 0 0 0; color: var(--text-muted); font-size: 12px;">
                                                Uploaded by <?= htmlspecialchars($note['uploader_name']) ?> •
                                                <?= formatDate($note['created_at']) ?> •
                                                Downloaded <?= $note['downloads'] ?> times
                                            </p>
                                        </div>
                                        <a href="download_notes.php?id=<?= $note['id'] ?>" class="btn btn-sm btn-success" style="margin-left: 12px;">
                                            📥 Download
                                        </a>
                                    </div>
                                    <?php if (!empty($note['content'])): ?>
                                        <div style="color: var(--text-muted); font-size: 14px; line-height: 1.5; margin-top: 8px;">
                                            <?= nl2br(htmlspecialchars(substr($note['content'], 0, 200))) ?>
                                            <?php if (strlen($note['content']) > 200): ?>...<?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php elseif ($selectedSubject): ?>
                            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                                <div style="font-size: 48px; margin-bottom: 16px;">�</div>
                                <h4>No notes available</h4>
                                <p>Notes for this subject haven't been uploaded yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
