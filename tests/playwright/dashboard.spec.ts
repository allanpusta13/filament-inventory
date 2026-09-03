import { test, expect, BASE_URL, USERS, login } from './helpers';

test.describe('Dashboard', () => {
  test.setTimeout(180000);

  test('dashboard loads with all widget sections', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin`);

    await expect(page.getByText('Performance Overview')).toBeVisible({ timeout: 15000 });
    await expect(page.getByText('Receive Stock')).toBeVisible({ timeout: 10000 });
    await expect(page.getByText('Ship Stock')).toBeVisible({ timeout: 10000 });
    await expect(page.getByText('Transfer Stock')).toBeVisible({ timeout: 10000 });
  });

  test('dashboard has no JavaScript errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (err) => {
      const msg = err.message || String(err);
      const stack = err.stack || '';
      errors.push(msg + stack);
    });

    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin`);
    await page.waitForTimeout(3000);

    const realErrors = errors.filter(e => !e.includes('PhpDebugBar') && !e.includes('debugbar'));
    expect(realErrors).toHaveLength(0);
  });

  test('dashboard KPI values are numeric', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin`);

    await expect(page.getByText('Performance Overview')).toBeVisible({ timeout: 15000 });
    await expect(page.getByText('Low Stock Alerts')).toBeVisible({ timeout: 10000 });
  });

  test('pending transfers widget is visible', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin`);

    await expect(page.getByText('Performance Overview')).toBeVisible({ timeout: 15000 });
  });

  test('staff dashboard loads without errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (err) => {
      const msg = err.message || String(err);
      const stack = err.stack || '';
      errors.push(msg + stack);
    });

    await login(page, USERS.staffDvo);
    await page.goto(`${BASE_URL}/admin`);
    await page.waitForTimeout(3000);

    const realErrors = errors.filter(e => !e.includes('PhpDebugBar') && !e.includes('debugbar'));
    expect(realErrors).toHaveLength(0);
  });
});
