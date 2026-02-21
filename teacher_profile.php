<?php
require_once 'config/functions.php';
requireLogin();

// Only teachers and admins can view teacher profile
if (!isTeacher() && !isAdmin()) {
    redirect('dashboard.php');
}

$user = getCurrentUser();
$pageTitle = 'My Teaching Profile';

// Get basic teacher metrics
$userId = $_SESSION['user_id'];

// Core metrics
$metrics = [];

// Total courses assigned (subjects where teacher created questions)
$coursesQuery = $conn->query("SELECT COUNT(DISTINCT subject_id) as total FROM questions WHERE created_by = $userId");
$metrics['courses_assigned'] = $coursesQuery->fetch_assoc()['total'];

// Total students enrolled (unique students who took teacher's quizzes)
$studentsQuery = $conn->query("
    SELECT COUNT(DISTINCT qr.user_id) as total
    FROM quiz_results qr
    JOIN questions q ON qr.subject_id = q.subject_id
    WHERE q.created_by = $userId AND qr.user_id != $userId
");
$metrics['students_enrolled'] = $studentsQuery->fetch_assoc()['total'];

// Total quizzes created
$quizzesQuery = $conn->query("SELECT COUNT(*) as total FROM questions WHERE created_by = $userId");
$metrics['quizzes_created'] = $quizzesQuery->fetch_assoc()['total'];

// Total notes uploaded
$notesQuery = $conn->query("SELECT COUNT(*) as total FROM notes WHERE uploaded_by = $userId");
$metrics['notes_uploaded'] = $notesQuery->fetch_assoc()['total'];

// Average student score in teacher's quizzes
$avgScoreQuery = $conn->query("
    SELECT AVG(qr.percentage) as avg_score
    FROM quiz_results qr
    JOIN questions q ON qr.subject_id = q.subject_id
    WHERE q.created_by = $userId AND qr.user_id != $userId
");
$avgScoreResult = $avgScoreQuery->fetch_assoc();
$metrics['avg_student_score'] = $avgScoreResult['avg_score'] ? round($avgScoreResult['avg_score'], 1) : 0;

// Pass percentage (assuming 60% is passing)
$passQuery = $conn->query("
    SELECT
        COUNT(CASE WHEN qr.percentage >= 60 THEN 1 END) as passed,
        COUNT(*) as total
    FROM quiz_results qr
    JOIN questions q ON qr.subject_id = q.subject_id
    WHERE q.created_by = $userId AND qr.user_id != $userId
");
$passResult = $passQuery->fetch_assoc();
$metrics['pass_percentage'] = $passResult['total'] > 0 ? round(($passResult['passed'] / $passResult['total']) * 100, 1) : 0;

// Activity metrics with audit table queries
$metrics['pending_submissions'] = 0; // Would need quiz_submissions table - showing 0 for now
try {
    $pendingQuery = $conn->query("SELECT COUNT(*) as total FROM quiz_submissions WHERE status = 'in_progress'");
    $metrics['pending_submissions'] = $pendingQuery->fetch_assoc()['total'];
} catch (Exception $e) {
    $metrics['pending_submissions'] = 0; // Table doesn't exist yet
}

try {
    $editedQuery = $conn->query("SELECT COUNT(*) as total FROM quiz_edits_audit WHERE teacher_id = $userId AND action_type = 'edited'");
    $metrics['edited_quiz'] = $editedQuery->fetch_assoc()['total'];
} catch (Exception $e) {
    $metrics['edited_quiz'] = 0; // Table doesn't exist yet
}

try {
    $deletedQuery = $conn->query("SELECT COUNT(*) as total FROM content_deletions_audit WHERE teacher_id = $userId");
    $metrics['deleted_content'] = $deletedQuery->fetch_assoc()['total'];
} catch (Exception $e) {
    $metrics['deleted_content'] = 0; // Table doesn't exist yet
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Teaching Profile - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            position: relative;
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 16px;
        }

        .metric-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 8px;
        }

        .metric-label {
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
        }

        .activity-section {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 24px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }

        .activity-header {
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 16px;
        }

        .activity-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }

        .activity-item {
            background: var(--surface-light);
            border-radius: var(--radius-md);
            padding: 16px;
            text-align: center;
            border: 1px solid var(--border);
        }

        .activity-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
        }

        .activity-label {
            color: var(--text-muted);
            font-size: 12px;
        }

        .performance-overview {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 24px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }

        .performance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .performance-card {
            text-align: center;
        }

        .performance-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--success);
            margin-bottom: 4px;
        }

        .performance-label {
            color: var(--text-muted);
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .metrics-grid {
                grid-template-columns: 1fr;
            }

            .activity-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .performance-grid {
                grid-template-columns: 1fr;
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
                                    <span style="color: var(--primary); font-size: 18px;">📚</span>
                                    <span style="font-weight: 600; color: var(--text);"><?= $metrics['courses_assigned'] ?></span>
                                    <span style="font-size: 12px; color: var(--text-muted);">Courses Assigned</span>
                                </div>

                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span style="color: var(--success); font-size: 18px;">👥</span>
                                    <span style="font-weight: 600; color: var(--text);"><?= $metrics['students_enrolled'] ?></span>
                                    <span style="font-size: 12px; color: var(--text-muted);">Students Enrolled</span>
                                </div>
                            </div>
                        </div>

                        <div style="text-align: right;">
                            <a href="edit_profile.php" class="btn btn-primary">Edit Profile</a>
                        </div>
                    </div>
                </div>

                <!-- Core Metrics Grid -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">📚</div>
                        <div class="metric-value"><?= $metrics['courses_assigned'] ?></div>
                        <div class="metric-label">Total Courses Assigned</div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">👥</div>
                        <div class="metric-value"><?= $metrics['students_enrolled'] ?></div>
                        <div class="metric-label">Total Students Enrolled</div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">🎯</div>
                        <div class="metric-value"><?= $metrics['quizzes_created'] ?></div>
                        <div class="metric-label">Total Quizzes Created</div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">📄</div>
                        <div class="metric-value"><?= $metrics['notes_uploaded'] ?></div>
                        <div class="metric-label">Total Notes Uploaded</div>
                    </div>
                </div>

                <!-- Activity Tracking -->
                <div class="activity-section">
                    <div class="activity-header">📊 Activity Tracking</div>
                    <div class="activity-grid">
                        <div class="activity-item">
                            <div class="activity-value"><?= $metrics['pending_submissions'] ?></div>
                            <div class="activity-label">Pending Submissions</div>
                        </div>

                        <div class="activity-item">
                            <div class="activity-value"><?= $metrics['notes_uploaded'] ?></div>
                            <div class="activity-label">Uploaded Notes</div>
                        </div>

                        <div class="activity-item">
                            <div class="activity-value"><?= $metrics['quizzes_created'] ?></div>
                            <div class="activity-label">Created Quiz</div>
                        </div>

                        <div class="activity-item">
                            <div class="activity-value"><?= $metrics['edited_quiz'] ?></div>
                            <div class="activity-label">Edited Quiz</div>
                        </div>

                        <div class="activity-item">
                            <div class="activity-value"><?= $metrics['deleted_content'] ?></div>
                            <div class="activity-label">Deleted Content</div>
                        </div>
                    </div>
                </div>

                <!-- Performance Overview -->
                <div class="performance-overview">
                    <div class="activity-header">🏆 Student Performance Overview</div>
                    <div class="performance-grid">
                        <div class="performance-card">
                            <div class="performance-value"><?= $metrics['avg_student_score'] ?>%</div>
                            <div class="performance-label">Average Student Score</div>
                        </div>

                        <div class="performance-card">
                            <div class="performance-value"><?= $metrics['pass_percentage'] ?>%</div>
                            <div class="performance-label">Pass Percentage</div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
