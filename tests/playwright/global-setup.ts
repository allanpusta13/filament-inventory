import { test as setup, expect } from '@playwright/test';

setup('authenticate as admin', async ({ page }) => {
  // Navigate to login page
  await page.goto('http://filament-inventory.test/login');

  // Fill login form
  await page.fill('input[name="email"]', 'admin@test.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');

  // Wait for navigation to admin panel
  await page.waitForURL('**/admin');

  // Save storage state
  await page.context().storageState({ path: './tests/playwright/.auth/user.json' });
});

setup('seed database', async ({ request }) => {
  // Use API or artisan command to seed database
  // This assumes we have an endpoint or can run artisan commands
  // For now, we'll rely on the fact that tests use RefreshDatabase
});
