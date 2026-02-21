/**
 * Debug test to verify application accessibility
 */

import { test, expect } from '@playwright/test';

test('debug - check login page load', async ({ page }) => {
  // Try loading the page with the Copy folder
  const response = await page.goto('http://localhost/education_hub%20-%20Copy/auth/login.php', { 
    waitUntil: 'networkidle',
    timeout: 10000 
  });
  
  console.log('Response status:', response?.status());
  console.log('Final URL:', page.url());
  
  // Wait for any content to load
  await page.waitForTimeout(2000);
  
  // Take screenshot to debug
  await page.screenshot({ path: 'debug-login.png', fullPage: true });
  
  // Get page content
  const title = await page.title();
  console.log('Page title:', title);
  
  // Check body text
  const bodyText = await page.locator('body').textContent();
  console.log('Body starts with:', bodyText?.substring(0, 200));
});

test('debug - check if education_hub works', async ({ page }) => {
  const response = await page.goto('http://localhost/education_hub/auth/login.php', { 
    waitUntil: 'networkidle',
    timeout: 10000 
  });
  
  console.log('Response status:', response?.status());
  console.log('Final URL:', page.url());
  console.log('Page title:', await page.title());
  
  await page.screenshot({ path: 'debug-education-hub.png', fullPage: true });
});
