<?php

/**
 * ============================================================
 * Education Hub - Professional Teacher Dashboard
 * ============================================================
 */

require_once 'config/functions.php';
requireTeacher(); // Enforce teacher/admin access only

$teacherId = (int)$_SESSION['user_id'];
$isAdmin = isAdmin();
$currentUser = getCurrentUser();

$tz = new DateTimeZone(date_default_timezone_get());

// Base conditions for IDOR prevention
$subCond = $isAdmin ? "1=1" : "created_by = $teacherId";
$qCond = $isAdmin ? "1=1" : "created_by = $teacherId";
$nCond = $isAdmin ? "1=1" : "created_by = $teacherId";

// 1. KPI Queries
// Total Subjects
$res = $conn->query("SELECT COUNT(*) as c FROM subjects WHERE $subCond");
$kpiSubjects = $res->fetch_assoc()['c'];

// Total Questions
$res = $conn->query("SELECT COUNT(*) as c FROM questions WHERE $qCond AND is_deleted=0");
$kpiQuestions = $res->fetch_assoc()['c'];

// Total Notes
$res = $conn->query("SELECT COUNT(*) as c FROM notes WHERE $nCond AND is_deleted=0");
$kpiNotes = $res->fetch_assoc()['c'];

// Total Quizzes Conducted (Distinct Sessions)
$sessCond = $isAdmin ? "1=1" : "subject_id IN (SELECT id FROM subjects WHERE $subCond)";
$res = $conn->query("SELECT COUNT(*) as c FROM quiz_sessions WHERE $sessCond AND status='completed'");
$kpiQuizzes = $res->fetch_assoc()['c'];

// Total Student Attempts (Total distinct answers or just sessions)
// Wait, prompt says: Total Quizzes Conducted AND Student Attempts.
// Let's interpret Quizzes Conducted as overall sessions, Student Attempts as total quiz_attempts rows or sessions in progress+completed.
$res = $conn->query("SELECT COUNT(*) as c FROM quiz_sessions WHERE $sessCond");
$kpiAttempts = $res->fetch_assoc()['c'];

// Average Student Score %
$res = $conn->query("SELECT ROUND(AVG(score), 1) as avg_score FROM quiz_sessions WHERE $sessCond AND status='completed'");
$kpiAvgScore = $res->fetch_assoc()['avg_score'] ?? 0;

