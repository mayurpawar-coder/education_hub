<?php
require_once 'config/functions.php';
requireTeacher();

$pageTitle = 'Manage Questions';
$errors = [];
$success = '';

// ============================================================
// FUNCTION DEFINITIONS (must be before any calls in PHP)
// ============================================================

function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function handlePostRequest()
{
    global $conn, $errors, $success;

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_question':
        case 'edit_question':
            handleQuestionSave();
            break;
        case 'delete_question':
            handleQuestionDelete();
            break;
        case 'bulk_delete':
            handleBulkDelete();
            break;
        case 'clone_question':
            handleQuestionClone();
            break;
        case 'bulk_upload':
            handleBulkUpload();
            break;
        default:
            $errors[] = 'Invalid action requested.';
    }
}

function handleQuestionSave()
{
    global $conn, $errors, $success;

    $questionId = $_POST['question_id'] ?? null;
    $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

    // Sanitize only for display/storage — strip_tags only (no double-escaping)
    $data = [
        'question_text' => trim(strip_tags($_POST['question_text'] ?? '')),
        'subject_id'    => (int)($_POST['subject_id'] ?? 0),
        'year'          => $_POST['year'] ?? 'FY',
        'semester'      => (int)($_POST['semester'] ?? 1),
        'option_a'      => trim(strip_tags($_POST['option_a'] ?? '')),
        'option_b'      => trim(strip_tags($_POST['option_b'] ?? '')),
        'option_c'      => trim(strip_tags($_POST['option_c'] ?? '')),
        'option_d'      => trim(strip_tags($_POST['option_d'] ?? '')),
        'correct_answer' => $_POST['correct_answer'] ?? '',
        'difficulty'    => $_POST['difficulty'] ?? 'medium'
    ];

    if (
        empty($data['question_text']) || empty($data['subject_id']) ||
        empty($data['option_a']) || empty($data['option_b']) ||
        empty($data['option_c']) || empty($data['option_d']) ||
        empty($data['correct_answer'])
    ) {
        $errors[] = 'All required fields must be filled.';
        return;
    }

    if (!in_array($data['correct_answer'], ['A', 'B', 'C', 'D'])) {
        $errors[] = 'Invalid correct answer selected.';
        return;
    }

    $duplicateQuery = "SELECT id FROM questions WHERE question_text = ? AND subject_id = ? AND id != ?";
    $stmt = $conn->prepare($duplicateQuery);
    $excludeId = $questionId ? (int)$questionId : 0;
    $stmt->bind_param("sii", $data['question_text'], $data['subject_id'], $excludeId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = 'A similar question already exists for this subject.';
        return;
    }

    if ($questionId) {
        // Admins can edit any question; teachers can only edit their own
        if ($isAdmin) {
            $query = "UPDATE questions SET question_text=?, option_a=?, option_b=?, option_c=?, option_d=?,
                      correct_answer=?, subject_id=?, year=?, semester=?, difficulty=? WHERE id=?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param(
                "ssssssissii",
                $data['question_text'],
                $data['option_a'],
                $data['option_b'],
                $data['option_c'],
                $data['option_d'],
                $data['correct_answer'],
                $data['subject_id'],
                $data['year'],
                $data['semester'],
                $data['difficulty'],
                $questionId
            );
        } else {
            $query = "UPDATE questions SET question_text=?, option_a=?, option_b=?, option_c=?, option_d=?,
                      correct_answer=?, subject_id=?, year=?, semester=?, difficulty=? WHERE id=? AND created_by=?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param(
                "ssssssissiii",
                $data['question_text'],
                $data['option_a'],
                $data['option_b'],
                $data['option_c'],
                $data['option_d'],
                $data['correct_answer'],
                $data['subject_id'],
                $data['year'],
                $data['semester'],
                $data['difficulty'],
                $questionId,
                $_SESSION['user_id']
            );
        }
        $actionText = 'updated';
    } else {
        $query = "INSERT INTO questions (question_text, option_a, option_b, option_c, option_d,
                  correct_answer, subject_id, year, semester, difficulty, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "ssssssissii",
            $data['question_text'],
            $data['option_a'],
            $data['option_b'],
            $data['option_c'],
            $data['option_d'],
            $data['correct_answer'],
            $data['subject_id'],
            $data['year'],
            $data['semester'],
            $data['difficulty'],
            $_SESSION['user_id']
        );
        $actionText = 'added';
    }

    if ($stmt->execute()) {
        $success = "Question $actionText successfully!";
        if (!$questionId) {
            header("Location: manage_questions.php?success=" . urlencode($success));
            exit;
        }
    } else {
        $errors[] = "Failed to $actionText question: " . $conn->error;
    }
}

