import { test, expect, BASE_URL, USERS, login } from './helpers';

test.describe('Stock Adjustment Page', () => {
  test.setTimeout(180000);

  test('admin can access stock adjustment page', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/stock-adjustment`);
    await expect(page.locator('h1')).toContainText('Stock Adjustment');
  });

  test('manager can access stock adjustment page', async ({ page }) => {
    await login(page, USERS.managerMnl);
    await page.goto(`${BASE_URL}/admin/stock-adjustment`);
    await expect(page.locator('h1')).toContainText('Stock Adjustment');
  });

  test('stock adjustment form has required fields', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/stock-adjustment`);

    await expect(page.getByLabel('Warehouse')).toBeVisible();
    await expect(page.getByLabel('Product Variant')).toBeVisible();
    await expect(page.getByLabel('Adjustment Quantity')).toBeVisible();
    await expect(page.getByLabel('Reason')).toBeVisible();
  });

  test('stock adjustment page has no JS errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (err) => {
      const msg = typeof err === 'string' ? err : (err as Error).message || '';
      if (!msg.includes('PhpDebugBar')) {
        errors.push(msg);
      }
    });

    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/stock-adjustment`);
    await page.waitForTimeout(2000);

    expect(errors).toHaveLength(0);
  });
});
