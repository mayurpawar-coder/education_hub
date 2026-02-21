<?php
require_once 'config/functions.php';
requireTeacher();

$pageTitle = 'My Uploads';
$userId = $_SESSION['user_id'] ?? 0;

$stmt = $conn->prepare("
    SELECT n.*, s.name as subject_name, s.color as subject_color, s.year, s.semester
    FROM notes n
    JOIN subjects s ON n.subject_id = s.id
    WHERE n.uploaded_by = ?
    ORDER BY s.year, s.semester, n.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$myNotes = $stmt->get_result();

$groupedNotes = [];
while ($row = $myNotes->fetch_assoc()) {
    $y = $row['year'] ?? 'Other';
    $sem = 'Sem ' . ($row['semester'] ?? '0');
    if (!isset($groupedNotes[$y])) $groupedNotes[$y] = [];
    if (!isset($groupedNotes[$y][$sem])) $groupedNotes[$y][$sem] = [];
    $groupedNotes[$y][$sem][] = $row;
}
?>

<section>

    <div class="card" style="margin-bottom: 24px; text-align: center; padding: 32px;">
        <h2 style="margin-bottom: 8px;">📄 My Uploaded Notes</h2>
        <p style="color: var(--text-muted);">View and manage all notes you have uploaded</p>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">📁 Your Notes (<?= $myNotes->num_rows ?>)</h3>
            <a href="upload_notes.php" class="btn btn-sm btn-primary">📤 Upload New</a>
        </div>

        <?php
        $totalCount = 0;
        foreach ($groupedNotes as $y => $sems) {
            foreach ($sems as $s => $notesArr) {
                $totalCount += count($notesArr);
            }
        }
        ?>

        <?php if ($totalCount > 0): ?>
            <?php foreach ($groupedNotes as $year => $sems): ?>
                <div style="margin-bottom: 18px;">
                    <h4 style="margin:12px 0;"><?= htmlspecialchars($year) ?></h4>

                    <?php foreach ($sems as $semester => $notesArr): ?>
                        <div style="margin-left: 8px; margin-bottom: 12px;">
                            <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
                                <span class="year-badge" style="font-weight:600;"><?= htmlspecialchars($semester) ?></span>
                            </div>

                            <div style="display:flex; flex-wrap:wrap; gap:12px;">
                                <?php foreach ($notesArr as $note): ?>
                                    <div style="width:320px; border:1px solid #eee; padding:12px; border-radius:8px; background:#fff;">
                                        <div style="display:flex; justify-content:space-between; align-items:start; gap:8px;">
                                            <div>
                                                <strong><?= htmlspecialchars($note['title']) ?></strong>
                                                <div style="margin-top:6px;">
                                                    <span style="background: <?= htmlspecialchars($note['subject_color']) ?>20; color: <?= htmlspecialchars($note['subject_color']) ?>; padding: 4px 10px; border-radius:16px; font-size:12px;">
                                                        <?= htmlspecialchars($note['subject_name']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div style="text-align:right; font-size:13px; color:var(--text-muted);">
                                                <div>⬇️ <?= intval($note['downloads']) ?></div>
                                                <div style="margin-top:8px;"><?= formatDate($note['created_at']) ?></div>
                                            </div>
                                        </div>

                                        <div style="margin-top:10px; display:flex; gap:8px;">
                                            <a class="btn btn-sm" href="download_notes.php?id=<?= intval($note['id']) ?>">Download</a>
                                            <a class="btn btn-sm" href="upload_notes.php?edit=<?= intval($note['id']) ?>">Edit</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 48px; color: var(--text-muted);">
                <div style="font-size: 48px; margin-bottom: 16px;">📭</div>
                <p>You haven't uploaded any notes yet.</p>
                <a href="upload_notes.php" class="btn btn-primary" style="margin-top: 16px;">📤 Upload Your First Note</a>
            </div>
        <?php endif; ?>
    </div>

</section>