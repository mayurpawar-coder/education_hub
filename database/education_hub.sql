-- -- Education Hub Database Schema for MySQL (XAMPP)
-- -- Run this in phpMyAdmin

-- CREATE DATABASE IF NOT EXISTS education_hub;
-- USE education_hub;

-- -- Users table with roles (student, teacher, admin)
-- CREATE TABLE users (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     name VARCHAR(100) NOT NULL,
--     email VARCHAR(100) UNIQUE NOT NULL,
--     password VARCHAR(255) NOT NULL,
--     role ENUM('student', 'teacher', 'admin') DEFAULT 'student',
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
-- );

-- -- Subjects table
-- CREATE TABLE subjects (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     name VARCHAR(100) NOT NULL,
--     description TEXT,
--     icon VARCHAR(50) DEFAULT 'book',
--     color VARCHAR(20) DEFAULT '#0099ff',
--     created_by INT,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
-- );

-- -- Notes table
-- CREATE TABLE notes (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     title VARCHAR(200) NOT NULL,
--     content TEXT,
--     file_path VARCHAR(255),
--     subject_id INT NOT NULL,
--     uploaded_by INT NOT NULL,
--     downloads INT DEFAULT 0,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--     FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
--     FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
-- );

-- -- Questions table for quizzes
-- CREATE TABLE questions (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     subject_id INT NOT NULL,
--     question_text TEXT NOT NULL,
--     option_a VARCHAR(255) NOT NULL,
--     option_b VARCHAR(255) NOT NULL,
--     option_c VARCHAR(255) NOT NULL,
--     option_d VARCHAR(255) NOT NULL,
--     correct_answer ENUM('A', 'B', 'C', 'D') NOT NULL,
--     difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
--     created_by INT,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
--     FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
-- );

-- -- Quiz results table
-- CREATE TABLE quiz_results (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     user_id INT NOT NULL,
--     subject_id INT NOT NULL,
--     score INT NOT NULL,
--     total_questions INT NOT NULL,
--     percentage DECIMAL(5,2) NOT NULL,
--     time_taken INT DEFAULT 0,
--     taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
--     FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
-- );

