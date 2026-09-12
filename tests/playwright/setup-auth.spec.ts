import { test, expect } from '@playwright/test';

test.describe.configure({ retries: 0 });

test('setup auth state', async ({ page }) => {
  await page.goto('http://filament-inventory.test/login');
  await page.fill('input[name="email"]', 'admin@test.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin');
  await page.context().storageState({ path: './tests/playwright/.auth/user.json' });
});