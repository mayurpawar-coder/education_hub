<?php
/**
 * Education Hub - Quiz Page (Modern UI)
 */

require_once 'config/functions.php';
requireLogin();

$pageTitle = 'Take Quiz';
$subjectId = (int)($_GET['subject'] ?? 0);
$submitted = isset($_POST['submit_quiz']);

// Get subjects for selection
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name");

// Handle quiz submission
if ($submitted && isset($_POST['answers']) && isset($_POST['subject_id'])) {
    $subjectId = (int)$_POST['subject_id'];
    $answers = $_POST['answers'];
    
    // Get questions and calculate score
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
    
    // Save result
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO quiz_results (user_id, subject_id, score, total_questions, percentage) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiid", $userId, $subjectId, $correctAnswers, $totalQuestions, $percentage);
    $stmt->execute();
    
    // Get subject name for results
    $subjectResult = $conn->query("SELECT name FROM subjects WHERE id = $subjectId")->fetch_assoc();
    $subjectName = $subjectResult['name'] ?? 'Quiz';
}

// Get questions for selected subject
$questions = null;
$subjectName = '';
if ($subjectId && !$submitted) {
    $questions = $conn->query("SELECT * FROM questions WHERE subject_id = $subjectId ORDER BY RAND() LIMIT 10");
    $subjectResult = $conn->query("SELECT name FROM subjects WHERE id = $subjectId")->fetch_assoc();
    $subjectName = $subjectResult['name'] ?? 'Quiz';
}

