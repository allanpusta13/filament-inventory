import { test, expect } from '@playwright/test';

test.describe('Authorization Bypass E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/user.json' });

  test('can render dashboard page', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin');
    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
  });
});