import { test, expect } from '@playwright/test';

test.describe('WarehouseResource E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/admin.json' });

  test('can render warehouse index page', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/warehouses');
    await expect(page.getByRole('heading', { name: 'Warehouses' })).toBeVisible();
  });

  test('can create warehouse', async ({ page }) => {
    const uniqueCode = 'WH-E2E-' + Date.now();
    await page.goto('http://127.0.0.1:8000/admin/warehouses/create');
    await expect(page.getByRole('heading', { name: 'Create Warehouse' })).toBeVisible();
    await page.fill('input[id="form.code"]', uniqueCode);
    await page.fill('input[id="form.name"]', 'E2E Test Warehouse');
    await page.fill('input[id="form.location"]', 'Test Location');
    await page.check('input[id="form.is_active"]');
    await page.click('button:has-text("Create")');
    await expect(page.getByRole('heading', { name: 'View ' + uniqueCode })).toBeVisible({ timeout: 10000 });
  });

  test('can edit warehouse', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/warehouses/create');
    await page.fill('input[id="form.code"]', 'WH-E2E-EDIT');
    await page.fill('input[id="form.name"]', 'E2E Test Warehouse Edit');
    await page.fill('input[id="form.location"]', 'Edit Location');
    await page.click('button:has-text("Create")');
    const warehouseId = page.url().split('/').pop();
    await page.goto('http://127.0.0.1:8000/admin/warehouses/' + warehouseId + '/edit');
    await expect(page.getByRole('heading', { name: 'Edit WH-E2E-EDIT' })).toBeVisible({ timeout: 10000 });
    await page.fill('input[id="form.name"]', 'Updated Warehouse');
    await page.click('button:has-text("Save changes")');
    await page.waitForLoadState('networkidle');
    await page.goto('http://127.0.0.1:8000/admin/warehouses/' + warehouseId);
    await expect(page.getByRole('heading', { name: 'View WH-E2E-EDIT' })).toBeVisible({ timeout: 10000 });
    await expect(page.locator('text=Updated Warehouse')).toBeVisible({ timeout: 5000 });
  });
});