// Subject icons and gradients
$subjectStyles = [
    'Mathematics' => ['icon' => '📐', 'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'],
    'Physics' => ['icon' => '⚛️', 'gradient' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)'],
    'Chemistry' => ['icon' => '🧪', 'gradient' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)'],
    'English' => ['icon' => '📖', 'gradient' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)'],
    'Computer Science' => ['icon' => '💻', 'gradient' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)'],
    'default' => ['icon' => '📚', 'gradient' => 'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz - Education Hub</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Modern Quiz Styles */
        .quiz-hero {
            text-align: center;
            padding: 48px 24px;
            margin-bottom: 32px;
            background: linear-gradient(135deg, rgba(0, 153, 255, 0.1) 0%, rgba(124, 58, 237, 0.1) 100%);
            border-radius: 24px;
            border: 1px solid var(--border);
        }
        
        .quiz-hero h1 {
            font-size: 36px;
            margin-bottom: 8px;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .quiz-hero p {
            color: var(--text-muted);
            font-size: 16px;
        }
        
        /* Subject Selection Grid - Modern Cards */
        .subject-selection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
            margin-top: 32px;
        }
        
        .quiz-subject-card {
            position: relative;
            background: var(--surface);
            border-radius: 20px;
            padding: 28px;
            border: 2px solid var(--border);
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .quiz-subject-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--card-gradient);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .quiz-subject-card:hover {
            transform: translateY(-8px);
            border-color: var(--primary);
            box-shadow: 0 20px 40px rgba(0, 153, 255, 0.15);
        }
        
        .quiz-subject-card:hover::before {
            opacity: 1;
        }
        
        .subject-card-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin-bottom: 20px;
            background: var(--card-gradient);
        }
        
        .subject-card-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .subject-card-stats {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .subject-card-stats .stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            background: var(--surface-light);
            border-radius: 20px;
            font-size: 12px;
        }
        
        .subject-card-arrow {
            position: absolute;
            right: 20px;
            bottom: 20px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--surface-light);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .quiz-subject-card:hover .subject-card-arrow {
            background: var(--primary);
            transform: translateX(4px);
        }
        
        /* Quiz Taking Interface */
        .quiz-progress-container {
            margin-bottom: 32px;
        }
        
        .quiz-progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .quiz-progress-title {
            font-size: 14px;
            color: var(--text-muted);
        }
        
        .quiz-progress-count {
            font-size: 14px;
            font-weight: 600;
            color: var(--primary);
        }
        
        .quiz-progress-bar {
            height: 8px;
            background: var(--surface-light);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .quiz-progress-fill {
            height: 100%;
            background: var(--gradient-primary);
            border-radius: 10px;
            transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Question Card - Premium Design */
        .quiz-question-card {
            background: var(--surface);
            border-radius: 24px;
            padding: 40px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }
        
        .quiz-question-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-primary);
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .question-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: linear-gradient(135deg, rgba(0, 153, 255, 0.2) 0%, rgba(124, 58, 237, 0.2) 100%);
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            color: var(--primary);
        }
        
        .question-badge span {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 400;
        }
        
        .quiz-question-text {
            font-size: 22px;
            font-weight: 600;
            line-height: 1.5;
            margin-bottom: 32px;
        }
        
        /* Answer Options - Modern Style */
        .quiz-options-grid {
            display: grid;
            gap: 16px;
        }
        
        .quiz-option {
            position: relative;
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px 24px;
            background: var(--surface-light);
            border-radius: 16px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .quiz-option:hover {
            border-color: var(--primary);
            background: rgba(0, 153, 255, 0.05);
            transform: translateX(8px);
        }
        
        .quiz-option input[type="radio"] {
            display: none;
        }
        
        .quiz-option input[type="radio"]:checked + .option-indicator {
            background: var(--gradient-primary);
            border-color: transparent;
        }
        
        .quiz-option input[type="radio"]:checked + .option-indicator::after {
            content: '✓';
            color: white;
            font-size: 14px;
        }
        
        .quiz-option input[type="radio"]:checked ~ .option-text {
            color: var(--primary);
            font-weight: 600;
        }
        
        .option-indicator {
            width: 32px;
            height: 32px;
            min-width: 32px;
            border-radius: 50%;
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .option-text {
            font-size: 16px;
            flex: 1;
        }
        
        /* Submit Button */
        .quiz-submit-section {
            text-align: center;
            margin-top: 48px;
            padding: 32px;
            background: linear-gradient(135deg, rgba(0, 153, 255, 0.05) 0%, rgba(124, 58, 237, 0.05) 100%);
            border-radius: 20px;
            border: 1px solid var(--border);
        }
        
        .quiz-submit-btn {
            padding: 18px 48px;
            font-size: 18px;
            font-weight: 700;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 10px 30px rgba(0, 153, 255, 0.3);
        }
        
        .quiz-submit-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 153, 255, 0.4);
        }
        
        /* Results - Celebration Style */
        .quiz-results-container {
            text-align: center;
        }
        
        .results-hero {
            padding: 64px 32px;
            background: linear-gradient(135deg, rgba(0, 153, 255, 0.1) 0%, rgba(124, 58, 237, 0.1) 100%);
            border-radius: 32px;
            margin-bottom: 48px;
            position: relative;
            overflow: hidden;
        }
        
        .results-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(0, 153, 255, 0.1) 0%, transparent 60%);
            animation: pulse 3s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 1; }
        }
        
        .score-circle {
            width: 180px;
            height: 180px;
            margin: 0 auto 32px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .score-circle-bg {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: conic-gradient(
                var(--primary) calc(var(--score) * 1%),
                var(--surface-light) calc(var(--score) * 1%)
            );
            animation: scoreReveal 1.5s ease-out forwards;
        }
        
        @keyframes scoreReveal {
            from { transform: rotate(-90deg); }
            to { transform: rotate(-90deg); }
        }
        
        .score-circle-inner {
            width: 150px;
            height: 150px;
            background: var(--surface);
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1;
        }
        
        .score-value {
            font-size: 48px;
            font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .score-label {
            font-size: 14px;
            color: var(--text-muted);
        }
        
        .results-emoji {
            font-size: 64px;
            margin-bottom: 16px;
        }
        
        .results-title {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 12px;
        }
        
        .results-subtitle {
            color: var(--text-muted);
            font-size: 18px;
            margin-bottom: 32px;
        }
        
        .results-stats {
            display: flex;
            justify-content: center;
            gap: 48px;
            margin-bottom: 32px;
        }
        
        .result-stat {
            text-align: center;
        }
        
        .result-stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
        }
        
        .result-stat-label {
            font-size: 14px;
            color: var(--text-muted);
        }
        
        .results-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        /* Review Section */
        .review-section {
            margin-top: 48px;
        }
        
        .review-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .review-header h2 {
            font-size: 24px;
            font-weight: 700;
        }
        
        .review-question-card {
            background: var(--surface);
            border-radius: 20px;
            padding: 32px;
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }
        
        .review-question-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        
        .review-question-status.correct {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
        }
        
        .review-question-status.wrong {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger);
        }
        
        .review-question-text {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .review-options {
            display: grid;
            gap: 12px;
        }
        
        .review-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            background: var(--surface-light);
            border-radius: 12px;
            font-size: 15px;
        }
        
        .review-option.correct-answer {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid var(--success);
        }
        
        .review-option.wrong-answer {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid var(--danger);
        }
        
        .review-option-letter {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
        }
        
        .review-option.correct-answer .review-option-letter {
            background: var(--success);
            color: white;
        }
        
        .review-option.wrong-answer .review-option-letter {
            background: var(--danger);
            color: white;
        }
        
        /* Empty State */
        .quiz-empty-state {
            text-align: center;
            padding: 64px 32px;
            color: var(--text-muted);
        }
        
        .quiz-empty-state .empty-icon {
            font-size: 64px;
            margin-bottom: 24px;
        }
        
        .quiz-empty-state h3 {
            font-size: 24px;
            color: var(--text);
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <section class="quiz-container">
                <?php if ($submitted): ?>
                    <!-- Quiz Results -->
                    <div class="quiz-results-container">
                        <div class="results-hero" style="--score: <?= $percentage ?>">
                            <div class="score-circle">
                                <div class="score-circle-bg"></div>
                                <div class="score-circle-inner">
                                    <span class="score-value"><?= $percentage ?>%</span>
                                    <span class="score-label">Score</span>
                                </div>
                            </div>
                            
                            <div class="results-emoji">
                                <?php
                                if ($percentage >= 80) echo "🏆";
                                elseif ($percentage >= 60) echo "⭐";
                                elseif ($percentage >= 40) echo "📚";
                                else echo "💪";
                                ?>
                            </div>
                            
                            <h1 class="results-title">
                                <?php
                                if ($percentage >= 80) echo "Outstanding!";
                                elseif ($percentage >= 60) echo "Great Job!";
                                elseif ($percentage >= 40) echo "Good Effort!";
                                else echo "Keep Practicing!";
                                ?>
                            </h1>
                            
                            <p class="results-subtitle">
                                You completed the <?= htmlspecialchars($subjectName) ?> quiz
                            </p>
                            
                            <div class="results-stats">
                                <div class="result-stat">
                                    <div class="result-stat-value"><?= $correctAnswers ?></div>
                                    <div class="result-stat-label">Correct</div>
                                </div>
                                <div class="result-stat">
                                    <div class="result-stat-value"><?= $totalQuestions - $correctAnswers ?></div>
                                    <div class="result-stat-label">Incorrect</div>
                                </div>
                                <div class="result-stat">
                                    <div class="result-stat-value"><?= $totalQuestions ?></div>
                                    <div class="result-stat-label">Total</div>
                                </div>
                            </div>
                            
                            <div class="results-actions">
                                <a href="quiz.php" class="btn btn-primary">🎯 Try Another Quiz</a>
                                <a href="performance.php" class="btn btn-secondary">📊 View All Stats</a>
                            </div>
                        </div>
                        
                        <!-- Review Answers -->
                        <div class="review-section">
                            <div class="review-header">
                                <span>📝</span>
                                <h2>Review Your Answers</h2>
                            </div>
                            
                            <?php foreach ($results as $index => $result): ?>
                            <div class="review-question-card">
                                <div class="review-question-status <?= $result['is_correct'] ? 'correct' : 'wrong' ?>">
                                    <?= $result['is_correct'] ? '✓ Correct' : '✗ Incorrect' ?>
                                </div>
                                <div class="review-question-text">
                                    <span style="color: var(--text-muted);">Q<?= $index + 1 ?>.</span>
                                    <?= htmlspecialchars($result['question']['question_text']) ?>
                                </div>
                                <div class="review-options">
                                    <?php
                                    $options = ['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'];
                                    foreach ($options as $letter => $field):
                                        $isUserAnswer = strtoupper($result['user_answer']) === $letter;
                                        $isCorrect = $result['question']['correct_answer'] === $letter;
                                        $class = '';
                                        if ($isCorrect) $class = 'correct-answer';
                                        elseif ($isUserAnswer && !$isCorrect) $class = 'wrong-answer';
                                    ?>
                                    <div class="review-option <?= $class ?>">
                                        <span class="review-option-letter"><?= $letter ?></span>
                                        <span><?= htmlspecialchars($result['question'][$field]) ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                <?php elseif ($subjectId && $questions && $questions->num_rows > 0): ?>
                    <!-- Quiz Taking Interface -->
                    <div class="quiz-hero">
                        <h1><?= htmlspecialchars($subjectName) ?> Quiz</h1>
                        <p>Answer all questions carefully. Good luck! 🍀</p>
                    </div>
                    
                    <form method="POST" id="quizForm">
                        <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
                        
                        <div class="quiz-progress-container">
                            <div class="quiz-progress-header">
                                <span class="quiz-progress-title">Progress</span>
                                <span class="quiz-progress-count"><span id="answeredCount">0</span> of <?= $questions->num_rows ?> answered</span>
                            </div>
                            <div class="quiz-progress-bar">
                                <div class="quiz-progress-fill" id="progressFill" style="width: 0%"></div>
                            </div>
                        </div>
                        
                        <?php 
                        $qNum = 0; 
                        $totalQ = $questions->num_rows;
                        while ($q = $questions->fetch_assoc()): 
                            $qNum++; 
                        ?>
                        <div class="quiz-question-card" data-question="<?= $qNum ?>">
                            <div class="question-header">
                                <div class="question-badge">
                                    Question <?= $qNum ?> <span>of <?= $totalQ ?></span>
                                </div>
                            </div>
                            <div class="quiz-question-text"><?= htmlspecialchars($q['question_text']) ?></div>
                            <div class="quiz-options-grid">
                                <?php
                                $options = ['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'];
                                foreach ($options as $letter => $field):
                                ?>
                                <label class="quiz-option">
                                    <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $letter ?>" required onchange="updateProgress()">
                                    <span class="option-indicator"><?= $letter ?></span>
                                    <span class="option-text"><?= htmlspecialchars($q[$field]) ?></span>
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
                        const totalQuestions = <?= $totalQ ?>;
                        
                        function updateProgress() {
                            const answered = document.querySelectorAll('input[type="radio"]:checked').length;
                            document.getElementById('answeredCount').textContent = answered;
                            document.getElementById('progressFill').style.width = (answered / totalQuestions * 100) + '%';
                        }
                    </script>
                    
                <?php elseif ($subjectId && $questions && $questions->num_rows === 0): ?>
                    <!-- No Questions Available -->
                    <div class="quiz-empty-state">
                        <div class="empty-icon">📭</div>
                        <h3>No Questions Available</h3>
                        <p>There are no questions for this subject yet. Please try another subject.</p>
                        <a href="quiz.php" class="btn btn-primary" style="margin-top: 24px;">← Back to Subjects</a>
                    </div>
                    
                <?php else: ?>
                    <!-- Subject Selection -->
                    <div class="quiz-hero">
                        <h1>🧠 Challenge Yourself</h1>
                        <p>Select a subject below to start your quiz journey</p>
                    </div>
                    
                    <div class="subject-selection-grid">
                        <?php while ($subject = $subjects->fetch_assoc()): 
                            $qCount = $conn->query("SELECT COUNT(*) as c FROM questions WHERE subject_id = " . $subject['id'])->fetch_assoc()['c'];
                            $style = $subjectStyles[$subject['name']] ?? $subjectStyles['default'];
                        ?>
                        <a href="quiz.php?subject=<?= $subject['id'] ?>" class="quiz-subject-card" style="--card-gradient: <?= $style['gradient'] ?>">
                            <div class="subject-card-icon" style="background: <?= $style['gradient'] ?>">
                                <?= $style['icon'] ?>
                            </div>
                            <h3 class="subject-card-title"><?= htmlspecialchars($subject['name']) ?></h3>
                            <div class="subject-card-stats">
                                <span class="stat-badge">
                                    📝 <?= $qCount ?> Questions
                                </span>
                            </div>
                            <div class="subject-card-arrow">→</div>
                        </a>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
