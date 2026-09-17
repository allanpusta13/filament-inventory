import { test, expect } from '@playwright/test';

test.describe('TransferRequisitionResource E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/user.json' });

  test('can render transfer-requisition index page', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/transfer-requisitions');
    await expect(page.getByRole('heading', { name: 'Transfer Requisitions' })).toBeVisible();
  });
});