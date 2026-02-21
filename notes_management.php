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
                    <iframe id="notes-frame" src="" style="width:100%; height:700px; border:0;" title="Notes Management Content"></iframe>
                </div>

            </section>
        </main>
    </div>

    <script>
        (function() {
            var tab = '<?= $tab ?>';
            var iframe = document.getElementById('notes-frame');
            var base = '';
            // Set iframe src based on tab
            if (tab === 'all') iframe.src = 'manage_notes.php';
            else if (tab === 'my') iframe.src = 'my_uploads.php';
            else if (tab === 'upload') iframe.src = 'upload_notes.php';
            else if (tab === 'search') iframe.src = 'search_notes.php';
            else iframe.src = 'manage_notes.php';
        })();
    </script>
</body>
</html>
