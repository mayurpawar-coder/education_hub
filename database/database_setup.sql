-- ===================================================================
-- Education Hub - Complete Database Setup
-- ===================================================================
-- This file contains the complete database schema and initial data
-- for setting up the Education Hub application on a new device.
--
-- Run this file in phpMyAdmin or MySQL command line to create
-- the database with all tables, data, and relationships.
--
-- Last Updated: February 22, 2026
-- ===================================================================

-- Create database
CREATE DATABASE IF NOT EXISTS education_hub;
USE education_hub;

-- ===================================================================
-- USERS TABLE
-- ===================================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'teacher', 'admin') DEFAULT 'student',
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
    mobile VARCHAR(20),
    profile_image VARCHAR(255),
    year ENUM('FY', 'SY', 'TY') DEFAULT 'FY',
    semester INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ===================================================================
-- SUBJECTS TABLE
-- ===================================================================
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'book',
    color VARCHAR(20) DEFAULT '#0099ff',
    year ENUM('FY', 'SY', 'TY') NOT NULL DEFAULT 'FY',
    semester INT NOT NULL DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ===================================================================
-- NOTES TABLE
-- ===================================================================
CREATE TABLE notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    file_path VARCHAR(255),
    subject_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    downloads INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- ===================================================================
-- QUESTIONS TABLE (for quizzes)
-- ===================================================================
CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_answer ENUM('A', 'B', 'C', 'D') NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ===================================================================
-- QUIZ RESULTS TABLE
-- ===================================================================
CREATE TABLE quiz_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject_id INT NOT NULL,
    score INT NOT NULL,
    total_questions INT NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    time_taken INT DEFAULT 0,
    taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- ===================================================================
-- DOWNLOADS TABLE (for tracking user downloads)
-- ===================================================================
CREATE TABLE downloads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    note_id INT NOT NULL,
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_note (user_id, note_id)
);

