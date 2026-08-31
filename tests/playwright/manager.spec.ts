import { test, expect, BASE_URL, USERS, login, logout } from './helpers';

test.describe('Branch Manager (manager.mnl@example.com)', () => {
  test.setTimeout(180000);

  test('dashboard: KPIs visible, no warehouse filter', async ({ page }) => {
    await login(page, USERS.managerMnl);
    await expect(page.getByText('Total SKUs On Hand')).toBeVisible();
    await expect(page.getByText('Pending Transfers')).toBeVisible();
    await expect(page.getByText('Filter by Warehouse')).not.toBeVisible();
  });

  test('transfer list visible', async ({ page }) => {
    await login(page, USERS.managerMnl);
    await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
    await expect(page.locator('h1')).toContainText('Transfer');
    await expect(page.locator('.fi-ta-table')).toBeVisible();
  });

  test('view dispatched requisition: Print STN + Scan to Receive visible', async ({ page }) => {
    await login(page, USERS.managerMnl);
    await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
    const row = page.locator('.fi-ta-table tbody tr').filter({ hasText: 'TRQ-2026-0002' });
    await expect(row).toBeVisible();
    await row.locator('.fi-ac-link-action').first().click();
    await page.waitForURL(/\/admin\/transfer-requisitions\/\d+$/, { timeout: 10000 });
    await expect(page.getByRole('button', { name: 'Print STN' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Scan to Receive' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Dispatch' })).not.toBeVisible();
  });

  test('view completed requisition: no dispatch/confirm', async ({ page }) => {
    await login(page, USERS.managerMnl);
    await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
    const row = page.locator('.fi-ta-table tbody tr').filter({ hasText: 'TRQ-2026-0001' });
    await expect(row).toBeVisible();
    await row.locator('.fi-ac-link-action').first().click();
    await page.waitForURL(/\/admin\/transfer-requisitions\/\d+$/, { timeout: 10000 });
    await expect(page.getByRole('button', { name: 'Dispatch' })).not.toBeVisible();
    await expect(page.getByRole('button', { name: 'Confirm Requisition' })).not.toBeVisible();
  });

  test('cannot delete dispatched requisition', async ({ page }) => {
    await login(page, USERS.managerMnl);
    await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
    const row = page.locator('.fi-ta-table tbody tr').filter({ hasText: 'TRQ-2026-0002' });
    await row.locator('.fi-ac-link-action').first().click();
    await page.waitForURL(/\/admin\/transfer-requisitions\/\d+$/, { timeout: 10000 });
    await expect(page.getByRole('button', { name: 'Delete' })).not.toBeVisible();
  });

  test('cannot delete completed requisition', async ({ page }) => {
    await login(page, USERS.managerMnl);
    await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
    const row = page.locator('.fi-ta-table tbody tr').filter({ hasText: 'TRQ-2026-0001' });
    await row.locator('.fi-ac-link-action').first().click();
    await page.waitForURL(/\/admin\/transfer-requisitions\/\d+$/, { timeout: 10000 });
    await expect(page.getByRole('button', { name: 'Delete' })).not.toBeVisible();
  });

  test('cannot access product create page', async ({ page }) => {
    await login(page, USERS.managerMnl);
    await page.goto(`${BASE_URL}/admin/products/create`);
    await page.waitForTimeout(2000);
    const bodyText = await page.locator('body').textContent();
    const isBlocked = bodyText?.includes('403') || bodyText?.includes('Forbidden') || bodyText?.includes('404') || !bodyText?.includes('Create Product');
    expect(isBlocked).toBeTruthy();
  });
});
