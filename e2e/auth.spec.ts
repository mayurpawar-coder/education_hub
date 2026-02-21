/**
 * Authentication E2E Tests for Education Hub
 * 
 * Tests login, logout, and registration flows for all user roles.
 */

import { test, expect } from '@playwright/test';
import { 
  CREDENTIALS, 
  PAGES, 
  SELECTORS, 
  generateTestUser, 
  login, 
  logout 
} from './utils';

/**
 * Test Suite 1: Login Flows
 * Tests login functionality for all user roles
 */
test.describe('Login Flows', () => {
  
  test.beforeEach(async ({ page }) => {
    // Clear any existing session before each test
    await page.context().clearCookies();
  });

  test('should display login page correctly', async ({ page }) => {
    await page.goto(PAGES.login);
    
    // Check page elements
    await expect(page.locator('h1')).toContainText('Education Hub');
    await expect(page.locator('text=Sign in to your account')).toBeVisible();
    await expect(page.locator(SELECTORS.login.email)).toBeVisible();
    await expect(page.locator(SELECTORS.login.password)).toBeVisible();
    await expect(page.locator(SELECTORS.login.submit)).toContainText('Sign In');
    await expect(page.locator(SELECTORS.login.registerLink)).toBeVisible();
  });

  test('should login as admin and redirect to admin dashboard', async ({ page }) => {
    await login(page, CREDENTIALS.admin.email, CREDENTIALS.admin.password);
    
    // Should redirect to admin dashboard
    await expect(page).toHaveURL(/.*admin\/dashboard\.php/);
    
    // Verify admin elements
    await expect(page.locator('text=Admin Dashboard')).toBeVisible();
    await expect(page.locator('text=Admin Panel')).toBeVisible();
    await expect(page.locator(SELECTORS.sidebar.manageUsersLink)).toBeVisible();
    await expect(page.locator(SELECTORS.sidebar.manageSubjectsLink)).toBeVisible();
  });

  test('should login as teacher and redirect to dashboard', async ({ page }) => {
    await login(page, CREDENTIALS.teacher.email, CREDENTIALS.teacher.password);
    
    // Should redirect to main dashboard
    await expect(page).toHaveURL(/.*dashboard\.php/);
    
    // Verify teacher elements
    await expect(page.locator('text=Teacher Panel')).toBeVisible();
    await expect(page.locator(SELECTORS.sidebar.notesManagementLink)).toBeVisible();
    await expect(page.locator(SELECTORS.sidebar.manageQuestionsLink)).toBeVisible();
    
    // Teacher should NOT see admin links
    await expect(page.locator(SELECTORS.sidebar.manageUsersLink)).not.toBeVisible();
    await expect(page.locator(SELECTORS.sidebar.manageSubjectsLink)).not.toBeVisible();
  });

  test('should login as student and redirect to dashboard', async ({ page }) => {
    await login(page, CREDENTIALS.student.email, CREDENTIALS.student.password);
    
    // Should redirect to main dashboard
    await expect(page).toHaveURL(/.*dashboard\.php/);
    
    // Verify student elements
    await expect(page.locator('text=Student Panel')).toBeVisible();
    await expect(page.locator('text=My Performance')).toBeVisible();
    
    // Student should NOT see teacher/admin links
    await expect(page.locator(SELECTORS.sidebar.notesManagementLink)).not.toBeVisible();
    await expect(page.locator(SELECTORS.sidebar.manageQuestionsLink)).not.toBeVisible();
    await expect(page.locator(SELECTORS.sidebar.manageUsersLink)).not.toBeVisible();
    await expect(page.locator(SELECTORS.sidebar.manageSubjectsLink)).not.toBeVisible();
  });

  test('should show error for invalid credentials', async ({ page }) => {
    await page.goto(PAGES.login);
    await page.fill(SELECTORS.login.email, 'invalid@example.com');
    await page.fill(SELECTORS.login.password, 'wrongpassword');
    await page.click(SELECTORS.login.submit);
    
    // Should show error message
    await expect(page.locator('.alert-error')).toContainText('Invalid email or password');
    
    // Should stay on login page
    await expect(page).toHaveURL(/.*login\.php/);
  });

  test('should show error for empty fields', async ({ page }) => {
    await page.goto(PAGES.login);
    await page.click(SELECTORS.login.submit);
    
    // HTML5 validation should prevent submission
    const emailInput = page.locator(SELECTORS.login.email);
    await expect(emailInput).toHaveAttribute('required', '');
  });
});