function handleQuestionDelete()
{
    global $conn, $errors, $success;

    $questionId = (int)($_POST['question_id'] ?? 0);
    $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

    if ($isAdmin) {
        $stmt = $conn->prepare("DELETE FROM questions WHERE id = ?");
        $stmt->bind_param("i", $questionId);
    } else {
        $stmt = $conn->prepare("DELETE FROM questions WHERE id = ? AND created_by = ?");
        $stmt->bind_param("ii", $questionId, $_SESSION['user_id']);
    }

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success = 'Question deleted successfully.';
    } else {
        $errors[] = 'Failed to delete question (not found or permission denied).';
    }
}

function handleBulkDelete()
{
    global $conn, $errors, $success;
    $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

    $questionIds = $_POST['question_ids'] ?? [];
    if (!empty($questionIds)) {
        $placeholders = str_repeat('?,', count($questionIds) - 1) . '?';
        if ($isAdmin) {
            $stmt = $conn->prepare("DELETE FROM questions WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($questionIds)), ...$questionIds);
        } else {
            $stmt = $conn->prepare("DELETE FROM questions WHERE id IN ($placeholders) AND created_by = ?");
            $params = array_merge($questionIds, [$_SESSION['user_id']]);
            $stmt->bind_param(str_repeat('i', count($params)), ...$params);
        }
        $stmt->execute();
        $success = count($questionIds) . ' questions deleted.';
    }
}

function handleQuestionClone()
{
    global $conn, $errors, $success;

    $questionId = (int)($_POST['question_id'] ?? 0);

    $stmt = $conn->prepare("INSERT INTO questions (question_text, option_a, option_b, option_c, option_d,
                           correct_answer, subject_id, year, semester, difficulty, created_by)
                           SELECT CONCAT(question_text, ' (Copy)'), option_a, option_b, option_c, option_d,
                           correct_answer, subject_id, year, semester, difficulty, ?
                           FROM questions WHERE id = ?");
    $stmt->bind_param("ii", $_SESSION['user_id'], $questionId);

    if ($stmt->execute()) {
        $success = 'Question cloned successfully!';
    } else {
        $errors[] = 'Failed to clone question.';
    }
}

function handleBulkUpload()
{
    global $conn, $errors, $success;

    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please select a valid CSV file.';
        return;
    }

    $file = $_FILES['excel_file']['tmp_name'];
    $handle = fopen($file, 'r');
    if (!$handle) {
        $errors[] = 'Failed to read the uploaded file.';
        return;
    }

    $header = fgetcsv($handle);
    $expectedHeaders = ['question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer', 'subject_id', 'year', 'semester', 'difficulty'];

    if ($header !== $expectedHeaders) {
        $errors[] = 'Invalid CSV format. Please use the correct template.';
        fclose($handle);
        return;
    }

    $successCount = 0;
    $errorCount = 0;

    while (($rowData = fgetcsv($handle)) !== false) {
        if (count($rowData) !== 10) {
            $errorCount++;
            continue;
        }

        $row = [
            'question_text' => trim($rowData[0]),
            'option_a'      => trim($rowData[1]),
            'option_b'      => trim($rowData[2]),
            'option_c'      => trim($rowData[3]),
            'option_d'      => trim($rowData[4]),
            'correct_answer' => strtoupper(trim($rowData[5])),
            'subject_id'    => (int)trim($rowData[6]),
            'year'          => trim($rowData[7]) ?: 'FY',
            'semester'      => (int)(trim($rowData[8]) ?: 1),
            'difficulty'    => trim($rowData[9]) ?: 'medium'
        ];

        if (
            empty($row['question_text']) || empty($row['option_a']) || empty($row['option_b']) ||
            empty($row['option_c']) || empty($row['option_d']) || empty($row['correct_answer'])
        ) {
            $errorCount++;
            continue;
        }

        if (!in_array($row['correct_answer'], ['A', 'B', 'C', 'D'])) {
            $errorCount++;
            continue;
        }

        $stmt = $conn->prepare("INSERT INTO questions (question_text, option_a, option_b, option_c, option_d,
                               correct_answer, subject_id, year, semester, difficulty, created_by)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "ssssssissii",
            $row['question_text'],
            $row['option_a'],
            $row['option_b'],
            $row['option_c'],
            $row['option_d'],
            $row['correct_answer'],
            $row['subject_id'],
            $row['year'],
            $row['semester'],
            $row['difficulty'],
            $_SESSION['user_id']
        );

        if ($stmt->execute()) {
            $successCount++;
        } else {
            $errorCount++;
        }
    }

    fclose($handle);

    if ($successCount > 0) {
        $success = "Bulk upload completed! $successCount questions added successfully.";
        if ($errorCount > 0) {
            $success .= " $errorCount questions failed to import.";
        }
    } else {
        $errors[] = "No questions were imported. Please check your CSV file.";
    }
}

