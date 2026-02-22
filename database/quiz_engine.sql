-- Drop table if we are renaming the old quiz_attempts
DROP TABLE IF EXISTS quiz_attempts;

DROP TABLE IF EXISTS quiz_sessions;

CREATE TABLE quiz_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    status ENUM(
        'in_progress',
        'completed',
        'abandoned'
    ) NOT NULL DEFAULT 'in_progress',
    score INT DEFAULT 0,
    total_questions INT NOT NULL,
    correct_answers INT DEFAULT 0,
    time_taken INT DEFAULT 0,
    INDEX idx_student_sess (student_id),
    INDEX idx_subject_sess (subject_id)
);

CREATE TABLE quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_answer CHAR(1) NULL,
    correct_answer CHAR(1) NOT NULL,
    is_correct TINYINT(1) DEFAULT 0,
    time_taken INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    INDEX idx_session_att (session_id),
    INDEX idx_question_att (question_id),
    FOREIGN KEY (session_id) REFERENCES quiz_sessions (id) ON DELETE CASCADE
);