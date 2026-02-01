<?php
/**
 * Education Hub - Quiz Page (Modern UI with Year/Semester)
 */

require_once 'config/functions.php';
requireLogin();

$pageTitle = 'Take Quiz';
$subjectId = (int)($_GET['subject'] ?? 0);
$yearFilter = sanitize($_GET['year'] ?? '');
$semesterFilter = (int)($_GET['semester'] ?? 0);
$submitted = isset($_POST['submit_quiz']);

// Get subjects grouped by year/semester
$subjectsSql = "SELECT s.*, 
                (SELECT COUNT(*) FROM questions WHERE subject_id = s.id) as question_count 
                FROM subjects s WHERE 1=1";
if ($yearFilter) $subjectsSql .= " AND s.year = '$yearFilter'";
if ($semesterFilter) $subjectsSql .= " AND s.semester = $semesterFilter";
$subjectsSql .= " ORDER BY s.year, s.semester, s.name";
$subjects = $conn->query($subjectsSql);

// Handle quiz submission
if ($submitted && isset($_POST['answers']) && isset($_POST['subject_id'])) {
    $subjectId = (int)$_POST['subject_id'];
    $answers = $_POST['answers'];
    
    $questions = $conn->query("SELECT * FROM questions WHERE subject_id = $subjectId ORDER BY id");
    $totalQuestions = $questions->num_rows;
    $correctAnswers = 0;
    
    $results = [];
    while ($q = $questions->fetch_assoc()) {
        $userAnswer = $answers[$q['id']] ?? '';
        $isCorrect = strtoupper($userAnswer) === $q['correct_answer'];
        if ($isCorrect) $correctAnswers++;
        
        $results[] = [
            'question' => $q,
            'user_answer' => $userAnswer,
            'is_correct' => $isCorrect
        ];
    }
    
    $percentage = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 1) : 0;
    
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO quiz_results (user_id, subject_id, score, total_questions, percentage) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiid", $userId, $subjectId, $correctAnswers, $totalQuestions, $percentage);
    $stmt->execute();
    
    $subjectResult = $conn->query("SELECT name, year, semester FROM subjects WHERE id = $subjectId")->fetch_assoc();
    $subjectName = $subjectResult['name'] ?? 'Quiz';
}

// Get questions for selected subject
$questions = null;
$subjectName = '';
if ($subjectId && !$submitted) {
    $questions = $conn->query("SELECT * FROM questions WHERE subject_id = $subjectId ORDER BY RAND() LIMIT 10");
    $subjectResult = $conn->query("SELECT name, year, semester FROM subjects WHERE id = $subjectId")->fetch_assoc();
    $subjectName = $subjectResult['name'] ?? 'Quiz';
}