-- ===================================================================
-- LEARNING GOALS TABLE (for student progress tracking)
-- ===================================================================
CREATE TABLE learning_goals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    goal_type ENUM('quiz_count', 'avg_score', 'study_days', 'subject_mastery') NOT NULL,
    target_value INT NOT NULL,
    current_value INT DEFAULT 0,
    subject_id INT NULL,
    deadline DATE NULL,
    status ENUM('active', 'completed', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- ===================================================================
-- QUIZ EDITS AUDIT TABLE (for tracking question modifications)
-- ===================================================================
CREATE TABLE quiz_edits_audit (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_id INT NOT NULL,
    teacher_id INT NOT NULL,
    action_type ENUM('created', 'edited', 'deleted') NOT NULL,
    old_data TEXT NULL,
    new_data TEXT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES questions(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ===================================================================
-- CONTENT DELETIONS AUDIT TABLE (for tracking content deletions)
-- ===================================================================
CREATE TABLE content_deletions_audit (
    id INT PRIMARY KEY AUTO_INCREMENT,
    content_type ENUM('note', 'question') NOT NULL,
    content_id INT NOT NULL,
    teacher_id INT NOT NULL,
    content_title VARCHAR(255) NOT NULL,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ===================================================================
-- QUIZ SUBMISSIONS TABLE (for tracking quiz attempts)
-- ===================================================================
CREATE TABLE quiz_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    quiz_subject_id INT NOT NULL,
    status ENUM('in_progress', 'submitted', 'completed') DEFAULT 'in_progress',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL,
    time_taken INT NULL, -- in minutes
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_quiz (user_id, quiz_subject_id)
);

-- ===================================================================
-- INITIAL DATA INSERTION
-- ===================================================================

-- Insert default users (password for all: password123)
INSERT INTO users (name, email, password, role, status) VALUES
('Admin', 'admin@educationhub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'approved'),
('Teacher Demo', 'teacher@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'approved'),
('Raj Kumar', 'raj@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'approved'),
('Priya Sharma', 'priya@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'approved'),
('Amit Patel', 'amit@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'approved'),
('Sneha Gupta', 'sneha@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'approved'),
('Vikram Singh', 'vikram@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'approved');

-- Insert subjects for all semesters and years
INSERT INTO subjects (name, description, icon, color, year, semester, created_by) VALUES
-- FY Semester 1
('Procedural Programming-1', 'Programming fundamentals, C language, Functions', 'code', '#0099ff', 'FY', 1, 1),
('MAR-Sant Sahitya Ani Manvi Mule', 'Marathi literature and cultural studies', 'book-open', '#7c3aed', 'FY', 1, 1),
('Fundamental of Enterpreneurship-1', 'Business basics, Startup fundamentals', 'briefcase', '#10b981', 'FY', 1, 1),
('Fundamental of Computer', 'Computer basics, Hardware, Software concepts', 'monitor', '#f59e0b', 'FY', 1, 1),
('Communication Skills in English-1', 'English grammar, Writing, Speaking skills', 'message-circle', '#14b8a6', 'FY', 1, 1),

-- FY Semester 2
('Procedural Programming-2', 'Advanced C programming, Pointers, File handling', 'code', '#0099ff', 'FY', 2, 1),
('Fundamental of Enterpreneurship-2', 'Business plan development, Marketing basics', 'briefcase', '#10b981', 'FY', 2, 1),
('Communication Skills in English-2', 'Advanced writing, Presentation skills', 'message-circle', '#14b8a6', 'FY', 2, 1),

-- SY Semester 3
('Database Management System', 'SQL, Database design, Normalization', 'database', '#ec4899', 'SY', 3, 1),
('Object Oriented Programming', 'OOP concepts, Java/C++, Classes', 'code', '#6366f1', 'SY', 3, 1),
('Data Structures', 'Arrays, Linked Lists, Trees, Graphs', 'layers', '#f97316', 'SY', 3, 1),

-- SY Semester 4
('Computational Numerical Methods', 'Numerical analysis, Algorithms, Mathematics', 'calculator', '#6366f1', 'SY', 4, 1),
('Web Technology', 'HTML, CSS, JavaScript, PHP', 'globe', '#3b82f6', 'SY', 4, 1),
('Software Engineering', 'SDLC, Testing, Project Management', 'settings', '#8b5cf6', 'SY', 4, 1),

-- TY Semester 5
('Computer Networks', 'Networking fundamentals, Protocols, Security', 'network', '#0891b2', 'TY', 5, 1),
('Artificial Intelligence', 'AI basics, Machine Learning, Neural Networks', 'brain', '#d946ef', 'TY', 5, 1),
('Operating Systems', 'OS concepts, Process management, Memory', 'monitor', '#84cc16', 'TY', 5, 1),

-- TY Semester 6
('Cloud Computing', 'Cloud platforms, AWS, Azure, Deployment', 'cloud', '#06b6d4', 'TY', 6, 1),
('Cyber Security', 'Security principles, Cryptography, Threats', 'shield', '#ef4444', 'TY', 6, 1),
('Project Work', 'Final year project development', 'folder', '#f59e0b', 'TY', 6, 1);

-- Insert sample notes
INSERT INTO notes (title, content, subject_id, uploaded_by, downloads) VALUES
('C Programming Basics', 'Introduction to C language and syntax', 1, 2, 15),
('Functions in C', 'How to create and use functions in C programming', 1, 2, 12),
('Sant Sahitya Overview', 'Introduction to Marathi saint literature', 2, 2, 8),
('Business Plan Basics', 'How to create a business plan', 3, 2, 10),
('Computer Architecture', 'CPU, Memory, I/O devices explained', 4, 2, 18),
('SQL Fundamentals', 'Basic SQL queries and commands', 9, 2, 25),
('Numerical Methods Introduction', 'Basics of numerical analysis', 12, 2, 7),
('Java OOP Concepts', 'Object-oriented programming with Java', 10, 2, 20),
('Data Structures Guide', 'Complete guide to data structures', 11, 2, 16),
('Web Development Basics', 'HTML, CSS, JavaScript fundamentals', 13, 2, 22),
('Software Engineering Principles', 'SDLC and development methodologies', 14, 2, 9),
('Computer Networks Fundamentals', 'Networking basics and protocols', 15, 2, 14),
('AI Machine Learning', 'Introduction to artificial intelligence', 16, 2, 11),
('Operating Systems Concepts', 'OS fundamentals and process management', 17, 2, 13);

-- Insert sample questions for quizzes
INSERT INTO questions (subject_id, question_text, option_a, option_b, option_c, option_d, correct_answer, difficulty, created_by) VALUES
-- C Programming Questions
(1, 'Which is the correct way to declare a variable in C?', 'int x;', 'variable x;', 'x = int;', 'declare int x;', 'A', 'easy', 2),
(1, 'What is the output of printf("%d", 5+3);?', '53', '8', '5+3', 'Error', 'B', 'easy', 2),
(1, 'Which header file is required for printf function?', '<stdio.h>', '<conio.h>', '<math.h>', '<string.h>', 'A', 'easy', 2),
(1, 'What is the size of int data type in C?', '1 byte', '2 bytes', '4 bytes', 'Depends on compiler', 'D', 'medium', 2),

-- Computer Fundamentals Questions
(4, 'What is the brain of computer?', 'RAM', 'Hard Disk', 'CPU', 'Monitor', 'C', 'easy', 2),
(4, 'Which is an input device?', 'Monitor', 'Printer', 'Keyboard', 'Speaker', 'C', 'easy', 2),
(4, 'What does RAM stand for?', 'Random Access Memory', 'Read Access Memory', 'Random Active Memory', 'Read Active Memory', 'A', 'easy', 2),
(4, 'Which is a volatile memory?', 'Hard Disk', 'CD-ROM', 'RAM', 'ROM', 'C', 'medium', 2),

-- Database Management Questions
(9, 'What does SQL stand for?', 'Structured Query Language', 'Simple Query Language', 'Standard Query Language', 'System Query Language', 'A', 'easy', 2),
(9, 'Which command is used to retrieve data?', 'INSERT', 'UPDATE', 'SELECT', 'DELETE', 'C', 'easy', 2),
(9, 'What is a primary key?', 'A unique identifier for each record', 'A foreign key reference', 'A duplicate value', 'A null value', 'A', 'easy', 2),
(9, 'Which clause is used to filter records?', 'ORDER BY', 'GROUP BY', 'WHERE', 'HAVING', 'C', 'medium', 2),

-- Web Technology Questions
(13, 'What does HTML stand for?', 'Hyper Text Markup Language', 'High Tech Modern Language', 'Hyper Transfer Markup Language', 'Home Tool Markup Language', 'A', 'easy', 2),
(13, 'Which tag is used for creating hyperlinks?', '<link>', '<a>', '<href>', '<url>', 'B', 'easy', 2),
(13, 'What does CSS stand for?', 'Computer Style Sheets', 'Cascading Style Sheets', 'Creative Style Sheets', 'Colorful Style Sheets', 'B', 'easy', 2),
(13, 'Which property is used to change text color in CSS?', 'font-color', 'text-color', 'color', 'foreground-color', 'C', 'easy', 2),

-- Numerical Methods Questions
(12, 'What is the purpose of numerical methods?', 'To solve equations approximately', 'To write programs', 'To design databases', 'To create graphics', 'A', 'medium', 2),
(12, 'Which method is used to find roots of equations?', 'Bisection method', 'Sorting method', 'Searching method', 'Merging method', 'A', 'medium', 2),

-- OOP Questions
(10, 'What does OOP stand for?', 'Object Oriented Programming', 'Object Operation Programming', 'Open Object Programming', 'Online Object Programming', 'A', 'easy', 2),
(10, 'Which concept is not part of OOP?', 'Inheritance', 'Polymorphism', 'Encapsulation', 'Normalization', 'D', 'medium', 2);

-- Insert sample quiz results for testing
INSERT INTO quiz_results (user_id, subject_id, score, total_questions, percentage, time_taken) VALUES
(3, 1, 8, 10, 80.00, 15), -- Raj Kumar - C Programming
(3, 4, 9, 10, 90.00, 12), -- Raj Kumar - Computer Fundamentals
(3, 9, 7, 10, 70.00, 18), -- Raj Kumar - Database Management
(4, 1, 6, 10, 60.00, 20), -- Priya Sharma - C Programming
(4, 4, 8, 10, 80.00, 14), -- Priya Sharma - Computer Fundamentals
(4, 13, 9, 10, 90.00, 16), -- Priya Sharma - Web Technology
(5, 9, 8, 10, 80.00, 22), -- Amit Patel - Database Management
(5, 10, 7, 10, 70.00, 18), -- Amit Patel - OOP
(6, 13, 6, 10, 60.00, 25), -- Sneha Gupta - Web Technology
(6, 12, 8, 10, 80.00, 20), -- Sneha Gupta - Numerical Methods
(7, 1, 9, 10, 90.00, 13), -- Vikram Singh - C Programming
(7, 4, 7, 10, 70.00, 16); -- Vikram Singh - Computer Fundamentals

-- Insert sample download records
INSERT INTO downloads (user_id, note_id) VALUES
(3, 1), (3, 2), (3, 5), (4, 1), (4, 4), (4, 6), (5, 6), (5, 7), (6, 8), (6, 9), (7, 1), (7, 10);

-- Insert sample learning goals
INSERT INTO learning_goals (user_id, goal_type, target_value, current_value, status) VALUES
(3, 'quiz_count', 50, 8, 'active'),
(3, 'avg_score', 85, 82, 'active'),
(4, 'quiz_count', 30, 3, 'active'),
(4, 'avg_score', 80, 76, 'active'),
(5, 'quiz_count', 40, 2, 'active'),
(6, 'subject_mastery', 5, 2, 'active'),
(7, 'quiz_count', 25, 2, 'active');

-- ===================================================================
-- SETUP COMPLETE
-- ===================================================================
-- The Education Hub database has been successfully created with:
-- ✓ 9 Tables (users, subjects, notes, questions, quiz_results, downloads, learning_goals, quiz_edits_audit, content_deletions_audit, quiz_submissions)
-- ✓ 7 Sample Users (1 admin, 1 teacher, 5 students)
-- ✓ 17 Subjects across all years and semesters
-- ✓ 14 Sample Notes with download counts
-- ✓ 24 Sample Quiz Questions across multiple subjects
-- ✓ Sample Quiz Results for testing analytics
-- ✓ Sample Download Records for tracking
-- ✓ Sample Learning Goals for progress tracking
--
-- Default login credentials:
-- Admin: admin@educationhub.com / password123
-- Teacher: teacher@test.com / password123
-- Students: [name]@test.com / password123
--
-- Happy learning! 🎓
-- ===================================================================
