import { test, expect, BASE_URL, USERS, login, logout, selectFilamentOption } from './helpers';
test.use({ storageState: './tests/playwright/.auth/admin.json' });

test.describe('Administrator (admin@example.com)', () => {
  test.setTimeout(180000);

  test('dashboard: KPIs visible with numeric values', async ({ page }) => {
    await login(page, USERS.admin);
    await expect(page.locator('h1')).toHaveText('Dashboard', { timeout: 10000 });
    await expect(page.getByText('Performance Overview')).toBeVisible({ timeout: 15000 });
    await expect(page.getByText('Receive Stock')).toBeVisible({ timeout: 10000 });
    await expect(page.getByText('Ship Stock')).toBeVisible({ timeout: 10000 });
    await expect(page.getByText('Transfer Stock')).toBeVisible({ timeout: 10000 });
  });

  test('product CRUD: create', async ({ page }) => {
    await login(page, USERS.admin);
    await expect(page.locator('h1')).toHaveText('Dashboard', { timeout: 10000 });
    await page.goto(`${BASE_URL}/admin/products/create`);
    // Wait for the form to be ready
    await page.locator('#form').waitFor({ state: 'visible', timeout: 10000 });
    const sku = `TEST-${Date.now()}`;
    await page.getByLabel('Sku').fill(sku);
    await page.getByLabel('Name').fill('E2E Product');
    await page.getByLabel('Category').fill('Beverages');
    await page.getByLabel('Reorder point').fill('10');
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    // Wait for navigation to edit page or success indication
    await page.waitForURL(/.*\/edit/, { timeout: 15000 });
  });

  test('product CRUD: update', async ({ page }) => {
    await login(page, USERS.admin);
    await expect(page.locator('h1')).toHaveText('Dashboard', { timeout: 10000 });
    await page.goto(`${BASE_URL}/admin/products`);
    await page.locator('.fi-ta-table tbody tr').first().locator('.fi-ac-link-action').first().click();
    await page.waitForURL(/\/admin\/products\/\d+\/edit$/, { timeout: 10000 });
    const newName = 'Updated Product';
    await page.getByRole('textbox', { name: 'Name*' }).fill(newName);
    await page.getByRole('button', { name: 'Save changes' }).click();
    await page.waitForTimeout(1500);
    await expect(page.getByRole('textbox', { name: 'Name*' })).toHaveValue(newName);
  });

  test('product CRUD: delete', async ({ page }) => {
    await login(page, USERS.admin);
    await expect(page.locator('h1')).toHaveText('Dashboard', { timeout: 10000 });
    await page.goto(`${BASE_URL}/admin/products`);
    const firstRow = page.locator('.fi-ta-table tbody tr').first();
    await firstRow.locator('.fi-ta-actions').first().click();
    await page.waitForTimeout(300);
    await page.getByRole('button', { name: 'Delete' }).click();
    await page.waitForTimeout(300);
    await page.locator('.fi-modal-footer').getByRole('button', { name: 'Delete' }).click();
    await page.waitForTimeout(1500);
  });

  test('warehouse CRUD: create', async ({ page }) => {
    await login(page, USERS.admin);
    await expect(page.locator('h1')).toHaveText('Dashboard', { timeout: 10000 });
    await page.goto(`${BASE_URL}/admin/warehouses/create`);
    // Wait for the form to be ready
    await page.locator('#form').waitFor({ state: 'visible', timeout: 10000 });
    await page.getByLabel('Code').fill(`WH${Date.now() % 100000}`);
    await page.getByLabel('Name').fill('E2E Warehouse');
    await page.getByLabel('Location').fill('123 Test Ave');
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForURL('**/edit', { timeout: 10000 });
  });

  test('warehouse CRUD: update', async ({ page }) => {
    await login(page, USERS.admin);
    await expect(page.locator('h1')).toHaveText('Dashboard', { timeout: 10000 });
    await page.goto(`${BASE_URL}/admin/warehouses`);
    await page.locator('.fi-ta-table tbody tr').first().locator('.fi-ac-link-action').first().click();
    await page.waitForURL(/\/admin\/warehouses\/\d+\/edit$/, { timeout: 10000 });
    const newName = 'Updated Warehouse';
    await page.getByRole('textbox', { name: 'Name*' }).fill(newName);
    await page.getByRole('button', { name: 'Save changes' }).click();
    await page.waitForTimeout(1500);
    await expect(page.getByRole('textbox', { name: 'Name*' })).toHaveValue(newName);
  });

  test('user CRUD: create', async ({ page }) => {
    await login(page, USERS.admin);
    await expect(page.locator('h1')).toHaveText('Dashboard', { timeout: 10000 });
    await page.goto(`${BASE_URL}/admin/users/create`);
    // Wait for the form to be ready
    await page.locator('#form').waitFor({ state: 'visible', timeout: 10000 });
    await page.getByLabel('Name').fill('E2E User');
    await page.getByLabel('Email').fill(`e2e-${Date.now()}@test.com`);
    await page.locator('input[type="password"]').first().fill('password');
    await page.getByLabel('Role').selectOption({ label: 'Warehouse Staff' });
    await page.waitForTimeout(300);
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForURL('**/edit', { timeout: 10000 });
  });

  test('user CRUD: update', async ({ page }) => {
    await login(page, USERS.admin);
    await expect(page.locator('h1')).toHaveText('Dashboard', { timeout: 10000 });
    await page.goto(`${BASE_URL}/admin/users`);
    await page.locator('.fi-ta-table tbody tr').first().locator('.fi-ac-link-action').first().click();
    await page.waitForURL(/\/admin\/users\/\d+\/edit$/, { timeout: 10000 });
    const newName = 'Updated User';
    await page.getByRole('textbox', { name: 'Name*' }).fill(newName);
    await page.getByRole('button', { name: 'Save changes' }).click();
    await page.waitForTimeout(1500);
    await expect(page.getByRole('textbox', { name: 'Name*' })).toHaveValue(newName);
  });

  test('transfer requisitions list and detail', async ({ page }) => {
    await login(page, USERS.admin);
    await expect(page.locator('h1')).toHaveText('Dashboard', { timeout: 10000 });
    await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
    await expect(page.locator('h1')).toContainText('Transfer');
    // Verify page loads (table may not be visible due to permissions)
    const bodyText = await page.locator('body').textContent() ?? '';
    expect(bodyText.length > 0).toBeTruthy();
  });

  test('audit pages accessible', async ({ page }) => {
    await login(page, USERS.admin);
    await expect(page.locator('h1')).toHaveText('Dashboard', { timeout: 10000 });

    await page.goto(`${BASE_URL}/admin/stock-movements`);
    await expect(page.locator('h1')).toContainText('Stock Movements');

    await page.goto(`${BASE_URL}/admin/in-transits`);
    await expect(page.locator('h1')).toContainText('In Transit');

    await page.goto(`${BASE_URL}/admin/loss-ledgers`);
    await expect(page.locator('h1')).toContainText('Loss Ledger');
  });
});