// Subject icons mapping
$subjectIcons = [
    'code' => '💻', 'book-open' => '📖', 'briefcase' => '💼', 'monitor' => '🖥️',
    'database' => '🗄️', 'calculator' => '🧮', 'message-circle' => '💬', 'globe' => '🌐',
    'layers' => '📚', 'settings' => '⚙️', 'network' => '🌐', 'brain' => '🧠',
    'cloud' => '☁️', 'shield' => '🛡️', 'folder' => '📁'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/quiz.css">
</head>
<body>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <section>
                <?php if ($submitted && isset($results)): ?>
                <!-- Results Section -->
                <div class="quiz-results-container">
                    <div class="results-hero">
                        <div class="results-emoji">
                            <?php 
                            if ($percentage >= 90) echo '🏆';
                            elseif ($percentage >= 70) echo '🎉';
                            elseif ($percentage >= 50) echo '👍';
                            else echo '💪';
                            ?>
                        </div>
                        <div class="score-circle" style="--score: <?= $percentage ?>">
                            <div class="score-circle-bg"></div>
                            <div class="score-circle-inner">
                                <span class="score-value"><?= $percentage ?>%</span>
                                <span class="score-label">Score</span>
                            </div>
                        </div>
                        <h2 class="results-title">
                            <?php 
                            if ($percentage >= 90) echo 'Excellent!';
                            elseif ($percentage >= 70) echo 'Great Job!';
                            elseif ($percentage >= 50) echo 'Good Effort!';
                            else echo 'Keep Practicing!';
                            ?>
                        </h2>
                        <p class="results-subtitle"><?= htmlspecialchars($subjectName) ?></p>
                        <div class="results-stats">
                            <div class="result-stat">
                                <div class="result-stat-value"><?= $correctAnswers ?></div>
                                <div class="result-stat-label">Correct</div>
                            </div>
                            <div class="result-stat">
                                <div class="result-stat-value"><?= $totalQuestions - $correctAnswers ?></div>
                                <div class="result-stat-label">Wrong</div>
                            </div>
                            <div class="result-stat">
                                <div class="result-stat-value"><?= $totalQuestions ?></div>
                                <div class="result-stat-label">Total</div>
                            </div>
                        </div>
                        <a href="quiz.php" class="btn btn-primary btn-lg">Take Another Quiz</a>
                    </div>
                    
                    <!-- Answer Review -->
                    <h3 class="review-title">📝 Review Your Answers</h3>
                    <div class="review-list">
                        <?php foreach ($results as $i => $r): ?>
                        <div class="review-card <?= $r['is_correct'] ? 'correct' : 'wrong' ?>">
                            <div class="review-header">
                                <span class="review-number">Q<?= $i + 1 ?></span>
                                <span class="review-status">
                                    <?= $r['is_correct'] ? '✅ Correct' : '❌ Wrong' ?>
                                </span>
                            </div>
                            <p class="review-question"><?= htmlspecialchars($r['question']['question_text']) ?></p>
                            <div class="review-answers">
                                <?php if (!$r['is_correct']): ?>
                                <div class="your-answer">Your answer: <?= $r['user_answer'] ?: 'Not answered' ?></div>
                                <?php endif; ?>
                                <div class="correct-answer">Correct: <?= $r['question']['correct_answer'] ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <?php elseif ($questions && $questions->num_rows > 0): ?>
                <!-- Quiz Taking Interface -->
                <div class="quiz-header">
                    <a href="quiz.php" class="back-link">← Back to Subjects</a>
                    <h2><?= htmlspecialchars($subjectName) ?></h2>
                </div>
                
                <form method="POST" id="quizForm">
                    <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
                    
                    <div class="quiz-progress-container">
                        <div class="quiz-progress-header">
                            <span class="quiz-progress-title">Progress</span>
                            <span class="quiz-progress-count"><span id="answered">0</span> / <?= $questions->num_rows ?></span>
                        </div>
                        <div class="quiz-progress-bar">
                            <div class="quiz-progress-fill" id="progressFill" style="width: 0%"></div>
                        </div>
                    </div>
                    
                    <?php $qNum = 0; while ($q = $questions->fetch_assoc()): $qNum++; ?>
                    <div class="quiz-question-card">
                        <div class="question-header">
                            <span class="question-badge">Question <?= $qNum ?></span>
                        </div>
                        <p class="quiz-question-text"><?= htmlspecialchars($q['question_text']) ?></p>
                        <div class="quiz-options-grid">
                            <?php foreach (['A', 'B', 'C', 'D'] as $opt): 
                                $optKey = 'option_' . strtolower($opt);
                            ?>
                            <label class="quiz-option">
                                <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $opt ?>" onchange="updateProgress()">
                                <span class="option-indicator"><?= $opt ?></span>
                                <span class="option-text"><?= htmlspecialchars($q[$optKey]) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    
                    <div class="quiz-submit-section">
                        <button type="submit" name="submit_quiz" class="quiz-submit-btn">
                            ✨ Submit Quiz
                        </button>
                    </div>
                </form>
                
                <script>
                    const totalQuestions = <?= $qNum ?>;
                    function updateProgress() {
                        const answered = document.querySelectorAll('input[type="radio"]:checked').length;
                        document.getElementById('answered').textContent = answered;
                        document.getElementById('progressFill').style.width = (answered / totalQuestions * 100) + '%';
                    }
                </script>
                
                <?php else: ?>
                <!-- Subject Selection with Year/Semester Filter -->
                <div class="quiz-hero">
                    <h1>🎯 Take a Quiz</h1>
                    <p>Test your knowledge and track your progress</p>
                </div>
                
                <!-- Year/Semester Tabs -->
                <div class="year-tabs">
                    <a href="?year=" class="year-tab <?= empty($yearFilter) ? 'active' : '' ?>">All Years</a>
                    <a href="?year=FY" class="year-tab <?= $yearFilter === 'FY' ? 'active' : '' ?>">
                        <span class="year-badge fy">FY</span> First Year
                    </a>
                    <a href="?year=SY" class="year-tab <?= $yearFilter === 'SY' ? 'active' : '' ?>">
                        <span class="year-badge sy">SY</span> Second Year
                    </a>
                    <a href="?year=TY" class="year-tab <?= $yearFilter === 'TY' ? 'active' : '' ?>">
                        <span class="year-badge ty">TY</span> Third Year
                    </a>
                </div>
                
                <?php if ($yearFilter): ?>
                <div class="semester-tabs">
                    <?php 
                    $semesters = ['FY' => [1, 2], 'SY' => [3, 4], 'TY' => [5, 6]];
                    $availableSems = $semesters[$yearFilter] ?? [];
                    ?>
                    <a href="?year=<?= $yearFilter ?>" class="semester-tab <?= !$semesterFilter ? 'active' : '' ?>">All Semesters</a>
                    <?php foreach ($availableSems as $sem): ?>
                    <a href="?year=<?= $yearFilter ?>&semester=<?= $sem ?>" 
                       class="semester-tab <?= $semesterFilter == $sem ? 'active' : '' ?>">
                        Semester <?= $sem ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="subject-selection-grid">
                    <?php if ($subjects->num_rows > 0): ?>
                        <?php while ($subject = $subjects->fetch_assoc()): 
                            $icon = $subjectIcons[$subject['icon']] ?? '📚';
                            $hasQuestions = $subject['question_count'] > 0;
                        ?>
                        <a href="<?= $hasQuestions ? 'quiz.php?subject=' . $subject['id'] : '#' ?>" 
                           class="quiz-subject-card <?= !$hasQuestions ? 'disabled' : '' ?>"
                           style="--card-gradient: <?= $subject['color'] ?>">
                            <div class="subject-card-icon" style="background: <?= $subject['color'] ?>20; color: <?= $subject['color'] ?>;">
                                <?= $icon ?>
                            </div>
                            <div class="subject-card-year"><?= $subject['year'] ?> - Sem <?= $subject['semester'] ?></div>
                            <h3 class="subject-card-title"><?= htmlspecialchars($subject['name']) ?></h3>
                            <div class="subject-card-stats">
                                <span class="stat-badge">
                                    📝 <?= $subject['question_count'] ?> Questions
                                </span>
                            </div>
                            <?php if ($hasQuestions): ?>
                            <div class="subject-card-arrow">→</div>
                            <?php else: ?>
                            <div class="no-questions-badge">No questions yet</div>
                            <?php endif; ?>
                        </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">📭</div>
                            <h3>No subjects found</h3>
                            <p>Try selecting a different year or semester</p>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
