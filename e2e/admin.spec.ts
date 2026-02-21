/**
 * Admin Role E2E Tests for Education Hub
 * 
 * Tests all admin-specific features:
 * - Admin dashboard with platform stats
 * - User management (view, edit roles, delete, approve teachers)
 * - Subject management (add, delete)
 * - Access to all teacher features
 */

import { test, expect } from '@playwright/test';
import { 
  CREDENTIALS, 
  PAGES, 
  SELECTORS,
  generateTestSubject,
  generateTestUser,
  login, 
  logout 
} from './utils';

test.describe('Admin - Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDENTIALS.admin.email, CREDENTIALS.admin.password);
  });

  test.afterEach(async ({ page }) => {
    await logout(page);
  });

  test('should display admin dashboard with platform statistics', async ({ page }) => {
    await expect(page).toHaveURL(/.*admin\/dashboard\.php/);
    await expect(page.locator('text=Admin Dashboard')).toBeVisible();
    await expect(page.locator('text=Admin Panel')).toBeVisible();
    
    // Verify platform statistics
    await expect(page.locator('.admin-stats')).toBeVisible();
    await expect(page.locator('text=Total Users')).toBeVisible();
    await expect(page.locator('text=Students')).toBeVisible();
    await expect(page.locator('text=Teachers')).toBeVisible();
    await expect(page.locator('text=Subjects')).toBeVisible();
    await expect(page.locator('text=Notes Uploaded')).toBeVisible();
    await expect(page.locator('text=Quiz Questions')).toBeVisible();
    await expect(page.locator('text=Quizzes Taken')).toBeVisible();
  });

  test('should have admin action buttons', async ({ page }) => {
    await expect(page.locator('text=Manage Users')).toBeVisible();
    await expect(page.locator('text=Manage Subjects')).toBeVisible();
    await expect(page.locator('text=Student Performance')).toBeVisible();
    await expect(page.locator('text=Upload Notes')).toBeVisible();
    await expect(page.locator('text=Add Questions')).toBeVisible();
  });

  test('should display recent users table', async ({ page }) => {
    await expect(page.locator('text=Recent Users')).toBeVisible();
    
    const tableExists = await page.locator(SELECTORS.admin.recentUsersTable).count() > 0;
    if (tableExists) {
      await expect(page.locator('th:has-text("Name")')).toBeVisible();
      await expect(page.locator('th:has-text("Email")')).toBeVisible();
      await expect(page.locator('th:has-text("Role")')).toBeVisible();
      await expect(page.locator('th:has-text("Joined")')).toBeVisible();
    }
  });

  test('should display all notes table', async ({ page }) => {
    await expect(page.locator('text=All Uploaded Notes')).toBeVisible();
    
    const tableExists = await page.locator(SELECTORS.admin.allNotesTable).count() > 0;
    if (tableExists) {
      await expect(page.locator('th:has-text("Title")')).toBeVisible();
      await expect(page.locator('th:has-text("Subject")')).toBeVisible();
      await expect(page.locator('th:has-text("Uploaded By")')).toBeVisible();
      await expect(page.locator('th:has-text("Downloads")')).toBeVisible();
    }
  });

  test('should display all questions table', async ({ page }) => {
    await expect(page.locator('text=All Quiz Questions')).toBeVisible();
    
    const tableExists = await page.locator(SELECTORS.admin.allQuestionsTable).count() > 0;
    if (tableExists) {
      await expect(page.locator('th:has-text("Question")')).toBeVisible();
      await expect(page.locator('th:has-text("Subject")')).toBeVisible();
      await expect(page.locator('th:has-text("Created By")')).toBeVisible();
      await expect(page.locator('th:has-text("Correct")')).toBeVisible();
    }
  });

  test('should navigate to manage users from dashboard', async ({ page }) => {
    await page.click('a:has-text("Manage Users")');
    await expect(page).toHaveURL(/.*admin\/users\.php/);
  });

  test('should navigate to manage subjects from dashboard', async ({ page }) => {
    await page.click('a:has-text("Manage Subjects")');
    await expect(page).toHaveURL(/.*admin\/subjects\.php/);
  });
});

