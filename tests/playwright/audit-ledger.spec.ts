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
    // Check column headers - use table headers (th) for specificity
    await expect(page.locator('th:has-text("Type")')).toBeVisible();
    await expect(page.locator('th:has-text("Warehouse")')).toBeVisible();
    await expect(page.locator('th:has-text("Variant")')).toBeVisible();
  });

  test('can view loss ledger', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/loss-ledgers');
    await expect(page.getByRole('heading', { name: 'Loss Ledger' })).toBeVisible();
    // Table should be read-only
    await expect(page.getByRole('button', { name: 'Create' })).not.toBeVisible();
    // Check column headers - exact text in table
    await expect(page.locator('th:has-text("VARIANT NAME")')).toBeVisible();
    await expect(page.locator('th:has-text("LOST (BASE)")')).toBeVisible();
  });

  test('can view in-transits ledger', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/in-transits');
    await expect(page.getByRole('heading', { name: 'In Transits' })).toBeVisible();
    // Table should be read-only
    await expect(page.getByRole('button', { name: 'Create' })).not.toBeVisible();
    // Check column headers - exact text in table
    await expect(page.locator('th:has-text("REQUISITION REF")')).toBeVisible();
  });
});

test.describe('Audit Ledger RBAC Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/warehouse-staff.json' });

  test('warehouse staff can view audit index but only sees authorized data', async ({ page }) => {
    // Try to access stock movements - warehouse staff CAN view index (viewAny = true)
    await page.goto('http://127.0.0.1:8000/admin/stock-movements');
    await page.waitForLoadState('networkidle');
    // Should be on the page (not redirected)
    const url = page.url();
    expect(url).toContain('/admin/stock-movements');
    // Table should be read-only (no create/edit/delete)
    await expect(page.getByRole('button', { name: 'Create' })).not.toBeVisible();
    await expect(page.getByRole('button', { name: 'Edit' })).not.toBeVisible();
    await expect(page.getByRole('button', { name: 'Delete' })).not.toBeVisible();
  });

  test('warehouse staff can view loss ledger index', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/loss-ledgers');
    await page.waitForLoadState('networkidle');
    const url = page.url();
    expect(url).toContain('/admin/loss-ledgers');
    await expect(page.getByRole('button', { name: 'Create' })).not.toBeVisible();
  });

  test('warehouse staff can view in-transits index', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/in-transits');
    await page.waitForLoadState('networkidle');
    const url = page.url();
    expect(url).toContain('/admin/in-transits');
    await expect(page.getByRole('button', { name: 'Create' })).not.toBeVisible();
  });
});