/**
 * Test Suite 2: Logout Flows
 * Tests logout functionality for all user roles
 */
test.describe('Logout Flows', () => {
  
  test('should logout as admin and redirect to login', async ({ page }) => {
    await login(page, CREDENTIALS.admin.email, CREDENTIALS.admin.password);
    await logout(page);
    
    // Should redirect to login page
    await expect(page).toHaveURL(/.*login\.php/);
    
    // Verify logged out state
    await expect(page.locator(SELECTORS.login.email)).toBeVisible();
  });

  test('should logout as teacher and redirect to login', async ({ page }) => {
    await login(page, CREDENTIALS.teacher.email, CREDENTIALS.teacher.password);
    await logout(page);
    
    await expect(page).toHaveURL(/.*login\.php/);
    await expect(page.locator(SELECTORS.login.email)).toBeVisible();
  });

  test('should logout as student and redirect to login', async ({ page }) => {
    await login(page, CREDENTIALS.student.email, CREDENTIALS.student.password);
    await logout(page);
    
    await expect(page).toHaveURL(/.*login\.php/);
    await expect(page.locator(SELECTORS.login.email)).toBeVisible();
  });
});

/**
 * Test Suite 3: Registration Flows
 * Tests user registration for students and teachers
 */
test.describe('Registration Flows', () => {
  
  test.beforeEach(async ({ page }) => {
    await page.context().clearCookies();
    await page.goto(PAGES.register);
  });

  test('should display registration page correctly', async ({ page }) => {
    await expect(page.locator('h1')).toContainText('Education Hub');
    await expect(page.locator('text=Create your account')).toBeVisible();
    await expect(page.locator(SELECTORS.register.name)).toBeVisible();
    await expect(page.locator(SELECTORS.register.email)).toBeVisible();
    await expect(page.locator(SELECTORS.register.role)).toBeVisible();
    await expect(page.locator(SELECTORS.register.password)).toBeVisible();
    await expect(page.locator(SELECTORS.register.confirmPassword)).toBeVisible();
    await expect(page.locator(SELECTORS.register.submit)).toContainText('Create Account');
  });

  test('should register a new student successfully', async ({ page }) => {
    const testUser = generateTestUser('student');
    
    await page.fill(SELECTORS.register.name, testUser.name);
    await page.fill(SELECTORS.register.email, testUser.email);
    await page.fill(SELECTORS.register.mobile, testUser.mobile);
    await page.selectOption(SELECTORS.register.role, 'student');
    await page.fill(SELECTORS.register.password, testUser.password);
    await page.fill(SELECTORS.register.confirmPassword, testUser.password);
    
    await page.click(SELECTORS.register.submit);
    
    // Should show success message
    await expect(page.locator('.alert-success')).toContainText('Registration successful');
    
    // Should redirect to login after 2 seconds
    await page.waitForURL(/.*login\.php/, { timeout: 5000 });
  });

  test('should register a new teacher successfully', async ({ page }) => {
    const testUser = generateTestUser('teacher');
    
    await page.fill(SELECTORS.register.name, testUser.name);
    await page.fill(SELECTORS.register.email, testUser.email);
    await page.fill(SELECTORS.register.mobile, testUser.mobile);
    await page.selectOption(SELECTORS.register.role, 'teacher');
    await page.fill(SELECTORS.register.password, testUser.password);
    await page.fill(SELECTORS.register.confirmPassword, testUser.password);
    
    await page.click(SELECTORS.register.submit);
    
    // Should show success message
    await expect(page.locator('.alert-success')).toContainText('Registration successful');
  });

  test('should show error for mismatched passwords', async ({ page }) => {
    const testUser = generateTestUser('student');
    
    await page.fill(SELECTORS.register.name, testUser.name);
    await page.fill(SELECTORS.register.email, testUser.email);
    await page.selectOption(SELECTORS.register.role, 'student');
    await page.fill(SELECTORS.register.password, testUser.password);
    await page.fill(SELECTORS.register.confirmPassword, 'DifferentPassword123');
    
    await page.click(SELECTORS.register.submit);
    
    await expect(page.locator('.alert-error')).toContainText('Passwords do not match');
  });

  test('should show error for short password', async ({ page }) => {
    const testUser = generateTestUser('student');
    
    await page.fill(SELECTORS.register.name, testUser.name);
    await page.fill(SELECTORS.register.email, testUser.email);
    await page.selectOption(SELECTORS.register.role, 'student');
    await page.fill(SELECTORS.register.password, '12345');
    await page.fill(SELECTORS.register.confirmPassword, '12345');
    
    await page.click(SELECTORS.register.submit);
    
    await expect(page.locator('.alert-error')).toContainText('at least 6 characters');
  });

  test('should show error for duplicate email', async ({ page }) => {
    // Try to register with existing email
    await page.fill(SELECTORS.register.name, 'Test User');
    await page.fill(SELECTORS.register.email, CREDENTIALS.student.email);
    await page.selectOption(SELECTORS.register.role, 'student');
    await page.fill(SELECTORS.register.password, 'TestPassword123');
    await page.fill(SELECTORS.register.confirmPassword, 'TestPassword123');
    
    await page.click(SELECTORS.register.submit);
    
    await expect(page.locator('.alert-error')).toContainText('Email already registered');
  });

  test('should show error for invalid email format', async ({ page }) => {
    await page.fill(SELECTORS.register.name, 'Test User');
    await page.fill(SELECTORS.register.email, 'invalid-email');
    await page.selectOption(SELECTORS.register.role, 'student');
    await page.fill(SELECTORS.register.password, 'TestPassword123');
    await page.fill(SELECTORS.register.confirmPassword, 'TestPassword123');
    
    await page.click(SELECTORS.register.submit);
    
    await expect(page.locator('.alert-error')).toContainText('valid email address');
  });

  test('should navigate from register to login page', async ({ page }) => {
    await page.click(SELECTORS.register.loginLink);
    await expect(page).toHaveURL(/.*login\.php/);
  });

  test('should navigate from login to register page', async ({ page }) => {
    await page.goto(PAGES.login);
    await page.click(SELECTORS.login.registerLink);
    await expect(page).toHaveURL(/.*register\.php/);
  });
});

