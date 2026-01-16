# Education Hub - XAMPP Installation Guide

## Project Overview
Education Hub is a digital learning platform built with PHP, MySQL, HTML, CSS, and JavaScript for running on XAMPP.

## Features
- **Student Portal**: Search notes, take quizzes, view performance
- **Teacher Portal**: Upload notes, manage quiz questions
- **Admin Panel**: Manage users, subjects, full system control
- **Quiz System**: Multiple choice questions with instant results
- **Performance Tracking**: View quiz history and statistics

## Installation Steps

### 1. Install XAMPP
Download and install XAMPP from https://www.apachefriends.org/

### 2. Start Services
- Open XAMPP Control Panel
- Start **Apache** and **MySQL**

### 3. Copy Files
Copy the entire `education_hub_xampp` folder to:
```
C:\xampp\htdocs\education_hub
```
(On Mac: `/Applications/XAMPP/htdocs/education_hub`)

### 4. Create Database
1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Click "New" to create a new database
3. Name it `education_hub`
4. Click "Create"

### 5. Import Database Schema
1. Select the `education_hub` database
2. Click "Import" tab
3. Choose the file: `database/education_hub.sql`
4. Click "Go" to import

### 6. Access the Application
Open your browser and go to:
```
http://localhost/education_hub
```

## Default Login Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@educationhub.com | password123 |
| Teacher | teacher@test.com | password123 |
| Student | raj@test.com | password123 |

## Folder Structure
```
education_hub/
├── admin/              # Admin panel pages
├── assets/
│   └── css/           # Stylesheets
├── auth/              # Login, register, logout
├── config/            # Database & helper functions
├── database/          # SQL schema file
├── includes/          # Reusable components (sidebar, header)
├── uploads/           # Uploaded notes (created automatically)
├── dashboard.php      # Student/Teacher dashboard
├── quiz.php           # Quiz system
├── performance.php    # Performance tracking
├── search_notes.php   # Notes search page
└── README.md          # This file
```

## Technologies Used
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript
- **Server**: Apache (XAMPP)

## Requirements
- XAMPP 7.4+ or higher
- Modern web browser (Chrome, Firefox, Edge)

## Troubleshooting

### Database Connection Error
1. Make sure MySQL is running in XAMPP
2. Check `config/database.php` for correct credentials:
   - Host: localhost
   - User: root
   - Password: (empty by default)
   - Database: education_hub

### File Upload Issues
1. Ensure `uploads/` folder exists and is writable
2. Check PHP upload limits in `php.ini`

## Support
For issues or questions, refer to your project synopsis document.

---
**Education Hub** - Built for BCA Final Year Project
