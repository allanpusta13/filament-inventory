import { test, expect } from '@playwright/test';

test.describe.configure({ retries: 0 });
test.use({ storageState: undefined });

test('setup auth state', async ({ page }) => {
  await page.goto('http://127.0.0.1:8000/admin/login');
  await page.fill('input[id="form.email"]', 'admin@example.com');
  await page.fill('input[id="form.password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin*', { timeout: 30000 });
  console.log('URL after login:', page.url());
  await page.context().storageState({ path: './tests/playwright/.auth/user.json' });
});
