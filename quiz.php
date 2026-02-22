<?php

/**
 * ============================================================
 * Education Hub - Advanced Assessment Quiz Module (quiz.php)
 * ============================================================
 */

require_once 'config/functions.php';
requireLogin();

$pageTitle = 'Assessments';
$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

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

// CONFIG
const QUIZ_TIME_LIMIT = 1800; // 30 minutes in seconds
const QUIZ_RANDOM_QUESTIONS_LIMIT = 10;
const NEGATIVE_MARKING = true;
const NEGATIVE_MARKS_WEIGHT = 0.25;

const ATTEMPT_LIMIT = 5;

// API Endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'auto_save') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    if (!validateCSRFToken($input['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $sessId = (int)($input['session_id'] ?? 0);
    $qId = (int)($input['question_id'] ?? 0);
    $selected = sanitize($input['selected_answer'] ?? '');
    $timeDelta = (int)($input['time_delta'] ?? 0); // time spent on this question during this slice

    $stmt = $conn->prepare("UPDATE quiz_attempts qa JOIN quiz_sessions qs ON qa.session_id = qs.id SET qa.selected_answer = ?, qa.time_taken = qa.time_taken + ? WHERE qa.session_id = ? AND qa.question_id = ? AND qs.student_id = ? AND qs.status = 'in_progress'");
    $stmt->bind_param("siiii", $selected, $timeDelta, $sessId, $qId, $userId);
    echo json_encode(['success' => $stmt->execute()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'submit') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die("Invalid CSRF token.");
    }

    $sessId = (int)$_POST['session_id'];

    // Verify ownership and in_progress
    $chkStmt = $conn->prepare("SELECT * FROM quiz_sessions WHERE id = ? AND student_id = ? AND status = 'in_progress'");
    $chkStmt->bind_param("ii", $sessId, $userId);
    $chkStmt->execute();
    $sess = $chkStmt->get_result()->fetch_assoc();

    if ($sess) {
        $answersStmt = $conn->prepare("SELECT id, selected_answer, correct_answer FROM quiz_attempts WHERE session_id = ?");
        $answersStmt->bind_param("i", $sessId);
        $answersStmt->execute();
        $answers = $answersStmt->get_result();

        $correctCount = 0;
        $attemptedCount = 0;

        while ($att = $answers->fetch_assoc()) {
            if (!empty($att['selected_answer'])) {
                $attemptedCount++;
                $isCorrect = (strtoupper($att['selected_answer']) === strtoupper($att['correct_answer'])) ? 1 : 0;
                if ($isCorrect) $correctCount++;

                $updAtt = $conn->prepare("UPDATE quiz_attempts SET is_correct = ? WHERE id = ?");
                $updAtt->bind_param("ii", $isCorrect, $att['id']);
                $updAtt->execute();
            }
        }

        // Finalize
        $totalTime = max(0, time() - strtotime($sess['started_at']));
        if ($totalTime > QUIZ_TIME_LIMIT) $totalTime = QUIZ_TIME_LIMIT;

        // Negative Marks Calc
        $unattempted = $sess['total_questions'] - $attemptedCount;
        $wrongCount = $attemptedCount - $correctCount;
        $scoreRaw = $correctCount;
        if (NEGATIVE_MARKING) {
            $scoreRaw -= ($wrongCount * NEGATIVE_MARKS_WEIGHT);
        }
        $scorePerc = $sess['total_questions'] > 0 ? max(0, min(100, round(($scoreRaw / $sess['total_questions']) * 100))) : 0;

        $updStatus = $conn->prepare("UPDATE quiz_sessions SET status='completed', completed_at=NOW(), score=?, correct_answers=?, time_taken=? WHERE id=?");
        $updStatus->bind_param("iiii", $scorePerc, $correctCount, $totalTime, $sessId);
        $updStatus->execute();
    }

    redirect("quiz.php?action=result&id=$sessId");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'start') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die("Invalid CSRF token.");
    }
    $subjId = (int)$_POST['subject_id'];

    // Check limit
    $limStmt = $conn->prepare("SELECT COUNT(*) as c FROM quiz_sessions WHERE student_id = ? AND subject_id = ?");
    $limStmt->bind_param("ii", $userId, $subjId);
    $limStmt->execute();
    $attemptsCount = $limStmt->get_result()->fetch_assoc()['c'];

    if ($attemptsCount >= ATTEMPT_LIMIT) {
        $_SESSION['quiz_error'] = "You have reached the maximum allowed attempts for this subject.";
        redirect("quiz.php?action=list");
    }

    // Check if an in_progress session exists
    $progStmt = $conn->prepare("SELECT id FROM quiz_sessions WHERE student_id = ? AND subject_id = ? AND status = 'in_progress'");
    $progStmt->bind_param("ii", $userId, $subjId);
    $progStmt->execute();
    $progRes = $progStmt->get_result()->fetch_assoc();

    if ($progRes) {
        redirect("quiz.php?action=take&id=" . $progRes['id']);
    }

    // Get random questions limit
    $qStmt = $conn->prepare("SELECT * FROM questions WHERE subject_id = ? ORDER BY RAND() LIMIT ?");
    $lim = QUIZ_RANDOM_QUESTIONS_LIMIT;
    $qStmt->bind_param("ii", $subjId, $lim);
    $qStmt->execute();
    $questRes = $qStmt->get_result();
    $totalQs = $questRes->num_rows;

    if ($totalQs > 0) {
        $insSess = $conn->prepare("INSERT INTO quiz_sessions (student_id, subject_id, started_at, status, total_questions) VALUES (?, ?, NOW(), 'in_progress', ?)");
        $insSess->bind_param("iii", $userId, $subjId, $totalQs);
        $insSess->execute();
        $newSessId = $insSess->insert_id;

        while ($q = $questRes->fetch_assoc()) {
            $correct = strtoupper($q['correct_answer']);
            $insAtt = $conn->prepare("INSERT INTO quiz_attempts (session_id, question_id, correct_answer, created_at) VALUES (?, ?, ?, NOW())");
            $insAtt->bind_param("iis", $newSessId, $q['id'], $correct);
            $insAtt->execute();
        }
        redirect("quiz.php?action=take&id=" . $newSessId);
    } else {
        $_SESSION['quiz_error'] = "No questions found for this subject.";
        redirect("quiz.php?action=list");
    }
}