/**
 * Test Suite 4: Access Control
 * Tests role-based access restrictions
 */
test.describe('Access Control', () => {
  
  test('should redirect unauthenticated users to login', async ({ page }) => {
    const protectedPages = [
      PAGES.dashboard,
      PAGES.searchNotes,
      PAGES.quiz,
      PAGES.performance,
      PAGES.uploadNotes,
      PAGES.manageQuestions,
      PAGES.adminDashboard,
      PAGES.manageUsers,
      PAGES.manageSubjects
    ];
    
    for (const protectedPage of protectedPages) {
      await page.goto(protectedPage);
      await expect(page).toHaveURL(/.*login\.php/);
    }
  });

  test('should prevent student from accessing teacher pages', async ({ page }) => {
    await login(page, CREDENTIALS.student.email, CREDENTIALS.student.password);
    
    // Try to access teacher-only pages
    await page.goto(PAGES.uploadNotes);
    await expect(page).not.toHaveURL(/.*upload_notes\.php/);
    
    await page.goto(PAGES.manageQuestions);
    await expect(page).not.toHaveURL(/.*manage_questions\.php/);
  });

  test('should prevent student from accessing admin pages', async ({ page }) => {
    await login(page, CREDENTIALS.student.email, CREDENTIALS.student.password);
    
    // Try to access admin-only pages
    await page.goto(PAGES.adminDashboard);
    await expect(page).not.toHaveURL(/.*admin\/dashboard\.php/);
    
    await page.goto(PAGES.manageUsers);
    await expect(page).not.toHaveURL(/.*admin\/users\.php/);
    
    await page.goto(PAGES.manageSubjects);
    await expect(page).not.toHaveURL(/.*admin\/subjects\.php/);
  });

  test('should prevent teacher from accessing admin pages', async ({ page }) => {
    await login(page, CREDENTIALS.teacher.email, CREDENTIALS.teacher.password);
    
    // Try to access admin-only pages
    await page.goto(PAGES.adminDashboard);
    await expect(page).not.toHaveURL(/.*admin\/dashboard\.php/);
    
    await page.goto(PAGES.manageUsers);
    await expect(page).not.toHaveURL(/.*admin\/users\.php/);
  });

  test('should allow admin to access all pages', async ({ page }) => {
    await login(page, CREDENTIALS.admin.email, CREDENTIALS.admin.password);
    
    // Admin can access teacher pages
    await page.goto(PAGES.uploadNotes);
    await expect(page).toHaveURL(/.*upload_notes\.php/);
    
    await page.goto(PAGES.manageQuestions);
    await expect(page).toHaveURL(/.*manage_questions\.php/);
    
    // Admin can access all admin pages
    await page.goto(PAGES.adminDashboard);
    await expect(page).toHaveURL(/.*admin\/dashboard\.php/);
  });
});
