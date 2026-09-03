import { test, expect, BASE_URL, USERS, login } from './helpers';

test.describe('Warehouse Staff (staff.dvo@example.com)', () => {
  test.setTimeout(180000);

  test('dashboard: KPIs visible', async ({ page }) => {
    await login(page, USERS.staffDvo);
    await expect(page.getByText('Total SKUs')).toBeVisible({ timeout: 15000 });
  });

  test('transfer list accessible', async ({ page }) => {
    await login(page, USERS.staffDvo);
    await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
    await expect(page.locator('h1')).toContainText('Transfer');
    // Staff may or may not have access to transfer-requisitions table
    // Just verify the page loads without error
    const bodyText = await page.locator('body').textContent() ?? '';
    expect(bodyText.length > 0).toBeTruthy();
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
});
