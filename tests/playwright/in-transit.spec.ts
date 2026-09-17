import { test, expect } from '@playwright/test';

test.describe('InTransitResource E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/user.json' });

  test('can render in-transit index page', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/in-transits');
    await expect(page.getByRole('heading', { name: 'In Transits' })).toBeVisible();
  });
});