# Education Hub - End-to-End Test Suite

This directory contains comprehensive end-to-end tests for the Education Hub application using [Playwright](https://playwright.dev/).

## 📋 Table of Contents

- [Overview](#overview)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Running Tests](#running-tests)
- [Test Structure](#test-structure)
- [Test Coverage](#test-coverage)
- [Configuration](#configuration)
- [Troubleshooting](#troubleshooting)

---

## 🎯 Overview

These tests cover all user flows across three roles:
- **Student**: Search notes, take quizzes, view performance
- **Teacher**: Upload notes, manage questions, view student performance
- **Admin**: User management, subject management, plus all teacher features

## ✅ Prerequisites

1. **XAMPP Server** running with:
   - Apache on port 80
   - MySQL on port 3306
   - Education Hub project in `htdocs/education_hub`

2. **Node.js** v18+ installed

3. **Demo accounts** set up in the database:
   - Admin: `admin@educationhub.com` / `password123`
   - Teacher: `teacher@test.com` / `password123`
   - Student: `raj@test.com` / `password123`

## 🚀 Installation

```bash
# Install dependencies and Playwright browsers
npm install
npx playwright install
```

## ▶️ Running Tests

```bash
# Run all tests
npm test

# Run with browser visible (headed mode)
npm run test:headed

# Run specific test file
npm run test:auth
npm run test:student
npm run test:teacher
npm run test:admin

# Run on specific browser
npm run test:chromium
npm run test:firefox
npm run test:webkit

# Debug mode
npm run test:debug

# UI mode for interactive debugging
npm run test:ui

# View HTML report
npm run report
```

## 📁 Test Structure

```
e2e/
├── utils.ts           # Test utilities, constants, helpers
├── auth.spec.ts       # Authentication tests (login, logout, register)
├── student.spec.ts    # Student role tests
├── teacher.spec.ts    # Teacher role tests
└── admin.spec.ts      # Admin role tests
```

### Test Utilities (`utils.ts`)

Contains:
- **Credentials**: Demo login credentials for all roles
- **Page URLs**: Centralized page path constants
- **Selectors**: Element selectors for common UI elements
- **Helper Functions**: `login()`, `logout()`, data generators

## 🧪 Test Coverage

### Authentication Tests (`auth.spec.ts`)

| Test Suite | Tests |
|------------|-------|
| Login Flows | Page display, login as all roles, invalid credentials, empty fields |
| Logout Flows | Logout for all roles, redirect verification |
| Registration Flows | Student/teacher registration, validation errors, duplicate email |
| Access Control | Protected routes, role-based access restrictions |

### Student Tests (`student.spec.ts`)

| Feature | Tests |
|---------|-------|
| Dashboard | Stats display, quick actions, navigation |
| Search Notes | Year/semester filters, keyword search, subject filter, download |
| Quiz | Subject selection, quiz taking, progress tracking, results |
| Performance | Stats display, quiz history, subject-wise performance |
| Profile | View profile, edit profile |
| E2E Flow | Complete student workflow |

### Teacher Tests (`teacher.spec.ts`)

| Feature | Tests |
|---------|-------|
| Dashboard | Teacher stats, sidebar links |
| Upload Notes | Form display, year/semester filtering, file upload |
| Manage Questions | Add questions, form validation, recent questions list |
| Student Performance | View all student performance |
| Notes Management | Access management pages |
| E2E Flow | Complete teacher workflow |

### Admin Tests (`admin.spec.ts`)

| Feature | Tests |
|---------|-------|
| Dashboard | Platform statistics, recent users, all notes/questions |
| Manage Users | User list, role changes, delete users, approve teachers |
| Manage Subjects | Add subjects, validation, delete subjects, subject table |
| Feature Access | Access to all teacher/student features |
| E2E Flow | Complete admin workflow |

## ⚙️ Configuration

### Base URL

Edit `playwright.config.ts` to match your local setup:

```typescript
use: {
  baseURL: 'http://localhost/education_hub', // Change if needed
}
```

### Demo Credentials

Update `e2e/utils.ts` if your demo accounts differ:

```typescript
export const CREDENTIALS = {
  admin: {
    email: 'admin@educationhub.com',
    password: 'password123'
  },
  teacher: {
    email: 'teacher@test.com',
    password: 'password123'
  },
  student: {
    email: 'raj@test.com',
    password: 'password123'
  }
};
```

### Browser Configuration

Modify `playwright.config.ts` to add/remove browsers:

```typescript
projects: [
  { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
  { name: 'webkit', use: { ...devices['Desktop Safari'] } },
]
```

## 🔧 Troubleshooting

### Tests fail with "Cannot find module"

Run `npm install` to install dependencies.

### Tests fail with browser not found

Run `npx playwright install` to install browser binaries.

### Tests timeout

Increase timeout in `playwright.config.ts`:

```typescript
timeout: 60 * 1000, // 60 seconds
```

### Database connection errors

Ensure:
1. XAMPP MySQL is running
2. Database `education_hub` exists
3. Demo users exist in the `users` table

### Element not found errors

The application may have changed. Update selectors in `e2e/utils.ts`.

### Permission denied on upload tests

Create the uploads directory:
```bash
mkdir -p uploads/notes
chmod 755 uploads/notes
```

## 📝 Best Practices

1. **Use data generators** for test data to avoid conflicts
2. **Clean up after tests** - tests are isolated via context clearing
3. **Use helper functions** from `utils.ts` for common operations
4. **Add proper waits** for page loads and async operations
5. **Use role-based organization** when adding new tests

## 🔄 CI/CD Integration

Add to your GitHub Actions workflow:

```yaml
- name: Run Playwright tests
  run: |
    npm ci
    npx playwright install --with-deps
    npx playwright test
- uses: actions/upload-artifact@v3
  if: always()
  with:
    name: playwright-report
    path: playwright-report/
    retention-days: 30
```

---

**Built with ❤️ using [Playwright](https://playwright.dev/)**
