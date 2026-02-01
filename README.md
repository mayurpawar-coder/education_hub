# 📚 Education Hub - Complete Project Documentation

> **A comprehensive educational platform built with PHP, MySQL, HTML, CSS, and JavaScript for XAMPP**

---

## 📋 Table of Contents

1. [Project Overview](#-project-overview)
2. [Technology Stack](#-technology-stack)
3. [File Structure](#-file-structure)
4. [Database Schema](#-database-schema)
5. [Detailed File Explanations](#-detailed-file-explanations)
6. [CSS Architecture & Styling](#-css-architecture--styling)
7. [Features & Functionality](#-features--functionality)
8. [User Roles & Permissions](#-user-roles--permissions)
9. [Installation Guide](#-installation-guide)
10. [Demo Credentials](#-demo-credentials)
11. [Screenshots & User Flow](#-screenshots--user-flow)

---

## 🎯 Project Overview

**Education Hub** is a full-featured educational management system designed for colleges and educational institutions. It provides:

- **Year and Semester-wise Distribution** (FY, SY, TY with Semesters 1-6)
- **Note Management** - Upload, search, and download study materials
- **Quiz System** - Practice quizzes with performance tracking
- **User Management** - Students, Teachers, and Admins with different permissions
- **Performance Analytics** - Track quiz scores and progress

### Key Highlights
- Pure PHP backend (no frameworks)
- MySQL database with XAMPP
- Modern dark-themed UI with CSS animations
- Responsive design for mobile and desktop
- Role-based access control

---

## 💻 Technology Stack

| Layer | Technology | Purpose |
|-------|------------|---------|
| **Backend** | PHP 7.4+ | Server-side logic, authentication, database operations |
| **Database** | MySQL 5.7+ | Data storage (users, subjects, notes, questions, results) |
| **Frontend** | HTML5 | Page structure and semantic markup |
| **Styling** | CSS3 | Modern UI with dark theme, animations, responsive design |
| **Interactivity** | Vanilla JavaScript | Form validation, dynamic filtering, drag & drop |
| **Server** | Apache (XAMPP) | Local development server |

### CSS Features Used
- **CSS Variables (Custom Properties)** - Theme colors and design tokens
- **CSS Grid & Flexbox** - Modern responsive layouts
- **CSS Gradients** - Beautiful gradient backgrounds
- **CSS Transitions & Transforms** - Smooth hover animations
- **CSS Media Queries** - Mobile responsive design

---

## 📁 File Structure

```
education_hub_clean/
├── config/                      # Configuration files
│   ├── database.php            # Database connection class
│   └── functions.php           # Helper functions (auth, sanitize, etc.)
│
├── auth/                        # Authentication pages
│   ├── login.php               # User login page
│   ├── register.php            # User registration page
│   └── logout.php              # Session logout handler
│
├── admin/                       # Admin-only pages
│   ├── dashboard.php           # Admin dashboard with stats
│   ├── users.php               # User management (CRUD)
│   └── subjects.php            # Subject management (CRUD)
│
├── includes/                    # Reusable components
│   ├── header.php              # Page header with user info
│   └── sidebar.php             # Navigation sidebar
│
├── assets/                      # Static assets
│   └── css/
│       ├── style.css           # Main stylesheet (748 lines)
│       ├── search_notes.css    # Search notes page styles
│       ├── quiz.css            # Quiz page styles
│       └── upload_notes.css    # Upload notes page styles
│
├── database/                    # Database files
│   └── education_hub.sql       # Complete database schema + seed data
│
├── uploads/                     # User uploads directory
│   └── notes/
│       └── .gitkeep            # Placeholder for uploaded files
│
├── index.php                    # Entry point (redirects based on auth)
├── dashboard.php                # Student/Teacher dashboard
├── search_notes.php             # Search and download notes
├── download_notes.php           # File download handler
├── upload_notes.php             # Note upload (Teachers only)
├── quiz.php                     # Take quiz page
├── performance.php              # Performance analytics
├── manage_questions.php         # Add quiz questions (Teachers)
└── README.md                    # This documentation file
```

---

## 🗄️ Database Schema

### Entity Relationship Diagram

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│    users    │     │   subjects  │     │   notes     │
├─────────────┤     ├─────────────┤     ├─────────────┤
│ id (PK)     │     │ id (PK)     │     │ id (PK)     │
│ name        │◄────│ created_by  │     │ title       │
│ email       │     │ name        │◄────│ subject_id  │
│ password    │     │ description │     │ uploaded_by │─►
│ role        │     │ year        │     │ content     │
│ year        │     │ semester    │     │ file_path   │
│ semester    │     │ color       │     │ downloads   │
│ created_at  │     │ icon        │     │ created_at  │
└─────────────┘     └─────────────┘     └─────────────┘
       │                   │
       │                   │
       ▼                   ▼
┌─────────────┐     ┌─────────────┐
│  questions  │     │quiz_results │
├─────────────┤     ├─────────────┤
│ id (PK)     │     │ id (PK)     │
│ subject_id  │◄────│ subject_id  │
│ question    │     │ user_id     │─►
│ option_a    │     │ score       │
│ option_b    │     │ total       │
│ option_c    │     │ percentage  │
│ option_d    │     │ taken_at    │
│ correct_ans │     └─────────────┘
│ difficulty  │
│ created_by  │─►
└─────────────┘
```

### Table Descriptions

| Table | Purpose | Key Fields |
|-------|---------|------------|
| `users` | Store all user accounts | role (student/teacher/admin), year, semester |
| `subjects` | Academic subjects by year/semester | year (FY/SY/TY), semester (1-6), color, icon |
| `notes` | Uploaded study materials | file_path, subject_id, downloads count |
| `questions` | Quiz questions with MCQ options | 4 options (A-D), correct_answer, difficulty |
| `quiz_results` | Track user quiz performance | score, percentage, time_taken |

---

## 📄 Detailed File Explanations

### Configuration Files

#### `config/database.php`
```php
Purpose: Database connection management
- Defines connection constants (DB_HOST, DB_USER, DB_PASS, DB_NAME)
- Creates Database class with mysqli connection
- Methods: query(), prepare(), escape(), lastInsertId()
- Starts PHP session if not started
- Creates global $db and $conn objects
```

#### `config/functions.php`
```php
Purpose: Reusable helper functions
- isLoggedIn() - Check if user is authenticated
- hasRole($role) - Check user's role
- isAdmin(), isTeacher(), isStudent() - Role shortcuts
- getBasePath() - Calculate relative path for redirects
- redirect($url) - Safe redirect with exit
- requireLogin(), requireAdmin(), requireTeacher() - Route protection
- sanitize($input) - Input sanitization (XSS prevention)
- showAlert($message, $type) - Display colored alert messages
- getCurrentUser() - Get logged-in user data
- formatDate($date) - Format MySQL dates
- getUserStats($userId) - Get user's quiz statistics
```

### Authentication Pages

#### `auth/login.php`
```php
Purpose: User authentication
CSS Used: style.css (auth-page, auth-card classes)
Features:
- Email/password validation
- Password verification with password_verify()
- Session variable setup on success
- Role-based redirect (admin vs student/teacher)
- Demo credentials display
Animations: Form input focus effects, button hover transforms
```

#### `auth/register.php`
```php
Purpose: New user registration
CSS Used: style.css
Features:
- Form validation (name, email, password, confirm)
- Email uniqueness check
- Password hashing with password_hash()
- Role selection (student/teacher only)
- Success redirect to login
```

#### `auth/logout.php`
```php
Purpose: End user session
Features:
- session_destroy() to clear all session data
- Redirect to login page
```

### Main Application Pages

#### `index.php`
```php
Purpose: Application entry point
Logic:
- If logged in → redirect to appropriate dashboard
- If not logged in → redirect to login page
```

#### `dashboard.php`
```php
Purpose: Main user dashboard
CSS Used: style.css
Features:
- Stats cards (quizzes taken, avg score, notes, subjects)
- Quick action buttons
- Subject grid with view notes/take quiz buttons
Components: sidebar.php, header.php
```

#### `search_notes.php`
```php
Purpose: Search and filter study notes
CSS Used: style.css + search_notes.css
Features:
- Year tabs (FY, SY, TY) with colored badges
- Semester tabs (dynamically based on year)
- Search input with icon
- Subject filter dropdown
- Note cards with download buttons
Animations:
- Tab hover/active transitions
- Card hover lift effect (translateY)
- Download button glow effect
```

#### `download_notes.php`
```php
Purpose: Handle file downloads
Features:
- Increment download counter
- Serve actual file if exists
- Generate text file from content if no file
- Proper HTTP headers for download
```

#### `upload_notes.php`
```php
Purpose: Teachers upload study materials
CSS Used: style.css + upload_notes.css
Features:
- Year selector buttons (FY/SY/TY)
- Semester selector buttons
- Dynamic subject filtering based on selection
- Drag & drop file upload area
- File type validation
JavaScript:
- Year/semester button event listeners
- Dynamic subject dropdown filtering
- Drag & drop file handling
- File preview display
Animations:
- Button active state transitions
- Dropzone hover/dragover effects
```

#### `quiz.php`
```php
Purpose: Take practice quizzes
CSS Used: style.css + quiz.css
Features:
- Subject selection with year/semester filters
- Question cards with 4 options each
- Progress bar tracking
- Score calculation and result display
- Answer review with correct/wrong indicators
- Circular score animation
JavaScript:
- Progress bar update on answer selection
- Form submission handling
Animations:
- Option hover slide effect (translateX)
- Score circle with conic-gradient animation
- Result card confetti-style effects
```

#### `performance.php`
```php
Purpose: View quiz history and analytics
CSS Used: style.css
Features:
- Stats overview cards
- Performance by subject with progress bars
- Quiz history table with score badges
- Status indicators (Excellent/Good/Average/Needs Work)
```

#### `manage_questions.php`
```php
Purpose: Teachers add quiz questions
CSS Used: style.css
Features:
- Add question form
- Subject and difficulty selection
- 4 options input with correct answer selector
- Recent questions table display
```

### Admin Pages

#### `admin/dashboard.php`
```php
Purpose: Admin overview and stats
CSS Used: style.css
Features:
- Platform-wide statistics (users, subjects, notes, questions)
- Quick action buttons
- Recent users table
```

#### `admin/users.php`
```php
Purpose: User management (CRUD)
CSS Used: style.css
Features:
- All users table
- Role change dropdown (instant update)
- Delete user functionality
- Self-delete prevention
```

#### `admin/subjects.php`
```php
Purpose: Subject management
CSS Used: style.css
Features:
- Add new subject form with year/semester
- Color picker for subject theme
- All subjects table with notes/questions count
- Delete subject with cascade warning
```

### Include Components

#### `includes/header.php`
```php
Purpose: Page header component
Features:
- Dynamic page title display
- User info with avatar initials
- User role badge
```

#### `includes/sidebar.php`
```php
Purpose: Navigation sidebar
Features:
- Dynamic active link highlighting
- Role-based menu items
- Teacher/Admin extra links
- Logout link at bottom
```

---

## 🎨 CSS Architecture & Styling

### CSS Variables (Design Tokens)

```css
:root {
    /* Primary Colors */
    --primary: #0099ff;          /* Main brand color */
    --primary-dark: #0077cc;     /* Darker shade for hover */
    --secondary: #7c3aed;        /* Purple accent */
    
    /* Status Colors */
    --success: #10b981;          /* Green for success */
    --warning: #f59e0b;          /* Orange for warnings */
    --danger: #ef4444;           /* Red for errors */
    
    /* Surface Colors */
    --background: #0f1419;       /* Page background */
    --surface: #1a1f2e;          /* Card backgrounds */
    --surface-light: #252b3b;    /* Elevated surfaces */
    
    /* Text Colors */
    --text: #ffffff;             /* Primary text */
    --text-muted: #9ca3af;       /* Secondary text */
    --border: #374151;           /* Border color */
    
    /* Gradients */
    --gradient-primary: linear-gradient(135deg, #0099ff, #7c3aed);
    --gradient-success: linear-gradient(135deg, #10b981, #0099ff);
    --gradient-warning: linear-gradient(135deg, #f59e0b, #ef4444);
}
```

### File-Specific CSS Details

#### `style.css` (Main - 748 lines)
| Section | Line Range | Purpose |
|---------|------------|---------|
| Auth Pages | 36-157 | Login/register form styling |
| Buttons | 159-214 | Button variants (primary, secondary, success) |
| Layout | 216-345 | Sidebar, main content, header |
| Cards | 347-475 | Stat cards, subject cards |
| Tables | 477-503 | Table styling with hover |
| Quiz | 505-596 | Quiz-specific base styles |
| Search | 598-664 | Search bar, notes grid |
| Admin | 666-679 | Admin dashboard styles |
| Alerts | 681-707 | Success/error/warning alerts |
| Responsive | 709-748 | Mobile breakpoints |

#### `search_notes.css` (336 lines)
| Section | Purpose |
|---------|---------|
| Hero | Gradient banner with title |
| Year Tabs | Horizontal tab navigation |
| Semester Tabs | Sub-navigation for semesters |
| Modern Search | Search input with icon |
| Note Cards | Modern card design with hover |
| Empty State | "No results" placeholder |

#### `quiz.css` (670 lines)
| Section | Purpose |
|---------|---------|
| Subject Grid | Card-based subject selection |
| Progress Bar | Quiz completion indicator |
| Question Cards | Question display with options |
| Option Styling | Radio button custom design |
| Results | Score circle, answer review |
| Review Cards | Correct/wrong answer display |

#### `upload_notes.css` (185 lines)
| Section | Purpose |
|---------|---------|
| Hero | Green gradient banner |
| Form Grid | Two-column form layout |
| Year/Sem Buttons | Toggle button groups |
| File Upload | Drag & drop area styling |
| Upload Button | Full-width gradient button |

### Animation Classes

```css
/* Card Hover Effect */
.card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

/* Button Hover */
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 153, 255, 0.3);
}

/* Quiz Option Slide */
.quiz-option:hover {
    transform: translateX(8px);
    border-color: var(--primary);
}

/* Score Circle Animation */
.score-circle-bg {
    background: conic-gradient(
        var(--primary) calc(var(--score) * 1%),
        var(--surface-light) calc(var(--score) * 1%)
    );
}
```

---

## ⚙️ Features & Functionality

### 1. Year/Semester Distribution
- **FY (First Year)**: Semester 1 & 2
- **SY (Second Year)**: Semester 3 & 4
- **TY (Third Year)**: Semester 5 & 6

Each subject is assigned to a specific year and semester, allowing:
- Filtered search of notes
- Subject-wise quiz selection
- Organized curriculum structure

### 2. Note Management
- **Upload**: Teachers can upload PDF, DOC, DOCX, TXT, PPT files
- **Search**: Filter by year, semester, subject, or keyword
- **Download**: Track download counts

### 3. Quiz System
- **Random Questions**: 10 random questions per quiz
- **MCQ Format**: 4 options with single correct answer
- **Instant Scoring**: Immediate results with percentage
- **Answer Review**: See correct answers after submission
- **History Tracking**: All attempts saved to database

### 4. Performance Analytics
- **Quiz Statistics**: Total quizzes, average score
- **Subject-wise Progress**: Bar chart visualization
- **History Table**: Date, subject, score for each attempt

---

## 👥 User Roles & Permissions

| Feature | Student | Teacher | Admin |
|---------|---------|---------|-------|
| View Dashboard | ✅ | ✅ | ✅ |
| Search Notes | ✅ | ✅ | ✅ |
| Download Notes | ✅ | ✅ | ✅ |
| Take Quiz | ✅ | ✅ | ✅ |
| View Performance | ✅ | ✅ | ✅ |
| Upload Notes | ❌ | ✅ | ✅ |
| Manage Questions | ❌ | ✅ | ✅ |
| Manage Users | ❌ | ❌ | ✅ |
| Manage Subjects | ❌ | ❌ | ✅ |
| Admin Dashboard | ❌ | ❌ | ✅ |

---

## 🚀 Installation Guide

### Prerequisites
- XAMPP (Apache + MySQL + PHP)
- Web browser (Chrome, Firefox, Edge)

### Step-by-Step Installation

1. **Download & Install XAMPP**
   - Visit: https://www.apachefriends.org/
   - Install with Apache and MySQL modules

2. **Start XAMPP Services**
   ```
   Start Apache (port 80)
   Start MySQL (port 3306)
   ```

3. **Copy Project Files**
   ```
   Copy 'education_hub_clean' folder to:
   Windows: C:\xampp\htdocs\
   Mac: /Applications/XAMPP/htdocs/
   Linux: /opt/lampp/htdocs/
   ```

4. **Create Database**
   - Open phpMyAdmin: http://localhost/phpmyadmin
   - Click "Import" tab
   - Select `database/education_hub.sql`
   - Click "Go" to execute

5. **Access Application**
   ```
   URL: http://localhost/education_hub_clean/
   ```

---

## 🔑 Demo Credentials

| Role | Email | Password |
|------|-------|----------|
| **Admin** | admin@educationhub.com | password123 |
| **Teacher** | teacher@test.com | password123 |
| **Student** | raj@test.com | password123 |

---

## 📸 User Flow

### Student Flow
```
Login → Dashboard → Search Notes → Download
                 └→ Take Quiz → View Results → Performance
```

### Teacher Flow
```
Login → Dashboard → Upload Notes
                 └→ Manage Questions → Add Question
                 └→ Take Quiz (for testing)
```

### Admin Flow
```
Login → Admin Dashboard → Manage Users (CRUD)
                       └→ Manage Subjects (CRUD)
                       └→ All Teacher Features
```

---

## 📝 Code Conventions

### PHP
- Functions use camelCase: `isLoggedIn()`, `getUserStats()`
- Classes use PascalCase: `Database`
- Constants use UPPER_SNAKE_CASE: `DB_HOST`
- All inputs sanitized with `sanitize()` function

### CSS
- Classes use kebab-case: `.nav-link`, `.stat-card`
- Modifiers use double-dash: `.stat-card--success`
- BEM-like structure for components

### JavaScript
- Variables use camelCase: `selectedYear`, `filterSubjects`
- Event handlers are inline for simplicity

---

## 🔒 Security Features

1. **Password Hashing**: Uses `password_hash()` with bcrypt
2. **SQL Injection Prevention**: Prepared statements with `bind_param()`
3. **XSS Prevention**: `htmlspecialchars()` and `sanitize()` function
4. **Session Security**: Session-based authentication
5. **Role-based Access**: Server-side permission checks

---

## 📄 License

This project is created for educational purposes. Feel free to use and modify for learning.

---

**Built with ❤️ for Education Hub**
