import { test, expect, BASE_URL, USERS, login } from './helpers';

test.describe('Transfer Orders Resource', () => {
  test.setTimeout(180000);

  test('admin can view transfer orders list', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/transfer-orders`);
    await expect(page.locator('h1')).toContainText('Transfer Order');
  });

  test('manager can view transfer orders list', async ({ page }) => {
    await login(page, USERS.managerMnl);
    await page.goto(`${BASE_URL}/admin/transfer-orders`);
    await expect(page.locator('h1')).toContainText('Transfer Order');
  });

  test('transfer orders page has no JS errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (err) => {
      const msg = typeof err === 'string' ? err : (err as Error).message || '';
      if (!msg.includes('PhpDebugBar')) {
        errors.push(msg);
      }
    });

    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/transfer-orders`);
    await page.waitForTimeout(2000);

    expect(errors).toHaveLength(0);
  });

  test('transfer orders list shows data table', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/transfer-orders`);

    await expect(page.locator('.fi-ta-table, .fi-ta-empty-state').first()).toBeVisible({ timeout: 15000 });
  });
});