// --- VIEW ROUTES ---

// 1. Result View
if ($action === 'result' && isset($_GET['id'])) {
    $sessId = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT qs.*, s.name as subject_name FROM quiz_sessions qs JOIN subjects s ON qs.subject_id = s.id WHERE qs.id = ? AND qs.student_id = ? AND qs.status='completed'");
    $stmt->bind_param("ii", $sessId, $userId);
    $stmt->execute();
    $sess = $stmt->get_result()->fetch_assoc();
    if (!$sess) {
        redirect("quiz.php?action=list");
    }

    $perc = $sess['score'];
    $perfLbl = 'Needs Improvement';
    $perfColor = 'var(--danger)';
    $emoji = '💪';
    if ($perc >= 80) {
        $perfLbl = 'Excellent';
        $perfColor = 'var(--success)';
        $emoji = '🏆';
    } elseif ($perc >= 60) {
        $perfLbl = 'Good';
        $perfColor = 'var(--primary)';
        $emoji = '👍';
    } elseif ($perc >= PASS_PERCENTAGE) {
        $perfLbl = 'Average';
        $perfColor = 'var(--warning)';
        $emoji = '⚡';
    }

    $ansStmt = $conn->prepare("SELECT qa.*, q.question_text, q.difficulty FROM quiz_attempts qa JOIN questions q ON qa.question_id = q.id WHERE qa.session_id = ?");
    $ansStmt->bind_param("i", $sessId);
    $ansStmt->execute();
    $answers = $ansStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Stats
    $diffStats = ['easy' => ['tot' => 0, 'cor' => 0], 'medium' => ['tot' => 0, 'cor' => 0], 'hard' => ['tot' => 0, 'cor' => 0]];
    $wrongCount = 0;
    $unattemptedCount = 0;
    foreach ($answers as $a) {
        $d = strtolower($a['difficulty'] ?? 'medium');
        if (!isset($diffStats[$d])) $d = 'medium';
        $diffStats[$d]['tot']++;
        if ($a['is_correct']) $diffStats[$d]['cor']++;
        else if (empty($a['selected_answer'])) $unattemptedCount++;
        else $wrongCount++;
    }
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Quiz Result - Education Hub</title>
        <link rel="stylesheet" href="assets/css/style.css">
        <style>
            .result-container {
                max-width: 900px;
                margin: 40px auto;
                background: var(--surface);
                border-radius: var(--radius-xl);
                box-shadow: var(--shadow-md);
                overflow: hidden;
            }

            .result-hero {
                background: linear-gradient(135deg, <?= $perfColor ?>, var(--dark));
                color: white;
                padding: 60px 20px;
                text-align: center;
            }

            .score-circle {
                width: 160px;
                height: 160px;
                border-radius: 50%;
                background: white;
                margin: 0 auto 20px auto;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
                position: relative;
            }

            .score-circle h1 {
                font-size: 48px;
                margin: 0;
                color: <?= $perfColor ?>;
                line-height: 1;
            }

            .score-circle span {
                font-size: 14px;
                color: var(--text-muted);
                text-transform: uppercase;
                letter-spacing: 1px;
            }

            .result-stats-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 20px;
                padding: 30px;
                border-bottom: 1px solid var(--border);
            }

            .stat-box {
                background: var(--surface-light);
                padding: 20px;
                border-radius: var(--radius-md);
                text-align: center;
            }

            .stat-box h3 {
                font-size: 24px;
                margin: 0 0 5px 0;
                color: var(--dark);
            }

            .stat-box p {
                font-size: 13px;
                color: var(--text-muted);
                margin: 0;
                text-transform: uppercase;
            }

            .chart-section {
                padding: 40px 30px;
                display: flex;
                gap: 40px;
            }

            .chart-box {
                flex: 1;
                background: var(--surface-light);
                padding: 20px;
                border-radius: var(--radius-lg);
            }

            canvas {
                width: 100% !important;
                height: 250px !important;
            }

            .btn-retake {
                display: inline-block;
                background: white;
                color: <?= $perfColor ?>;
                padding: 12px 30px;
                border-radius: 30px;
                font-weight: bold;
                text-decoration: none;
                margin-top: 20px;
                transition: transform 0.2s;
            }

            .btn-retake:hover {
                transform: scale(1.05);
            }
        </style>
    </head>

    <body>
        <div class="layout">
            <?php include 'includes/sidebar.php'; ?>
            <main class="main-content">
                <?php include 'includes/header.php'; ?>
                <div class="result-container">
                    <div class="result-hero">
                        <div style="font-size: 50px; margin-bottom: 10px;"><?= $emoji ?></div>
                        <div class="score-circle">
                            <h1><?= $sess['score'] ?>%</h1>
                            <span>Final Score</span>
                        </div>
                        <h2><?= $perfLbl ?> Performance</h2>
                        <p style="opacity: 0.8;">Subject: <?= htmlspecialchars($sess['subject_name']) ?></p>
                        <a href="quiz.php" class="btn-retake">Back to Quizzes</a>
                    </div>

                    <div class="result-stats-grid">
                        <div class="stat-box">
                            <h3><?= $sess['total_questions'] ?></h3>
                            <p>Questions</p>
                        </div>
                        <div class="stat-box">
                            <h3><span style="color:var(--success)"><?= $sess['correct_answers'] ?></span></h3>
                            <p>Correct</p>
                        </div>
                        <div class="stat-box">
                            <h3><span style="color:var(--danger)"><?= $wrongCount ?></span></h3>
                            <p>Wrong</p>
                        </div>
                        <div class="stat-box">
                            <h3><?= floor($sess['time_taken'] / 60) ?>:<?= str_pad($sess['time_taken'] % 60, 2, '0', STR_PAD_LEFT) ?></h3>
                            <p>Time Taken</p>
                        </div>
                    </div>

                    <div class="chart-section">
                        <div class="chart-box">
                            <h4 style="margin-top:0; margin-bottom: 20px; text-align:center;">Accuracy by Difficulty</h4>
                            <canvas id="diffChart"></canvas>
                        </div>
                        <?php if (NEGATIVE_MARKING): ?>
                            <div class="chart-box" style="display:flex; flex-direction:column; justify-content:center; align-items:center;">
                                <h4 style="margin-top:0;">Negative Marking Applied</h4>
                                <p style="text-align:center; color:var(--text-muted);">
                                    You lost <strong><?= $wrongCount * NEGATIVE_MARKS_WEIGHT ?></strong> points due to <?= $wrongCount ?> incorrect answers. Unattempted questions (<?= $unattemptedCount ?>) carried no penalty.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <script>
                    // Pure Canvas API Bar Chart
                    const canvas = document.getElementById('diffChart');
                    const ctx = canvas.getContext('2d');

                    // Deal with pixel scaling for HDPI displays
                    const scale = window.devicePixelRatio;
                    canvas.width = canvas.parentElement.clientWidth * scale;
                    canvas.height = 250 * scale;
                    ctx.scale(scale, scale);

                    const w = canvas.width / scale;
                    const h = canvas.height / scale;

                    const rawData = [{
                            label: 'Easy',
                            tot: <?= $diffStats['easy']['tot'] ?>,
                            cor: <?= $diffStats['easy']['cor'] ?>,
                            color: '#10b981'
                        },
                        {
                            label: 'Medium',
                            tot: <?= $diffStats['medium']['tot'] ?>,
                            cor: <?= $diffStats['medium']['cor'] ?>,
                            color: '#f59e0b'
                        },
                        {
                            label: 'Hard',
                            tot: <?= $diffStats['hard']['tot'] ?>,
                            cor: <?= $diffStats['hard']['cor'] ?>,
                            color: '#ef4444'
                        }
                    ];

                    const maxBarHeight = h - 60;
                    const barWidth = 40;
                    const gap = (w - (barWidth * 3)) / 4;

                    ctx.font = 'bold 12px Arial';
                    ctx.textAlign = 'center';

                    rawData.forEach((item, i) => {
                        const x = gap + (i * (barWidth + gap));
                        const perc = item.tot > 0 ? (item.cor / item.tot) : 0;
                        const barH = perc * maxBarHeight;
                        const y = h - 30 - barH;

                        // Background bar
                        ctx.fillStyle = '#e5e7eb';
                        ctx.beginPath();
                        ctx.roundRect(x, h - 30 - maxBarHeight, barWidth, maxBarHeight, 5);
                        ctx.fill();

                        // Value bar
                        ctx.fillStyle = item.color;
                        ctx.beginPath();
                        ctx.roundRect(x, y, barWidth, barH, [5, 5, 0, 0]);
                        ctx.fill();

                        // Text
                        ctx.fillStyle = '#6b7280';
                        ctx.fillText(item.label, x + (barWidth / 2), h - 10);
                        if (item.tot > 0) {
                            ctx.fillStyle = '#111827';
                            ctx.fillText(Math.round(perc * 100) + '%', x + (barWidth / 2), y - 10);
                        }
                    });
                </script>
            </main>
        </div>
    </body>

    </html>
