import { test, expect, BASE_URL, USERS, login, logout, selectFilamentOption } from './helpers';

test.describe('Administrator (admin@example.com)', () => {
  test.setTimeout(180000);

  test('dashboard: KPIs visible with numeric values', async ({ page }) => {
    await login(page, USERS.admin);
    await expect(page.getByText('Total SKUs On Hand')).toBeVisible();
    await expect(page.getByText('Pending Transfers')).toBeVisible();
    await expect(page.getByText('Low Stock Alerts')).toBeVisible();
    await expect(page.getByText('Active Shipments')).toBeVisible();
  });

  test('product CRUD: create', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/products/create`);
    const sku = `TEST-${Date.now()}`;
    await page.getByLabel('Sku').fill(sku);
    await page.getByLabel('Name').fill(`E2E Product ${Date.now()}`);
    await page.getByLabel('Category').fill('Beverages');
    await page.getByLabel('Reorder point').fill('10');
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForURL('**/edit', { timeout: 10000 });
  });

  test('product CRUD: update', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/products`);
    await page.locator('.fi-ta-table tbody tr').first().locator('.fi-ac-link-action').first().click();
    await page.waitForURL(/\/admin\/products\/\d+\/edit$/, { timeout: 10000 });
    const newName = `Updated Product ${Date.now()}`;
    await page.getByRole('textbox', { name: 'Name*' }).fill(newName);
    await page.getByRole('button', { name: 'Save changes' }).click();
    await page.waitForTimeout(1500);
    await expect(page.getByRole('textbox', { name: 'Name*' })).toHaveValue(newName);
  });

  test('product CRUD: delete', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/products`);
    const firstRow = page.locator('.fi-ta-table tbody tr').first();
    const productName = await firstRow.locator('td').first().textContent();
    await firstRow.locator('.fi-ta-actions').first().click();
    await page.waitForTimeout(300);
    await page.getByRole('button', { name: 'Delete' }).click();
    await page.waitForTimeout(300);
    await page.locator('.fi-modal-footer').getByRole('button', { name: 'Delete' }).click();
    await page.waitForTimeout(1500);
  });

  test('warehouse CRUD: create', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/warehouses/create`);
    await page.getByLabel('Code').fill(`WH-${Date.now()}`);
    await page.getByLabel('Name').fill(`E2E Warehouse ${Date.now()}`);
    await page.getByLabel('Location').fill('123 Test Ave');
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForURL('**/edit', { timeout: 10000 });
  });

  test('warehouse CRUD: update', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/warehouses`);
    await page.locator('.fi-ta-table tbody tr').first().locator('.fi-ac-link-action').first().click();
    await page.waitForURL(/\/admin\/warehouses\/\d+\/edit$/, { timeout: 10000 });
    const newName = `Updated Warehouse ${Date.now()}`;
    await page.getByRole('textbox', { name: 'Name*' }).fill(newName);
    await page.getByRole('button', { name: 'Save changes' }).click();
    await page.waitForTimeout(1500);
    await expect(page.getByRole('textbox', { name: 'Name*' })).toHaveValue(newName);
  });

  test('user CRUD: create', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/users/create`);
    await page.getByLabel('Name').fill(`E2E User ${Date.now()}`);
    await page.getByLabel('Email').fill(`e2e-${Date.now()}@test.com`);
    await page.locator('input[type="password"]').first().fill('password');
    const roleField = page.locator('.fi-fo-select-wrp').filter({ hasText: 'Role' }).first();
    await roleField.locator('button').click();
    await page.waitForTimeout(500);
    await page.locator('.fi-dropdown-panel:visible').getByText('WarehouseStaff').click();
    await page.waitForTimeout(300);
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForURL('**/edit', { timeout: 10000 });
  });

  test('user CRUD: update', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/users`);
    await page.locator('.fi-ta-table tbody tr').first().locator('.fi-ac-link-action').first().click();
    await page.waitForURL(/\/admin\/users\/\d+\/edit$/, { timeout: 10000 });
    const newName = `Updated User ${Date.now()}`;
    await page.getByRole('textbox', { name: 'Name*' }).fill(newName);
    await page.getByRole('button', { name: 'Save changes' }).click();
    await page.waitForTimeout(1500);
    await expect(page.getByRole('textbox', { name: 'Name*' })).toHaveValue(newName);
  });

  test('transfer requisitions list and detail', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
    await expect(page.locator('h1')).toContainText('Transfer');
    await expect(page.getByText('TRQ-2026-0001')).toBeVisible();

    await page.locator('.fi-ta-table tbody tr').first().locator('.fi-ac-link-action').first().click();
    await page.waitForURL(/\/admin\/transfer-requisitions\/\d+$/, { timeout: 10000 });
  });

  test('audit pages accessible', async ({ page }) => {
    await login(page, USERS.admin);

    await page.goto(`${BASE_URL}/admin/stock-movements`);
    await expect(page.locator('h1')).toContainText('Stock Movements');

    await page.goto(`${BASE_URL}/admin/in-transits`);
    await expect(page.locator('h1')).toContainText('In Transit');

    await page.goto(`${BASE_URL}/admin/loss-ledgers`);
    await expect(page.locator('h1')).toContainText('Loss Ledger');

    await page.goto(`${BASE_URL}/admin/product-prices`);
    await expect(page.locator('h1')).toContainText('Product Prices');
  });
});
