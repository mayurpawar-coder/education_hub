<?php
require_once 'config/functions.php';
requireTeacher();

$pageTitle = 'Notes Management';
$userId = $_SESSION['user_id'];
$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

// CSRF Handling
function generateCSRFToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function validateCSRFToken($token)
{
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
$csrf_token = generateCSRFToken();

$uploadDir = __DIR__ . '/uploads/notes/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
    // Secure the directory
    file_put_contents($uploadDir . '.htaccess', "Deny from all");
}

$successMsg = '';
$errorMsg = '';

// Allowed extensions and corresponding MIME types
$allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip'];
$allowedMimeTypes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/zip',
    'application/x-zip-compressed'
];

$action = $_REQUEST['action'] ?? '';

// --- Handle GET Actions (Download / Export / JSON Get) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (($action === 'download' || $action === 'preview') && isset($_GET['id'])) {
        $noteId = (int)$_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM notes WHERE id = ? AND is_deleted = 0");
        $stmt->bind_param("i", $noteId);
        $stmt->execute();
        $note = $stmt->get_result()->fetch_assoc();

        if ($note) {
            $filePath = $uploadDir . $note['file_name'];
            if (file_exists($filePath)) {
                if ($action === 'download') {
                    // Track download
                    $dlStmt = $conn->prepare("INSERT INTO note_downloads (note_id, user_id) VALUES (?, ?)");
                    $dlStmt->bind_param("ii", $noteId, $userId);
                    $dlStmt->execute();

                    $upStmt = $conn->prepare("UPDATE notes SET download_count = download_count + 1 WHERE id = ?");
                    $upStmt->bind_param("i", $noteId);
                    $upStmt->execute();
                }

                // Serve file securely
                $disposition = ($action === 'preview') ? 'inline' : 'attachment';
                header('Content-Description: File Transfer');
                header('Content-Type: ' . ($action === 'preview' && strtolower($note['file_type']) === 'pdf' ? 'application/pdf' : 'application/octet-stream'));
                header('Content-Disposition: ' . $disposition . '; filename="' . basename($note['original_name']) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($filePath));
                readfile($filePath);
                exit;
            } else {
                $errorMsg = "File not found on server.";
            }
        }
    }

    if ($action === 'export') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="notes_export_' . date('Ymd') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Title', 'Description', 'Original Name', 'File Size', 'File Type', 'Subject', 'Year', 'Semester', 'Downloads', 'Created At']);

        $sql = "SELECT n.*, s.name as subject_name FROM notes n JOIN subjects s ON n.subject_id = s.id WHERE n.is_deleted = 0 ";
        if (!$isAdmin) $sql .= "AND n.created_by = " . (int)$userId;
        $sql .= " ORDER BY n.created_at DESC";
        $res = $conn->query($sql);

        while ($row = $res->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['title'],
                $row['description'],
                $row['original_name'],
                $row['file_size'],
                $row['file_type'],
                $row['subject_name'],
                $row['year'],
                $row['semester'],
                $row['download_count'],
                $row['created_at']
            ]);
        }
        fclose($output);
        exit;
    }

    if ($action === 'get_note' && isset($_GET['id'])) {
        header('Content-Type: application/json');
        $noteId = (int)$_GET['id'];
        $sql = "SELECT n.*, s.name as subject_name FROM notes n JOIN subjects s ON n.subject_id = s.id WHERE n.id = ? AND n.is_deleted = 0";
        if (!$isAdmin) $sql .= " AND n.created_by = " . (int)$userId;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $noteId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            echo json_encode(['success' => true, 'note' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Not found or permission denied.']);
        }
        exit;
    }
}

