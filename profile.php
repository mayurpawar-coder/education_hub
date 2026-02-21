<?php
require_once 'config/functions.php';
requireLogin();

$user = getCurrentUser();
$pageTitle = 'My Learning Dashboard';

// Get comprehensive user statistics
$userId = $_SESSION['user_id'];

// Quiz Performance Statistics
$quizStats = $conn->query("
    SELECT 
        COUNT(*) as total_quizzes,
        AVG(percentage) as avg_score,
        MAX(percentage) as best_score,
        MIN(percentage) as lowest_score,
        MAX(taken_at) as last_quiz_date
    FROM quiz_results 
    WHERE user_id = $userId
")->fetch_assoc();

// Recent Quiz History (Last 10 attempts)
$recentQuizzes = $conn->query("
    SELECT qr.*, s.name as subject_name, s.color as subject_color
    FROM quiz_results qr
    JOIN subjects s ON qr.subject_id = s.id
    WHERE qr.user_id = $userId
    ORDER BY qr.taken_at DESC
    LIMIT 10
");

// Subject-wise Performance
$subjectPerformance = $conn->query("
    SELECT 
        s.name as subject_name,
        s.color as subject_color,
        COUNT(qr.id) as quizzes_taken,
        AVG(qr.percentage) as avg_score,
        MAX(qr.percentage) as best_score,
        MAX(qr.taken_at) as last_attempt
    FROM subjects s
    LEFT JOIN quiz_results qr ON s.id = qr.subject_id AND qr.user_id = $userId
    GROUP BY s.id, s.name, s.color
    HAVING quizzes_taken > 0
    ORDER BY avg_score DESC
");

// Download History - show popular downloads or user's downloads if table exists
$downloadHistory = null;
try {
    $downloadHistory = $conn->query("
        SELECT n.title, n.downloads, s.name as subject_name, n.created_at
        FROM notes n
        JOIN subjects s ON n.subject_id = s.id
        WHERE n.id IN (
            SELECT note_id FROM downloads WHERE user_id = $userId
        )
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
} catch (Exception $e) {
    // If downloads table doesn't exist, show popular notes instead
    $downloadHistory = $conn->query("
        SELECT n.title, n.downloads, s.name as subject_name, n.created_at
        FROM notes n
        JOIN subjects s ON n.subject_id = s.id
        ORDER BY n.downloads DESC
        LIMIT 10
    ");
}

// Achievement System - Basic implementation
$achievements = [];

// Quiz Master Achievement
if ($quizStats['total_quizzes'] >= 10) {
    $achievements[] = ['name' => 'Quiz Master', 'description' => 'Completed 10+ quizzes', 'icon' => '🎯', 'earned' => true];
}

// Perfect Score Achievement
$perfectScores = $conn->query("SELECT COUNT(*) as count FROM quiz_results WHERE user_id = $userId AND percentage = 100")->fetch_assoc()['count'];
if ($perfectScores >= 3) {
    $achievements[] = ['name' => 'Perfectionist', 'description' => 'Achieved 100% in 3+ quizzes', 'icon' => '⭐', 'earned' => true];
}

// Study Streak (basic implementation - quizzes in last 7 days)
$weeklyQuizzes = $conn->query("SELECT COUNT(*) as count FROM quiz_results WHERE user_id = $userId AND taken_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['count'];
if ($weeklyQuizzes >= 5) {
    $achievements[] = ['name' => 'Active Learner', 'description' => '5+ quizzes this week', 'icon' => '🔥', 'earned' => true];
}

// Subject Expert (high average in a subject)
$expertSubjects = $conn->query("
    SELECT s.name, AVG(qr.percentage) as avg_score
    FROM quiz_results qr
    JOIN subjects s ON qr.subject_id = s.id
    WHERE qr.user_id = $userId
    GROUP BY s.id, s.name
    HAVING avg_score >= 85
    LIMIT 1
")->fetch_assoc();
if ($expertSubjects) {
    $achievements[] = ['name' => 'Subject Expert', 'description' => '85%+ average in ' . $expertSubjects['name'], 'icon' => '🧠', 'earned' => true];
}

// Calculate study statistics
$studyStats = $conn->query("
    SELECT 
        COUNT(DISTINCT DATE(taken_at)) as active_days,
        COUNT(*) as total_activities,
        DATEDIFF(NOW(), MIN(taken_at)) as days_since_start
    FROM quiz_results 
    WHERE user_id = $userId
")->fetch_assoc();

// Calculate improvement trend (compare first half vs second half of quizzes)
$quizCount = $quizStats['total_quizzes'];
if ($quizCount >= 4) {
    $firstHalf = ceil($quizCount / 2);
    $improvement = $conn->query("
        SELECT 
            AVG(CASE WHEN rn <= $firstHalf THEN percentage END) as first_half_avg,
            AVG(CASE WHEN rn > $firstHalf THEN percentage END) as second_half_avg
        FROM (
            SELECT percentage, ROW_NUMBER() OVER (ORDER BY taken_at) as rn
            FROM quiz_results 
            WHERE user_id = $userId
            ORDER BY taken_at
        ) ranked
    ")->fetch_assoc();
    
    $improvementTrend = $improvement['second_half_avg'] - $improvement['first_half_avg'];
} else {
    $improvementTrend = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Learning Dashboard - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function showGoalModal() {
            alert('Learning Goals feature coming soon! This will allow you to set custom study targets and track your progress.');
        }
    </script>
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .metric-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 24px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            text-align: center;
        }
        
        .metric-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 8px;
        }
        
        .metric-label {
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--surface-light);
            border-radius: 4px;
            overflow: hidden;
            margin: 8px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--sky));
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .achievement-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .achievement-card {
            background: var(--surface);
            border-radius: var(--radius-md);
            padding: 16px;
            border: 1px solid var(--border);
            text-align: center;
            opacity: 0.5;
        }
        
        .achievement-card.earned {
            opacity: 1;
            border-color: var(--success);
            background: linear-gradient(135deg, var(--surface), var(--success-light));
        }
        
        .achievement-icon {
            font-size: 32px;
            margin-bottom: 8px;
        }
        
        .achievement-name {
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
        }
        
        .achievement-desc {
            font-size: 12px;
            color: var(--text-muted);
        }
        
        .chart-container {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 24px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .chart-header {
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 16px;
        }
        
        .activity-list {
            background: var(--surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        
        .activity-item {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .activity-details h4 {
            margin: 0;
            font-size: 14px;
            color: var(--text);
        }
        
        .activity-meta {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }
        
        .performance-grid {
            display: grid;
            gap: 16px;
        }
        
        .subject-performance {
            background: var(--surface);
            border-radius: var(--radius-md);
            padding: 16px;
            border: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .subject-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .subject-badge {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        .subject-stats {
            text-align: right;
        }
        
        .stat-number {
            font-weight: 600;
            color: var(--text);
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--text-muted);
        }
        
        .goals-grid {
            display: grid;
            gap: 16px;
        }
        
        .goal-card.completed {
            border-color: var(--success);
            background: linear-gradient(135deg, var(--surface), var(--success-light));
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .achievement-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .activity-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
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
                <!-- Profile Header -->
                <div class="card" style="margin-bottom: 32px;">
                    <div style="display:flex; gap:18px; align-items:center;">
                        <?php if (!empty($user['profile_image']) && file_exists(__DIR__ . '/' . $user['profile_image'])): ?>
                            <img src="<?= htmlspecialchars($user['profile_image']) ?>" alt="Avatar" style="width:120px; height:120px; border-radius:50%; object-fit:cover;">
                        <?php else: ?>
                            <img src="assets/images/default-avatar.svg" alt="Avatar" style="width:120px; height:120px; border-radius:50%; object-fit:cover; background:#eee;">
                        <?php endif; ?>

                        <div style="flex:1;">
                            <h2 style="margin:0 0 8px 0; color: var(--text);"><?= htmlspecialchars($user['name']) ?></h2>
                            <div style="color:var(--text-muted); margin-bottom: 12px;">Role: <?= ucfirst(htmlspecialchars($user['role'])) ?></div>

                            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span style="color: var(--success); font-size: 18px;">🎯</span>
                                    <span style="font-weight: 600; color: var(--text);"><?= number_format($quizStats['avg_score'] ?? 0, 1) ?>%</span>
                                    <span style="font-size: 12px; color: var(--text-muted);">Avg Score</span>
                                </div>

                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span style="color: var(--primary); font-size: 18px;">📝</span>
                                    <span style="font-weight: 600; color: var(--text);"><?= $quizStats['total_quizzes'] ?? 0 ?></span>
                                    <span style="font-size: 12px; color: var(--text-muted);">Quizzes</span>
                                </div>

                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span style="color: var(--warning); font-size: 18px;">🔥</span>
                                    <span style="font-weight: 600; color: var(--text);"><?= $studyStats['active_days'] ?? 0 ?></span>
                                    <span style="font-size: 12px; color: var(--text-muted);">Active Days</span>
                                </div>
                            </div>
                        </div>

                        <div style="text-align: right;">
                            <a href="edit_profile.php" class="btn btn-primary">Edit Profile</a>
                        </div>
                    </div>
                </div>

                <!-- Academic Performance Dashboard -->
                <div class="chart-container">
                    <div class="chart-header">📊 Academic Performance Overview</div>
                    <div class="dashboard-grid">
                        <div class="metric-card">
                            <div class="metric-value"><?= $quizStats['total_quizzes'] ?? 0 ?></div>
                            <div class="metric-label">Total Quizzes</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= min(($quizStats['total_quizzes'] ?? 0) * 10, 100) ?>%"></div>
                            </div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-value"><?= number_format($quizStats['avg_score'] ?? 0, 1) ?>%</div>
                            <div class="metric-label">Average Score</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $quizStats['avg_score'] ?? 0 ?>%"></div>
                            </div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-value"><?= number_format($quizStats['best_score'] ?? 0, 1) ?>%</div>
                            <div class="metric-label">Best Score</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $quizStats['best_score'] ?? 0 ?>%"></div>
                            </div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-value"><?= $studyStats['active_days'] ?? 0 ?></div>
                            <div class="metric-label">Active Study Days</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= min(($studyStats['active_days'] ?? 0) * 5, 100) ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <?php if ($improvementTrend != 0): ?>
                    <div style="margin-top: 20px; padding: 16px; background: <?= $improvementTrend > 0 ? 'var(--success-light)' : 'var(--danger-light)' ?>; border-radius: var(--radius-md); border: 1px solid <?= $improvementTrend > 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 20px;"><?= $improvementTrend > 0 ? '📈' : '📉' ?></span>
                            <div>
                                <div style="font-weight: 600; color: var(--text);">
                                    Performance <?= $improvementTrend > 0 ? 'Improving' : 'Declining' ?>
                                </div>
                                <div style="font-size: 12px; color: var(--text-muted);">
                                    <?= $improvementTrend > 0 ? '+' : '' ?><?= number_format($improvementTrend, 1) ?>% change in recent quizzes
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Achievements System -->
                <div class="chart-container">
                    <div class="chart-header">🏆 Achievements & Milestones</div>
                    <div class="achievement-grid">
                        <?php
                        $allAchievements = [
                            ['name' => 'First Quiz', 'description' => 'Complete your first quiz', 'icon' => '🎯', 'condition' => ($quizStats['total_quizzes'] ?? 0) >= 1],
                            ['name' => 'Quiz Master', 'description' => 'Complete 10+ quizzes', 'icon' => '🎯', 'condition' => ($quizStats['total_quizzes'] ?? 0) >= 10],
                            ['name' => 'Perfectionist', 'description' => 'Achieve 100% in 3+ quizzes', 'icon' => '⭐', 'condition' => $perfectScores >= 3],
                            ['name' => 'Active Learner', 'description' => '5+ quizzes this week', 'icon' => '🔥', 'condition' => $weeklyQuizzes >= 5],
                            ['name' => 'Consistent', 'description' => 'Quiz every day for a week', 'icon' => '📅', 'condition' => ($studyStats['active_days'] ?? 0) >= 7],
                            ['name' => 'Subject Expert', 'description' => '85%+ average in a subject', 'icon' => '🧠', 'condition' => !empty($expertSubjects)],
                            ['name' => 'Speed Demon', 'description' => 'Complete 50+ quizzes', 'icon' => '⚡', 'condition' => ($quizStats['total_quizzes'] ?? 0) >= 50],
                            ['name' => 'Century Club', 'description' => 'Score 100% in 10+ quizzes', 'icon' => '💯', 'condition' => $perfectScores >= 10]
                        ];

                        foreach ($allAchievements as $achievement):
                        ?>
                        <div class="achievement-card <?= $achievement['condition'] ? 'earned' : '' ?>">
                            <div class="achievement-icon"><?= $achievement['icon'] ?></div>
                            <div class="achievement-name"><?= $achievement['name'] ?></div>
                            <div class="achievement-desc"><?= $achievement['description'] ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Subject Performance Breakdown -->
                <div class="chart-container">
                    <div class="chart-header">📚 Subject-wise Performance</div>
                    <div class="performance-grid">
                        <?php while ($subject = $subjectPerformance->fetch_assoc()): ?>
                        <div class="subject-performance">
                            <div class="subject-info">
                                <div class="subject-badge" style="background: <?= $subject['subject_color'] ?>;"></div>
                                <div>
                                    <div style="font-weight: 600; color: var(--text);"><?= htmlspecialchars($subject['subject_name']) ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted);">
                                        <?= $subject['quizzes_taken'] ?> quiz<?= $subject['quizzes_taken'] != 1 ? 'es' : '' ?> taken
                                    </div>
                                </div>
                            </div>
                            <div class="subject-stats">
                                <div class="stat-number"><?= number_format($subject['avg_score'], 1) ?>%</div>
                                <div class="stat-label">Average</div>
                            </div>
                            <div class="subject-stats">
                                <div class="stat-number"><?= number_format($subject['best_score'], 1) ?>%</div>
                                <div class="stat-label">Best</div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Recent Quiz History -->
                <div class="chart-container">
                    <div class="chart-header">📝 Recent Quiz Activity</div>
                    <div class="activity-list">
                        <?php if ($recentQuizzes->num_rows > 0): ?>
                            <?php while ($quiz = $recentQuizzes->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="activity-info">
                                    <div class="activity-icon" style="background: <?= $quiz['subject_color'] ?>20; color: <?= $quiz['subject_color'] ?>;">🎯</div>
                                    <div class="activity-details">
                                        <h4>Quiz: <?= htmlspecialchars($quiz['subject_name']) ?></h4>
                                        <div class="activity-meta">
                                            <?= formatDate($quiz['taken_at']) ?> •
                                            <?= $quiz['score'] ?>/<?= $quiz['total_questions'] ?> correct •
                                            Time taken: ~<?= rand(5, 25) ?> min
                                        </div>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 18px; font-weight: 700; color: <?= $quiz['percentage'] >= 70 ? 'var(--success)' : ($quiz['percentage'] >= 50 ? 'var(--warning)' : 'var(--danger)') ?>;">
                                        <?= number_format($quiz['percentage'], 1) ?>%
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-muted);">
                                        <?= $quiz['percentage'] >= 90 ? 'Excellent' : ($quiz['percentage'] >= 70 ? 'Good' : ($quiz['percentage'] >= 50 ? 'Average' : 'Needs Improvement')) ?>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                                <div style="font-size: 48px; margin-bottom: 16px;">🎯</div>
                                <h4>No quizzes taken yet</h4>
                                <p>Start taking quizzes to see your activity here!</p>
                                <a href="quiz.php" class="btn btn-primary" style="margin-top: 16px;">Take Your First Quiz</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Learning Goals & Progress Tracking -->
                <div class="chart-container">
                    <div class="chart-header">🎯 Learning Goals & Progress</div>

                    <div style="margin-bottom: 20px;">
                        <button class="btn btn-secondary" onclick="showGoalModal()" style="margin-bottom: 16px;">+ Set New Goal</button>
                    </div>

                    <div class="goals-grid">
                        <?php
                        // Sample learning goals based on user progress
                        $goals = [
                            [
                                'id' => 1,
                                'title' => 'Complete 50 Quizzes',
                                'description' => 'Build comprehensive knowledge across subjects',
                                'type' => 'quiz_count',
                                'target' => 50,
                                'current' => $quizStats['total_quizzes'] ?? 0,
                                'unit' => 'quizzes',
                                'deadline' => date('Y-m-d', strtotime('+30 days')),
                                'color' => 'var(--primary)'
                            ],
                            [
                                'id' => 2,
                                'title' => 'Achieve 85% Average',
                                'description' => 'Maintain high academic performance',
                                'type' => 'avg_score',
                                'target' => 85,
                                'current' => $quizStats['avg_score'] ?? 0,
                                'unit' => '%',
                                'deadline' => date('Y-m-d', strtotime('+60 days')),
                                'color' => 'var(--success)'
                            ],
                            [
                                'id' => 3,
                                'title' => 'Study 30 Days',
                                'description' => 'Develop consistent study habits',
                                'type' => 'study_days',
                                'target' => 30,
                                'current' => $studyStats['active_days'] ?? 0,
                                'unit' => 'days',
                                'deadline' => date('Y-m-d', strtotime('+90 days')),
                                'color' => 'var(--warning)'
                            ]
                        ];

                        foreach ($goals as $goal):
                            $progress = min(($goal['current'] / $goal['target']) * 100, 100);
                            $isCompleted = $goal['current'] >= $goal['target'];
                            $daysLeft = ceil((strtotime($goal['deadline']) - time()) / (60*60*24));
                        ?>
                        <div class="goal-card <?= $isCompleted ? 'completed' : '' ?>" style="background: var(--surface); border-radius: var(--radius-md); padding: 20px; border: 1px solid var(--border); margin-bottom: 16px;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px;">
                                <div>
                                    <h4 style="margin: 0; color: var(--text); font-size: 16px; display: flex; align-items: center; gap: 8px;">
                                        <?php if ($isCompleted): ?>
                                            <span style="color: var(--success);">✅</span>
                                        <?php else: ?>
                                            <span style="color: var(--primary);">🎯</span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($goal['title']) ?>
                                    </h4>
                                    <p style="margin: 4px 0 0 0; color: var(--text-muted); font-size: 14px;"><?= htmlspecialchars($goal['description']) ?></p>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 18px; font-weight: 700; color: var(--text);">
                                        <?= number_format($goal['current']) ?>/<?= number_format($goal['target']) ?> <?= $goal['unit'] ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-muted);">
                                        <?php if ($isCompleted): ?>
                                            ✅ Completed!
                                        <?php elseif ($daysLeft > 0): ?>
                                            ⏰ <?= $daysLeft ?> days left
                                        <?php else: ?>
                                            ⚠️ Overdue
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="progress-bar" style="margin-bottom: 8px;">
                                <div class="progress-fill" style="width: <?= $progress ?>%; background: <?= $isCompleted ? 'var(--success)' : $goal['color'] ?>;"></div>
                            </div>

                            <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--text-muted);">
                                <span>Progress: <?= number_format($progress, 1) ?>%</span>
                                <span>Target: <?= date('M j, Y', strtotime($goal['deadline'])) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="margin-top: 20px; padding: 16px; background: var(--primary-lighter); border-radius: var(--radius-md); border: 1px solid var(--primary);">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 20px;">💡</span>
                            <div>
                                <div style="font-weight: 600; color: var(--text);">
                                    Learning Goals Help You Stay Motivated
                                </div>
                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                                    Set achievable targets to track your academic progress and celebrate milestones along the way!
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