-- -- Insert default admin user (password: admin123)
-- INSERT INTO users (name, email, password, role) VALUES 
-- ('Admin', 'admin@educationhub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
-- ('Teacher Demo', 'teacher@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher'),
-- ('Raj Kumar', 'raj@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student');

-- -- Insert sample subjects
-- INSERT INTO subjects (name, description, icon, color, created_by) VALUES
-- ('Mathematics', 'Algebra, Calculus, Statistics and more', 'calculator', '#0099ff', 1),
-- ('Physics', 'Mechanics, Thermodynamics, Optics', 'atom', '#7c3aed', 1),
-- ('Chemistry', 'Organic, Inorganic, Physical Chemistry', 'flask', '#10b981', 1),
-- ('Computer Science', 'Programming, Data Structures, Algorithms', 'code', '#f59e0b', 1),
-- ('English', 'Grammar, Literature, Writing Skills', 'book-open', '#ec4899', 1);

-- -- Insert sample notes
-- INSERT INTO notes (title, content, subject_id, uploaded_by) VALUES
-- ('Algebra Fundamentals', 'Basic algebra concepts and formulas', 1, 2),
-- ('Calculus Introduction', 'Derivatives and integrals basics', 1, 2),
-- ('Newton Laws of Motion', 'First, Second and Third laws explained', 2, 2),
-- ('Organic Chemistry Basics', 'Introduction to organic compounds', 3, 2),
-- ('Python Programming', 'Getting started with Python', 4, 2);

-- -- Insert sample questions
-- INSERT INTO questions (subject_id, question_text, option_a, option_b, option_c, option_d, correct_answer, created_by) VALUES
-- (1, 'What is the value of x in 2x + 5 = 15?', '5', '10', '7', '3', 'A', 2),
-- (1, 'What is the derivative of x²?', 'x', '2x', '2', 'x²', 'B', 2),
-- (2, 'What is the SI unit of force?', 'Joule', 'Newton', 'Watt', 'Pascal', 'B', 2),
-- (2, 'Speed of light is approximately?', '3×10⁶ m/s', '3×10⁸ m/s', '3×10¹⁰ m/s', '3×10⁴ m/s', 'B', 2),
-- (3, 'What is the atomic number of Carbon?', '4', '6', '8', '12', 'B', 2),
-- (4, 'Which is not a programming language?', 'Python', 'Java', 'HTML', 'C++', 'C', 2),
-- (4, 'What does CPU stand for?', 'Central Processing Unit', 'Computer Personal Unit', 'Central Program Utility', 'Core Processing Unit', 'A', 2);
-- Education Hub Database Schema for MySQL (XAMPP)
-- Run this in phpMyAdmin

CREATE DATABASE IF NOT EXISTS education_hub;
USE education_hub;

-- Users table with roles (student, teacher, admin)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'teacher', 'admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Subjects table
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'book',
    color VARCHAR(20) DEFAULT '#0099ff',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Notes table
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

-- Questions table for quizzes
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

-- Quiz results table
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

-- Insert default admin user (password: admin123)
INSERT INTO users (name, email, password, role) VALUES 
('Admin', 'admin@educationhub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Teacher Demo', 'teacher@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher'),
('Raj Kumar', 'raj@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student');

-- Insert sample subjects
INSERT INTO subjects (name, description, icon, color, created_by) VALUES
('Procedural Programming-1', 'Programming fundamentals, C language, Functions', 'code', '#0099ff', 1),
('MAR-Sant Sahitya Ani Manvi Mule', 'Marathi literature and cultural studies', 'book-open', '#7c3aed', 1),
('Fundamental of Enterpreneurship-1', 'Business basics, Startup fundamentals', 'briefcase', '#10b981', 1),
('Fundamental of Computer', 'Computer basics, Hardware, Software concepts', 'monitor', '#f59e0b', 1),
('Database Management System', 'SQL, Database design, Normalization', 'database', '#ec4899', 1),
('Computational Numerical Methods', 'Numerical analysis, Algorithms, Mathematics', 'calculator', '#6366f1', 1),
('Communication Skills in English-1', 'English grammar, Writing, Speaking skills', 'message-circle', '#14b8a6', 1);

-- Insert sample notes
INSERT INTO notes (title, content, subject_id, uploaded_by) VALUES
('C Programming Basics', 'Introduction to C language and syntax', 1, 2),
('Functions in C', 'How to create and use functions', 1, 2),
('Sant Sahitya Overview', 'Introduction to Marathi saint literature', 2, 2),
('Business Plan Basics', 'How to create a business plan', 3, 2),
('Computer Architecture', 'CPU, Memory, I/O devices explained', 4, 2),
('SQL Fundamentals', 'Basic SQL queries and commands', 5, 2),
('Numerical Methods Introduction', 'Basics of numerical analysis', 6, 2);

-- Insert sample questions
INSERT INTO questions (subject_id, question_text, option_a, option_b, option_c, option_d, correct_answer, created_by) VALUES
(1, 'Which is the correct way to declare a variable in C?', 'int x;', 'variable x;', 'x = int;', 'declare int x;', 'A', 2),
(1, 'What is the output of printf("%d", 5+3);?', '53', '8', '5+3', 'Error', 'B', 2),
(4, 'What is the brain of computer?', 'RAM', 'Hard Disk', 'CPU', 'Monitor', 'C', 2),
(4, 'Which is an input device?', 'Monitor', 'Printer', 'Keyboard', 'Speaker', 'C', 2),
(5, 'What does SQL stand for?', 'Structured Query Language', 'Simple Query Language', 'Standard Query Language', 'System Query Language', 'A', 2),
(5, 'Which command is used to retrieve data?', 'INSERT', 'UPDATE', 'SELECT', 'DELETE', 'C', 2),
(6, 'What is the purpose of numerical methods?', 'To solve equations approximately', 'To write programs', 'To design databases', 'To create graphics', 'A', 2);