function handleGetRequest()
{
    global $conn;

    if (isset($_GET['export'])) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="questions_export_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Question', 'Option_A', 'Option_B', 'Option_C', 'Option_D', 'Correct', 'Subject', 'Year', 'Semester', 'Difficulty', 'Created']);

        $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
        if ($isAdmin) {
            $query = "SELECT q.*, s.name as subject_name FROM questions q
                     JOIN subjects s ON q.subject_id = s.id
                     ORDER BY q.created_at DESC";
            $result = $conn->query($query);
        } else {
            $query = "SELECT q.*, s.name as subject_name FROM questions q
                     JOIN subjects s ON q.subject_id = s.id
                     WHERE q.created_by = ? ORDER BY q.created_at DESC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
        }

        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'] ?? '',
                $row['question_text'] ?? '',
                $row['option_a'] ?? '',
                $row['option_b'] ?? '',
                $row['option_c'] ?? '',
                $row['option_d'] ?? '',
                $row['correct_answer'] ?? '',
                $row['subject_name'] ?? '',
                $row['year'] ?? 'FY',
                $row['semester'] ?? 1,
                $row['difficulty'] ?? 'medium',
                $row['created_at'] ?? ''
            ]);
        }
        fclose($output);
        exit;
    }

    if (isset($_GET['download_template'])) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="question_template.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer', 'subject_id', 'year', 'semester', 'difficulty']);
        fputcsv($output, ['What is 2+2?', '3', '4', '5', '6', 'B', '1', 'FY', '1', 'easy']);
        fputcsv($output, ['What does HTML stand for?', 'HyperText Markup Language', 'High Tech Modern Language', 'Home Tool Markup Language', 'Hyper Transfer Markup Language', 'A', '9', 'SY', '4', 'medium']);
        fclose($output);
        exit;
    }

    if (isset($_GET['get_question'])) {
        header('Content-Type: application/json');
        $questionId = (int)$_GET['get_question'];
        $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

        if ($isAdmin) {
            $stmt = $conn->prepare("SELECT q.*, s.name as subject_name FROM questions q
                                   JOIN subjects s ON q.subject_id = s.id
                                   WHERE q.id = ?");
            $stmt->bind_param("i", $questionId);
        } else {
            $stmt = $conn->prepare("SELECT q.*, s.name as subject_name FROM questions q
                                   JOIN subjects s ON q.subject_id = s.id
                                   WHERE q.id = ? AND q.created_by = ?");
            $stmt->bind_param("ii", $questionId, $_SESSION['user_id']);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            echo json_encode(['success' => true, 'question' => $result->fetch_assoc()]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }
}

function getDisplayData()
{
    global $conn, $success;

    // Preserve success message set by POST handlers; only override from URL if not already set
    if (empty($success) && isset($_GET['success'])) {
        $success = htmlspecialchars(strip_tags(trim($_GET['success'])));
    }

    $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

    $search          = htmlspecialchars(strip_tags(trim($_GET['search'] ?? '')));
    $subjectFilter   = htmlspecialchars(strip_tags(trim($_GET['subject'] ?? '')));
    $yearFilter      = htmlspecialchars(strip_tags(trim($_GET['year'] ?? '')));
    $semesterFilter  = htmlspecialchars(strip_tags(trim($_GET['semester'] ?? '')));
    $difficultyFilter = htmlspecialchars(strip_tags(trim($_GET['difficulty'] ?? '')));
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 10;
    $offset  = ($page - 1) * $perPage;

    $whereConditions = [];
    $params = [];
    $types  = "";

    // Admins see all questions; teachers only see their own
    if (!$isAdmin) {
        $whereConditions[] = "q.created_by = ?";
        $params[] = $_SESSION['user_id'];
        $types   .= "i";
    }

    if ($search) {
        $whereConditions[] = "q.question_text LIKE ?";
        $params[] = "%$search%";
        $types   .= "s";
    }
    if ($subjectFilter) {
        $whereConditions[] = "q.subject_id = ?";
        $params[] = $subjectFilter;
        $types   .= "i";
    }
    if ($yearFilter) {
        $whereConditions[] = "q.year = ?";
        $params[] = $yearFilter;
        $types   .= "s";
    }
    if ($semesterFilter) {
        $whereConditions[] = "q.semester = ?";
        $params[] = $semesterFilter;
        $types   .= "i";
    }
    if ($difficultyFilter) {
        $whereConditions[] = "q.difficulty = ?";
        $params[] = $difficultyFilter;
        $types   .= "s";
    }

    $whereClause = !empty($whereConditions)
        ? 'WHERE ' . implode(" AND ", $whereConditions)
        : '';

    $countQuery = "SELECT COUNT(*) as total FROM questions q $whereClause";
    if (!empty($types)) {
        $countStmt = $conn->prepare($countQuery);
        $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $totalQuestions = $countStmt->get_result()->fetch_assoc()['total'] ?? 0;
    } else {
        $totalQuestions = $conn->query($countQuery)->fetch_assoc()['total'] ?? 0;
    }
    $totalPages = ceil($totalQuestions / $perPage);

    $query = "SELECT q.*, s.name as subject_name FROM questions q
             JOIN subjects s ON q.subject_id = s.id
             $whereClause ORDER BY q.created_at DESC LIMIT ? OFFSET ?";
    if (!empty($types)) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types . "ii", ...array_merge($params, [$perPage, $offset]));
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $perPage, $offset);
    }
    $stmt->execute();
    $questions = $stmt->get_result();

    $subjects = $conn->query("SELECT * FROM subjects ORDER BY year, semester, name");

    return compact(
        'success',
        'search',
        'subjectFilter',
        'yearFilter',
        'semesterFilter',
        'difficultyFilter',
        'page',
        'totalQuestions',
        'totalPages',
        'questions',
        'subjects',
        'isAdmin'
    );
}

