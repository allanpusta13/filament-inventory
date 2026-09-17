import { test, expect } from '@playwright/test';

test.describe('SoftDeleteGuard E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/user.json' });

  test('can render product index page', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/products');
    await expect(page.getByRole('heading', { name: 'Product Variants' })).toBeVisible();
  });
});