// 2. Trend Calculations (Last 7 days vs Previous 7 days) simply approximated
// We will calculate Questions added this week
$res = $conn->query("SELECT COUNT(*) as c FROM questions WHERE $qCond AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$qThisWeek = $res->fetch_assoc()['c'];
$res = $conn->query("SELECT COUNT(*) as c FROM questions WHERE $qCond AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY)");
$qLastWeek = $res->fetch_assoc()['c'];
$qTrend = $qLastWeek > 0 ? round((($qThisWeek - $qLastWeek) / $qLastWeek) * 100) : ($qThisWeek > 0 ? 100 : 0);

// Average score trend
$res = $conn->query("SELECT AVG(score) as c FROM quiz_sessions WHERE $sessCond AND status='completed' AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$sThisWeek = $res->fetch_assoc()['c'] ?? 0;
$res = $conn->query("SELECT AVG(score) as c FROM quiz_sessions WHERE $sessCond AND status='completed' AND completed_at BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY)");
$sLastWeek = $res->fetch_assoc()['c'] ?? 0;
$scoreTrend = $sLastWeek > 0 ? round((($sThisWeek - $sLastWeek) / $sLastWeek) * 100) : ($sThisWeek > 0 ? 10 : 0);

// Alerts Logic
$alerts = [];
$res = $conn->query("SELECT COUNT(*) as c FROM quiz_sessions WHERE $sessCond AND status='completed' AND score < 40 AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$lowScores = $res->fetch_assoc()['c'];
if ($lowScores > 0) $alerts[] = ["type" => "danger", "icon" => "🚨", "text" => "$lowScores students scored below 40% this week."];

if ($scoreTrend > 0) $alerts[] = ["type" => "success", "icon" => "📈", "text" => "Average score improved by $scoreTrend% compared to last week."];
elseif ($scoreTrend < 0) $alerts[] = ["type" => "warning", "icon" => "📉", "text" => "Average score dropped by " . abs($scoreTrend) . "% compared to last week."];

if ($qThisWeek == 0) $alerts[] = ["type" => "info", "icon" => "💡", "text" => "No new questions added in the last 7 days."];

if (empty($alerts)) $alerts[] = ["type" => "success", "icon" => "✅", "text" => "All systems are stable. Students are performing consistently."];

// 3. Performance Overview Data (Charts)
// Left: Line chart - Average performance over last 6 months
$months = [];
$monthlyScores = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('m', strtotime("-$i months"));
    $y = date('Y', strtotime("-$i months"));
    $label = date('M', strtotime("-$i months"));
    $months[] = $label;

    $res = $conn->query("SELECT AVG(score) as avg_sc FROM quiz_sessions WHERE $sessCond AND status='completed' AND MONTH(completed_at) = '$m' AND YEAR(completed_at) = '$y'");
    $val = $res->fetch_assoc()['avg_sc'] ?? 0;
    $monthlyScores[] = round($val, 1);
}

// Right: Bar chart - Questions by Difficulty
$diffStats = ['Easy' => 0, 'Medium' => 0, 'Hard' => 0];
$res = $conn->query("SELECT difficulty, COUNT(*) as c FROM questions WHERE $qCond AND is_deleted=0 GROUP BY difficulty");
while ($r = $res->fetch_assoc()) {
    $d = ucfirst(strtolower($r['difficulty']));
    if (isset($diffStats[$d])) $diffStats[$d] = $r['c'];
}

// 4. Recent Activity Feed
$activities = [];
// Quizzes
$res = $conn->query("
    SELECT 'quiz' as type, u.name, qs.completed_at as time, CONCAT('completed quiz for ', s.name, ' (Score: ', qs.score, '%)') as action
    FROM quiz_sessions qs 
    JOIN users u ON qs.student_id = u.id 
    JOIN subjects s ON qs.subject_id = s.id
    WHERE $sessCond AND qs.status='completed'
    ORDER BY qs.completed_at DESC LIMIT 5
");
while ($r = $res->fetch_assoc()) $activities[] = $r;
// Notes
$res = $conn->query("
    SELECT 'note' as type, u.name, n.created_at as time, CONCAT('uploaded new note: ', n.title) as action
    FROM notes n 
    JOIN users u ON n.created_by = u.id 
    WHERE $nCond AND n.is_deleted=0
    ORDER BY n.created_at DESC LIMIT 5
");
while ($r = $res->fetch_assoc()) $activities[] = $r;
// Questions
$res = $conn->query("
    SELECT 'question' as type, u.name, q.created_at as time, CONCAT('added a new question to subject #', q.subject_id) as action
    FROM questions q 
    JOIN users u ON q.created_by = u.id 
    WHERE $qCond AND q.is_deleted=0
    ORDER BY q.created_at DESC LIMIT 5
");
while ($r = $res->fetch_assoc()) $activities[] = $r;

// Sort by time DESC
usort($activities, function ($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});
$activities = array_slice($activities, 0, 8); // Top 8

function timeAgo($datetime)
{
    global $tz;
    $now = new DateTime('now', $tz);
    $ago = new DateTime($datetime, $tz);
    $diff = $now->diff($ago);
    if ($diff->d > 0) return $diff->d . " days ago";
    if ($diff->h > 0) return $diff->h . " hours ago";
    if ($diff->i > 0) return $diff->i . " mins ago";
    return "Just now";
}

// 6. Subject Performance Table
$page = (int)($_GET['page'] ?? 1);
$limit = 5;
$offset = ($page - 1) * $limit;
$search = sanitize($_GET['search'] ?? '');
$sort = sanitize($_GET['sort'] ?? 's.name');
$dir = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'DESC' : 'ASC';

$validSorts = ['s.name', 's.year', 'total_qs', 'attempts', 'avg_score', 'pass_rate'];
if (!in_array($sort, $validSorts)) $sort = 's.name';

$tableWhere = $subCond;
if ($search) $tableWhere .= " AND s.name LIKE '%$search%'";

$tableQuery = "
    SELECT s.id, s.name, s.year, s.semester,
        (SELECT COUNT(*) FROM questions WHERE subject_id = s.id AND is_deleted=0) as total_qs,
        (SELECT COUNT(*) FROM quiz_sessions WHERE subject_id = s.id) as attempts,
        (SELECT AVG(score) FROM quiz_sessions WHERE subject_id = s.id AND status='completed') as avg_score,
        (SELECT (SUM(CASE WHEN score >= " . PASS_PERCENTAGE . " THEN 1 ELSE 0 END)/COUNT(*))*100 FROM quiz_sessions WHERE subject_id = s.id AND status='completed') as pass_rate
    FROM subjects s
    WHERE $tableWhere
    ORDER BY $sort $dir
    LIMIT $offset, $limit
";
$subjData = $conn->query($tableQuery)->fetch_all(MYSQLI_ASSOC);

$totalSubj = $conn->query("SELECT COUNT(*) as c FROM subjects s WHERE $tableWhere")->fetch_assoc()['c'];
$totalPages = ceil($totalSubj / $limit);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Admin Dashboard - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Modern SaaS Dashboard Overrides */
        body {
            background: #f3f4f6;
            font-family: 'Inter', sans-serif;
            color: var(--dark);
            margin: 0;
        }

        .dashboard-container {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Typography */
        h1,
        h2,
        h3,
        h4 {
            margin: 0;
            font-weight: 700;
            color: var(--dark);
        }

        .text-muted {
            color: var(--text-muted);
        }

        .fw-bold {
            font-weight: 700;
        }

        .fw-semibold {
            font-weight: 600;
        }

        /* Cards */
        .saas-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .card-header-styled {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
        }

        .card-body-styled {
            padding: 24px;
        }

        /* KPI Grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .kpi-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .kpi-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .kpi-card.c-primary::before {
            background: var(--primary);
        }

        .kpi-card.c-success::before {
            background: var(--success);
        }

        .kpi-card.c-warning::before {
            background: var(--warning);
        }

        .kpi-card.c-info::before {
            background: var(--info, #3b82f6);
        }

        .kpi-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .kpi-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .icon-primary {
            background: var(--primary-light);
            color: var(--primary);
        }

        .icon-success {
            background: var(--success-light);
            color: var(--success);
        }

        .icon-warning {
            background: var(--warning-light);
            color: var(--warning);
        }

        .icon-info {
            background: #dbeafe;
            color: #3b82f6;
        }

        .kpi-value {
            font-size: 28px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 8px;
            color: var(--dark);
        }

        .kpi-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kpi-trend {
            font-size: 12px;
            font-weight: 600;
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .trend-up {
            color: var(--success);
        }

        .trend-down {
            color: var(--danger);
        }

        .trend-flat {
            color: var(--text-muted);
        }

        /* Quick Actions */
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 15px;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: white;
            border: 1px solid var(--border);
            padding: 20px 10px;
            border-radius: var(--radius-md);
            text-decoration: none;
            color: var(--text);
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s;
            gap: 10px;
            text-align: center;
        }

        .action-btn:hover {
            border-color: var(--primary);
            background: var(--primary-light);
            color: var(--primary);
            transform: translateY(-2px);
        }

        .action-btn i {
            font-size: 24px;
        }

        /* Multi-Column Layout */
        .dashboard-row-split {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        @media (max-width: 1024px) {
            .dashboard-row-split {
                grid-template-columns: 1fr;
            }
        }

        /* Activity Feed */
        .activity-feed {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .activity-item {
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-size: 14px;
            color: var(--dark);
            margin: 0 0 4px 0;
            line-height: 1.4;
        }

        .activity-time {
            font-size: 12px;
            color: var(--text-muted);
            margin: 0;
        }

        /* Table */
        .saas-table {
            width: 100%;
            border-collapse: collapse;
        }

        .saas-table th {
            text-align: left;
            padding: 12px 16px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            border-bottom: 2px solid var(--border);
            background: var(--surface-light);
            font-weight: 700;
            white-space: nowrap;
        }

        .saas-table td {
            padding: 16px;
            font-size: 14px;
            border-bottom: 1px solid var(--border);
            color: var(--dark);
            vertical-align: middle;
        }

        .saas-table tr:hover td {
            background: var(--surface-light);
        }

        .saas-table a.sortable {
            color: var(--text-muted);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .saas-table a.sortable:hover {
            color: var(--primary);
        }

        /* Alerts Panel */
        .alerts-panel {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .alert-card {
            padding: 16px;
            border-radius: var(--radius-md);
            border-left: 4px solid;
            display: flex;
            gap: 12px;
            align-items: center;
            font-size: 14px;
            background: white;
        }

        .alert-success {
            border-color: var(--success);
            background: var(--success-light);
            color: #065f46;
        }

        .alert-warning {
            border-color: var(--warning);
            background: var(--warning-light);
            color: #92400e;
        }

        .alert-danger {
            border-color: var(--danger);
            background: var(--danger-light);
            color: #991b1b;
        }

        .alert-info {
            border-color: #3b82f6;
            background: #dbeafe;
            color: #1e40af;
        }

        /* Canvas Wrappers */
        .chart-wrapper {
            position: relative;
            height: 250px;
            width: 100%;
            display: block;
        }

        canvas {
            display: block;
            width: 100%;
            height: 100%;
        }

        /* Header specifics */
        .saas-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 40px;
            background: white;
            border-bottom: 1px solid var(--border);
        }

        .header-title-wrapper h1 {
            font-size: 24px;
            margin-bottom: 4px;
        }

        .header-title-wrapper p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
        }

        .header-profile-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notif-bell {
            position: relative;
            font-size: 20px;
            cursor: pointer;
            color: var(--text-muted);
            transition: color 0.2s;
        }

        .notif-bell:hover {
            color: var(--primary);
        }

        .notif-badge {
            position: absolute;
            top: -5px;
            right: -8px;
            background: var(--danger);
            color: white;
            font-size: 10px;
            padding: 2px 5px;
            border-radius: 10px;
            font-weight: bold;
            border: 2px solid white;
        }

        .profile-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 30px;
            border: 1px solid var(--border);
            transition: background 0.2s;
        }

        .profile-chip:hover {
            background: var(--surface-light);
        }

        .profile-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }

        .pagination {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-top: 20px;
            justify-content: flex-end;
        }

        .page-btn {
            padding: 6px 12px;
            border: 1px solid var(--border);
            background: white;
            border-radius: 4px;
            color: var(--text);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .page-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .page-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .search-input {
            padding: 8px 16px;
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: 13px;
            width: 250px;
            outline: none;
            transition: border-color 0.2s;
        }

        .search-input:focus {
            border-color: var(--primary);
        }
    </style>
</head>

<body>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="padding:0; background: #f9fafb;">
            <!-- Top Header -->
            <header class="saas-header">
                <div class="header-title-wrapper">
                    <h1>Teacher Dashboard</h1>
                    <p>Overview of your classes and activity</p>
                </div>
                <div class="header-profile-section">
                    <div class="notif-bell">
                        🔔 <span class="notif-badge"><?= count($alerts) ?></span>
                    </div>
                    <div class="profile-chip" onclick="window.location.href='profile.php'">
                        <div class="profile-avatar">
                            <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div style="font-size: 14px; font-weight: 700; color: var(--dark); line-height: 1.2;"><?= htmlspecialchars($currentUser['name']) ?></div>
                            <div style="font-size: 11px; color: var(--primary); font-weight: 600; text-transform: uppercase;">
                                <?= $isAdmin ? 'Administrator' : 'Instructor' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="dashboard-container">

                <!-- ROW 1: KPI CARDS -->
                <div class="kpi-grid">
                    <div class="kpi-card c-primary">
                        <div class="kpi-top">
                            <div>
                                <div class="kpi-value"><?= number_format($kpiSubjects) ?></div>
                                <div class="kpi-label">Total Subjects</div>
                            </div>
                            <div class="kpi-icon icon-primary">📚</div>
                        </div>
                    </div>

                    <div class="kpi-card c-success">
                        <div class="kpi-top">
                            <div>
                                <div class="kpi-value"><?= number_format($kpiQuestions) ?></div>
                                <div class="kpi-label">Questions Created</div>
                            </div>
                            <div class="kpi-icon icon-success">❓</div>
                        </div>
                        <div class="kpi-trend <?= $qTrend >= 0 ? 'trend-up' : 'trend-down' ?>">
                            <?= $qTrend >= 0 ? '↑' : '↓' ?> <?= abs($qTrend) ?>% this week
                        </div>
                    </div>

                    <div class="kpi-card c-info">
                        <div class="kpi-top">
                            <div>
                                <div class="kpi-value"><?= number_format($kpiNotes) ?></div>
                                <div class="kpi-label">Notes Uploaded</div>
                            </div>
                            <div class="kpi-icon icon-info">📝</div>
                        </div>
                    </div>

                    <div class="kpi-card c-warning">
                        <div class="kpi-top">
                            <div>
                                <div class="kpi-value"><?= number_format($kpiQuizzes) ?></div>
                                <div class="kpi-label">Quizzes Conducted</div>
                            </div>
                            <div class="kpi-icon icon-warning">🎯</div>
                        </div>
                    </div>

                    <div class="kpi-card c-primary">
                        <div class="kpi-top">
                            <div>
                                <div class="kpi-value"><?= number_format($kpiAttempts) ?></div>
                                <div class="kpi-label">Student Attempts</div>
                            </div>
                            <div class="kpi-icon icon-primary">👥</div>
                        </div>
                    </div>

                    <div class="kpi-card c-success">
                        <div class="kpi-top">
                            <div>
                                <div class="kpi-value"><?= $kpiAvgScore ?>%</div>
                                <div class="kpi-label">Avg Student Score</div>
                            </div>
                            <div class="kpi-icon icon-success">📊</div>
                        </div>
                        <div class="kpi-trend <?= $scoreTrend >= 0 ? 'trend-up' : 'trend-down' ?>">
                            <?= $scoreTrend >= 0 ? '↑' : '↓' ?> <?= abs($scoreTrend) ?>% vs last week
                        </div>
                    </div>
                </div>

                <!-- ROW 2: CHARTS & QUICK ACTIONS -->
                <div class="dashboard-row-split">
                    <!-- Analytics -->
                    <div class="saas-card">
                        <div class="card-header-styled">
                            <h3 style="font-size: 16px;">Performance Overview</h3>
                        </div>
                        <div class="card-body-styled">
                            <div class="dashboard-row-split" style="gap: 40px;">
                                <div style="flex:1;">
                                    <h4 style="font-size: 13px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 20px;">Average Score (Last 6 Months)</h4>
                                    <div class="chart-wrapper">
                                        <canvas id="lineChart"></canvas>
                                    </div>
                                </div>
                                <div style="flex: 1;">
                                    <h4 style="font-size: 13px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 20px;">Questions by Difficulty</h4>
                                    <div class="chart-wrapper">
                                        <canvas id="barChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Side Panel: Quick Actions + Alerts -->
                    <div style="display: flex; flex-direction: column; gap: 24px;">
                        <div class="saas-card">
                            <div class="card-header-styled">
                                <h3 style="font-size: 16px;">Quick Actions</h3>
                            </div>
                            <div class="card-body-styled">
                                <div class="action-grid">
                                    <a href="manage_questions.php" class="action-btn"><i>➕</i> Add Question</a>
                                    <a href="notes_management.php" class="action-btn"><i>📤</i> Upload Notes</a>
                                    <a href="teacher_performance.php" class="action-btn"><i>📈</i> Analytics</a>
                                    <a href="manage_subjects.php" class="action-btn"><i>📚</i> Subjects</a>
                                </div>
                            </div>
                        </div>

                        <div class="saas-card">
                            <div class="card-header-styled">
                                <h3 style="font-size: 16px;">Smart Insights</h3>
                            </div>
                            <div class="card-body-styled">
                                <div class="alerts-panel">
                                    <?php foreach ($alerts as $al): ?>
                                        <div class="alert-card alert-<?= $al['type'] ?>">
                                            <span style="font-size: 18px;"><?= $al['icon'] ?></span>
                                            <span><?= htmlspecialchars($al['text']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ROW 3: TABLE & RECENT ACTIVITY -->
                <div class="dashboard-row-split">
                    <!-- Subject Table -->
                    <div class="saas-card">
                        <div class="card-header-styled">
                            <h3 style="font-size: 16px;">Subject Performance</h3>
                            <form method="GET" style="margin: 0;">
                                <input type="text" name="search" class="search-input" placeholder="Search subjects..." value="<?= htmlspecialchars($search) ?>">
                            </form>
                        </div>
                        <div style="overflow-x: auto;">
                            <table class="saas-table">
                                <thead>
                                    <tr>
                                        <?php
                                        $cols = ['s.name' => 'Subject Name', 's.year' => 'Program', 'total_qs' => 'Questions', 'attempts' => 'Attempts', 'avg_score' => 'Avg Score', 'pass_rate' => 'Pass Rate'];
                                        foreach ($cols as $k => $v):
                                            $newDir = ($sort === $k && $dir === 'ASC') ? 'desc' : 'asc';
                                            $arrow = $sort === $k ? ($dir === 'ASC' ? '↑' : '↓') : '';
                                        ?>
                                            <th><a href="?sort=<?= $k ?>&dir=<?= $newDir ?>&search=<?= urlencode($search) ?>" class="sortable"><?= $v ?> <?= $arrow ?></a></th>
                                        <?php endforeach; ?>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($subjData)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align:center; padding: 40px; color: var(--text-muted);">No subjects found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($subjData as $s): ?>
                                            <tr>
                                                <td class="fw-bold"><?= htmlspecialchars($s['name']) ?></td>
                                                <td><?= $s['year'] ?> - S<?= $s['semester'] ?></td>
                                                <td><?= $s['total_qs'] ?></td>
                                                <td><?= $s['attempts'] ?></td>
                                                <td><span class="<?= $s['avg_score'] >= PASS_PERCENTAGE ? 'text-success' : 'text-danger' ?> fw-bold"><?= $s['avg_score'] !== null ? round($s['avg_score'], 1) . '%' : '--' ?></span></td>
                                                <td>
                                                    <div style="display:flex; align-items:center; gap: 8px;">
                                                        <div style="flex:1; background: var(--surface-light); height: 6px; border-radius: 3px; overflow:hidden;">
                                                            <div style="height:100%; width: <?= $s['pass_rate'] ?? 0 ?>%; background: var(--primary);"></div>
                                                        </div>
                                                        <span style="font-size:12px; font-weight:700;"><?= $s['pass_rate'] !== null ? round($s['pass_rate']) . '%' : '--' ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="teacher_performance.php?subject_id=<?= $s['id'] ?>" style="color: var(--primary); font-weight: 600; text-decoration: none; font-size: 13px;">View Insights →</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <div class="card-body-styled" style="border-top: 1px solid var(--border);">
                                <div class="pagination">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <a href="?page=<?= $i ?>&sort=<?= $sort ?>&dir=<?= $dir ?>&search=<?= urlencode($search) ?>" class="page-btn <?= $page === $i ? 'active' : '' ?>"><?= $i ?></a>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Activity -->
                    <div class="saas-card">
                        <div class="card-header-styled">
                            <h3 style="font-size: 16px;">Recent Activity</h3>
                        </div>
                        <div class="card-body-styled">
                            <div class="activity-feed">
                                <?php if (empty($activities)): ?>
                                    <p class="text-muted" style="text-align:center; padding: 20px;">No recent activity.</p>
                                <?php else: ?>
                                    <?php foreach ($activities as $act):
                                        $iconParams = [
                                            'quiz' => ['bg' => 'var(--primary-light)', 'color' => 'var(--primary)', 'char' => '🎯'],
                                            'note' => ['bg' => '#dbeafe', 'color' => '#3b82f6', 'char' => '📝'],
                                            'question' => ['bg' => 'var(--success-light)', 'color' => 'var(--success)', 'char' => '➕']
                                        ];
                                        $ic = $iconParams[$act['type']];
                                    ?>
                                        <div class="activity-item">
                                            <div class="activity-icon" style="background: <?= $ic['bg'] ?>; color: <?= $ic['color'] ?>;">
                                                <?= $ic['char'] ?>
                                            </div>
                                            <div class="activity-content">
                                                <p class="activity-text"><span class="fw-bold"><?= htmlspecialchars($act['name']) ?></span> <?= htmlspecialchars($act['action']) ?></p>
                                                <p class="activity-time"><?= timeAgo($act['time']) ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        // Custom HTML5 Canvas Line Chart
        function drawLineChart(canvasId, labels, dataPoints, colorPrimary) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const dpr = window.devicePixelRatio || 1;
            const rect = canvas.parentNode.getBoundingClientRect();
            canvas.width = rect.width * dpr;
            canvas.height = rect.height * dpr;
            ctx.scale(dpr, dpr);

            const w = rect.width;
            const h = rect.height;
            const padding = 30;
            const maxVal = Math.max(...dataPoints, 100);

            // Draw Grid
            ctx.strokeStyle = '#e5e7eb';
            ctx.lineWidth = 1;
            ctx.beginPath();
            for (let i = 0; i <= 5; i++) {
                const y = padding + (i * (h - padding * 2) / 5);
                ctx.moveTo(padding, y);
                ctx.lineTo(w - padding, y);
            }
            ctx.stroke();

            // Draw Line
            ctx.strokeStyle = colorPrimary;
            ctx.lineWidth = 3;
            ctx.lineJoin = 'round';
            ctx.beginPath();
            const points = [];
            dataPoints.forEach((val, i) => {
                const x = padding + (i * (w - padding * 2) / (dataPoints.length - 1 || 1));
                const y = (h - padding) - ((val / maxVal) * (h - padding * 2));
                points.push({
                    x,
                    y
                });
                if (i === 0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            });
            ctx.stroke();

            // Draw Area Gradient
            const gradient = ctx.createLinearGradient(0, padding, 0, h - padding);
            gradient.addColorStop(0, colorPrimary + '60');
            gradient.addColorStop(1, colorPrimary + '00');
            ctx.lineTo(w - padding, h - padding);
            ctx.lineTo(padding, h - padding);
            ctx.closePath();
            ctx.fillStyle = gradient;
            ctx.fill();

            // Draw Points & Text
            ctx.font = '11px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillStyle = '#6b7280';
            points.forEach((p, i) => {
                ctx.fillText(labels[i], p.x, h - 5);
                ctx.fillStyle = colorPrimary;
                ctx.beginPath();
                ctx.arc(p.x, p.y, 4, 0, Math.PI * 2);
                ctx.fill();
                ctx.fillStyle = '#6b7280';
            });
        }

        // Custom HTML5 Canvas Bar Chart
        function drawBarChart(canvasId, dataArr) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const dpr = window.devicePixelRatio || 1;
            const rect = canvas.parentNode.getBoundingClientRect();
            canvas.width = rect.width * dpr;
            canvas.height = rect.height * dpr;
            ctx.scale(dpr, dpr);

            const w = rect.width;
            const h = rect.height;
            const padding = 30;
            const maxVal = Math.max(...dataArr.map(d => d.val), 10) * 1.2;
            const barWidth = 40;
            const gap = (w - (padding * 2) - (barWidth * dataArr.length)) / (dataArr.length + 1);

            ctx.font = '12px sans-serif';
            ctx.textAlign = 'center';

            dataArr.forEach((item, i) => {
                const x = padding + gap + (i * (barWidth + gap));
                const barH = (item.val / maxVal) * (h - padding * 2);
                const y = h - padding - barH;

                // Track bar
                ctx.fillStyle = '#f3f4f6';
                ctx.beginPath();
                ctx.roundRect(x, padding, barWidth, h - padding * 2, 4);
                ctx.fill();
                // Value bar
                ctx.fillStyle = item.color;
                ctx.beginPath();
                ctx.roundRect(x, y, barWidth, barH, [4, 4, 0, 0]);
                ctx.fill();
                // Text
                ctx.fillStyle = '#6b7280';
                ctx.fillText(item.label, x + barWidth / 2, h - 10);
                if (item.val > 0) {
                    ctx.fillStyle = '#111827';
                    ctx.font = 'bold 12px sans-serif';
                    ctx.fillText(item.val, x + barWidth / 2, y - 8);
                    ctx.font = '12px sans-serif';
                }
            });
        }

        window.onload = function() {
            const root = getComputedStyle(document.body);
            const primary = root.getPropertyValue('--primary').trim() || '#1a56db';

            const lineLabels = <?= json_encode(array_reverse($months)) ?>;
            const lineData = <?= json_encode(array_reverse($monthlyScores)) ?>;
            drawLineChart('lineChart', lineLabels, lineData, primary);

            const barData = [{
                    label: 'Easy',
                    val: <?= $diffStats['Easy'] ?>,
                    color: '#10b981'
                },
                {
                    label: 'Medium',
                    val: <?= $diffStats['Medium'] ?>,
                    color: '#f59e0b'
                },
                {
                    label: 'Hard',
                    val: <?= $diffStats['Hard'] ?>,
                    color: '#ef4444'
                }
            ];
            drawBarChart('barChart', barData);
        };
        // Auto redraw on resize
        window.addEventListener('resize', () => {
            window.onload();
        });
    </script>
</body>

</html>