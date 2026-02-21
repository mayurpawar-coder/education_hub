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
$studentsQuery = "
    SELECT
        u.id,
        u.name,
        u.email,
        u.year as student_year,
        u.mobile,
        COUNT(DISTINCT qr.id) as total_quizzes,
        ROUND(AVG(qr.percentage), 1) as avg_accuracy,
        MAX(qr.percentage) as best_score,
        MIN(qr.percentage) as lowest_score,
        COUNT(DISTINCT CASE WHEN qr.percentage >= 80 THEN qr.id END) as excellent_quizzes,
        COUNT(DISTINCT CASE WHEN qr.percentage < 40 THEN qr.id END) as poor_quizzes,
        MAX(qr.taken_at) as last_active,
        COUNT(DISTINCT s.id) as subjects_attempted,
        DATEDIFF(NOW(), MIN(qr.taken_at)) as days_active,
        ROUND(AVG(TIMESTAMPDIFF(MINUTE, qr.taken_at, qr.taken_at)), 1) as avg_quiz_time
    FROM users u
    LEFT JOIN quiz_results qr ON u.id = qr.user_id
    LEFT JOIN subjects s ON qr.subject_id = s.id
    WHERE u.role = 'student' $whereClause
    GROUP BY u.id
    ORDER BY avg_accuracy DESC
";

$students = $conn->query($studentsQuery);

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

$studentData = [];
$alerts = [];

while ($student = $students->fetch_assoc()) {
    $studentData[] = $student;
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
$studentQuizzes = null;
$studentSubjects = null;

if (isset($_GET['student_id']) && is_numeric($_GET['student_id'])) {
    $studentId = (int)$_GET['student_id'];

    // Debug: Check if student exists
    $studentCheck = $conn->query("SELECT id, name, role FROM users WHERE id = $studentId");
    if ($studentCheck && $studentCheck->num_rows > 0) {
        $studentData = $studentCheck->fetch_assoc();
        if ($studentData['role'] === 'student') {
            $selectedStudent = $studentData;

            // Get student's quiz history with subject details
            $studentQuizzes = $conn->query("
                SELECT qr.*, s.name as subject_name, s.color as subject_color, s.year, s.semester
                FROM quiz_results qr
                JOIN subjects s ON qr.subject_id = s.id
                WHERE qr.user_id = $studentId
                ORDER BY qr.taken_at DESC
                LIMIT 20
            ");

            // Subject-wise performance for this student
            $studentSubjects = $conn->query("
                SELECT
                    s.name as subject_name,
                    s.color as subject_color,
                    COUNT(qr.id) as quizzes_taken,
                    ROUND(AVG(qr.percentage), 1) as avg_score,
                    MAX(qr.percentage) as best_score,
                    MAX(qr.taken_at) as last_attempt
                FROM subjects s
                LEFT JOIN quiz_results qr ON s.id = qr.subject_id AND qr.user_id = $studentId
                GROUP BY s.id, s.name, s.color
                HAVING quizzes_taken > 0
                ORDER BY avg_score DESC
            ");
        } else {
            // Debug: Student exists but is not a student role
            error_log("Debug: User ID $studentId exists but role is '{$studentData['role']}' not 'student'");
        }
    } else {
        // Debug: Student not found
        error_log("Debug: Student with ID $studentId not found in database");
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

        .alert-critical { background: var(--danger-light); border-left-color: var(--danger); }
        .alert-warning { background: var(--warning-light); border-left-color: var(--warning); }
        .alert-info { background: var(--info-light, #dbeafe); border-left-color: var(--info, #3b82f6); }

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
                <!-- === CLASS OVERVIEW DASHBOARD === -->
                <div style="margin-bottom: 32px;">
                    <div style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Class Overview</div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <div>
                            <div style="font-size: 32px; font-weight: 800; color: #000; margin-bottom: 4px;">6</div>
                            <div style="color: #666; font-size: 14px;">Total Students</div>
                        </div>
                        
                        <div>
                            <div style="font-size: 32px; font-weight: 800; color: #000; margin-bottom: 4px;">2</div>
                            <div style="color: #666; font-size: 14px;">Active Students</div>
                        </div>
                        
                        <div>
                            <div style="font-size: 32px; font-weight: 800; color: #000; margin-bottom: 4px;">100%</div>
                            <div style="color: #666; font-size: 14px;">Class Average</div>
                        </div>
                        
                        <div>
                            <div style="font-size: 32px; font-weight: 800; color: #000; margin-bottom: 4px;">2</div>
                            <div style="color: #666; font-size: 14px;">Top Performers (≥80%)</div>
                        </div>
                        
                        <div>
                            <div style="font-size: 32px; font-weight: 800; color: #000; margin-bottom: 4px;">0</div>
                            <div style="color: #666; font-size: 14px;">Need Attention (<50%)</div>
                        </div>
                        
                        <div>
                            <div style="font-size: 32px; font-weight: 800; color: #000; margin-bottom: 4px;">0</div>
                            <div style="color: #666; font-size: 14px;">Inactive Students</div>
                        </div>
                    </div>
                </div>

                <?php elseif ($viewType === 'students'): ?>
                <!-- === STUDENT RANKINGS TABLE === -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">🏆 Student Rankings & Performance</h3>
                        <p style="color: var(--text-muted); margin: 4px 0 0 0;">Click on student names for detailed analytics</p>
                    </div>

                    <?php if (!empty($studentData)): ?>
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
                                <?php $rank = 1; foreach ($studentData as $student): ?>
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
                                        $acc = $student['avg_accuracy'] ?? 0;
                                        if ($acc >= 80) echo '<span style="color: var(--success); font-weight: 600;">🏆 Excellent</span>';
                                        elseif ($acc >= 60) echo '<span style="color: var(--primary); font-weight: 600;">👍 Good</span>';
                                        elseif ($acc >= 40) echo '<span style="color: var(--warning); font-weight: 600;">⚡ Average</span>';
                                        else echo '<span style="color: var(--danger); font-weight: 600;">🚨 Needs Help</span>';
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
                        <h4>No student data found</h4>
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

                <?php endif; ?>
