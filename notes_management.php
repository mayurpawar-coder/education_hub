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
// Base path for correct relative links in iframe
$basePath = getBasePath();

// Determine iframe source using server-side base path to avoid 404s from incorrect relative URLs
switch ($tab) {
    case 'my': $iframeSrc = $basePath . 'my_uploads.php'; break;
    case 'upload': $iframeSrc = $basePath . 'upload_notes.php'; break;
    case 'search': $iframeSrc = $basePath . 'search_notes.php'; break;
    default: $iframeSrc = $basePath . 'manage_notes.php'; break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes Management - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include 'includes/header.php'; ?>

            <section>
                <div class="page-header">
                    <h1>Notes Management</h1>
                </div>

                <div class="tabs">
                    <a class="tab <?= $tab === 'all' ? 'active' : '' ?>" href="?tab=all">All Notes</a>
                    <a class="tab <?= $tab === 'my' ? 'active' : '' ?>" href="?tab=my">My Uploads</a>
                    <a class="tab <?= $tab === 'upload' ? 'active' : '' ?>" href="?tab=upload">Upload Notes</a>
                    <a class="tab <?= $tab === 'search' ? 'active' : '' ?>" href="?tab=search">Search Notes</a>
                </div>

                <div class="tab-content" style="min-height:600px; border:1px solid var(--muted); padding:0; margin-top:12px;">
                    <iframe id="notes-frame" src="<?= $iframeSrc ?>" style="width:100%; height:700px; border:0;" title="Notes Management Content"></iframe>
                </div>

            </section>
        </main>
    </div>

    <script>
        // no-op JS kept for compatibility; iframe src is set server-side
    </script>
</body>
</html>
