import { test, expect, BASE_URL, USERS, login } from './helpers';

test.describe('Stock Movements List', () => {
  test.setTimeout(180000);

  test('admin can view stock movements list', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/stock-movements`);
    await expect(page.locator('h1')).toContainText('Stock Movement');
  });

  test('auditor can view stock movements list', async ({ page }) => {
    await login(page, USERS.auditor);
    await page.goto(`${BASE_URL}/admin/stock-movements`);
    await expect(page.locator('h1')).toContainText('Stock Movement');
  });

  test('stock movements page has no JS errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (err) => {
      const msg = typeof err === 'string' ? err : (err as Error).message || '';
      if (!msg.includes('PhpDebugBar')) {
        errors.push(msg);
      }
    });

    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/stock-movements`);
    await page.waitForTimeout(2000);

    expect(errors).toHaveLength(0);
  });

  test('stock movements list shows data table', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/stock-movements`);

    // Wait for Filament table or empty state to be visible
    await expect(page.locator('.fi-ta-table, .fi-ta-empty-state').first()).toBeVisible({ timeout: 15000 });
  });
});