// --- Handle POST Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errorMsg = "Invalid CSRF token.";
    } else {
        // Upload (Single/Bulk)
        if ($action === 'upload') {
            $title = trim(strip_tags($_POST['title'] ?? ''));
            $desc = trim(strip_tags($_POST['description'] ?? ''));
            $subjectId = (int)$_POST['subject_id'];
            $year = trim(strip_tags($_POST['year'] ?? ''));
            $semester = (int)$_POST['semester'];

            if (empty($_FILES['files']['name'][0])) {
                $errorMsg = "Please select at least one file.";
            } elseif (empty($subjectId) || empty($year) || empty($semester)) {
                $errorMsg = "Subject, Year, and Semester are required.";
            } else {
                $successCount = 0;
                $failCount = 0;

                $fileCount = count($_FILES['files']['name']);
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                        $origName = basename($_FILES['files']['name'][$i]);
                        $fileSize = $_FILES['files']['size'][$i];
                        $tmpName = $_FILES['files']['tmp_name'][$i];

                        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $tmpName);
                        finfo_close($finfo);

                        // Overwrite title for bulk uploads
                        $fileTitle = ($fileCount > 1) ? pathinfo($origName, PATHINFO_FILENAME) : (empty($title) ? pathinfo($origName, PATHINFO_FILENAME) : $title);

                        if ($fileSize > 10 * 1024 * 1024) {
                            $failCount++;
                            continue;
                        }
                        if (!in_array($ext, $allowedExtensions) || !in_array($mime, $allowedMimeTypes)) {
                            $failCount++;
                            continue;
                        }

                        $uniqueName = uniqid() . '_' . time() . '.' . $ext;
                        if (move_uploaded_file($tmpName, $uploadDir . $uniqueName)) {
                            $stmt = $conn->prepare("INSERT INTO notes (title, description, file_name, original_name, file_size, file_type, subject_id, year, semester, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->bind_param("ssssssisii", $fileTitle, $desc, $uniqueName, $origName, $fileSize, $ext, $subjectId, $year, $semester, $userId);
                            if ($stmt->execute()) $successCount++;
                            else $failCount++;
                        } else {
                            $failCount++;
                        }
                    } else {
                        $failCount++;
                    }
                }

                if ($successCount > 0) {
                    $successMsg = "$successCount file(s) uploaded successfully.";
                    if ($failCount > 0) $successMsg .= " $failCount file(s) failed.";
                } else {
                    $errorMsg = "Failed to upload file(s). Invalid format or size > 10MB.";
                }
            }
        }

        // Edit Note Meta
        elseif ($action === 'edit') {
            $noteId = (int)$_POST['note_id'];
            $title = trim(strip_tags($_POST['title']));
            $desc = trim(strip_tags($_POST['description']));
            $subjectId = (int)$_POST['subject_id'];
            $year = trim($_POST['year']);
            $semester = (int)$_POST['semester'];

            $sql = "UPDATE notes SET title=?, description=?, subject_id=?, year=?, semester=? WHERE id=? AND is_deleted=0";
            if (!$isAdmin) $sql .= " AND created_by=" . (int)$userId;

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssisii", $title, $desc, $subjectId, $year, $semester, $noteId);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $successMsg = "Note updated successfully.";
            } else {
                $errorMsg = "Failed to update note or unauthorized.";
            }
        }

        // Single Delete
        elseif ($action === 'delete') {
            $noteId = (int)$_POST['note_id'];
            $sql = "UPDATE notes SET is_deleted=1 WHERE id=?";
            if (!$isAdmin) $sql .= " AND created_by=" . (int)$userId;

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $noteId);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $successMsg = "Note deleted successfully.";
            } else {
                $errorMsg = "Failed to delete note or unauthorized.";
            }
        }

        // Bulk Delete
        elseif ($action === 'bulk_delete') {
            if (!empty($_POST['note_ids']) && is_array($_POST['note_ids'])) {
                $ids = array_map('intval', $_POST['note_ids']);
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $types = str_repeat('i', count($ids));

                $sql = "UPDATE notes SET is_deleted=1 WHERE id IN ($placeholders)";
                if (!$isAdmin) {
                    $sql .= " AND created_by = ?";
                    $types .= 'i';
                    $ids[] = $userId;
                }

                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$ids);
                if ($stmt->execute()) {
                    $successMsg = $stmt->affected_rows . " note(s) deleted successfully.";
                }
            }
        }
    }
}

