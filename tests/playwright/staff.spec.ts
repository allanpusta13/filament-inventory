import { test, expect, BASE_URL, USERS, login, logout } from './helpers';

test.describe('Warehouse Staff (staff.dvo@example.com)', () => {
  test.setTimeout(180000);

  test('dashboard: KPIs visible', async ({ page }) => {
    await login(page, USERS.staffDvo);
    await expect(page.getByText('Total SKUs On Hand')).toBeVisible();
    await expect(page.getByText('Pending Transfers')).toBeVisible();
  });

  test('transfer list and create form accessible', async ({ page }) => {
    await login(page, USERS.staffDvo);
    await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
    await expect(page.locator('h1')).toContainText('Transfer');

    await page.goto(`${BASE_URL}/admin/transfer-requisitions/create`);
    await expect(page.getByText('Source Warehouse')).toBeVisible();
  });

  test('view requisition: no dispatch/confirm buttons', async ({ page }) => {
    await login(page, USERS.staffDvo);
    await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
    const row = page.locator('.fi-ta-table tbody tr').filter({ hasText: 'TRQ-2026-0003' });
    await expect(row).toBeVisible();
    await row.locator('.fi-ac-link-action').first().click();
    await page.waitForURL(/\/admin\/transfer-requisitions\/\d+$/, { timeout: 10000 });
    await expect(page.getByRole('button', { name: 'Dispatch' })).not.toBeVisible();
    await expect(page.getByRole('button', { name: 'Confirm Requisition' })).not.toBeVisible();
  });

  test('no "New product" button on products page', async ({ page }) => {
    await login(page, USERS.staffDvo);
    await page.goto(`${BASE_URL}/admin/products`);
    await page.waitForTimeout(2000);
    await expect(page.getByRole('button', { name: 'New product' })).not.toBeVisible();
  });

  test('sidebar navigation visible', async ({ page }) => {
    await login(page, USERS.staffDvo);
    await expect(page.locator('.fi-sidebar')).toBeVisible();
    await expect(page.locator('.fi-sidebar').getByRole('link', { name: 'Dashboard' })).toBeVisible();
    await expect(page.locator('.fi-sidebar').getByRole('link', { name: 'Products' })).toBeVisible();
  });

  test('no edit buttons on products list', async ({ page }) => {
    await login(page, USERS.staffDvo);
    await page.goto(`${BASE_URL}/admin/products`);
    await page.waitForTimeout(2000);
    await expect(page.locator('.fi-ta-table')).toBeVisible();
    await expect(page.locator('.fi-ta-table .fi-ac-link-action').first()).not.toBeVisible();
  });

  test('cross-tenant: cannot see other warehouse requisitions in list', async ({ page }) => {
    await login(page, USERS.staffDvo);
    await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
    // TRQ-2026-0001 is MNL→CEB — staff.dvo (DVO only) should not see it
    await expect(page.getByText('TRQ-2026-0001')).not.toBeVisible();
  });
});