// ============================================================
// MAIN EXECUTION (now all functions are defined above)
// ============================================================

$csrf_token = generateCSRFToken();

// Handle all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Security validation failed.';
    } else {
        handlePostRequest();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleGetRequest();
}

$data = getDisplayData();
extract($data);


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Education Hub</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        /* Hero Section - Enhanced with theme reference */
        .hero-section {
            text-align: center;
            padding: 36px 24px;
            margin: -1rem -1rem 2rem -1rem;
            background: linear-gradient(135deg, var(--sky-light) 0%, var(--primary-light) 100%);
            border-radius: 0 0 var(--radius-xl) var(--radius-xl);
            border: 1px solid var(--border);
        }

        .hero-section h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 6px;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-section p {
            color: var(--text-muted);
            font-size: 15px;
        }

        /* Form Container - Centered max-width like reference */
        .form-container {
            max-width: 700px;
            margin: 0 auto;
        }

        .form-card {
            background: var(--surface);
            border-radius: var(--radius-xl);
            padding: 32px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }

        /* Form Labels - Matching reference styling */
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
            color: var(--text);
        }

        /* Form Inputs - Enhanced focus states like reference */
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: var(--radius-md);
            background: var(--surface-light);
            color: var(--text);
            font-size: 15px;
            font-family: inherit;
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(26, 86, 219, 0.1);
        }

        /* 2-column grid for form elements */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        /* Full-width items span both columns */
        .form-group.full-width {
            grid-column: 1 / -1;
        }

        /* Filter Card - Enhanced styling */
        .filter-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 24px;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }

        /* Stats Cards - Professional grid layout */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
        }

        /* Main Card - Enhanced with reference styling */
        .main-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .main-card .card-header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 1rem 1.5rem;
        }

        /* Table - Improved with reference styling */
        .table-responsive {
            border-radius: 0;
        }

        .table {
            border-radius: var(--radius-sm);
            overflow: hidden;
            margin-bottom: 0;
        }

        .table thead th {
            background: var(--primary-lighter);
            color: var(--text-muted);
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table tbody tr:hover {
            background: var(--primary-lighter);
        }

        .table tbody tr td {
            vertical-align: middle;
            padding: 1rem;
            border-bottom: 1px solid var(--border);
        }

        /* Button improvements - Using reference patterns */
        .btn-sm {
            padding: 6px 10px;
            font-size: 13px;
            border-radius: var(--radius-sm);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
            border: none;
        }

        .btn-danger:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }

        .btn-outline-primary {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            border-color: var(--primary);
        }

        .btn-outline-secondary {
            border-color: var(--primary-light);
            color: var(--text);
        }

        .btn-outline-secondary:hover {
            background: var(--primary-light);
            border-color: var(--primary-light);
        }

        .btn-outline-danger {
            border-color: var(--danger);
            color: var(--danger);
        }

        .btn-outline-danger:hover {
            background: var(--danger);
            border-color: var(--danger);
        }

        /* Button upload styling - Matching reference */
        .btn-upload {
            width: 100%;
            padding: 16px;
            font-size: 17px;
            font-weight: 700;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-upload:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-primary);
        }

        /* Badge improvements */
        .badge {
            border-radius: var(--radius-md);
            font-weight: 600;
            padding: 0.375rem 0.75rem;
        }

        .badge-success {
            background: var(--success);
            color: white;
        }

        .badge-warning {
            background: var(--warning);
            color: var(--text);
        }

        .badge-danger {
            background: var(--danger);
            color: white;
        }

        /* Action buttons - Improved layout */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Modal positioning fixes for sidebar layout */
        .modal {
            z-index: 1055 !important;
        }

        .modal-backdrop {
            z-index: 1050 !important;
        }

        /* Ensure sidebar stays below modal */
        .layout .sidebar {
            z-index: 100;
        }

        /* Prevent body scroll lock issues */
        body.modal-open {
            overflow: auto;
            padding-right: 0 !important;
        }

        /* Modal content improvements */
        .modal-content {
            border-radius: var(--radius-lg);
            border: none;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            background: var(--primary);
            color: white;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            border-bottom: none;
        }

        /* Form improvements in modal */
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 86, 219, 0.1);
        }

        /* Alert improvements */
        .alert {
            border-radius: var(--radius-md);
            border: none;
            margin-bottom: 1rem;
        }

        /* Pagination improvements */
        .pagination {
            justify-content: center;
            margin-top: 2rem;
        }

        .page-link {
            border-radius: var(--radius-sm);
            margin: 0 2px;
            border: 1px solid var(--border);
            color: var(--primary);
        }

        .page-link:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .page-item.active .page-link {
            background: var(--primary);
            border-color: var(--primary);
        }

        /* Question preview */
        .question-preview {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: help;
        }

        /* Correct answer styling */
        .correct-answer {
            background: var(--success);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-md);
            font-weight: bold;
            font-size: 0.9rem;
        }

        /* Container improvements */
        .container-fluid {
            max-width: none;
            padding-left: 1rem;
            padding-right: 1rem;
        }

        /* Responsive improvements - Enhanced mobile handling */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }

            .hero-section {
                text-align: center;
                padding: 1.5rem 1rem;
            }

            .container-fluid {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }

            .table-responsive {
                font-size: 0.9rem;
            }

            .table tbody tr {
                display: block;
                margin-bottom: 12px;
                background: var(--surface);
                padding: 12px;
                border-radius: var(--radius-sm);
            }

            .table tbody tr td {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
            }

            .table thead {
                display: none;
            }
        }

        @media (max-width: 576px) {
            .filter-card {
                padding: 1rem;
            }

            .hero-section {
                margin: -0.5rem -0.5rem 1rem -0.5rem;
                padding: 1rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-number {
                font-size: 1.5rem;
            }
        }

        /* Mobile responsive for form grid */
        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="container-fluid px-4 py-4">
                <div class="hero-section mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="display-4 fw-bold mb-0"><?php echo htmlspecialchars($pageTitle); ?></h1>
                            <p class="lead mb-0">Create, manage, and organize your quiz questions with advanced filtering and bulk operations.</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="d-flex gap-2 justify-content-end flex-wrap">
                                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                                    <i class="bi bi-plus-circle-fill me-2"></i>Add Question
                                </button>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">
                                    <i class="bi bi-upload me-2"></i>Bulk Upload
                                </button>
                                <a href="?export=1" class="btn btn-info">
                                    <i class="bi bi-download me-2"></i>Export CSV
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="stats-grid mb-4">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $totalQuestions; ?></div>
                        <div class="stat-label">Total Questions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number text-success">
                            <?php
                            $activeQuery = $conn->prepare("SELECT COUNT(*) as count FROM questions WHERE created_by = ?");
                            $activeQuery->bind_param("i", $_SESSION['user_id']);
                            $activeQuery->execute();
                            echo $activeQuery->get_result()->fetch_assoc()['count'] ?? 0;
                            ?>
                        </div>
                        <div class="stat-label">Your Questions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number text-warning">
                            <?php
                            $subjectsQuery = $conn->prepare("SELECT COUNT(DISTINCT subject_id) as count FROM questions WHERE created_by = ?");
                            $subjectsQuery->bind_param("i", $_SESSION['user_id']);
                            $subjectsQuery->execute();
                            echo $subjectsQuery->get_result()->fetch_assoc()['count'] ?? 0;
                            ?>
                        </div>
                        <div class="stat-label">Subjects Covered</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number text-info">
                            <?php
                            $recentQuery = $conn->prepare("SELECT COUNT(*) as count FROM questions WHERE created_by = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                            $recentQuery->bind_param("i", $_SESSION['user_id']);
                            $recentQuery->execute();
                            echo $recentQuery->get_result()->fetch_assoc()['count'] ?? 0;
                            ?>
                        </div>
                        <div class="stat-label">Added This Week</div>
                    </div>
                </div>

                <div class="filter-card mb-4">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Search Questions</label>
                            <input type="text" class="form-control" name="search" placeholder="Enter question text..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Subject</label>
                            <select class="form-select" name="subject">
                                <option value="">All Subjects</option>
                                <?php
                                $subjects->data_seek(0);
                                while ($subject = $subjects->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $subject['id']; ?>" <?php echo $subjectFilter == $subject['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subject['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label fw-semibold">Year</label>
                            <select class="form-select" name="year">
                                <option value="">All</option>
                                <option value="FY" <?php echo $yearFilter == 'FY' ? 'selected' : ''; ?>>FY</option>
                                <option value="SY" <?php echo $yearFilter == 'SY' ? 'selected' : ''; ?>>SY</option>
                                <option value="TY" <?php echo $yearFilter == 'TY' ? 'selected' : ''; ?>>TY</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label fw-semibold">Semester</label>
                            <select class="form-select" name="semester">
                                <option value="">All</option>
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $semesterFilter == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Difficulty</label>
                            <select class="form-select" name="difficulty">
                                <option value="">All Levels</option>
                                <option value="easy" <?php echo $difficultyFilter == 'easy' ? 'selected' : ''; ?>>Easy</option>
                                <option value="medium" <?php echo $difficultyFilter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="hard" <?php echo $difficultyFilter == 'hard' ? 'selected' : ''; ?>>Hard</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search me-1"></i>Filter
                                </button>
                                <a href="manage_questions.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle me-1"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="main-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Questions (<?php echo $totalQuestions; ?>)</h5>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAll">
                            <label class="form-check-label fw-semibold" for="selectAll">
                                Select All
                            </label>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <form id="bulkDeleteForm" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="bulk_delete">

                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th width="5%" class="text-center"><input type="checkbox" id="selectAllTop"></th>
                                            <th width="5%">ID</th>
                                            <th width="25%">Question</th>
                                            <th width="15%">Subject</th>
                                            <th width="8%">Year</th>
                                            <th width="8%">Sem</th>
                                            <th width="10%">Difficulty</th>
                                            <th width="10%">Correct</th>
                                            <th width="14%">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($question = $questions->fetch_assoc()): ?>
                                            <tr>
                                                <td class="text-center"><input type="checkbox" name="question_ids[]" value="<?php echo $question['id']; ?>" class="question-checkbox"></td>
                                                <td><strong><?php echo $question['id']; ?></strong></td>
                                                <td>
                                                    <div class="question-preview" title="<?php echo htmlspecialchars($question['question_text'] ?? ''); ?>">
                                                        <?php echo htmlspecialchars(substr($question['question_text'] ?? '', 0, 50)); ?>...
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($question['subject_name'] ?? ''); ?></td>
                                                <td><span class="badge bg-light text-dark"><?php echo $question['year'] ?? 'FY'; ?></span></td>
                                                <td><span class="badge bg-light text-dark"><?php echo $question['semester'] ?? 1; ?></span></td>
                                                <td>
                                                    <?php
                                                    $difficulty = $question['difficulty'] ?? 'medium';
                                                    $badgeClass = $difficulty == 'easy' ? 'badge-success' : ($difficulty == 'medium' ? 'badge-warning' : 'badge-danger');
                                                    ?>
                                                    <span class="badge <?php echo $badgeClass; ?>">
                                                        <?php echo ucfirst($difficulty); ?>
                                                    </span>
                                                </td>
                                                <td><span class="correct-answer"><?php echo $question['correct_answer'] ?? ''; ?></span></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewQuestion(<?php echo $question['id']; ?>)">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="editQuestion(<?php echo $question['id']; ?>)">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="cloneQuestion(<?php echo $question['id']; ?>)">
                                                            <i class="bi bi-copy"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteQuestion(<?php echo $question['id']; ?>)">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-between align-items-center p-3">
                                <div>
                                    <button type="submit" class="btn btn-danger" id="bulkDeleteBtn" style="display: none;">
                                        <i class="bi bi-trash me-1"></i>Delete Selected (<span id="selectedCount">0</span>)
                                    </button>
                                </div>

                                <?php if ($totalPages > 1): ?>
                                    <nav>
                                        <ul class="pagination mb-0">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&subject=<?php echo $subjectFilter; ?>&year=<?php echo $yearFilter; ?>&semester=<?php echo $semesterFilter; ?>&difficulty=<?php echo $difficultyFilter; ?>">
                                                        <i class="bi bi-chevron-left"></i>
                                                    </a>
                                                </li>
                                            <?php endif; ?>

                                            <?php
                                            $startPage = max(1, $page - 2);
                                            $endPage = min($totalPages, $page + 2);
                                            for ($i = $startPage; $i <= $endPage; $i++):
                                            ?>
                                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&subject=<?php echo $subjectFilter; ?>&year=<?php echo $yearFilter; ?>&semester=<?php echo $semesterFilter; ?>&difficulty=<?php echo $difficultyFilter; ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>

                                            <?php if ($page < $totalPages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&subject=<?php echo $subjectFilter; ?>&year=<?php echo $yearFilter; ?>&semester=<?php echo $semesterFilter; ?>&difficulty=<?php echo $difficultyFilter; ?>">
                                                        <i class="bi bi-chevron-right"></i>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="modal fade" id="addQuestionModal" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalLabel">Add New Question</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="questionForm" method="POST" novalidate>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="add_question" id="formAction">
                        <input type="hidden" name="question_id" id="questionId">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Subject *</label>
                                <select class="form-select" name="subject_id" required>
                                    <option value="">Select Subject</option>
                                    <?php
                                    $subjects->data_seek(0);
                                    while ($subject = $subjects->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $subject['id']; ?>" data-year="<?php echo $subject['year']; ?>" data-semester="<?php echo $subject['semester']; ?>">
                                            <?php echo htmlspecialchars($subject['name'] . ' (' . $subject['year'] . ' Sem ' . $subject['semester'] . ')'); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Year *</label>
                                <select class="form-select" name="year" required>
                                    <option value="FY">FY</option>
                                    <option value="SY">SY</option>
                                    <option value="TY">TY</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Semester *</label>
                                <select class="form-select" name="semester" required>
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Question Text *</label>
                            <textarea class="form-control" name="question_text" rows="3" required placeholder="Enter the question..."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">🅰️ Option A *</label>
                                    <input type="text" class="form-control" name="option_a" required placeholder="Option A">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">🅱️ Option B *</label>
                                    <input type="text" class="form-control" name="option_b" required placeholder="Option B">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">©️ Option C *</label>
                                    <input type="text" class="form-control" name="option_c" required placeholder="Option C">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">🅳 Option D *</label>
                                    <input type="text" class="form-control" name="option_d" required placeholder="Option D">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Correct Answer *</label>
                                <select class="form-select" name="correct_answer" required>
                                    <option value="">Select Answer</option>
                                    <option value="A">A - Option A</option>
                                    <option value="B">B - Option B</option>
                                    <option value="C">C - Option C</option>
                                    <option value="D">D - Option D</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Difficulty *</label>
                                <select class="form-select" name="difficulty" required>
                                    <option value="easy">Easy</option>
                                    <option value="medium">Medium</option>
                                    <option value="hard">Hard</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <span class="spinner-border spinner-border-sm d-none me-2" role="status"></span>
                            <i class="bi bi-check-circle me-1"></i>Add Question
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewQuestionModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewModalLabel">View Question</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="questionDetails"></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bulkUploadModal" tabindex="-1" aria-labelledby="bulkModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkModalLabel">Bulk Upload Questions</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="bulk_upload">

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Bulk Upload Instructions:</strong>
                            <ol class="mb-0 mt-2">
                                <li><a href="?download_template=1" target="_blank">Download the CSV template</a></li>
                                <li>Fill in your questions following the exact format</li>
                                <li>Upload the CSV file below</li>
                                <li>Review the import results</li>
                            </ol>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Select CSV File *</label>
                            <input type="file" class="form-control" name="excel_file" accept=".csv" required>
                            <div class="form-text">
                                File must be in CSV format with the correct column headers.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-upload me-1"></i>Upload Questions
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <script>
        $('#selectAll, #selectAllTop').change(function() {
            $('.question-checkbox').prop('checked', $(this).prop('checked'));
            updateBulkDeleteButton();
        });

        $('.question-checkbox').change(updateBulkDeleteButton);

        function updateBulkDeleteButton() {
            const checkedBoxes = $('.question-checkbox:checked');
            const count = checkedBoxes.length;
            if (count > 0) {
                $('#bulkDeleteBtn').show();
                $('#selectedCount').text(count);
            } else {
                $('#bulkDeleteBtn').hide();
            }
        }

        function editQuestion(id) {
            $('#modalLabel').text('Edit Question');
            $('#formAction').val('edit_question');
            $('#submitBtn').html('<span class="spinner-border spinner-border-sm d-none me-2"></span><i class="bi bi-pencil me-1"></i>Update Question');

            fetch(`manage_questions.php?get_question=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const q = data.question;
                        $('#questionId').val(q.id);
                        $('#questionForm [name=subject_id]').val(q.subject_id);
                        $('#questionForm [name=year]').val(q.year || 'FY');
                        $('#questionForm [name=semester]').val(q.semester || 1);
                        $('#questionForm [name=question_text]').val(q.question_text);
                        $('#questionForm [name=option_a]').val(q.option_a);
                        $('#questionForm [name=option_b]').val(q.option_b);
                        $('#questionForm [name=option_c]').val(q.option_c);
                        $('#questionForm [name=option_d]').val(q.option_d);
                        $('#questionForm [name=correct_answer]').val(q.correct_answer);
                        $('#questionForm [name=difficulty]').val(q.difficulty || 'medium');

                        new bootstrap.Modal(document.getElementById('addQuestionModal')).show();
                    } else {
                        alert('Question not found or access denied.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading question data.');
                });
        }

        function viewQuestion(id) {
            fetch(`manage_questions.php?get_question=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const q = data.question;
                        let html = `
                            <div class="mb-4">
                                <h6 class="text-muted mb-2">Question:</h6>
                                <div class="border-start border-primary border-4 ps-3">
                                    <p class="mb-0 fs-5">${q.question_text}</p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2">Options:</h6>
                                    <div class="mb-2 p-2 rounded ${q.correct_answer === 'A' ? 'bg-success text-white' : 'bg-light'}">
                                        <strong>A:</strong> ${q.option_a}
                                    </div>
                                    <div class="mb-2 p-2 rounded ${q.correct_answer === 'B' ? 'bg-success text-white' : 'bg-light'}">
                                        <strong>B:</strong> ${q.option_b}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-2 p-2 rounded ${q.correct_answer === 'C' ? 'bg-success text-white' : 'bg-light'}">
                                        <strong>C:</strong> ${q.option_c}
                                    </div>
                                    <div class="mb-2 rounded ${q.correct_answer === 'D' ? 'bg-success text-white p-2' : 'bg-light p-2'}">
                                        <strong>D:</strong> ${q.option_d}
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <div class="p-2">
                                        <strong class="text-success fs-4">${q.correct_answer}</strong><br>
                                        <small class="text-muted">Correct Answer</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-2">
                                        <strong class="fs-4">${q.subject_name}</strong><br>
                                        <small class="text-muted">Subject</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-2">
                                        <strong class="fs-4">${q.difficulty || 'medium'}</strong><br>
                                        <small class="text-muted">Difficulty</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-2">
                                        <strong class="fs-4">${q.year || 'FY'} - Sem ${q.semester || 1}</strong><br>
                                        <small class="text-muted">Year & Semester</small>
                                    </div>
                                </div>
                            </div>
                        `;
                        $('#questionDetails').html(html);
                        new bootstrap.Modal(document.getElementById('viewQuestionModal')).show();
                    } else {
                        alert('Question not found or access denied.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading question data.');
                });
        }

        function deleteQuestion(id) {
            if (confirm('Are you sure you want to delete this question? This action cannot be undone.')) {
                const formData = new FormData();
                formData.append('csrf_token', '<?php echo $csrf_token; ?>');
                formData.append('action', 'delete_question');
                formData.append('question_id', id);

                fetch('manage_questions.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(() => {
                        location.reload();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error deleting question.');
                    });
            }
        }

        function cloneQuestion(id) {
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $csrf_token; ?>');
            formData.append('action', 'clone_question');
            formData.append('question_id', id);

            fetch('manage_questions.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error cloning question.');
                });
        }

        $('#questionForm [name=subject_id]').change(function() {
            const selectedOption = $(this).find('option:selected');
            const year = selectedOption.data('year');
            const semester = selectedOption.data('semester');

            if (year) $('#questionForm [name=year]').val(year);
            if (semester) $('#questionForm [name=semester]').val(semester);
        });

        $('#addQuestionModal').on('hidden.bs.modal', function() {
            $('#questionForm')[0].reset();
            $('#modalLabel').text('Add New Question');
            $('#formAction').val('add_question');
            $('#questionId').val('');
            $('#submitBtn').html('<span class="spinner-border spinner-border-sm d-none me-2"></span><i class="bi bi-check-circle me-1"></i>Add Question');
        });

        $('#questionForm').submit(function(e) {
            const submitBtn = $('#submitBtn');
            const spinner = submitBtn.find('.spinner-border');

            submitBtn.prop('disabled', true);
            spinner.removeClass('d-none');

            const requiredFields = ['question_text', 'subject_id', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer'];
            let isValid = true;

            requiredFields.forEach(field => {
                const element = $(`[name=${field}]`);
                if (!element.val().trim()) {
                    element.addClass('is-invalid');
                    isValid = false;
                } else {
                    element.removeClass('is-invalid');
                }
            });

            if (!isValid) {
                e.preventDefault();
                submitBtn.prop('disabled', false);
                spinner.addClass('d-none');
                alert('Please fill in all required fields.');
                return false;
            }
        });
    </script>
</body>

</html>