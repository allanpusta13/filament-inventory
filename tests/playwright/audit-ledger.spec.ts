import { test, expect } from '@playwright/test';

test.describe('Audit Ledger E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/admin.json' });

  test('can view stock movements ledger', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/stock-movements');
    await expect(page.getByRole('heading', { name: 'Stock Movements' })).toBeVisible();
    // Table should be read-only
    await expect(page.getByRole('button', { name: 'Create' })).not.toBeVisible();
    await expect(page.getByRole('button', { name: 'Edit' })).not.toBeVisible();
    await expect(page.getByRole('button', { name: 'Delete' })).not.toBeVisible();
    // Check column headers
    await expect(page.getByText('Type')).toBeVisible();
    await expect(page.getByText('Warehouse')).toBeVisible();
    await expect(page.getByText('Variant')).toBeVisible();
  });

  test('can view loss ledger', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/loss-ledgers');
    await expect(page.getByRole('heading', { name: 'Loss Ledger' })).toBeVisible();
    // Table should be read-only
    await expect(page.getByRole('button', { name: 'Create' })).not.toBeVisible();
    // Check column headers
    await expect(page.getByText('Variant')).toBeVisible();
    await expect(page.getByText('Quantity Lost')).toBeVisible();
  });

  test('can view in-transits ledger', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/in-transits');
    await expect(page.getByRole('heading', { name: 'In Transits' })).toBeVisible();
    // Table should be read-only
    await expect(page.getByRole('button', { name: 'Create' })).not.toBeVisible();
    // Check column headers
    await expect(page.getByText('Reference Code')).toBeVisible();
  });

  test('RBAC: warehouse staff cannot access audit resources', async ({ page }) => {
    // First login as warehouse staff
    await page.goto('http://127.0.0.1:8000/admin/login');
    await page.fill('input[name="email"]', 'warehouse@test.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin', { timeout: 30000 });

    // Try to access stock movements
    await page.goto('http://127.0.0.1:8000/admin/stock-movements');
    // Should be redirected or see access denied
    const url = page.url();
    expect(url).not.toContain('/admin/stock-movements') || page.getByText('Access Denied').toBeVisible();
  });
});
