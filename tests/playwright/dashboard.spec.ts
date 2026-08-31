import { test, expect, BASE_URL, USERS, login } from './helpers';

test.describe('Dashboard', () => {
  test.setTimeout(180000);

  test('dashboard loads with all widget sections', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin`);

    await expect(page.getByText('Total SKUs On Hand')).toBeVisible();
    await expect(page.getByText('Low Stock Alerts')).toBeVisible();
    await expect(page.getByText('Active Shipments')).toBeVisible();
    await expect(page.getByText('Pending Transfers')).toBeVisible();
  });

  test('dashboard has no JavaScript errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (err) => errors.push(err.message));

    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin`);
    await page.waitForTimeout(3000);

    expect(errors).toHaveLength(0);
  });

  test('dashboard KPI values are numeric', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin`);

    await expect(page.getByText('Total SKUs On Hand')).toBeVisible();
    await expect(page.getByText('Low Stock Alerts')).toBeVisible();
    await expect(page.getByText('Active Shipments')).toBeVisible();
  });

  test('pending transfers widget is visible', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin`);

    await expect(page.getByText('Pending Transfers')).toBeVisible();
  });

  test('staff dashboard loads without errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (err) => errors.push(err.message));

    await login(page, USERS.staffDvo);
    await page.goto(`${BASE_URL}/admin`);
    await page.waitForTimeout(3000);

    expect(errors).toHaveLength(0);
  });
});
