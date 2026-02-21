const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './',
  timeout: 60 * 1000,
  expect: { timeout: 5000 },
  use: {
    headless: true,
    baseURL: process.env.BASE_URL || 'http://localhost/education_hub%20-%20Copy',
    viewport: { width: 1280, height: 800 }
  }
});
