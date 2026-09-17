import { test, expect } from '@playwright/test';

test.describe.configure({ retries: 0 });

test('login test', async ({ page }) => {
  test.setTimeout(120000);
  console.log('Starting login test...');

  try {
    await page.goto('http://127.0.0.1:8000/admin/login', { waitUntil: 'networkidle', timeout: 30000 });
    console.log('Page loaded:', page.url());

    // Check if we're already on the login page
    const title = await page.title();
    console.log('Page title:', title);

    // Check for login form
    const emailInput = page.locator('input[name="email"]');
    await expect(emailInput).toBeVisible({ timeout: 10000 });
    console.log('Email input found');

    await page.fill('input[name="email"]', 'admin@test.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    console.log('Submitted login form');

    await page.waitForURL('**/admin', { timeout: 30000 });
    console.log('Redirected to admin:', page.url());

    await page.context().storageState({ path: './tests/playwright/.auth/user.json' });
    console.log('Storage state saved');
  } catch (error) {
    console.error('Error during login:', error);
    await page.screenshot({ path: './tests/playwright/login-error.png', fullPage: true });
    throw error;
  }
});