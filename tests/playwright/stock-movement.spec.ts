import { test, expect } from '@playwright/test';

test.describe('StockMovementResource E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/user.json' });

  test('can render stock-movement index page', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/stock-movements');
    await expect(page.getByRole('heading', { name: 'Stock Movements' })).toBeVisible();
  });
});