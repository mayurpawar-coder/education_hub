const { test, expect } = require('@playwright/test');

// Helper: login by filling auth form
async function login(page, email, password) {
  await page.goto('/auth/login.php');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await Promise.all([
    page.click('button:has-text("Sign In")'),
    page.waitForLoadState('networkidle')
  ]);
}

test.describe('Education Hub E2E', () => {

  test('Admin can view admin dashboard and recent users', async ({ page }) => {
    await login(page, 'admin@educationhub.com', 'password123');
    // Expect admin dashboard title
    await expect(page.locator('h1')).toHaveText(/Admin Dashboard/i);
    // Navigate to Manage Users
    await page.click('text=Manage Users');
    await expect(page).toHaveURL(/admin\/users.php/);
    await expect(page.locator('h3.card-title')).toHaveText(/Users|Manage Users/i);
  });

  test('Teacher can upload, edit, and delete a note', async ({ page }) => {
    await login(page, 'teacher@test.com', 'password123');

    // Open Notes Management and click Upload Notes tab
    await page.click('text=Notes Management');
    await page.goto('/upload_notes.php');

    const title = 'E2E Test Note ' + Date.now();

    // Fill upload form
    await page.fill('#title', title);
    // Choose first non-empty subject option
    const opt = await page.locator('#subject_id option:not([value=""])').first();
    const value = await opt.getAttribute('value');
    await page.selectOption('#subject_id', value);
    await page.fill('#content', 'Automated test upload');
    // Attach file
    await page.setInputFiles('input[type=file]', 'tests/assets/dummy.pdf');
    await Promise.all([
      page.click('.btn-upload'),
      page.waitForNavigation({ waitUntil: 'networkidle' })
    ]);

    await expect(page.locator('text=Note uploaded successfully')).toBeVisible({ timeout: 5000 });

    // Go to manage_notes and find the uploaded note
    await page.goto('/manage_notes.php');
    await expect(page.locator('text=' + title)).toBeVisible();

    // Click Edit
    await page.click(`a:has-text("Edit"):near(:text("${title}"))`);
    await page.waitForLoadState('networkidle');
    await page.fill('#title', title + ' - edited');
    await Promise.all([
      page.click('.btn-upload'),
      page.waitForNavigation({ waitUntil: 'networkidle' })
    ]);
    await expect(page.locator('text=Note updated successfully')).toBeVisible();

    // Delete the note from manage_notes
    await page.goto('/manage_notes.php');
    // Find the row containing edited title and click Delete button
    const row = page.locator('tr', { hasText: title + ' - edited' }).first();
    await row.locator('button:has-text("Delete")').click();
    // Confirm page shows success
    await expect(page.locator('text=Note deleted successfully')).toBeVisible();
  });

  test('Student can search notes', async ({ page }) => {
    await login(page, 'raj@test.com', 'password123');
    await page.goto('/search_notes.php');
    await page.fill('input[name="search"]', 'C Programming');
    await Promise.all([
      page.click('button:has-text("Search")'),
      page.waitForLoadState('networkidle')
    ]);
    // Expect at least one note card with matching text
    await expect(page.locator('.note-card')).toHaveCountGreaterThan(0);
  });

});