<?php
    exit;
}

// 2. Take Quiz View
if ($action === 'take' && isset($_GET['id'])) {
    $sessId = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT qs.*, s.name as subject_name FROM quiz_sessions qs JOIN subjects s ON qs.subject_id = s.id WHERE qs.id = ? AND qs.student_id = ? AND qs.status='in_progress'");
    $stmt->bind_param("ii", $sessId, $userId);
    $stmt->execute();
    $sess = $stmt->get_result()->fetch_assoc();
    if (!$sess) {
        redirect("quiz.php?action=list");
    }

    // Fetch attempts
    $attStmt = $conn->prepare("
        SELECT qa.question_id, qa.selected_answer, 
               q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer 
        FROM quiz_attempts qa 
        JOIN questions q ON qa.question_id = q.id 
        WHERE qa.session_id = ? 
        ORDER BY qa.id
    ");
    $attStmt->bind_param("i", $sessId);
    $attStmt->execute();
    $attRes = $attStmt->get_result();

    $jsQuestions = [];
    while ($r = $attRes->fetch_assoc()) {
        // Deterministic Shuffling of Options using MD5 of session + question
        $optionsRaw = [
            ['val' => 'A', 'text' => $r['option_a']],
            ['val' => 'B', 'text' => $r['option_b']],
            ['val' => 'C', 'text' => $r['option_c']],
            ['val' => 'D', 'text' => $r['option_d']],
        ];
        usort($optionsRaw, function ($a, $b) use ($sessId, $r) {
            return strcmp(md5($sessId . $r['question_id'] . $a['val']), md5($sessId . $r['question_id'] . $b['val']));
        });

        $jsQuestions[] = [
            'id' => $r['question_id'],
            'text' => $r['question_text'],
            'options' => $optionsRaw,
            'selected' => $r['selected_answer'] ?? '',
            'marked' => false
        ];
    }

    $elapsedSeconds = time() - strtotime($sess['started_at']);
    $remainingSeconds = max(0, QUIZ_TIME_LIMIT - $elapsedSeconds);
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>In Progress: <?= htmlspecialchars($sess['subject_name']) ?> - Education Hub</title>
        <link rel="stylesheet" href="assets/css/style.css">
        <style>
            .quiz-engine-layout {
                display: flex;
                height: 100vh;
                overflow: hidden;
                background: var(--surface-light);
                font-family: 'Inter', sans-serif;
            }

            .quiz-main {
                flex: 1;
                display: flex;
                flex-direction: column;
                height: 100%;
                position: relative;
            }

            .quiz-sidebar {
                width: 320px;
                background: var(--surface);
                border-left: 1px solid var(--border);
                display: flex;
                flex-direction: column;
                box-shadow: -2px 0 10px rgba(0, 0, 0, 0.02);
            }

            .quiz-header {
                background: white;
                padding: 20px 40px;
                border-bottom: 1px solid var(--border);
                display: flex;
                justify-content: space-between;
                align-items: center;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
            }

            .quiz-title {
                margin: 0;
                font-size: 20px;
                font-weight: 700;
                color: var(--dark);
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .quiz-timer {
                font-size: 24px;
                font-weight: 800;
                color: var(--danger);
                font-variant-numeric: tabular-nums;
                background: var(--danger-light);
                padding: 8px 16px;
                border-radius: 8px;
                border: 1px solid var(--danger);
            }

            .quiz-body {
                flex: 1;
                overflow-y: auto;
                padding: 50px;
                display: flex;
                flex-direction: column;
                align-items: center;
            }

            .question-card {
                width: 100%;
                max-width: 800px;
                background: white;
                border-radius: 12px;
                padding: 40px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
                border: 1px solid var(--border);
            }

            .q-number {
                text-transform: uppercase;
                font-size: 13px;
                font-weight: 700;
                color: var(--primary);
                letter-spacing: 1px;
                margin-bottom: 15px;
                display: inline-block;
                padding: 6px 12px;
                background: var(--primary-light);
                border-radius: 20px;
            }

            .q-text {
                font-size: 22px;
                font-weight: 600;
                color: var(--dark);
                line-height: 1.5;
                margin-bottom: 30px;
            }

            .options-list {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }

            .option-label {
                display: flex;
                align-items: center;
                padding: 15px 20px;
                border: 2px solid var(--border);
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.2s;
                font-size: 16px;
                color: var(--dark);
            }

            .option-label:hover {
                border-color: var(--primary);
                background: var(--surface-light);
            }

            .option-label.selected {
                border-color: var(--primary);
                background: rgba(26, 86, 219, 0.05);
            }

            .option-label input {
                display: none;
            }

            .option-indicator {
                width: 24px;
                height: 24px;
                border-radius: 50%;
                border: 2px solid var(--border);
                margin-right: 15px;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
            }

            .option-label.selected .option-indicator {
                border-color: var(--primary);
            }

            .option-label.selected .option-indicator::after {
                content: '';
                width: 12px;
                height: 12px;
                background: var(--primary);
                border-radius: 50%;
                position: absolute;
            }

            .quiz-footer {
                margin-top: 40px;
                display: flex;
                justify-content: space-between;
                width: 100%;
                max-width: 800px;
            }

            .btn-engine {
                padding: 12px 24px;
                font-size: 16px;
                font-weight: 600;
                border-radius: 8px;
                border: none;
                cursor: pointer;
                transition: transform 0.1s;
            }

            .btn-engine:hover {
                transform: translateY(-2px);
            }

            .btn-engine:active {
                transform: translateY(0);
            }

            .btn-secondary {
                background: var(--surface);
                color: var(--dark);
                border: 1px solid var(--border);
            }

            .btn-primary {
                background: var(--primary);
                color: white;
                box-shadow: 0 4px 10px rgba(26, 86, 219, 0.2);
            }

            .btn-mark {
                background: var(--warning-light);
                color: var(--warning);
                border: 1px solid var(--warning);
            }

            .sidebar-header {
                padding: 20px;
                border-bottom: 1px solid var(--border);
                font-weight: 700;
                font-size: 16px;
                text-transform: uppercase;
                color: var(--text-muted);
                letter-spacing: 1px;
            }

            .sidebar-body {
                padding: 20px;
                flex: 1;
                overflow-y: auto;
            }

            .nav-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 10px;
            }

            .nav-node {
                width: 100%;
                aspect-ratio: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                font-weight: 700;
                font-size: 15px;
                cursor: pointer;
                border: 2px solid var(--border);
                background: var(--surface-light);
                color: var(--text-muted);
                transition: all 0.2s;
            }

            .nav-node:hover {
                border-color: var(--primary);
                color: var(--primary);
            }

            .nav-node.active {
                border-color: var(--dark);
                color: var(--dark);
            }

            .nav-node.answered {
                background: var(--success);
                color: white;
                border-color: var(--success);
            }

            .nav-node.marked {
                background: var(--warning);
                color: white;
                border-color: var(--warning);
            }

            .nav-node.answered.marked {
                background: linear-gradient(135deg, var(--warning) 50%, var(--success) 50%);
            }

            .sidebar-footer {
                padding: 20px;
                border-top: 1px solid var(--border);
            }

            .btn-submit-final {
                width: 100%;
                padding: 16px;
                background: var(--success);
                color: white;
                font-weight: 800;
                font-size: 16px;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
                transition: transform 0.2s;
            }

            .btn-submit-final:hover {
                transform: scale(1.02);
            }

            .toast {
                position: fixed;
                bottom: 30px;
                left: 50%;
                transform: translateX(-50%) translateY(100px);
                background: var(--dark);
                color: white;
                padding: 12px 24px;
                border-radius: 30px;
                font-weight: 600;
                opacity: 0;
                transition: all 0.3s;
                z-index: 9999;
            }

            .toast.show {
                transform: translateX(-50%) translateY(0);
                opacity: 1;
            }
        </style>
    </head>

    <body>
        <div class="quiz-engine-layout">
            <div class="quiz-main">
                <div class="quiz-header">
                    <h1 class="quiz-title"><span style="font-size: 24px;">📝</span> <?= htmlspecialchars($sess['subject_name']) ?></h1>
                    <div class="quiz-timer" id="timerDisplay">--:--</div>
                </div>

                <div class="quiz-body">
                    <div class="question-card" id="qCard">
                        <!-- Rendered by JS -->
                    </div>

                    <div class="quiz-footer">
                        <button class="btn-engine btn-secondary" id="btnPrev">← Previous</button>
                        <div style="display:flex; gap: 15px;">
                            <button class="btn-engine btn-mark" id="btnMark">★ Mark for Review</button>
                            <button class="btn-engine btn-primary" id="btnNext">Save & Next →</button>
                        </div>
                    </div>
                </div>

                <div id="toast" class="toast">Saving...</div>
            </div>

            <div class="quiz-sidebar">
                <div class="sidebar-header">Question Navigator</div>
                <div class="sidebar-body">
                    <div class="nav-grid" id="navGrid">
                        <!-- Rendered by JS -->
                    </div>
                </div>
                <div class="sidebar-footer">
                    <form method="POST" id="submitForm" onsubmit="preventUnload = false;">
                        <input type="hidden" name="action" value="submit">
                        <input type="hidden" name="session_id" value="<?= $sessId ?>">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <button type="button" class="btn-submit-final" onclick="confirmSubmit()">SUBMIT EXAM</button>
                    </form>
                </div>
            </div>
        </div>

        <script>
            const API_URL = 'quiz.php?action=auto_save';
            const SESSION_ID = <?= $sessId ?>;
            const CSRF_TOKEN = '<?= $csrf_token ?>';
            let remainingSeconds = <?= $remainingSeconds ?>;

            // Data State
            let questions = <?= json_encode($jsQuestions) ?>;

            // Load local marks state since it's transient
            const markStateKey = 'quiz_marks_' + SESSION_ID;
            try {
                const stored = JSON.parse(localStorage.getItem(markStateKey)) || {};
                questions.forEach(q => {
                    if (stored[q.id]) q.marked = true;
                });
            } catch (e) {}

            let currentIndex = 0;
            let questionEnterTime = Date.now();
            let preventUnload = true;

            // Elements
            const timerDisplay = document.getElementById('timerDisplay');
            const qCard = document.getElementById('qCard');
            const navGrid = document.getElementById('navGrid');
            const btnPrev = document.getElementById('btnPrev');
            const btnNext = document.getElementById('btnNext');
            const btnMark = document.getElementById('btnMark');
            const toast = document.getElementById('toast');

            // Render Functions
            function renderQuestion() {
                const q = questions[currentIndex];
                questionEnterTime = Date.now();

                let html = `
                <div class="q-number">Question ${currentIndex + 1} OF ${questions.length}</div>
                <div class="q-text">${q.text.replace(/</g, '&lt;')}</div>
                <div class="options-list">
            `;

                q.options.forEach(opt => {
                    const isSel = q.selected === opt.val;
                    html += `
                    <label class="option-label ${isSel ? 'selected' : ''}" onclick="selectOption('${opt.val}')">
                        <div class="option-indicator"></div>
                        <div style="flex:1">${opt.text.replace(/</g, '&lt;')}</div>
                    </label>
                `;
                });
                html += `</div>`;
                qCard.innerHTML = html;

                // Buttons state
                btnPrev.style.visibility = currentIndex === 0 ? 'hidden' : 'visible';
                btnNext.innerText = currentIndex === questions.length - 1 ? 'Save Answer' : 'Save & Next →';
                btnMark.innerText = q.marked ? '★ Unmark' : '☆ Mark for Review';

                updateNavUI();
            }

            function renderNav() {
                let html = '';
                questions.forEach((q, idx) => {
                    let classes = ['nav-node'];
                    if (q.selected) classes.push('answered');
                    if (q.marked) classes.push('marked');
                    if (idx === currentIndex) classes.push('active');

                    html += `<div class="nav-node ${classes.join(' ')}" id="nav-${idx}" onclick="jumpTo(${idx})">${idx + 1}</div>`;
                });
                navGrid.innerHTML = html;
            }

            function updateNavUI() {
                questions.forEach((q, idx) => {
                    const el = document.getElementById('nav-' + idx);
                    if (el) {
                        el.className = 'nav-node';
                        if (q.selected) el.classList.add('answered');
                        if (q.marked) el.classList.add('marked');
                        if (idx === currentIndex) el.classList.add('active');
                    }
                });
            }

            // Actions
            window.selectOption = function(val) {
                questions[currentIndex].selected = val;
                renderQuestion();
                autoSave(questions[currentIndex]);
            };

            window.jumpTo = function(idx) {
                saveTimeDelta(questions[currentIndex]);
                currentIndex = idx;
                renderQuestion();
            };

            btnPrev.onclick = () => {
                if (currentIndex > 0) jumpTo(currentIndex - 1);
            };
            btnNext.onclick = () => {
                saveTimeDelta(questions[currentIndex]);
                if (currentIndex < questions.length - 1) {
                    currentIndex++;
                    renderQuestion();
                } else {
                    showToast("Answer Saved! Ready to submit.");
                }
            };

            btnMark.onclick = () => {
                const q = questions[currentIndex];
                q.marked = !q.marked;

                // Save locally
                let stored = {};
                questions.forEach(x => {
                    if (x.marked) stored[x.id] = true;
                });
                localStorage.setItem(markStateKey, JSON.stringify(stored));

                renderQuestion();
            };

            function confirmSubmit() {
                let unans = questions.filter(q => !q.selected).length;
                let msg = unans > 0 ? `You have ${unans} unanswered questions! Are you sure you want to submit?` : "Are you sure you want to finalize your exam?";
                if (confirm(msg)) {
                    preventUnload = false;
                    localStorage.removeItem(markStateKey);
                    document.getElementById('submitForm').submit();
                }
            }

            // Auto Save Logic
            function saveTimeDelta(q) {
                // Send time delta before switching
                const delta = Math.round((Date.now() - questionEnterTime) / 1000);
                if (delta > 0) {
                    fetch(API_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            csrf_token: CSRF_TOKEN,
                            session_id: SESSION_ID,
                            question_id: q.id,
                            selected_answer: q.selected,
                            time_delta: delta
                        })
                    });
                }
            }

            let saveTimeout;

            function autoSave(q) {
                clearTimeout(saveTimeout);
                showToast("Saving...");
                saveTimeout = setTimeout(() => {
                    fetch(API_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            csrf_token: CSRF_TOKEN,
                            session_id: SESSION_ID,
                            question_id: q.id,
                            selected_answer: q.selected,
                            time_delta: 0 // delta saved on jump
                        })
                    }).then(() => showToast("Saved", 1000));
                }, 1000);
            }

            function showToast(msg, duration = 2000) {
                toast.innerText = msg;
                toast.classList.add('show');
                setTimeout(() => toast.classList.remove('show'), duration);
            }

            // Timer Logic
            setInterval(() => {
                if (remainingSeconds <= 0) {
                    preventUnload = false;
                    document.getElementById('submitForm').submit();
                    return;
                }
                remainingSeconds--;
                let m = Math.floor(remainingSeconds / 60);
                let s = remainingSeconds % 60;
                timerDisplay.innerText = `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
                if (remainingSeconds < 300) timerDisplay.style.color = 'var(--danger)'; // red at 5 mins
            }, 1000);

            // Prevent exact leaving
            window.addEventListener('beforeunload', function(e) {
                if (preventUnload) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            // Init
            renderNav();
            renderQuestion();
        </script>
    </body>

    </html>
<?php
    exit;
}

// 3. List View (Default)
$yearFilter = sanitize($_GET['year'] ?? '');
$semesterFilter = (int)($_GET['semester'] ?? 0);
$searchFilter = sanitize($_GET['search'] ?? '');
$sortFilter = sanitize($_GET['sort'] ?? 'recent'); // recent, max_score, most_attempted

// Build subjects query joining aggregates
$subWhere = ["1=1"];
if ($yearFilter) $subWhere[] = "s.year = '$yearFilter'";
if ($semesterFilter > 0) $subWhere[] = "s.semester = $semesterFilter";
if ($searchFilter) $subWhere[] = "s.name LIKE '%$searchFilter%'";
$whereStr = implode(' AND ', $subWhere);

$orderStr = "ORDER BY FIELD(s.year, 'FY', 'SY', 'TY'), s.semester, s.name";
if ($sortFilter === 'max_score') $orderStr = "ORDER BY best_score DESC, FIELD(s.year, 'FY', 'SY', 'TY'), s.semester";
if ($sortFilter === 'most_attempted') $orderStr = "ORDER BY my_attempts DESC, FIELD(s.year, 'FY', 'SY', 'TY'), s.semester";
if ($sortFilter === 'recent') $orderStr = "ORDER BY s.id DESC";

$listSql = "
    SELECT s.*, 
           (SELECT COUNT(*) FROM questions WHERE subject_id = s.id) as q_count,
           (SELECT COUNT(*) FROM quiz_sessions WHERE subject_id = s.id AND student_id = $userId) as my_attempts,
           (SELECT status FROM quiz_sessions WHERE subject_id = s.id AND student_id = $userId ORDER BY started_at DESC LIMIT 1) as last_status,
           (SELECT MAX(score) FROM quiz_sessions WHERE subject_id = s.id AND student_id = $userId AND status='completed') as best_score,
           (SELECT AVG(score) FROM quiz_sessions WHERE subject_id = s.id AND student_id = $userId AND status='completed') as avg_score,
           (SELECT MAX(completed_at) FROM quiz_sessions WHERE subject_id = s.id AND student_id = $userId AND status='completed') as last_attempt
    FROM subjects s
    WHERE $whereStr
    $orderStr
";
$subjects = $conn->query($listSql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .filter-ribbon {
            background: var(--surface);
            padding: 20px;
            border-radius: var(--radius-lg);
            margin-bottom: 30px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }

        .btn-filter {
            background: #111827;
            /* Dark background */
            color: #ffffff;
            padding: 10px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 42px;
            /* Matching height of select inputs */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-filter:hover {
            background: #1f2937;
            transform: translateY(-1px);
            box-shadow: 0 6px 12px -2px rgba(0, 0, 0, 0.15);
        }

        .btn-filter:active {
            transform: translateY(0);
        }

        .grid-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }

        .quiz-card {
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            padding: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }

        .quiz-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .quiz-card.locked {
            opacity: 0.8;
            filter: grayscale(0.5);
        }

        .qc-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(120deg, var(--surface-light), white);
        }

        .qc-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            background: var(--primary-light);
            color: var(--primary);
            margin-bottom: 10px;
        }

        .qc-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .qc-body {
            padding: 20px;
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .qc-stat {
            display: flex;
            flex-direction: column;
        }

        .qc-stat span {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .qc-stat strong {
            font-size: 18px;
            color: var(--dark);
            font-weight: 800;
        }

        .qc-footer {
            padding: 20px;
            background: var(--surface-light);
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-start {
            background: var(--primary);
            color: white;
            padding: 10px 24px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-start:hover {
            background: var(--primary-dark);
        }

        .btn-continue {
            background: var(--warning);
            color: white;
        }

        .btn-continue:hover {
            background: #d97706;
        }

        .error-msg {
            background: var(--danger-light);
            color: var(--danger);
            padding: 15px 20px;
            border-radius: var(--radius-md);
            border-left: 4px solid var(--danger);
            font-weight: 600;
            margin-bottom: 24px;
        }
    </style>
</head>

<body>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include 'includes/header.php'; ?>
            <div class="container-fluid" style="padding: 20px 40px;">
                <div style="margin-bottom: 30px;">
                    <h1 style="font-size: 28px; font-weight: 800; margin: 0 0 10px 0;">🎯 Mastery Assessments</h1>
                    <p style="color: var(--text-muted); margin: 0;">Challenge yourself with deep evaluations. Sessions are timed and randomized.</p>
                </div>

                <?php if (isset($_SESSION['quiz_error'])): ?>
                    <div class="error-msg"><?= htmlspecialchars($_SESSION['quiz_error']);
                                            unset($_SESSION['quiz_error']); ?></div>
                <?php endif; ?>

                <div class="filter-ribbon">
                    <form method="GET" style="display: flex; gap: 15px; align-items:flex-end; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 200px;">
                            <label style="font-size: 12px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 6px;">SEARCH</label>
                            <input type="text" name="search" value="<?= $searchFilter ?>" placeholder="Subject name..." style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px;">
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 6px;">YEAR</label>
                            <select name="year" style="padding: 10px; border: 1px solid var(--border); border-radius: 8px; min-width: 100px;">
                                <option value="">All Years</option>
                                <option value="FY" <?= $yearFilter == 'FY' ? 'selected' : '' ?>>First Year (FY)</option>
                                <option value="SY" <?= $yearFilter == 'SY' ? 'selected' : '' ?>>Second Year (SY)</option>
                                <option value="TY" <?= $yearFilter == 'TY' ? 'selected' : '' ?>>Third Year (TY)</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 6px;">SEMESTER</label>
                            <select name="semester" style="padding: 10px; border: 1px solid var(--border); border-radius: 8px; min-width: 100px;">
                                <option value="0">All</option>
                                <?php for ($i = 1; $i <= 6; $i++) echo "<option value='$i' " . ($semesterFilter == $i ? 'selected' : '') . ">Sem $i</option>"; ?>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 6px;">SORT BY</label>
                            <select name="sort" style="padding: 10px; border: 1px solid var(--border); border-radius: 8px; min-width: 140px;">
                                <option value="recent" <?= $sortFilter == 'recent' ? 'selected' : '' ?>>Most Recent</option>
                                <option value="max_score" <?= $sortFilter == 'max_score' ? 'selected' : '' ?>>Highest Score</option>
                                <option value="most_attempted" <?= $sortFilter == 'most_attempted' ? 'selected' : '' ?>>Most Attempted</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="btn-filter">Filter</button>
                        </div>
                    </form>
                </div>

                <div class="grid-cards">
                    <?php while ($s = $subjects->fetch_assoc()):
                        $isLocked = $s['q_count'] < 5; // require at least some questions
                        $isAtLimit = $s['my_attempts'] >= ATTEMPT_LIMIT;
                        $canStart = !$isLocked && !$isAtLimit;
                        $isInProgress = $s['last_status'] === 'in_progress';
                    ?>
                        <div class="quiz-card <?= $isLocked ? 'locked' : '' ?>">
                            <div class="qc-header">
                                <span class="qc-badge"><?= $s['year'] ?> / Sem <?= $s['semester'] ?></span>
                                <h3 class="qc-title"><?= htmlspecialchars($s['name']) ?></h3>
                            </div>
                            <div class="qc-body">
                                <div class="qc-stat">
                                    <span>Total Questions</span>
                                    <strong><?= $s['q_count'] ?></strong>
                                </div>
                                <div class="qc-stat">
                                    <span>Attempts</span>
                                    <strong><?= $s['my_attempts'] ?> / <?= ATTEMPT_LIMIT ?></strong>
                                </div>
                                <div class="qc-stat">
                                    <span>Best Score</span>
                                    <strong><?= $s['best_score'] !== null ? $s['best_score'] . '%' : '--' ?></strong>
                                </div>
                                <div class="qc-stat">
                                    <span>Average Score</span>
                                    <strong><?= $s['avg_score'] !== null ? round($s['avg_score']) . '%' : '--' ?></strong>
                                </div>
                            </div>
                            <div class="qc-footer">
                                <div style="font-size: 11px; color: var(--text-muted); font-weight: 600;">
                                    <?php if ($s['last_attempt']) echo 'LAST: ' . date('M d, Y', strtotime($s['last_attempt']));
                                    else echo 'NO ATTEMPTS YET'; ?>
                                </div>

                                <?php if ($isInProgress): ?>
                                    <form method="POST" action="quiz.php?action=start">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <input type="hidden" name="subject_id" value="<?= $s['id'] ?>">
                                        <button class="btn-start btn-continue">CONTINUE</button>
                                    </form>
                                <?php elseif ($canStart): ?>
                                    <form method="POST" action="quiz.php?action=start">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <input type="hidden" name="subject_id" value="<?= $s['id'] ?>">
                                        <button class="btn-start">START QUIZ</button>
                                    </form>
                                <?php else: ?>
                                    <div style="font-size: 13px; font-weight: 700; color: var(--danger);"><span style="margin-right:4px;">🔒</span> <?= $isAtLimit ? 'LIMIT REACHED' : 'UNAVAILABLE' ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </main>
    </div>
</body>

</html>