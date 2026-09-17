import { test, expect } from '@playwright/test';

test.describe('LossLedgerResource E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/user.json' });

  test('can render loss-ledger index page', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/loss-ledgers');
    await expect(page.getByRole('heading', { name: 'Loss Ledgers' })).toBeVisible();
  });
});