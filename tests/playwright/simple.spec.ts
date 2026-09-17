import { test, expect } from '@playwright/test';

test.describe.configure({ retries: 0 });
test.use({ storageState: undefined });

test('simple test', async ({ page }) => {
  await page.goto('http://127.0.0.1:8000/admin/login');
  await expect(page.locator('input[id="form.email"]')).toBeVisible();
});
