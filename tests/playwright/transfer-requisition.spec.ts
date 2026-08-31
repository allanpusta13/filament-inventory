import { test, expect, BASE_URL, USERS, login, selectFilamentOption } from './helpers';

test.describe('Transfer Requisition Workflow', () => {
  test.setTimeout(180000);

  test('admin can view transfer requisitions list', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
    await expect(page.locator('h1')).toContainText('Transfer');
  });

  test('admin can access create transfer requisition page', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/transfer-requisitions/create`);
    await expect(page.getByLabel('Source Warehouse')).toBeVisible();
    await expect(page.getByLabel('Destination Warehouse')).toBeVisible();
  });

  test('transfer requisition list has correct columns', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/transfer-requisitions`);

    await expect(page.getByText('Reference')).toBeVisible();
    await expect(page.getByText('Status')).toBeVisible();
  });

  test('staff can view transfer requisitions involving their warehouse', async ({ page }) => {
    await login(page, USERS.staffDvo);
    await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
    await expect(page.locator('h1')).toContainText('Transfer');
  });

  test('auditor can view transfer requisitions', async ({ page }) => {
    await login(page, USERS.auditor);
    await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
    await expect(page.locator('h1')).toContainText('Transfer');
  });

  test('transfer requisition page has no JS errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (err) => errors.push(err.message));

    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
    await page.waitForTimeout(2000);

    expect(errors).toHaveLength(0);
  });
});
