<?php

/**
 * ============================================================
 * Education Hub - Advanced Student Performance Analytics (teacher_performance.php)
 * ============================================================
 *
 * PURPOSE:
 *   Comprehensive analytics dashboard for teachers showing detailed student
 *   performance insights, individual tracking, class analytics, and alerts.
 *
 * FEATURES:
 *   - Individual student detail views with charts
 *   - Class overview dashboard with performance metrics
 *   - Student rankings and comparative analysis
 *   - Performance alerts for struggling students
 *   - Subject-wise performance breakdown
 *   - Progress trends and engagement metrics
 *
 * ACCESS: Teachers and Admins only
 * ============================================================
 */

require_once 'config/functions.php';
requireLogin();

/* Only teachers and admins can view student performance */
if (!isTeacher() && !isAdmin()) {
    redirect('dashboard.php');
}

$pageTitle = 'Student Performance Analytics';

/* --- Get Semester Filter --- */
$semesterFilter = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
$yearFilter = isset($_GET['year']) ? sanitize($_GET['year']) : '';
$viewType = isset($_GET['view']) ? sanitize($_GET['view']) : 'overview'; // overview, student, alerts

/* --- Build WHERE clause for filters --- */
$whereClause = '';
$filterConditions = [];
if ($semesterFilter > 0) $filterConditions[] = "s.semester = $semesterFilter";
if ($yearFilter) $filterConditions[] = "s.year = '$yearFilter'";
$whereClause = !empty($filterConditions) ? " AND " . implode(" AND ", $filterConditions) : '';

/* --- Comprehensive Student Analytics Query --- */
// Using prepared statement for security and production-ready logic
$filterSql = "";
$params = [];
$types = "";

if ($semesterFilter > 0) {
    $filterSql .= " AND s.semester = ?";
    $params[] = $semesterFilter;
    $types .= "i";
}
if ($yearFilter) {
    $filterSql .= " AND s.year = ?";
    $params[] = $yearFilter;
    $types .= "s";
}

$studentsQuery = "
    SELECT
        u.id,
        u.name,
        u.email,
        u.year as student_year,
        u.mobile,
        COUNT(qs.id) as total_quizzes,
        IFNULL(ROUND(AVG(qs.score), 1), 0) as avg_accuracy,
        IFNULL(MAX(qs.score), 0) as best_score,
        IFNULL(MIN(qs.score), 0) as lowest_score,
        COUNT(DISTINCT CASE WHEN qs.score >= 80 THEN qs.id END) as excellent_quizzes,
        COUNT(DISTINCT CASE WHEN qs.score < 40 THEN qs.id END) as poor_quizzes,
        MAX(qs.completed_at) as last_active,
        COUNT(DISTINCT qs.subject_id) as subjects_attempted
    FROM users u
    LEFT JOIN quiz_sessions qs ON u.id = qs.student_id AND qs.status = 'completed'
    LEFT JOIN subjects s ON qs.subject_id = s.id
    WHERE u.role = 'student'
    $filterSql
    GROUP BY u.id
    ORDER BY avg_accuracy DESC
";

