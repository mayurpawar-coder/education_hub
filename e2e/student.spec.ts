/**
 * Student Role E2E Tests for Education Hub
 * 
 * Tests all features accessible to students:
 * - Dashboard navigation
 * - Search and download notes
 * - Take quizzes
 * - View performance
 * - Profile management
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

test.describe('Student - Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDENTIALS.student.email, CREDENTIALS.student.password);
  });

  test.afterEach(async ({ page }) => {
    await logout(page);
  });

  test('should display dashboard with correct elements', async ({ page }) => {
    await expect(page).toHaveURL(/.*dashboard\.php/);
    
    // Verify stat cards
    await expect(page.locator(SELECTORS.dashboard.statCards).first()).toBeVisible();
    await expect(page.locator('text=Quizzes Taken')).toBeVisible();
    await expect(page.locator('text=Average Score')).toBeVisible();
    await expect(page.locator('text=Notes Available')).toBeVisible();
    await expect(page.locator('text=Subjects Studied')).toBeVisible();
    
    // Verify quick actions
    await expect(page.locator('text=Search Notes')).toBeVisible();
    await expect(page.locator('text=Take Quiz')).toBeVisible();
    await expect(page.locator('text=View Performance')).toBeVisible();
    
    // Student should NOT see upload button
    await expect(page.locator('text=Upload Notes')).not.toBeVisible();
  });

  test('should navigate to all sidebar links', async ({ page }) => {
    // Dashboard
    await page.click(SELECTORS.sidebar.dashboardLink);
    await expect(page).toHaveURL(/.*dashboard\.php/);
    
    // Search Notes
    await page.click(SELECTORS.sidebar.searchNotesLink);
    await expect(page).toHaveURL(/.*search_notes\.php/);
    
    // Quiz
    await page.click(SELECTORS.sidebar.quizLink);
    await expect(page).toHaveURL(/.*quiz\.php/);
    
    // Performance
    await page.click(SELECTORS.sidebar.performanceLink);
    await expect(page).toHaveURL(/.*performance\.php/);
  });

  test('should navigate via quick action buttons', async ({ page }) => {
    // Search Notes button
    await page.click('a:has-text("Search Notes")');
    await expect(page).toHaveURL(/.*search_notes\.php/);
    
    // Go back and click Take Quiz
    await page.goto(PAGES.dashboard);
    await page.click('a:has-text("Take Quiz")');
    await expect(page).toHaveURL(/.*quiz\.php/);
    
    // Go back and click View Performance
    await page.goto(PAGES.dashboard);
    await page.click('a:has-text("View Performance")');
    await expect(page).toHaveURL(/.*performance\.php/);
  });
});

test.describe('Student - Search Notes', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDENTIALS.student.email, CREDENTIALS.student.password);
    await page.goto(PAGES.searchNotes);
  });

  test.afterEach(async ({ page }) => {
    await logout(page);
  });

  test('should display search notes page correctly', async ({ page }) => {
    await expect(page.locator('h1')).toContainText('Study Materials');
    await expect(page.locator('.year-tabs')).toBeVisible();
    await expect(page.locator('.modern-search')).toBeVisible();
    await expect(page.locator('.filter-select')).toBeVisible();
  });

  test('should filter notes by year tabs', async ({ page }) => {
    // Click FY tab
    await page.click('.year-tab:has-text("First Year")');
    await expect(page).toHaveURL(/.*year=FY/);
    
    // Semester tabs should appear
    await expect(page.locator('.semester-tabs')).toBeVisible();
    
    // Click SY tab
    await page.click('.year-tab:has-text("Second Year")');
    await expect(page).toHaveURL(/.*year=SY/);
    
    // Click TY tab
    await page.click('.year-tab:has-text("Third Year")');
    await expect(page).toHaveURL(/.*year=TY/);
  });

  test('should filter notes by semester', async ({ page }) => {
    // Select FY first
    await page.click('.year-tab:has-text("First Year")');
    await page.waitForSelector('.semester-tabs');
    
    // Click Semester 1
    await page.click('.semester-tab:has-text("Semester 1")');
    await expect(page).toHaveURL(/.*semester=1/);
    
    // Click Semester 2
    await page.click('.semester-tab:has-text("Semester 2")');
    await expect(page).toHaveURL(/.*semester=2/);
  });

  test('should search notes by keyword', async ({ page }) => {
    const searchTerm = 'test';
    await page.fill('.search-input', searchTerm);
    await page.click('button[type="submit"]');
    
    await expect(page).toHaveURL(new RegExp(`.*search=${searchTerm}`));
  });

  test('should filter by subject dropdown', async ({ page }) => {
    // Get first available subject option
    const options = await page.locator('select[name="subject"] option').all();
    if (options.length > 1) {
      await page.selectOption('select[name="subject"]', { index: 1 });
      await page.click('button[type="submit"]');
      await expect(page).toHaveURL(/.*subject=/);
    }
  });

  test('should download a note', async ({ page }) => {
    // Look for download button
    const downloadButton = page.locator('.btn-download').first();
    
    if (await downloadButton.isVisible().catch(() => false)) {
      // Wait for download to start
      const [download] = await Promise.all([
        page.waitForEvent('download'),
        downloadButton.click()
      ]);
      
      expect(download.suggestedFilename()).toBeTruthy();
    }
  });

  test('should show empty state when no notes found', async ({ page }) => {
    // Search for non-existent term
    await page.fill('.search-input', 'xyznonexistent123');
    await page.click('button[type="submit"]');
    
    await expect(page.locator('.empty-state')).toBeVisible();
    await expect(page.locator('text=No notes found')).toBeVisible();
  });
});

test.describe('Student - Quiz', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDENTIALS.student.email, CREDENTIALS.student.password);
    await page.goto(PAGES.quiz);
  });

  test.afterEach(async ({ page }) => {
    await logout(page);
  });

  test('should display quiz page with subject selection', async ({ page }) => {
    await expect(page.locator('h1')).toContainText('Take a Quiz');
    await expect(page.locator('.year-tabs')).toBeVisible();
    await expect(page.locator('.subject-selection-grid')).toBeVisible();
  });

  test('should filter subjects by year', async ({ page }) => {
    await page.click('.year-tab:has-text("First Year")');
    await expect(page).toHaveURL(/.*year=FY/);
    
    // Check if semester tabs appear
    await expect(page.locator('.semester-tabs')).toBeVisible();
  });

  test('should start quiz for subject with questions', async ({ page }) => {
    // Find a subject card that is not disabled and has questions
    const subjectCards = page.locator('.quiz-subject-card:not(.disabled)');
    const count = await subjectCards.count();
    
    if (count > 0) {
      await subjectCards.first().click();
      
      // Should be on quiz taking page
      await expect(page.locator('.quiz-question-card')).toBeVisible();
      await expect(page.locator('.quiz-progress-container')).toBeVisible();
    }
  });

  test('should complete quiz and show results', async ({ page }) => {
    // Find a subject with questions
    const subjectCards = page.locator('.quiz-subject-card:not(.disabled)');
    
    if (await subjectCards.first().isVisible().catch(() => false)) {
      await subjectCards.first().click();
      
      // Wait for quiz form
      await page.waitForSelector('#quizForm');
      
      // Answer all questions (select first option for each)
      const questions = await page.locator('.quiz-question-card').count();
      
      for (let i = 0; i < questions; i++) {
        await page.locator('.quiz-option input[type="radio"]').nth(i).click();
      }
      
      // Submit quiz
      await page.click('button[name="submit_quiz"]');
      
      // Should show results
      await expect(page.locator('.quiz-results-container')).toBeVisible();
      await expect(page.locator('.score-circle')).toBeVisible();
      await expect(page.locator('text=Review Your Answers')).toBeVisible();
    }
  });

  test('should show progress bar updates', async ({ page }) => {
    const subjectCards = page.locator('.quiz-subject-card:not(.disabled)');
    
    if (await subjectCards.first().isVisible().catch(() => false)) {
      await subjectCards.first().click();
      await page.waitForSelector('#quizForm');
      
      // Check initial progress
      await expect(page.locator('#answered')).toHaveText('0');
      
      // Answer first question
      await page.locator('.quiz-option input[type="radio"]').first().click();
      await expect(page.locator('#answered')).toHaveText('1');
    }
  });

  test('should navigate back to subject selection after quiz', async ({ page }) => {
    const subjectCards = page.locator('.quiz-subject-card:not(.disabled)');
    
    if (await subjectCards.first().isVisible().catch(() => false)) {
      await subjectCards.first().click();
      await page.waitForSelector('#quizForm');
      
      // Answer and submit
      const questions = await page.locator('.quiz-option input[type="radio"]').count();
      for (let i = 0; i < Math.min(questions, 2); i++) {
        await page.locator('.quiz-option input[type="radio"]').nth(i).click();
      }
      
      await page.click('button[name="submit_quiz"]');
      await page.waitForSelector('.quiz-results-container');
      
      // Click "Take Another Quiz"
      await page.click('a:has-text("Take Another Quiz")');
      await expect(page.locator('h1')).toContainText('Take a Quiz');
    }
  });
});

test.describe('Student - Performance', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDENTIALS.student.email, CREDENTIALS.student.password);
    await page.goto(PAGES.performance);
  });

  test.afterEach(async ({ page }) => {
    await logout(page);
  });

  test('should display performance page correctly', async ({ page }) => {
    await expect(page.locator('text=My Performance')).toBeVisible();
    
    // Verify stat cards
    await expect(page.locator('.stat-card:has-text("Total Quizzes")')).toBeVisible();
    await expect(page.locator('.stat-card:has-text("Overall Accuracy")')).toBeVisible();
    await expect(page.locator('.stat-card:has-text("Subjects Studied")')).toBeVisible();
  });

  test('should display quiz history table if quizzes exist', async ({ page }) => {
    const tableExists = await page.locator('.card:has-text("Quiz History") table').count() > 0;
    
    if (tableExists) {
      await expect(page.locator('th:has-text("Date")')).toBeVisible();
      await expect(page.locator('th:has-text("Subject")')).toBeVisible();
      await expect(page.locator('th:has-text("Score")')).toBeVisible();
      await expect(page.locator('th:has-text("Status")')).toBeVisible();
    } else {
      await expect(page.locator('text=No quiz history yet')).toBeVisible();
    }
  });

  test('should show subject-wise performance if data exists', async ({ page }) => {
    const subjectPerformance = await page.locator('.card:has-text("Performance by Subject")').count();
    
    if (subjectPerformance > 0) {
      await expect(page.locator('.card:has-text("Performance by Subject")')).toBeVisible();
    }
  });
});

test.describe('Student - Profile', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDENTIALS.student.email, CREDENTIALS.student.password);
  });

  test.afterEach(async ({ page }) => {
    await logout(page);
  });

  test('should view profile page', async ({ page }) => {
    await page.goto(PAGES.profile);
    
    await expect(page.locator('h2')).toContainText('Profile');
    await expect(page.locator(`text=${CREDENTIALS.student.name}`)).toBeVisible();
    await expect(page.locator(`text=${CREDENTIALS.student.email}`)).toBeVisible();
    await expect(page.locator('text=student')).toBeVisible();
  });

  test('should navigate to edit profile', async ({ page }) => {
    await page.goto(PAGES.profile);
    await page.click('a:has-text("Edit Profile")');
    
    await expect(page).toHaveURL(/.*edit_profile\.php/);
    await expect(page.locator('h2')).toContainText('Edit Profile');
  });

  test('should edit profile information', async ({ page }) => {
    await page.goto(PAGES.editProfile);
    
    // Update name
    const newName = `Updated Student ${Date.now()}`;
    await page.fill('input#name', newName);
    await page.click('button[type="submit"]');
    
    // Should redirect back to profile
    await expect(page).toHaveURL(/.*profile\.php/);
    await expect(page.locator('.alert-success')).toContainText('updated successfully');
  });
});

test.describe('Student - End-to-End Flow', () => {
  test('should complete full student workflow', async ({ page }) => {
    // Login
    await login(page, CREDENTIALS.student.email, CREDENTIALS.student.password);
    
    // View dashboard
    await expect(page).toHaveURL(/.*dashboard\.php/);
    
    // Search for notes
    await page.click(SELECTORS.sidebar.searchNotesLink);
    await expect(page).toHaveURL(/.*search_notes\.php/);
    
    // Filter by FY
    await page.click('.year-tab:has-text("First Year")');
    await expect(page).toHaveURL(/.*year=FY/);
    
    // Take a quiz
    await page.click(SELECTORS.sidebar.quizLink);
    await expect(page).toHaveURL(/.*quiz\.php/);
    
    // Select subject if available
    const subjectCards = page.locator('.quiz-subject-card:not(.disabled)');
    if (await subjectCards.first().isVisible().catch(() => false)) {
      await subjectCards.first().click();
      await page.waitForSelector('#quizForm');
      
      // Answer some questions
      const radios = page.locator('.quiz-option input[type="radio"]');
      const count = await radios.count();
      for (let i = 0; i < Math.min(count, 3); i++) {
        await radios.nth(i).click();
      }
      
      // Submit
      await page.click('button[name="submit_quiz"]');
      await page.waitForSelector('.quiz-results-container');
    }
    
    // View performance
    await page.goto(PAGES.performance);
    await expect(page.locator('text=My Performance')).toBeVisible();
    
    // Logout
    await logout(page);
    await expect(page).toHaveURL(/.*login\.php/);
  });
});