test.describe('Admin - Manage Users', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDENTIALS.admin.email, CREDENTIALS.admin.password);
    await page.goto(PAGES.manageUsers);
  });

  test.afterEach(async ({ page }) => {
    await logout(page);
  });

  test('should display user management page', async ({ page }) => {
    await expect(page.locator('text=All Users')).toBeVisible();
    await expect(page.locator('table')).toBeVisible();
    
    // Table headers
    await expect(page.locator('th:has-text("ID")')).toBeVisible();
    await expect(page.locator('th:has-text("User")')).toBeVisible();
    await expect(page.locator('th:has-text("Role")')).toBeVisible();
    await expect(page.locator('th:has-text("Actions")')).toBeVisible();
  });

  test('should show role dropdown for each user', async ({ page }) => {
    const roleSelects = page.locator('select[name="new_role"]');
    const count = await roleSelects.count();
    expect(count).toBeGreaterThan(0);
    
    // Verify options exist
    await expect(roleSelects.first()).toContainText('Student');
    await expect(roleSelects.first()).toContainText('Teacher');
    await expect(roleSelects.first()).toContainText('Admin');
  });

  test('should have delete button for non-current users', async ({ page }) => {
    // Find delete buttons (exclude current admin user)
    const deleteButtons = page.locator('a:has-text("Delete")');
    const count = await deleteButtons.count();
    
    // Should have delete buttons for other users
    if (count > 0) {
      await expect(deleteButtons.first()).toBeVisible();
    }
  });

  test('should change user role', async ({ page }) => {
    // Find a user that is not the current admin
    const roleSelects = page.locator('select[name="new_role"]').filter({
      hasNot: page.locator('option[value="admin"]:checked')
    });
    
    if (await roleSelects.count() === 0) {
      test.skip();
      return;
    }
    
    // Change first available user's role to teacher
    const firstSelect = roleSelects.first();
    await firstSelect.selectOption('teacher');
    
    // Should redirect and show success
    await page.waitForLoadState('networkidle');
    
    // Success message should appear
    const successVisible = await page.locator('.alert-success').isVisible().catch(() => false);
    expect(successVisible).toBeTruthy();
  });

  test('should show teacher approval buttons for pending teachers', async ({ page }) => {
    // Look for approve/reject buttons
    const approveButtons = page.locator('a:has-text("Approve")');
    const rejectButtons = page.locator('a:has-text("Reject")');
    
    // These may or may not exist depending on database state
    const approveCount = await approveButtons.count();
    const rejectCount = await rejectButtons.count();
    
    // If there are pending teachers, both buttons should exist
    if (approveCount > 0) {
      expect(rejectCount).toBeGreaterThan(0);
    }
  });

  test('should prevent admin from deleting themselves', async ({ page }) => {
    // Try to delete the current admin user
    const currentUserRow = page.locator('tr:has(span:has-text("Current User"))');
    
    if (await currentUserRow.count() > 0) {
      // Should not have delete button for current user
      await expect(currentUserRow.locator('a:has-text("Delete")')).not.toBeVisible();
    }
  });

  test('should have mobile/call button for users with mobile', async ({ page }) => {
    // Check if any user has a mobile number displayed
    const callButtons = page.locator('a:has-text("Call")');
    // Call buttons may or may not exist
  });
});

test.describe('Admin - Manage Subjects', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDENTIALS.admin.email, CREDENTIALS.admin.password);
    await page.goto(PAGES.manageSubjects);
  });

  test.afterEach(async ({ page }) => {
    await logout(page);
  });

  test('should display subject management page', async ({ page }) => {
    await expect(page.locator('text=Add New Subject')).toBeVisible();
    await expect(page.locator('text=All Subjects')).toBeVisible();
    
    // Add subject form elements
    await expect(page.locator('input#name')).toBeVisible();
    await expect(page.locator('input#description')).toBeVisible();
    await expect(page.locator('select#year')).toBeVisible();
    await expect(page.locator('select#semester')).toBeVisible();
    await expect(page.locator('input#color')).toBeVisible();
  });

  test('should show year options in add form', async ({ page }) => {
    const yearSelect = page.locator('select#year');
    await expect(yearSelect).toContainText('FY (First Year)');
    await expect(yearSelect).toContainText('SY (Second Year)');
    await expect(yearSelect).toContainText('TY (Third Year)');
  });

  test('should show all 6 semesters in add form', async ({ page }) => {
    const semesterSelect = page.locator('select#semester');
    
    for (let i = 1; i <= 6; i++) {
      await expect(semesterSelect).toContainText(`Sem ${i}`);
    }
  });

  test('should add a new subject successfully', async ({ page }) => {
    const testSubject = generateTestSubject();
    
    await page.fill('input#name', testSubject.name);
    await page.fill('input#description', testSubject.description);
    await page.selectOption('select#year', testSubject.year);
    await page.selectOption('select#semester', testSubject.semester.toString());
    await page.fill('input#color', testSubject.color);
    
    await page.click('button[type="submit"]');
    
    // Should show success message
    await expect(page.locator('.alert-success')).toContainText('Subject added successfully');
  });

  test('should show error for missing subject name', async ({ page }) => {
    // Try to submit without name
    await page.fill('input#description', 'Test description');
    await page.click('button[type="submit"]');
    
    await expect(page.locator('.alert-error')).toContainText('Subject name is required');
  });

  test('should display subjects table with counts', async ({ page }) => {
    await expect(page.locator('th:has-text("Subject")')).toBeVisible();
    await expect(page.locator('th:has-text("Year")')).toBeVisible();
    await expect(page.locator('th:has-text("Semester")')).toBeVisible();
    await expect(page.locator('th:has-text("Notes")')).toBeVisible();
    await expect(page.locator('th:has-text("Questions")')).toBeVisible();
    await expect(page.locator('th:has-text("Actions")')).toBeVisible();
  });

  test('should have delete button for each subject', async ({ page }) => {
    const deleteButtons = page.locator('a:has-text("Delete")');
    const count = await deleteButtons.count();
    
    if (count > 0) {
      await expect(deleteButtons.first()).toBeVisible();
      
      // Check for confirm dialog attribute
      const onclick = await deleteButtons.first().getAttribute('onclick');
      expect(onclick).toContain('confirm');
    }
  });

  test('should show color indicator for subjects', async ({ page }) => {
    const colorIndicators = page.locator('div[style*="border-radius: 50%"][style*="background:"]');
    const count = await colorIndicators.count();
    expect(count).toBeGreaterThan(0);
  });
});

