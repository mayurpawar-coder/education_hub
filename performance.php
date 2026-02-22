<?php

/**
 * ============================================================
 * Education Hub - Advanced Production-Ready Performance Dashboard
 * ============================================================
 */

require_once 'config/functions.php';
requireLogin();

$userId = $_SESSION['user_id'];
$pageTitle = 'My Academic Performance';

// 1. PERFORMANCE SUMMARY SECTION
$summarySql = "
    SELECT 
        COUNT(*) as total_quizzes,
        IFNULL(AVG(score), 0) as avg_score,
        IFNULL(MAX(score), 0) as max_score,
        IFNULL(MIN(score), 100) as min_score,
        IFNULL(SUM(time_taken), 0) as total_time
    FROM quiz_sessions 
    WHERE student_id = $userId AND status = 'completed'";
$summary = $conn->query($summarySql)->fetch_assoc();

// Improvement Calculation (Last 5 vs Previous 5)
$last5Sql = "SELECT score FROM quiz_sessions WHERE student_id = $userId AND status = 'completed' ORDER BY completed_at DESC LIMIT 5";
$prev5Sql = "SELECT score FROM quiz_sessions WHERE student_id = $userId AND status = 'completed' ORDER BY completed_at DESC LIMIT 5 OFFSET 5";

function calculateAvg($result)
{
    if (!$result) return 0;
    $sum = 0;
    $count = 0;
    while ($r = $result->fetch_assoc()) {
        $sum += $r['score'];
        $count++;
    }
    return $count > 0 ? $sum / $count : 0;
}

$last5Avg = calculateAvg($conn->query($last5Sql));
$prev5Avg = calculateAvg($conn->query($prev5Sql));
$improvement = $prev5Avg > 0 ? (($last5Avg - $prev5Avg) / $prev5Avg) * 100 : ($last5Avg > 0 ? 100 : 0);

// 2. TREND DATA (Line Chart)
$trendSql = "
    SELECT DATE_FORMAT(completed_at, '%b %d') as label, score 
    FROM quiz_sessions 
    WHERE student_id = $userId AND status = 'completed' 
    ORDER BY completed_at ASC 
    LIMIT 20";
$trendRes = $conn->query($trendSql);
$trendData = [];
while ($r = $trendRes->fetch_assoc()) $trendData[] = $r;

// 3. SUBJECT-WISE BREAKDOWN (Bar Chart & Table)
$subjectAnalysisSql = "
    SELECT 
        s.name as subject_name, 
        s.color,
        s.year,
        s.semester,
        COUNT(qs.id) as attempts,
        AVG(qs.score) as avg_score
    FROM subjects s
    JOIN quiz_sessions qs ON s.id = qs.subject_id
    WHERE qs.student_id = $userId AND qs.status = 'completed'
    GROUP BY s.id
    ORDER BY avg_score DESC";
$subjectRes = $conn->query($subjectAnalysisSql);
$subjectData = [];
while ($r = $subjectRes->fetch_assoc()) $subjectData[] = $r;

// 4. HEATMAP DATA (Last 6 Months)
$heatmapSql = "
    SELECT DATE(completed_at) as date, COUNT(*) as count 
    FROM quiz_sessions 
    WHERE student_id = $userId AND completed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE(completed_at)";
$heatmapRes = $conn->query($heatmapSql);
$heatmapData = [];
while ($r = $heatmapRes->fetch_assoc()) $heatmapData[$r['date']] = $r['count'];

// 5. GAMIFICATION & BADGES
$badges = [
    'first_quiz' => ['icon' => '🏅', 'label' => 'First Blood', 'desc' => 'Completed your first quiz', 'earned' => $summary['total_quizzes'] >= 1],
    'quiz_5' => ['icon' => '🔥', 'label' => 'Dedicated', 'desc' => '5 quizzes completed', 'earned' => $summary['total_quizzes'] >= 5],
    'accuracy_80' => ['icon' => '🎯', 'label' => 'Sharpshooter', 'desc' => 'Achieved >80% accuracy', 'earned' => $summary['max_score'] >= 80],
    'streak_10' => ['icon' => '💎', 'label' => 'Legendary', 'desc' => '10 quizzes completed', 'earned' => $summary['total_quizzes'] >= 10],
];

// Streak Count
$streakSql = "
    SELECT COUNT(DISTINCT DATE(completed_at)) as streak 
    FROM quiz_sessions 
    WHERE student_id = $userId AND completed_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)";
$streak = $conn->query($streakSql)->fetch_assoc()['streak'];

// 6. RANK & COMPARISON
$rankSql = "
    SELECT student_id, AVG(score) as overall_avg 
    FROM quiz_sessions 
    WHERE status = 'completed'
    GROUP BY student_id 
    ORDER BY overall_avg DESC";
