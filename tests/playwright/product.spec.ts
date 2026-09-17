import { test, expect } from '@playwright/test';

test.describe('ProductResource E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/user.json' });

  test('can render product index page', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/products');
    await expect(page.getByRole('heading', { name: 'Product Variants' })).toBeVisible();
  });

  test('can create product variant', async ({ page }) => {
    const uniqueSku = `E2E-TEST-${Date.now()}`;
    await page.goto('http://127.0.0.1:8000/admin/products/create');
    await expect(page.getByRole('heading', { name: 'Create Product Variant' })).toBeVisible();

    await page.locator('select[id="form.product_id"]').selectOption({ index: 1 });
    await page.fill('input[id="form.sku"]', uniqueSku);
    await page.fill('input[id="form.name"]', 'E2E Test Variant');
    await page.fill('input[id="form.base_unit_name"]', 'piece');
    await page.fill('input[id="form.reorder_point"]', '10');
    await page.click('button:has-text("Create")');

    await expect(page.getByRole('heading', { name: `View ${uniqueSku}` })).toBeVisible({ timeout: 10000 });
  });

  test('can edit product variant', async ({ page }) => {
    const uniqueSku = `E2E-EDIT-${Date.now()}`;
    await page.goto('http://127.0.0.1:8000/admin/products/create');
    await page.locator('select[id="form.product_id"]').selectOption({ index: 1 });
    await page.fill('input[id="form.sku"]', uniqueSku);
    await page.fill('input[id="form.name"]', 'Original Name');
    await page.fill('input[id="form.base_unit_name"]', 'piece');
    await page.fill('input[id="form.reorder_point"]', '5');
    await page.click('button:has-text("Create")');
    await expect(page.getByRole('heading', { name: `View ${uniqueSku}` })).toBeVisible({ timeout: 10000 });

    const productId = page.url().split('/').pop();
    await page.goto(`http://127.0.0.1:8000/admin/products/${productId}/edit`);
    await expect(page.getByRole('heading', { name: `Edit ${uniqueSku}` })).toBeVisible({ timeout: 10000 });
    await page.fill('input[id="form.name"]', 'Updated Name');
    await page.click('button:has-text("Save changes")');
    await page.waitForLoadState('networkidle');
    await page.goto(`http://127.0.0.1:8000/admin/products/${productId}`);
    await expect(page.getByRole('heading', { name: `View ${uniqueSku}` })).toBeVisible({ timeout: 10000 });
    await expect(page.locator('text=Updated Name')).toBeVisible({ timeout: 5000 });
  });

  test('can delete product variant', async ({ page }) => {
    const uniqueSku = `E2E-DELETE-${Date.now()}`;
    await page.goto('http://127.0.0.1:8000/admin/products/create');
    await page.locator('select[id="form.product_id"]').selectOption({ index: 1 });
    await page.fill('input[id="form.sku"]', uniqueSku);
    await page.fill('input[id="form.name"]', 'Delete Variant');
    await page.fill('input[id="form.base_unit_name"]', 'piece');
    await page.fill('input[id="form.reorder_point"]', '1');
    await page.click('button:has-text("Create")');
    await expect(page.getByRole('heading', { name: `View ${uniqueSku}` })).toBeVisible({ timeout: 10000 });

    await page.getByRole('button', { name: 'Delete' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click();
    await expect(page.getByRole('heading', { name: 'Product Variants' })).toBeVisible({ timeout: 10000 });
  });

  test('can filter products by search', async ({ page }) => {
    const uniqueSku = `E2E-FILTER-${Date.now()}`;
    await page.goto('http://127.0.0.1:8000/admin/products/create');
    await page.locator('select[id="form.product_id"]').selectOption({ index: 1 });
    await page.fill('input[id="form.sku"]', uniqueSku);
    await page.fill('input[id="form.name"]', 'Filter Test Product');
    await page.fill('input[id="form.base_unit_name"]', 'piece');
    await page.fill('input[id="form.reorder_point"]', '10');
    await page.click('button:has-text("Create")');
    await expect(page.getByRole('heading', { name: `View ${uniqueSku}` })).toBeVisible({ timeout: 10000 });

    await page.goto('http://127.0.0.1:8000/admin/products');
    const searchInput = page.getByRole('searchbox', { name: 'Search', exact: true });
    await searchInput.fill(uniqueSku);
    await page.keyboard.press('Enter');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    await expect(page.getByRole('row').nth(1)).toContainText(uniqueSku);
  });

  test('can sort products by column', async ({ page }) => {
    const uniqueSku = `AAA-SORT-${Date.now()}`;
    await page.goto('http://127.0.0.1:8000/admin/products/create');
    await page.locator('select[id="form.product_id"]').selectOption({ index: 1 });
    await page.fill('input[id="form.sku"]', uniqueSku);
    await page.fill('input[id="form.name"]', 'Sort Test Product');
    await page.fill('input[id="form.base_unit_name"]', 'piece');
    await page.fill('input[id="form.reorder_point"]', '10');
    await page.click('button:has-text("Create")');
    await expect(page.getByRole('heading', { name: `View ${uniqueSku}` })).toBeVisible({ timeout: 10000 });

    await page.goto('http://127.0.0.1:8000/admin/products');
    // Click SKU header once for ascending sort (AAA-SORT should be first)
    await page.getByRole('button', { name: 'Sku' }).click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    await expect(page.getByRole('row').nth(1)).toContainText('AAA-SORT');
  });
});