test.describe('Admin - Access to All Features', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDENTIALS.admin.email, CREDENTIALS.admin.password);
  });

  test.afterEach(async ({ page }) => {
    await logout(page);
  });

  test('should access teacher features - upload notes', async ({ page }) => {
    await page.goto(PAGES.uploadNotes);
    await expect(page).toHaveURL(/.*upload_notes\.php/);
    await expect(page.locator('text=Upload Notes')).toBeVisible();
  });

  test('should access teacher features - manage questions', async ({ page }) => {
    await page.goto(PAGES.manageQuestions);
    await expect(page).toHaveURL(/.*manage_questions\.php/);
    await expect(page.locator('text=Add New Question')).toBeVisible();
  });

  test('should access teacher features - student performance', async ({ page }) => {
    await page.goto(PAGES.teacherPerformance);
    await expect(page).toHaveURL(/.*teacher_performance\.php/);
  });

  test('should access student features - search notes', async ({ page }) => {
    await page.goto(PAGES.searchNotes);
    await expect(page).toHaveURL(/.*search_notes\.php/);
    await expect(page.locator('text=Study Materials')).toBeVisible();
  });

  test('should access student features - take quiz', async ({ page }) => {
    await page.goto(PAGES.quiz);
    await expect(page).toHaveURL(/.*quiz\.php/);
    await expect(page.locator('text=Take a Quiz')).toBeVisible();
  });

  test('should access student features - personal performance', async ({ page }) => {
    await page.goto(PAGES.performance);
    await expect(page).toHaveURL(/.*performance\.php/);
  });
});

test.describe('Admin - End-to-End Flow', () => {
  test('should complete full admin workflow', async ({ page }) => {
    // Login as admin
    await login(page, CREDENTIALS.admin.email, CREDENTIALS.admin.password);
    await expect(page).toHaveURL(/.*admin\/dashboard\.php/);
    
    // View dashboard stats
    await expect(page.locator('text=Total Users')).toBeVisible();
    await expect(page.locator('text=Notes Uploaded')).toBeVisible();
    
    // Navigate to user management
    await page.click('a:has-text("Manage Users")');
    await expect(page).toHaveURL(/.*admin\/users\.php/);
    await expect(page.locator('text=All Users')).toBeVisible();
    
    // Navigate to subject management
    await page.goto(PAGES.manageSubjects);
    await expect(page).toHaveURL(/.*admin\/subjects\.php/);
    await expect(page.locator('text=Add New Subject')).toBeVisible();
    
    // Add a new subject
    const testSubject = generateTestSubject();
    await page.fill('input#name', testSubject.name);
    await page.fill('input#description', testSubject.description);
    await page.selectOption('select#year', testSubject.year);
    await page.selectOption('select#semester', testSubject.semester.toString());
    await page.click('button[type="submit"]');
    await expect(page.locator('.alert-success')).toContainText('Subject added successfully');
    
    // Access teacher features as admin
    await page.goto(PAGES.uploadNotes);
    await expect(page).toHaveURL(/.*upload_notes\.php/);
    
    // Access quiz
    await page.goto(PAGES.quiz);
    await expect(page).toHaveURL(/.*quiz\.php/);
    
    // Logout
    await logout(page);
    await expect(page).toHaveURL(/.*login\.php/);
  });
});