$rankRes = $conn->query($rankSql);
$rank = 0;
$totalStudents = 0;
while ($r = $rankRes->fetch_assoc()) {
    $totalStudents++;
    if ($r['student_id'] == $userId) $rank = $totalStudents;
}
$percentile = $totalStudents > 0 ? round((($totalStudents - $rank) / $totalStudents) * 100, 1) : 0;

// 7. WEAK AREA DETECTION
$weakSubject = null;
if (!empty($subjectData)) {
    $weakSubject = end($subjectData); // Last after DESC sort
}

// 8. INSIGHTS GENERATION
$insights = [];
if ($improvement > 5) $insights[] = "🚀 You are improving steadily! Your score increased by " . round($improvement) . "% recently.";
if ($summary['avg_score'] < 50) $insights[] = "⚠️ Your average is below 50%. Focus on revision and basic concepts.";
if ($weakSubject) $insights[] = "💡 Practice more in " . $weakSubject['subject_name'] . " (Avg: " . round($weakSubject['avg_score']) . "%).";
if ($summary['total_quizzes'] < 3) $insights[] = "👋 Welcome aboard! Take more quizzes to unlock insights.";

// 9. PAGINATED HISTORY
$page = (int)($_GET['p'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

$historySql = "
    SELECT qs.*, s.name as subject_name, s.color as subject_color 
    FROM quiz_sessions qs 
    JOIN subjects s ON qs.subject_id = s.id 
    WHERE qs.student_id = $userId AND qs.status = 'completed'
    ORDER BY qs.completed_at DESC 
    LIMIT $limit OFFSET $offset";
$history = $conn->query($historySql);

$totalHistory = $conn->query("SELECT COUNT(*) as c FROM quiz_sessions WHERE student_id = $userId AND status='completed'")->fetch_assoc()['c'];
$totalPages = ceil($totalHistory / $limit);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Performance - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/performance.css">
</head>

<body>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include 'includes/header.php'; ?>

            <section class="performance-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1>📊 Performance Dashboard</h1>
                        <p style="color: var(--text-muted);">Real-time analytics and achievement tracking</p>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button onclick="exportCSV()" class="btn btn-secondary">📥 CSV Export</button>
                        <button onclick="printReport()" class="btn btn-primary">🖨️ Print Report</button>
                    </div>
                </div>
            </section>

            <!-- Dynamic Insights -->
            <div class="insights-banner">
                <div style="font-size: 32px;">💡</div>
                <div class="insights-content">
                    <h4>Smart Insights</h4>
                    <p><?= !empty($insights) ? implode(' • ', $insights) : "Keep taking quizzes to see personalized tips!" ?></p>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="analytics-grid">
                <div class="stat-card-modern">
                    <span class="icon">📝</span>
                    <div class="value"><?= $summary['total_quizzes'] ?></div>
                    <div class="label">Total Quizzes</div>
                </div>
                <div class="stat-card-modern">
                    <span class="icon">🎯</span>
                    <div class="value"><?= round($summary['avg_score'], 1) ?>%</div>
                    <div class="label">Overall Accuracy</div>
                    <div class="trend <?= $improvement >= 0 ? 'up' : 'down' ?>">
                        <?= $improvement >= 0 ? '↑' : '↓' ?> <?= abs(round($improvement, 1)) ?>% vs Prev
                    </div>
                </div>
                <div class="stat-card-modern">
                    <span class="icon">🏆</span>
                    <div class="value"><?= $summary['max_score'] ?>%</div>
                    <div class="label">Highest Score</div>
                </div>
                <div class="stat-card-modern">
                    <span class="icon">⏱️</span>
                    <div class="value"><?= floor($summary['total_time'] / 60) ?>m</div>
                    <div class="label">Total Time Spent</div>
                </div>
                <div class="stat-card-modern">
                    <span class="icon">📈</span>
                    <div class="value">#<?= $rank ?></div>
                    <div class="label">Class Rank (Top <?= $percentile ?>%)</div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-grid">
                <div class="chart-card">
                    <h3>📈 Score Trend (Last 20)</h3>
                    <div id="trendChart" class="canvas-container"></div>
                </div>
                <div class="chart-card">
                    <h3>📊 Subject Proficiency</h3>
                    <div id="subjectChart" class="canvas-container"></div>
                </div>
            </div>

            <!-- Gamification & Extra Stats -->
            <div class="gamification-grid">
                <div class="chart-card">
                    <h3>🏆 Achievement Badges</h3>
                    <div class="badge-gallery">
                        <?php foreach ($badges as $id => $b): ?>
                            <div class="achievement-badge <?= $b['earned'] ? 'unlocked' : '' ?>">
                                <?= $b['icon'] ?>
                                <div class="badge-tooltip">
                                    <strong><?= $b['label'] ?></strong><br>
                                    <?= $b['desc'] ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="chart-card">
                    <h3>🔥 Activity Heatmap (Last 6m)</h3>
                    <div class="heatmap-container" id="heatmap">
                        <!-- Filled via JS -->
                    </div>
                    <p style="font-size: 11px; margin-top: 10px; color: var(--text-muted);"><?= $streak ?> day active streak!</p>
                </div>
                <div class="chart-card" style="display: flex; align-items: center; justify-content: center;">
                    <div id="ratioChart"></div>
                </div>
            </div>

            <!-- Subject-wise Analysis Table -->
            <div class="card" style="margin-bottom: 32px;">
                <div class="card-header">
                    <h3>📚 Subject-wise Performance</h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Year/Sem</th>
                                <th>Attempts</th>
                                <th>Avg. Score</th>
                                <th>Indicator</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjectData as $s): ?>
                                <tr>
                                    <td>
                                        <span class="indicator-dot" style="background: <?= $s['color'] ?>;"></span>
                                        <strong><?= htmlspecialchars($s['subject_name']) ?></strong>
                                    </td>
                                    <td><?= $s['year'] ?> / Sem <?= $s['semester'] ?></td>
                                    <td><?= $s['attempts'] ?></td>
                                    <td><strong><?= round($s['avg_score'], 1) ?>%</strong></td>
                                    <td>
                                        <?php if ($s['avg_score'] >= 80): ?>
                                            <span class="badge badge-strong">Strong</span>
                                        <?php elseif ($s['avg_score'] >= 50): ?>
                                            <span class="badge badge-moderate">Moderate</span>
                                        <?php else: ?>
                                            <span class="badge badge-weak">Needs Work</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quiz History with Filter -->
            <div class="card">
                <div class="card-header" style="flex-wrap: wrap; gap: 15px;">
                    <h3>📋 Extended Quiz History</h3>
                    <div class="filter-bar">
                        <input type="text" id="hist-search" class="filter-input" placeholder="Quick search subject...">
                    </div>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Subject</th>
                                <th>Score</th>
                                <th>Accuracy</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="quiz-history-body">
                            <?php while ($row = $history->fetch_assoc()): ?>
                                <tr>
                                    <td><?= formatDate($row['completed_at']) ?></td>
                                    <td>
                                        <span style="border-left: 4px solid <?= $row['subject_color'] ?>; padding-left: 10px;">
                                            <?= htmlspecialchars($row['subject_name']) ?>
                                        </span>
                                    </td>
                                    <td><?= $row['correct_answers'] ?>/<?= $row['total_questions'] ?></td>
                                    <td><strong><?= $row['score'] ?>%</strong></td>
                                    <td><?= floor($row['time_taken'] / 60) ?>m <?= $row['time_taken'] % 60 ?>s</td>
                                    <td>
                                        <span class="badge <?= $row['score'] >= 80 ? 'badge-strong' : ($row['score'] >= 40 ? 'badge-moderate' : 'badge-weak') ?>">
                                            <?= $row['score'] >= 40 ? 'Passed' : 'Failed' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div style="display: flex; gap: 8px; justify-content: center; padding: 20px;">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?p=<?= $i ?>" class="btn btn-sm <?= $page == $i ? 'btn-primary' : 'btn-secondary' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="assets/js/performance.js"></script>
    <script>
        // Pass PHP data to JS
        const trendData = <?= json_encode($trendData) ?>;
        const subjectData = <?= json_encode($subjectData) ?>;
        const avgScore = <?= $summary['avg_score'] ?>;
        const heatmapData = <?= json_encode($heatmapData) ?>;

        window.addEventListener('load', () => {
            // Draw Trend Chart
            PerformanceCharts.drawLineChart('trendChart', trendData);

            // Draw Subject Chart (Top 6 for visibility)
            PerformanceCharts.drawBarChart('subjectChart', subjectData.slice(0, 6).map(s => ({
                label: s.subject_name,
                score: s.avg_score,
                color: s.color
            })));

            // Draw Accuracy Doughnut
            PerformanceCharts.drawDoughnut('ratioChart', avgScore);

            // Generate Heatmap (Dummy last 180 days)
            const heatmap = document.getElementById('heatmap');
            const today = new Date();
            for (let i = 180; i >= 0; i--) {
                const d = new Date();
                d.setDate(today.getDate() - i);
                const dateKey = d.toISOString().split('T')[0];
                const count = heatmapData[dateKey] || 0;
                let level = 0;
                if (count > 0) level = Math.min(count, 4);

                const day = document.createElement('div');
                day.className = `heatmap-day level-${level}`;
                day.title = `${dateKey}: ${count} quizzes`;
                heatmap.appendChild(day);
            }
        });
    </script>
</body>

</html>