// --- Fetch Stats ---
$statsSql = "SELECT 
    COUNT(id) as total_notes,
    SUM(download_count) as total_downloads,
    COUNT(DISTINCT subject_id) as total_subjects,
    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as recent_uploads
FROM notes WHERE is_deleted = 0";

if (!$isAdmin) $statsSql .= " AND created_by = " . (int)$userId;
$stats = $conn->query($statsSql)->fetch_assoc();

// --- Build Table Query (with Filters & Pagination) ---
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$search = trim(strip_tags($_GET['search'] ?? ''));
$subjectFilter = (int)($_GET['subject'] ?? 0);
$yearFilter = trim(strip_tags($_GET['year'] ?? ''));
$semesterFilter = (int)($_GET['semester'] ?? 0);

$where = ["n.is_deleted = 0"];
$params = [];
$types = "";

if (!$isAdmin) {
    $where[] = "n.created_by = ?";
    $params[] = $userId;
    $types .= "i";
}
if ($search) {
    $where[] = "n.title LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}
if ($subjectFilter) {
    $where[] = "n.subject_id = ?";
    $params[] = $subjectFilter;
    $types .= "i";
}
if ($yearFilter) {
    $where[] = "n.year = ?";
    $params[] = $yearFilter;
    $types .= "s";
}
if ($semesterFilter) {
    $where[] = "n.semester = ?";
    $params[] = $semesterFilter;
    $types .= "i";
}

$whereClause = "WHERE " . implode(" AND ", $where);

// Count total
$countSql = "SELECT COUNT(*) as total FROM notes n $whereClause";
$stmtCount = $conn->prepare($countSql);
if ($types) $stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalRecords = $stmtCount->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

// Fetch paginated
$query = "SELECT n.*, s.name as subject_name 
          FROM notes n 
          JOIN subjects s ON n.subject_id = s.id 
          $whereClause ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$notesResult = $stmt->get_result();

// Get Subjects for dropdowns
$subjectsResult = $conn->query("SELECT * FROM subjects ORDER BY year, semester, name");
$subjects = [];
while ($row = $subjectsResult->fetch_assoc()) $subjects[] = $row;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Education Hub</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-info h3 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
        }

        .stat-info p {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
        }

        .main-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header {
            padding: 20px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table th {
            font-size: 13px;
            text-transform: uppercase;
            color: var(--text-muted);
            background: var(--surface-light);
            padding: 12px 16px;
            font-weight: 600;
            border-bottom: 1px solid var(--border);
        }

        .table td {
            vertical-align: middle;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        .table tr:hover {
            background: var(--surface-light);
        }

        .file-icon {
            font-size: 28px;
            line-height: 1;
        }

        .icon-pdf {
            color: #e21836;
        }

        .icon-doc,
        .icon-docx {
            color: #1a56db;
        }

        .icon-ppt,
        .icon-pptx {
            color: #d04423;
        }

        .icon-zip {
            color: #ffb100;
        }

        .floating-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            z-index: 1000;
            cursor: pointer;
            border: none;
        }

        .floating-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.05) translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 64px;
            color: var(--text-light);
        }

        .empty-state h4 {
            margin-top: 16px;
            color: var(--text);
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .custom-file-upload {
            border: 2px dashed var(--border);
            border-radius: var(--radius-md);
            padding: 40px;
            text-align: center;
            background: var(--surface-light);
            cursor: pointer;
            transition: all 0.3s;
        }

        .custom-file-upload:hover {
            border-color: var(--primary);
            background: rgba(26, 86, 219, 0.05);
        }

        #files-input {
            display: none;
        }

        /* Ensures sidebar overlap modal fixes */
        .modal {
            z-index: 1055 !important;
        }

        .modal-backdrop {
            z-index: 1050 !important;
        }

        .sidebar {
            z-index: 100;
        }

        body.modal-open {
            padding-right: 0 !important;
        }
    </style>
