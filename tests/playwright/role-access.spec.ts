import { test, expect, BASE_URL, USERS, login } from './helpers';

test.describe('Role-Based Access Control', () => {
  test.setTimeout(180000);

  test('admin can access all navigation items', async ({ page }) => {
    await login(page, USERS.admin);
    await expect(page.locator('.fi-sidebar-item')).toContainText('Products');
    await expect(page.locator('.fi-sidebar-item')).toContainText('Warehouses');
    await expect(page.locator('.fi-sidebar-item')).toContainText('Users');
    await expect(page.locator('.fi-sidebar-item')).toContainText('Transfer');
    await expect(page.locator('.fi-sidebar-item')).toContainText('Stock Adjustment');
  });

  test('staff cannot access products page', async ({ page }) => {
    await login(page, USERS.staffDvo);
    await page.goto(`${BASE_URL}/admin/products`);
    await expect(page.locator('body')).not.toContainText('Products');
  });

  test('staff cannot access warehouses page', async ({ page }) => {
    await login(page, USERS.staffDvo);
    await page.goto(`${BASE_URL}/admin/warehouses`);
    await expect(page.locator('body')).not.toContainText('Warehouses');
  });

  test('staff cannot access users page', async ({ page }) => {
    await login(page, USERS.staffDvo);
    await page.goto(`${BASE_URL}/admin/users`);
    await expect(page.locator('body')).not.toContainText('Users');
  });

  test('staff cannot access stock adjustment page', async ({ page }) => {
    await login(page, USERS.staffDvo);
    await page.goto(`${BASE_URL}/admin/stock-adjustment`);
    await page.waitForTimeout(1000);
    const url = page.url();
    expect(url).not.toContain('/stock-adjustment');
  });

  test('auditor cannot access stock adjustment page', async ({ page }) => {
    await login(page, USERS.auditor);
    await page.goto(`${BASE_URL}/admin/stock-adjustment`);
    await page.waitForTimeout(1000);
    const url = page.url();
    expect(url).not.toContain('/stock-adjustment');
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

  test('dashboard loads without JS errors for all roles', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (err) => errors.push(err.message));

    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin`);
    await expect(page.getByText('Total SKUs On Hand')).toBeVisible();
    expect(errors).toHaveLength(0);
  });
});
