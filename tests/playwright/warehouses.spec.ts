import { test, expect, BASE_URL, USERS, login } from './helpers';

test.describe('Warehouses Resource', () => {
  test.setTimeout(180000);

  test('admin can view warehouses list', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/warehouses`);
    await expect(page.locator('h1')).toContainText('Warehouse');
  });

  test('admin can access create warehouse page', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/warehouses/create`);
    await expect(page.locator('h1')).toContainText('Create');
  });

  test('manager cannot view warehouses list (403)', async ({ page }) => {
    await login(page, USERS.managerMnl);
    await page.goto(`${BASE_URL}/admin/warehouses`);
    await expect(page.locator('h1')).toContainText('403');
  });

  test('staff cannot view warehouses list (403)', async ({ page }) => {
    await login(page, USERS.staffDvo);
    await page.goto(`${BASE_URL}/admin/warehouses`);
    await expect(page.locator('h1')).toContainText('403');
  });

  test('warehouses page has no JS errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (err) => {
      const msg = typeof err === 'string' ? err : (err as Error).message || '';
      if (!msg.includes('PhpDebugBar')) {
        errors.push(msg);
      }
    });

    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/warehouses`);
    await page.waitForTimeout(2000);

    expect(errors).toHaveLength(0);
  });

  test('warehouses list shows data table', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/warehouses`);

    await expect(page.locator('.fi-ta-table, .fi-ta-empty-state').first()).toBeVisible({ timeout: 15000 });
  });
});
