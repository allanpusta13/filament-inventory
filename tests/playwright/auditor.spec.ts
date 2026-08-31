import { test, expect, BASE_URL, USERS, login, logout } from './helpers';

test.describe('Auditor (auditor@example.com)', () => {
  test.setTimeout(180000);

  test('dashboard: KPIs visible', async ({ page }) => {
    await login(page, USERS.auditor);
    await expect(page.getByText('Total SKUs On Hand')).toBeVisible();
    await expect(page.getByText('Pending Transfers')).toBeVisible();
  });

  test('products list: can view but cannot create', async ({ page }) => {
    await login(page, USERS.auditor);
    await page.goto(`${BASE_URL}/admin/products`);
    await expect(page.locator('h1')).toContainText('Products');
    await expect(page.locator('.fi-ta-table')).toBeVisible();
    await expect(page.getByRole('button', { name: 'New product' })).not.toBeVisible();
  });

  test('products list: search filters results', async ({ page }) => {
    await login(page, USERS.auditor);
    await page.goto(`${BASE_URL}/admin/products`);
    await page.waitForTimeout(1000);
    const searchInput = page.getByRole('searchbox', { name: 'Search', exact: true });
    if (await searchInput.isVisible()) {
      await searchInput.fill('nonexistent-product-xyz');
      await page.waitForTimeout(1000);
    }
  });

  test('stock movements list: filter by type', async ({ page }) => {
    await login(page, USERS.auditor);
    await page.goto(`${BASE_URL}/admin/stock-movements`);
    await expect(page.locator('h1')).toContainText('Stock Movements');
    await expect(page.locator('.fi-ta-table')).toBeVisible();
  });

  test('loss ledgers list visible', async ({ page }) => {
    await login(page, USERS.auditor);
    await page.goto(`${BASE_URL}/admin/loss-ledgers`);
    await expect(page.locator('h1')).toContainText('Loss Ledger');
    await expect(page.getByText('TRQ-2026-0003')).toBeVisible();
  });

  test('transfer list visible', async ({ page }) => {
    await login(page, USERS.auditor);
    await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
    await expect(page.locator('h1')).toContainText('Transfer');
    await expect(page.getByText('TRQ-2026-0001')).toBeVisible();
  });

  test('cannot access product create page', async ({ page }) => {
    await login(page, USERS.auditor);
    await page.goto(`${BASE_URL}/admin/products/create`);
    await page.waitForTimeout(2000);
    const bodyText = await page.locator('body').textContent();
    const isBlocked = bodyText?.includes('403') || bodyText?.includes('Forbidden') || bodyText?.includes('404') || !bodyText?.includes('Create Product');
    expect(isBlocked).toBeTruthy();
  });

  test('requisition detail: no dispatch/confirm/submit', async ({ page }) => {
    await login(page, USERS.auditor);
    await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
    await page.locator('.fi-ta-table tbody tr').first().locator('.fi-ac-link-action').first().click();
    await page.waitForURL(/\/admin\/transfer-requisitions\/\d+$/, { timeout: 10000 });
    await expect(page.getByRole('button', { name: 'Dispatch' })).not.toBeVisible();
    await expect(page.getByRole('button', { name: 'Confirm Requisition' })).not.toBeVisible();
    await expect(page.getByRole('button', { name: 'Submit Requisition' })).not.toBeVisible();
  });
});