</head>

<body>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold m-0"><i class="bi bi-stack me-2"></i>Notes Management</h2>
                    <a href="?export=1" class="btn btn-outline-primary"><i class="bi bi-download me-2"></i>Export CSV</a>
                </div>

                <!-- Stats Section -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-file-earmark-text"></i></div>
                        <div class="stat-info">
                            <h3><?= (int)$stats['total_notes'] ?></h3>
                            <p>Total Notes</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-cloud-arrow-down"></i></div>
                        <div class="stat-info">
                            <h3><?= (int)$stats['total_downloads'] ?></h3>
                            <p>Total Downloads</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-journal-bookmark"></i></div>
                        <div class="stat-info">
                            <h3><?= (int)$stats['total_subjects'] ?></h3>
                            <p>Subjects Covered</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-calendar-check"></i></div>
                        <div class="stat-info">
                            <h3><?= (int)$stats['recent_uploads'] ?></h3>
                            <p>Recent (7 Days)</p>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="main-card p-3 mb-4">
                    <form method="GET" class="row border-0 gx-3 gy-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label text-muted fw-bold" style="font-size: 13px;">Search Title</label>
                            <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-muted fw-bold" style="font-size: 13px;">Subject</label>
                            <select name="subject" class="form-select">
                                <option value="">All Subjects</option>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $subjectFilter == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-muted fw-bold" style="font-size: 13px;">Year</label>
                            <select name="year" class="form-select">
                                <option value="">All</option>
                                <option value="FY" <?= $yearFilter == 'FY' ? 'selected' : '' ?>>FY</option>
                                <option value="SY" <?= $yearFilter == 'SY' ? 'selected' : '' ?>>SY</option>
                                <option value="TY" <?= $yearFilter == 'TY' ? 'selected' : '' ?>>TY</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-muted fw-bold" style="font-size: 13px;">Semester</label>
                            <select name="semester" class="form-select">
                                <option value="">All</option>
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?= $i ?>" <?= $semesterFilter == $i ? 'selected' : '' ?>>Sem <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Filter</button>
                            <a href="notes_management.php" class="btn btn-light border w-100"><i class="bi bi-x-circle me-1"></i>Reset</a>
                        </div>
                    </form>
                </div>

                <!-- Notes Table -->
                <form id="bulkDeleteForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="bulk_delete">

                    <div class="main-card">
                        <div class="card-header bg-white">
                            <h5 class="m-0 fw-bold">Note Inventory</h5>
                            <button type="button" class="btn btn-danger btn-sm" id="btnBulkDelete" style="display:none;" onclick="confirmBulkDelete()">
                                <i class="bi bi-trash me-1"></i>Delete Selected
                            </button>
                        </div>

                        <?php if ($totalRecords > 0): ?>
                            <div class="table-responsive">
                                <table class="table mb-0 border-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="checkAll"></th>
                                            <th>Note Detail</th>
                                            <th>Subject Area</th>
                                            <th>Size</th>
                                            <th>Metrics</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $notesResult->fetch_assoc()):
                                            $icon = 'bi-file-earmark';
                                            $iconClass = 'text-secondary';
                                            switch ($row['file_type']) {
                                                case 'pdf':
                                                    $icon = 'bi-file-earmark-pdf-fill';
                                                    $iconClass = 'icon-pdf';
                                                    break;
                                                case 'doc':
                                                case 'docx':
                                                    $icon = 'bi-file-earmark-word-fill';
                                                    $iconClass = 'icon-docx';
                                                    break;
                                                case 'ppt':
                                                case 'pptx':
                                                    $icon = 'bi-file-earmark-ppt-fill';
                                                    $iconClass = 'icon-pptx';
                                                    break;
                                                case 'zip':
                                                    $icon = 'bi-file-earmark-zip-fill';
                                                    $iconClass = 'icon-zip';
                                                    break;
                                            }
                                        ?>
                                            <tr>
                                                <td><input type="checkbox" name="note_ids[]" value="<?= $row['id'] ?>" class="form-check-input note-cb"></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <i class="bi <?= $icon ?> <?= $iconClass ?> file-icon"></i>
                                                        <div>
                                                            <h6 class="m-0 fw-bold text-dark"><?= htmlspecialchars($row['title']) ?></h6>
                                                            <small class="text-muted"><?= htmlspecialchars($row['original_name']) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle rounded-pill px-2 py-1">
                                                        <?= htmlspecialchars($row['subject_name']) ?>
                                                    </span>
                                                    <div class="mt-1"><small class="text-muted fw-bold"><?= $row['year'] ?> - Sem <?= $row['semester'] ?></small></div>
                                                </td>
                                                <td>
                                                    <span class="fw-semibold"><?= number_format($row['file_size'] / 1024, 1) ?> KB</span>
                                                    <div class="text-muted" style="font-size: 11px;"><?= strtoupper($row['file_type']) ?></div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-1 text-success fw-bold">
                                                        <i class="bi bi-cloud-download"></i> <?= $row['download_count'] ?>
                                                    </div>
                                                    <div class="text-muted mt-1" style="font-size: 11px;"><i class="bi bi-clock me-1"></i><?= date('M d, Y', strtotime($row['created_at'])) ?></div>
                                                </td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-sm btn-light border text-primary" onclick="previewNote(<?= $row['id'] ?>, '<?= $row['file_type'] ?>', '<?= htmlspecialchars(addslashes($row['file_name'])) ?>', '<?= htmlspecialchars(addslashes($row['original_name'])) ?>')" title="Preview">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <a href="?action=download&id=<?= $row['id'] ?>" class="btn btn-sm btn-light border text-success" title="Download">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-light border text-warning" onclick="openEditModal(<?= $row['id'] ?>)" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-light border text-danger" onclick="confirmDelete(<?= $row['id'] ?>)" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <div class="card-footer bg-white border-top border-light d-flex justify-content-between align-items-center">
                                    <span class="text-muted" style="font-size: 13px;">Showing page <?= $page ?> of <?= $totalPages ?></span>
                                    <ul class="pagination pagination-sm m-0">
                                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&subject=<?= $subjectFilter ?>&year=<?= $yearFilter ?>&semester=<?= $semesterFilter ?>">Prev</a>
                                        </li>
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&subject=<?= $subjectFilter ?>&year=<?= $yearFilter ?>&semester=<?= $semesterFilter ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&subject=<?= $subjectFilter ?>&year=<?= $yearFilter ?>&semester=<?= $semesterFilter ?>">Next</a>
                                        </li>
                                    </ul>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-folder-x"></i>
                                <h4>No Notes Found</h4>
                                <p class="text-muted text-center" style="max-width: 400px; margin: 0 auto;">You have not uploaded any notes yet, or no notes match your current filters.</p>
                                <button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                    <i class="bi bi-cloud-arrow-up me-2"></i>Upload Note
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>

            </div>
        </main>
    </div>

    <!-- Floating Action Button -->
    <button class="floating-btn" data-bs-toggle="modal" data-bs-target="#uploadModal" title="Upload New Notes">
        <i class="bi bi-plus"></i>
    </button>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-xl);">
                <div class="modal-header border-0 bg-primary text-white" style="border-radius: var(--radius-xl) var(--radius-xl) 0 0;">
                    <h5 class="modal-title fw-bold"><i class="bi bi-cloud-upload me-2"></i>Upload Notes</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="modal-body p-4">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="upload">

                        <div class="alert alert-info border-0 rounded-3 mb-4 text-primary bg-primary bg-opacity-10 d-flex gap-3">
                            <i class="bi bi-info-circle-fill fs-4 mt-1"></i>
                            <div>
                                <strong>Bulk Upload Supported!</strong><br>
                                You can select multiple files at once. The selected Subject, Year, and Semester will apply to all of them. The Title field will be ignored if multiple files are selected, using original filenames instead. Max 10MB per file.
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Note Title (Optional for Bulk)</label>
                                <input type="text" name="title" class="form-control" placeholder="E.g., Intro to PHP Variables">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Description (Optional)</label>
                                <textarea name="description" class="form-control" rows="2" placeholder="Brief context about these notes..."></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Subject <span class="text-danger">*</span></label>
                                <select name="subject_id" class="form-select" required>
                                    <option value="">Select...</option>
                                    <?php foreach ($subjects as $s): ?>
                                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= $s['year'] ?>-S<?= $s['semester'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Year <span class="text-danger">*</span></label>
                                <select name="year" class="form-select" required>
                                    <option value="FY">FY</option>
                                    <option value="SY">SY</option>
                                    <option value="TY">TY</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Semester <span class="text-danger">*</span></label>
                                <select name="semester" class="form-select" required>
                                    <?php for ($i = 1; $i <= 6; $i++) echo "<option value='$i'>$i</option>"; ?>
                                </select>
                            </div>
                            <div class="col-md-12 mt-4">
                                <label class="form-label fw-bold mb-2">Select Files (PDF, DOC/X, PPT/X, ZIP) <span class="text-danger">*</span></label>
                                <label class="custom-file-upload w-100 d-block" for="files-input">
                                    <i class="bi bi-cloud-arrow-up text-primary" style="font-size: 48px;"></i>
                                    <h5 class="mt-3 text-dark">Click to browse or Drag & Drop files</h5>
                                    <p class="text-muted mb-0">Select multiple files at once using Shift/Ctrl</p>
                                    <div id="file-selection-list" class="mt-3 text-success fw-bold"></div>
                                </label>
                                <input type="file" id="files-input" name="files[]" multiple accept=".pdf,.doc,.docx,.ppt,.pptx,.zip" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4">
                        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-upload me-2"></i>Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Note Info</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="note_id" id="edit_note_id">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="edit_title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-bold">Subject <span class="text-danger">*</span></label>
                                <select name="subject_id" id="edit_subject" class="form-select" required>
                                    <?php foreach ($subjects as $s): ?>
                                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold">Year <span class="text-danger">*</span></label>
                                <select name="year" id="edit_year" class="form-select" required>
                                    <option value="FY">FY</option>
                                    <option value="SY">SY</option>
                                    <option value="TY">TY</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold">Semester <span class="text-danger">*</span></label>
                                <select name="semester" id="edit_semester" class="form-select" required>
                                    <?php for ($i = 1; $i <= 6; $i++) echo "<option value='$i'>$i</option>"; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4">
                        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning px-4"><i class="bi bi-check-circle me-1"></i>Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-dark text-white border-0 py-3">
                    <h5 class="modal-title fw-bold text-truncate" id="previewTitle" style="max-width: 80%;">Preview</h5>
                    <div class="d-flex gap-2">
                        <a href="#" id="previewDownloadBtn" class="btn btn-sm btn-success px-3"><i class="bi bi-download me-1"></i>Download</a>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-0 bg-light position-relative" style="height: 75vh;">
                    <iframe id="previewIframe" src="" style="width: 100%; height: 100%; border: none; display: none;"></iframe>
                    <div id="noPreviewMsg" class="d-flex flex-column align-items-center justify-content-center h-100" style="display: none !important;">
                        <i class="bi bi-file-earmark-x" style="font-size: 80px; color: var(--text-muted);"></i>
                        <h4 class="mt-3">Preview Not Available</h4>
                        <p class="text-muted">This file type cannot be previewed in the browser.</p>
                        <p class="text-muted">Please download the file to view it.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Single Form -->
    <form id="deleteForm" method="POST" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="note_id" id="delete_note_id">
    </form>

    <!-- Toast Notifications -->
    <div class="toast-container">
        <?php if ($successMsg): ?>
            <div class="toast show align-items-center text-bg-success border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4000">
                <div class="d-flex">
                    <div class="toast-body fw-semibold"><i class="bi bi-check-circle-fill me-2 fs-5"></i><?= htmlspecialchars($successMsg) ?></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="toast show align-items-center text-bg-danger border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="6000">
                <div class="d-flex">
                    <div class="toast-body fw-semibold"><i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i><?= htmlspecialchars($errorMsg) ?></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File input summary display
        document.getElementById('files-input').addEventListener('change', function(e) {
            const list = document.getElementById('file-selection-list');
            const files = e.target.files;
            if (files.length > 0) {
                list.innerHTML = `<i class="bi bi-check2-all me-1"></i> ${files.length} file(s) selected`;
            } else {
                list.innerHTML = '';
            }
        });

        // Edit Modal Filler
        function openEditModal(id) {
            fetch(`?action=get_note&id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_note_id').value = data.note.id;
                        document.getElementById('edit_title').value = data.note.title;
                        document.getElementById('edit_description').value = data.note.description;
                        document.getElementById('edit_subject').value = data.note.subject_id;
                        document.getElementById('edit_year').value = data.note.year;
                        document.getElementById('edit_semester').value = data.note.semester;
                        new bootstrap.Modal(document.getElementById('editModal')).show();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(err => alert("Error fetching data."));
        }

        // Preview Logic
        function previewNote(id, type, filename, originalName) {
            const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
            document.getElementById('previewTitle').innerText = originalName;
            document.getElementById('previewDownloadBtn').href = `?action=download&id=${id}`;

            const iframe = document.getElementById('previewIframe');
            const noPreview = document.getElementById('noPreviewMsg');

            if (type.toLowerCase() === 'pdf') {
                noPreview.style.setProperty('display', 'none', 'important');
                iframe.style.display = 'block';
                // Serve safely through download action using inline header
                iframe.src = `?action=preview&id=${id}`;
            } else {
                iframe.style.display = 'none';
                noPreview.style.setProperty('display', 'flex', 'important');
            }
            previewModal.show();
        }

        // Delete Confirm
        function confirmDelete(id) {
            if (confirm("Are you sure you want to delete this note? This action cannot be undone.")) {
                document.getElementById('delete_note_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        // Bulk Delete Checkbox Logic
        const checkAll = document.getElementById('checkAll');
        const cbs = document.querySelectorAll('.note-cb');
        const bulkBtn = document.getElementById('btnBulkDelete');

        if (checkAll) {
            checkAll.addEventListener('change', (e) => {
                cbs.forEach(cb => cb.checked = e.target.checked);
                toggleBulkBtn();
            });
        }

        cbs.forEach(cb => cb.addEventListener('change', toggleBulkBtn));

        function toggleBulkBtn() {
            const checkedCount = document.querySelectorAll('.note-cb:checked').length;
            bulkBtn.style.display = checkedCount > 0 ? 'inline-block' : 'none';
        }

        function confirmBulkDelete() {
            if (confirm("Are you sure you want to delete all selected notes?")) {
                document.getElementById('bulkDeleteForm').submit();
            }
        }

        // Auto hide toasts
        document.querySelectorAll('.toast').forEach(t => {
            const toast = new bootstrap.Toast(t);
            toast.show();
            setTimeout(() => {
                toast.hide();
            }, 5000);
        });
    </script>
</body>

</html>