$stmt = $conn->prepare($studentsQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$studentsRes = $stmt->get_result();

/* --- Calculate Class Statistics --- */
$classStats = [
    'total_students' => 0,
    'active_students' => 0,
    'avg_accuracy' => 0,
    'excellent_performers' => 0,
    'struggling_students' => 0,
    'inactive_students' => 0,
    'total_quizzes' => 0
];

$studentRankingData = [];
$alerts = [];
$inactiveList = [];

while ($student = $studentsRes->fetch_assoc()) {
    $studentRankingData[] = $student;
    $classStats['total_students']++;
    $classStats['total_quizzes'] += $student['total_quizzes'];

    if ($student['avg_accuracy'] > 0) {
        $classStats['active_students']++;
        $classStats['avg_accuracy'] += $student['avg_accuracy'];

        if ($student['avg_accuracy'] >= 80) $classStats['excellent_performers']++;
        if ($student['avg_accuracy'] < 50) $classStats['struggling_students']++;

        // Performance alerts
        if ($student['avg_accuracy'] < 40) {
            $alerts[] = [
                'type' => 'critical',
                'student' => $student['name'],
                'message' => 'Very low performance - needs immediate attention',
                'metric' => $student['avg_accuracy'] . '% average'
            ];
        } elseif ($student['poor_quizzes'] > $student['excellent_quizzes']) {
            $alerts[] = [
                'type' => 'warning',
                'student' => $student['name'],
                'message' => 'Inconsistent performance - more poor scores than excellent',
                'metric' => $student['poor_quizzes'] . ' poor vs ' . $student['excellent_quizzes'] . ' excellent quizzes'
            ];
        }

        // Inactive alerts (no activity in 7 days)
        if ($student['last_active'] && strtotime($student['last_active']) < strtotime('-7 days')) {
            $classStats['inactive_students']++;
            $inactiveList[] = $student; // Add to list
            $alerts[] = [
                'type' => 'info',
                'student' => $student['name'],
                'message' => 'Inactive for 7+ days - encourage participation',
                'metric' => 'Last active: ' . formatDate($student['last_active'])
            ];
        }
    }
}

$classStats['avg_accuracy'] = $classStats['active_students'] > 0 ?
    round($classStats['avg_accuracy'] / $classStats['active_students'], 1) : 0;

/* --- Individual Student Detail View --- */
$selectedStudent = null;
$academicSnapshot = null;
$subjectPerformance = null;
$recentQuizzes = null;
$performanceTrend = null;
$rankingInfo = null;
$weakAreas = [];

if ((isset($_GET['student_id']) && is_numeric($_GET['student_id'])) || ($viewType === 'student_detail' && isset($_GET['student_id']))) {
    $studentId = (int)$_GET['student_id'];

    // 1. Fetch Student Profile
    $stmt = $conn->prepare("SELECT id, name, email, year, mobile, role, created_at FROM users WHERE id = ? AND role = 'student'");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $selectedStudent = $stmt->get_result()->fetch_assoc();

    if ($selectedStudent) {
        // 2. Academic Overview (Snapshot)
        $snapStmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_quizzes,
                IFNULL(ROUND(AVG(score), 1), 0) as avg_score,
                IFNULL(MAX(score), 0) as max_score,
                IFNULL(MIN(score), 0) as min_score,
                COUNT(DISTINCT subject_id) as total_subjects,
                IFNULL(ROUND((SUM(CASE WHEN score >= ? THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1), 0) as pass_rate,
                MAX(completed_at) as last_active
            FROM quiz_sessions 
            WHERE student_id = ? AND status = 'completed'
        ");
        $passPercentage = PASS_PERCENTAGE;
        $snapStmt->bind_param("ii", $passPercentage, $studentId);
        $snapStmt->execute();
        $academicSnapshot = $snapStmt->get_result()->fetch_assoc();

        // 3. Subject-wise Analysis
        $subjStmt = $conn->prepare("
            SELECT 
                s.name, 
                COUNT(qs.id) as total_quizzes,
                IFNULL(ROUND(AVG(qs.score), 1), 0) as avg_score,
                IFNULL(MAX(qs.score), 0) as best_score
            FROM subjects s
            LEFT JOIN quiz_sessions qs ON s.id = qs.subject_id AND qs.student_id = ? AND qs.status = 'completed'
            GROUP BY s.id
            HAVING total_quizzes > 0
            ORDER BY avg_score DESC
        ");
        $subjStmt->bind_param("i", $studentId);
        $subjStmt->execute();
        $subjectPerformance = $subjStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Weak Areas Detection
        foreach ($subjectPerformance as $subj) {
            if ($subj['avg_score'] < 50) {
                $weakAreas[] = $subj;
            }
        }

        // 4. Quiz History (Recent 15)
        $histStmt = $conn->prepare("
            SELECT qs.*, s.name as subject_name
            FROM quiz_sessions qs
            JOIN subjects s ON qs.subject_id = s.id
            WHERE qs.student_id = ? AND qs.status = 'completed'
            ORDER BY qs.completed_at DESC
            LIMIT 15
        ");
        $histStmt->bind_param("i", $studentId);
        $histStmt->execute();
        $recentQuizzes = $histStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // 5. Performance Trend (Last 5 vs Previous 5)
        $trendStmt = $conn->prepare("
            SELECT score FROM quiz_sessions 
            WHERE student_id = ? AND status = 'completed' 
            ORDER BY completed_at DESC LIMIT 10
        ");
        $trendStmt->bind_param("i", $studentId);
        $trendStmt->execute();
        $trendRes = $trendStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $last5 = array_slice($trendRes, 0, 5);
        $prev5 = array_slice($trendRes, 5, 5);

        $last5Avg = count($last5) > 0 ? array_sum(array_column($last5, 'score')) / count($last5) : 0;
        $prev5Avg = count($prev5) > 0 ? array_sum(array_column($prev5, 'score')) / count($prev5) : 0;

        $trendIndicator = 'Stable';
        if ($last5Avg > $prev5Avg + 5) $trendIndicator = 'Improving';
        elseif ($last5Avg < $prev5Avg - 5) $trendIndicator = 'Declining';

        $performanceTrend = [
            'last_5_avg' => round($last5Avg, 1),
            'prev_5_avg' => round($prev5Avg, 1),
            'indicator' => $trendIndicator
        ];

        // 6. Ranking Info
        $rankStmt = $conn->prepare("
            SELECT rank, total_students 
            FROM (
                SELECT student_id, RANK() OVER (ORDER BY AVG(score) DESC) as rank, COUNT(*) OVER() as total_students
                FROM quiz_sessions WHERE status = 'completed' GROUP BY student_id
            ) ranked WHERE student_id = ?
        ");
        $rankStmt->bind_param("i", $studentId);
        $rankStmt->execute();
        $rankingInfo = $rankStmt->get_result()->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Performance Analytics - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/performance.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 16px;
        }

        .analytics-tab {
            padding: 10px 20px;
            border-radius: var(--radius-md);
            background: var(--surface-light);
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .analytics-tab.active {
            background: var(--primary);
            color: white;
        }

        .analytics-tab:hover:not(.active) {
            background: var(--primary-light);
            color: var(--primary);
        }

        .alerts-section {
            margin-bottom: 24px;
        }

        .alert-item {
            padding: 16px;
            border-radius: var(--radius-md);
            margin-bottom: 12px;
            border-left: 4px solid;
        }

        .alert-critical {
            background: var(--danger-light);
            border-left-color: var(--danger);
        }

        .alert-warning {
            background: var(--warning-light);
            border-left-color: var(--warning);
        }

        .alert-info {
            background: var(--info-light, #dbeafe);
            border-left-color: var(--info, #3b82f6);
        }

        .student-detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .student-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .performance-card {
            background: var(--surface);
            border-radius: var(--radius-md);
            padding: 16px;
            border: 1px solid var(--border);
            text-align: center;
        }

        .performance-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
        }

        .performance-label {
            color: var(--text-muted);
            font-size: 12px;
        }

        .subject-performance-grid {
            display: grid;
            gap: 12px;
        }

        .subject-performance-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: var(--surface);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
        }

        .subject-badge {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .filters-section {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: end;
        }

        .student-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .student-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .analytics-tabs {
                flex-wrap: wrap;
            }

            .student-detail-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
        }

        .weakness-analysis {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .student-weakness-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .student-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: linear-gradient(135deg, var(--primary-lighter), var(--surface));
            border-bottom: 1px solid var(--border);
        }

        .student-info h4 {
            margin: 0 0 4px 0;
            color: var(--text);
            font-size: 18px;
        }

        .student-meta {
            font-size: 12px;
            color: var(--text-muted);
        }

        .weakness-indicator {
            text-align: right;
        }

        .weakness-badge {
            padding: 6px 12px;
            border-radius: var(--radius-md);
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .weakness-badge.danger {
            background: var(--danger-light);
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        .weakness-badge.success {
            background: var(--success-light);
            color: var(--success);
            border: 1px solid var(--success);
        }

        .subject-performance-list {
            padding: 0;
        }

        .subject-performance-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            transition: background-color 0.2s ease;
        }

        .subject-performance-item:last-child {
            border-bottom: none;
        }

        .subject-performance-item:hover {
            background: var(--surface-light);
        }

        .subject-performance-item.weak {
            background: var(--danger-light);
            border-left: 4px solid var(--danger);
        }

        .subject-performance-item.average {
            background: var(--warning-light);
            border-left: 4px solid var(--warning);
        }

        .subject-performance-item.strong {
            background: var(--success-light);
            border-left: 4px solid var(--success);
        }

        .subject-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .subject-name {
            font-weight: 600;
            color: var(--text);
            margin-bottom: 2px;
        }

        .subject-stats {
            font-size: 12px;
            color: var(--text-muted);
        }

        .performance-score {
            text-align: right;
        }

        .score-value {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .score-value.danger {
            color: var(--danger);
        }

        .score-value.warning {
            color: var(--warning);
        }

        .score-value.success {
            color: var(--success);
        }

        .score-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        @media (max-width: 768px) {
            .student-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .subject-performance-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .performance-score {
                text-align: left;
            }
        }
    </style>
</head>

<body>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'includes/header.php'; ?>

            <section>
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-header">
                        <h3 class="card-title">📊 Student Performance Analytics</h3>
                        <p style="color: var(--text-muted); margin: 4px 0 0 0;">Comprehensive insights into student learning and performance</p>
                    </div>
                </div>

                <!-- === SEPARATE INACTIVE STUDENTS TILE === -->
                <?php if (!empty($inactiveList)): ?>
                    <div class="card" style="border-left: 5px solid var(--danger); margin-bottom: 32px;">
                        <div class="card-header">
                            <h3 class="card-title" style="color: var(--danger);">💤 Inactive Students (Attention Required)</h3>
                            <span class="badge badge-danger"><?= count($inactiveList) ?> Pending</span>
                        </div>
                        <p style="color: var(--text-muted); margin-bottom: 20px;">The following students haven't attempted a quiz in over 7 days. Consider sending a reminders.</p>

                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
                            <?php foreach ($inactiveList as $is): ?>
                                <div class="review-card" style="flex-direction: row; align-items: center; gap: 15px; padding: 16px;">
                                    <div class="user-avatar" style="width: 45px; height: 45px; font-size: 16px;">
                                        <?= strtoupper(substr($is['name'], 0, 1)) ?>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 700; font-size: 15px;"><?= htmlspecialchars($is['name']) ?></div>
                                        <div style="font-size: 12px; color: var(--text-muted);">
                                            Last Quiz: <?= formatDate($is['last_active']) ?>
                                        </div>
                                    </div>
                                    <a href="?view=students&student_id=<?= $is['id'] ?>" class="btn btn-sm btn-secondary" title="View Profile">👤</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Analytics Tabs -->
                <div class="analytics-tabs">
                    <a href="?view=overview<?= $semesterFilter ? '&semester=' . $semesterFilter : '' ?><?= $yearFilter ? '&year=' . $yearFilter : '' ?>"
                        class="analytics-tab <?= $viewType === 'overview' ? 'active' : '' ?>">📈 Overview</a>
                    <a href="?view=students<?= $semesterFilter ? '&semester=' . $semesterFilter : '' ?><?= $yearFilter ? '&year=' . $yearFilter : '' ?>"
                        class="analytics-tab <?= $viewType === 'students' ? 'active' : '' ?>">👥 Students</a>
                    <a href="?view=alerts<?= $semesterFilter ? '&semester=' . $semesterFilter : '' ?><?= $yearFilter ? '&year=' . $yearFilter : '' ?>"
                        class="analytics-tab <?= $viewType === 'alerts' ? 'active' : '' ?>">🚨 Alerts (<?= count($alerts) ?>)</a>
                </div>

                <!-- Filters Section -->
                <div class="filters-section">
                    <div style="margin-bottom: 16px;">
                        <strong>🎯 Filters</strong>
                    </div>
                    <form method="GET" class="filters-grid">
                        <input type="hidden" name="view" value="<?= $viewType ?>">

                        <div>
                            <label style="display: block; margin-bottom: 4px; font-weight: 500;">Academic Year</label>
                            <select name="year" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: var(--radius-sm);">
                                <option value="">All Years</option>
                                <option value="FY" <?= $yearFilter === 'FY' ? 'selected' : '' ?>>First Year (FY)</option>
                                <option value="SY" <?= $yearFilter === 'SY' ? 'selected' : '' ?>>Second Year (SY)</option>
                                <option value="TY" <?= $yearFilter === 'TY' ? 'selected' : '' ?>>Third Year (TY)</option>
                            </select>
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 4px; font-weight: 500;">Semester</label>
                            <select name="semester" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: var(--radius-sm);">
                                <option value="0">All Semesters</option>
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?= $i ?>" <?= $semesterFilter === $i ? 'selected' : '' ?>>Semester <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div>
                            <button type="submit" class="btn btn-primary" style="width: 100%;">Apply Filters</button>
                        </div>
                    </form>
                </div>

                <?php if ($viewType === 'overview'): ?>
                    <!-- === CLASS REVIEW DASHBOARD === -->
                    <div style="margin-bottom: 32px;">
                        <div style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">🎓 Class Performance Review</div>

                        <div class="class-review-grid">
                            <!-- Total Students -->
                            <div class="review-card">
                                <div class="icon-box">👥</div>
                                <div class="data">
                                    <div class="val"><?= $classStats['total_students'] ?></div>
                                    <div class="lbl">Total Students</div>
                                </div>
                            </div>

                            <!-- Active Students -->
                            <div class="review-card">
                                <div class="icon-box" style="background: var(--primary-light); color: var(--primary);">✨</div>
                                <div class="data">
                                    <div class="val"><?= $classStats['active_students'] ?></div>
                                    <div class="lbl">Active Students</div>
                                </div>
                            </div>

                            <!-- Class Average -->
                            <div class="review-card">
                                <div class="icon-box" style="background: var(--success-light); color: var(--success);">📈</div>
                                <div class="data">
                                    <div class="val"><?= $classStats['avg_accuracy'] ?>%</div>
                                    <div class="lbl">Class Average</div>
                                </div>
                            </div>

                            <!-- Top Performers -->
                            <div class="review-card">
                                <div class="icon-box" style="background: #fef3c7; color: #d97706;">🏆</div>
                                <div class="data">
                                    <div class="val"><?= $classStats['excellent_performers'] ?></div>
                                    <div class="lbl">Top Performers (≥80%)</div>
                                </div>
                            </div>

                            <!-- Struggling Students -->
                            <div class="review-card">
                                <div class="icon-box" style="background: var(--danger-light); color: var(--danger);">🚨</div>
                                <div class="data">
                                    <div class="val"><?= $classStats['struggling_students'] ?></div>
                                    <div class="lbl">Need Attention (<50%)< /div>
                                    </div>
                                </div>

                                <!-- Inactive Students -->
                                <div class="review-card">
                                    <div class="icon-box" style="background: var(--surface-light); color: var(--text-muted);">💤</div>
                                    <div class="data">
                                        <div class="val"><?= $classStats['inactive_students'] ?></div>
                                        <div class="lbl">Inactive Students</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($viewType === 'students' && !isset($_GET['student_id'])): ?>
                        <!-- === STUDENT RANKINGS TABLE === -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">🏆 Student Rankings & Performance</h3>
                                <p style="color: var(--text-muted); margin: 4px 0 0 0;">Click on student names for detailed analytics</p>
                            </div>

                            <?php if (!empty($studentRankingData)): ?>
                                <div class="table-container">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Rank</th>
                                                <th>Student</th>
                                                <th>Year</th>
                                                <th>Quizzes</th>
                                                <th>Avg Score</th>
                                                <th>Best Score</th>
                                                <th>Subjects</th>
                                                <th>Status</th>
                                                <th>Last Active</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $rank = 1;
                                            foreach ($studentRankingData as $student): ?>
                                                <tr>
                                                    <td><strong>#<?= $rank++ ?></strong></td>
                                                    <td>
                                                        <a href="?view=students&student_id=<?= $student['id'] ?><?= $semesterFilter ? '&semester=' . $semesterFilter : '' ?><?= $yearFilter ? '&year=' . $yearFilter : '' ?>"
                                                            class="student-link">
                                                            <?= htmlspecialchars($student['name']) ?>
                                                        </a>
                                                    </td>
                                                    <td><?= htmlspecialchars($student['student_year'] ?? '-') ?></td>
                                                    <td><strong><?= $student['total_quizzes'] ?? 0 ?></strong></td>
                                                    <td><strong><?= $student['avg_accuracy'] ?? 0 ?>%</strong></td>
                                                    <td><?= $student['best_score'] ?? '-' ?>%</td>
                                                    <td><?= $student['subjects_attempted'] ?? 0 ?></td>
                                                    <td>
                                                        <?php
                                                        $last = $student['last_active'];
                                                        $isActive = ($last && strtotime($last) >= strtotime('-7 days'));
                                                        if ($isActive) echo '<span class="badge status-approved">Active</span>';
                                                        else echo '<span class="badge" style="background:var(--border); color:var(--text-muted);">Inactive</span>';
                                                        ?>
                                                    </td>
                                                    <td><?= $student['last_active'] ? formatDate($student['last_active']) : '<span style="color: var(--text-muted);">Never</span>' ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div style="text-align: center; padding: 48px; color: var(--text-muted);">
                                    <div style="font-size: 48px; margin-bottom: 16px;">📊</div>
                                    <h4>No students found for selected filters.</h4>
                                    <p>Try adjusting your filters or check if students have taken quizzes.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                    <?php elseif ($viewType === 'alerts'): ?>
                        <!-- === PERFORMANCE ALERTS === -->
                        <div class="alerts-section">
                            <?php if (!empty($alerts)): ?>
                                <?php foreach ($alerts as $alert): ?>
                                    <div class="alert-item alert-<?= $alert['type'] ?>">
                                        <div style="display: flex; justify-content: space-between; align-items: start;">
                                            <div>
                                                <strong><?= htmlspecialchars($alert['student']) ?></strong>
                                                <p style="margin: 4px 0;"><?= htmlspecialchars($alert['message']) ?></p>
                                                <small style="color: var(--text-muted);"><?= htmlspecialchars($alert['metric']) ?></small>
                                            </div>
                                            <div style="text-align: right;">
                                                <?php if ($alert['type'] === 'critical'): ?>
                                                    <span style="color: var(--danger); font-size: 20px;">🚨</span>
                                                <?php elseif ($alert['type'] === 'warning'): ?>
                                                    <span style="color: var(--warning); font-size: 20px;">⚠️</span>
                                                <?php else: ?>
                                                    <span style="color: var(--info, #3b82f6); font-size: 20px;">ℹ️</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 48px; color: var(--text-muted);">
                                    <div style="font-size: 48px; margin-bottom: 16px;">✅</div>
                                    <h4>All students performing well!</h4>
                                    <p>No performance alerts at this time.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                    <?php elseif ($viewType === 'student_detail' || ($viewType === 'students' && isset($_GET['student_id']))): ?>
                        <!-- === STUDENT DETAILED ANALYTICS === -->
                        <?php if ($selectedStudent): ?>
                            <div class="student-detail-container">
                                <div style="margin-bottom: 24px;">
                                    <a href="?view=students" class="back-link">← Back to Rankings</a>
                                </div>

                                <div class="dashboard-row-split" style="grid-template-columns: 1fr 2fr;">
                                    <!-- Left Column: Profile & Stats -->
                                    <div style="display: flex; flex-direction: column; gap: 24px;">
                                        <!-- Profile Card -->
                                        <div class="card">
                                            <div style="text-align: center; margin-bottom: 20px;">
                                                <div style="width: 80px; height: 80px; background: var(--primary-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 12px; color: var(--primary);">
                                                    <?= strtoupper(substr($selectedStudent['name'], 0, 1)) ?>
                                                </div>
                                                <h3 style="margin-bottom: 4px;"><?= htmlspecialchars($selectedStudent['name']) ?></h3>
                                                <span class="badge status-approved">Student</span>
                                            </div>
                                            <div style="display: flex; flex-direction: column; gap: 12px; font-size: 14px;">
                                                <div style="display: flex; justify-content: space-between;">
                                                    <span style="color: var(--text-muted);">Year:</span>
                                                    <span style="font-weight: 600;"><?= htmlspecialchars($selectedStudent['year'] ?? '-') ?></span>
                                                </div>
                                                <div style="display: flex; justify-content: space-between;">
                                                    <span style="color: var(--text-muted);">Email:</span>
                                                    <span style="font-weight: 600;"><?= htmlspecialchars($selectedStudent['email']) ?></span>
                                                </div>
                                                <div style="display: flex; justify-content: space-between;">
                                                    <span style="color: var(--text-muted);">Joined:</span>
                                                    <span style="font-weight: 600;"><?= date('M Y', strtotime($selectedStudent['created_at'])) ?></span>
                                                </div>
                                                <div style="display: flex; justify-content: space-between;">
                                                    <span style="color: var(--text-muted);">Rank:</span>
                                                    <span style="font-weight: 600; color: var(--primary);">#<?= $rankingInfo['rank'] ?? '-' ?> / <?= $rankingInfo['total_students'] ?? '-' ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Performance Snapshot -->
                                        <div class="card">
                                            <div class="card-header">
                                                <h4 class="card-title">Snapshot</h4>
                                            </div>
                                            <div class="analytics-grid" style="grid-template-columns: 1fr; gap: 12px; margin-top: 16px;">
                                                <div class="stat-card-modern" style="padding: 16px;">
                                                    <div class="value"><?= $academicSnapshot['avg_score'] ?? 0 ?>%</div>
                                                    <div class="label">Avg Accuracy</div>
                                                </div>
                                                <div class="stat-card-modern" style="padding: 16px;">
                                                    <div class="value"><?= $academicSnapshot['total_quizzes'] ?? 0 ?></div>
                                                    <div class="label">Quizzes Completed</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Trend Component -->
                                        <?php if ($performanceTrend): ?>
                                            <div class="card" style="border-left: 4px solid var(--primary);">
                                                <div class="card-header">
                                                    <h4 class="card-title">Performance Trend</h4>
                                                </div>
                                                <div style="text-align: center; padding: 10px 0;">
                                                    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 8px;">Indicator</div>
                                                    <?php
                                                    $trend = $performanceTrend['indicator'];
                                                    $color = $trend === 'Improving' ? 'var(--success)' : ($trend === 'Declining' ? 'var(--danger)' : 'var(--primary)');
                                                    $icon = $trend === 'Improving' ? '📈' : ($trend === 'Declining' ? '📉' : '➖');
                                                    ?>
                                                    <div style="font-size: 24px; font-weight: 800; color: <?= $color ?>;">
                                                        <?= $icon ?> <?= $trend ?>
                                                    </div>
                                                    <small style="display: block; margin-top: 8px; color: var(--text-muted);">
                                                        Last 5: <?= $performanceTrend['last_5_avg'] ?>% | Prev 5: <?= $performanceTrend['prev_5_avg'] ?>%
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Weak Areas Detection -->
                                        <?php if (!empty($weakAreas)): ?>
                                            <div class="card" style="border-left: 4px solid var(--danger);">
                                                <div class="card-header">
                                                    <h4 class="card-title" style="color: var(--danger);">🚨 Weak Areas</h4>
                                                </div>
                                                <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 12px;">
                                                    <?php foreach ($weakAreas as $wa): ?>
                                                        <div style="padding: 10px; background: var(--danger-light); border-radius: 8px;">
                                                            <div style="font-weight: 700; font-size: 14px;"><?= htmlspecialchars($wa['name']) ?></div>
                                                            <div style="font-size: 12px; color: var(--danger);">Average: <?= $wa['avg_score'] ?>% - Needs Practice</div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Right Column: Tables & History -->
                                    <div style="display: flex; flex-direction: column; gap: 24px;">
                                        <!-- Subject Wise Performance Table -->
                                        <div class="card">
                                            <div class="card-header">
                                                <h4 class="card-title">Subject Breakdown</h4>
                                            </div>
                                            <div class="table-container">
                                                <table style="font-size: 14px;">
                                                    <thead>
                                                        <tr>
                                                            <th>Subject</th>
                                                            <th>Quizzes</th>
                                                            <th>Avg Score</th>
                                                            <th>Best</th>
                                                            <th>Status</th>
                                                            <th>Recommendation</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if ($subjectPerformance): foreach ($subjectPerformance as $subj): ?>
                                                                <?php
                                                                $avg = $subj['avg_score'];
                                                                $status = $avg >= 75 ? 'Strong' : ($avg >= 50 ? 'Moderate' : 'Weak');
                                                                $sColor = $avg >= 75 ? 'var(--success)' : ($avg >= 50 ? 'var(--warning)' : 'var(--danger)');
                                                                $rec = $avg >= 75 ? 'Continue mastery' : ($avg >= 50 ? 'Review concepts' : 'Urgent practice');
                                                                ?>
                                                                <tr>
                                                                    <td style="font-weight: 600;"><?= htmlspecialchars($subj['name']) ?></td>
                                                                    <td><?= $subj['total_quizzes'] ?></td>
                                                                    <td><strong><?= $avg ?>%</strong></td>
                                                                    <td><?= $subj['best_score'] ?>%</td>
                                                                    <td><span style="color: <?= $sColor ?>; font-weight: 800; font-size: 12px; text-transform: uppercase;"><?= $status ?></span></td>
                                                                    <td style="font-size: 12px; color: var(--text-muted);"><?= $rec ?></td>
                                                                </tr>
                                                            <?php endforeach;
                                                        else: ?>
                                                            <tr>
                                                                <td colspan="6" style="text-align: center; padding: 20px;">No subject data found.</td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <!-- Quiz History -->
                                        <div class="card">
                                            <div class="card-header">
                                                <h4 class="card-title">Recent Quiz History</h4>
                                            </div>
                                            <div class="table-container">
                                                <table style="font-size: 13px;">
                                                    <thead>
                                                        <tr>
                                                            <th>Subject</th>
                                                            <th>Marks</th>
                                                            <th>%</th>
                                                            <th>Status</th>
                                                            <th>Date</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if ($recentQuizzes): foreach ($recentQuizzes as $q): ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($q['subject_name']) ?></td>
                                                                    <td><strong><?= $q['correct_answers'] ?></strong> / <?= $q['total_questions'] ?></td>
                                                                    <td><?= $q['score'] ?>%</td>
                                                                    <td>
                                                                        <?php if ($q['score'] >= PASS_PERCENTAGE): ?>
                                                                            <span class="badge status-approved" style="font-size: 10px;">PASS</span>
                                                                        <?php else: ?>
                                                                            <span class="badge" style="background:var(--danger-light); color:var(--danger); font-size: 10px;">FAIL</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td style="color: var(--text-muted);"><?= date('d M, Y', strtotime($q['completed_at'])) ?></td>
                                                                </tr>
                                                            <?php endforeach;
                                                        else: ?>
                                                            <tr>
                                                                <td colspan="5" style="text-align: center; padding: 20px;">No quiz attempts yet.</td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">📂</div>
                                <h3>Student not found</h3>
                                <p>The student ID provided is invalid or the student no longer exists.</p>
                                <a href="?view=students" class="btn btn-primary" style="margin-top: 12px; display: inline-block;">Back to Rankings</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
            </section>
        </main>
    </div>
</body>

</html>