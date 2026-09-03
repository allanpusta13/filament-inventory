import { test, expect, BASE_URL, USERS, login } from './helpers';

test.describe('In Transit Resource', () => {
  test.setTimeout(180000);

  test('admin can view in-transit list', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/in-transits`);
    await expect(page.locator('h1')).toContainText('In Transit');
  });

  test('manager can view in-transit list', async ({ page }) => {
    await login(page, USERS.managerMnl);
    await page.goto(`${BASE_URL}/admin/in-transits`);
    await expect(page.locator('h1')).toContainText('In Transit');
  });

  test('in-transit page has no JS errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (err) => {
      const msg = typeof err === 'string' ? err : (err as Error).message || '';
      if (!msg.includes('PhpDebugBar')) {
        errors.push(msg);
      }
    });

    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/in-transits`);
    await page.waitForTimeout(2000);

    expect(errors).toHaveLength(0);
  });

  test('in-transit list shows data table', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/in-transits`);

    await expect(page.locator('.fi-ta-table, .fi-ta-empty-state').first()).toBeVisible({ timeout: 15000 });
  });
});
