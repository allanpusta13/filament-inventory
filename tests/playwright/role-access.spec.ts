import { test, expect, BASE_URL, USERS, login } from './helpers';

test.describe('Role-Based Access Control', () => {
  test.setTimeout(180000);

  test('admin can see key navigation items in sidebar', async ({ page }) => {
    await login(page, USERS.admin);
    const sidebar = page.locator('.fi-sidebar');
    await expect(sidebar.getByRole('link', { name: 'Products' })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: 'Warehouses' })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: 'Transfer Orders' })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: 'Stock Adjustment' })).toBeVisible();
  });

  test('staff can see products page in sidebar', async ({ page }) => {
    await login(page, USERS.staffDvo);
    await expect(page.locator('.fi-sidebar').getByRole('link', { name: 'Products' })).toBeVisible();
  });

  test('auditor can access audit pages', async ({ page }) => {
    await login(page, USERS.auditor);
    await page.goto(`${BASE_URL}/admin/stock-movements`);
    await expect(page.locator('h1')).toContainText('Stock Movements');

    await page.goto(`${BASE_URL}/admin/in-transits`);
    await expect(page.locator('h1')).toContainText('In Transit');

    await page.goto(`${BASE_URL}/admin/loss-ledgers`);
    await expect(page.locator('h1')).toContainText('Loss Ledger');
  });

  test('staff can access stock movements page', async ({ page }) => {
    await login(page, USERS.staffDvo);
    await page.goto(`${BASE_URL}/admin/stock-movements`);
    await expect(page.locator('h1')).toContainText('Stock Movements');
  });

  test('manager can access stock adjustment page', async ({ page }) => {
    await login(page, USERS.managerMnl);
    await page.goto(`${BASE_URL}/admin/stock-adjustment`);
    await expect(page.locator('h1')).toContainText('Stock Adjustment');
  });

  test('dashboard loads without JS errors for admin', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (err) => {
      const msg = typeof err === 'string' ? err : (err as Error).message || '';
      if (!msg.includes('PhpDebugBar')) {
        errors.push(msg);
      }
    });

    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin`);
    await expect(page.getByText('Total SKUs')).toBeVisible({ timeout: 15000 });
    expect(errors).toHaveLength(0);
  });
});
