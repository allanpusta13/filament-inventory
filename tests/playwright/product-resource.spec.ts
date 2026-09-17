import { test, expect } from '@playwright/test';

test.describe('ProductResource E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/admin.json' });

  test('can render product index page', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/products');
    await expect(page.getByRole('heading', { name: 'Product Variants' })).toBeVisible();
  });

  test('can create product variant', async ({ page }) => {
    const uniqueSku = 'E2E-TEST-' + Date.now();
    await page.goto('http://127.0.0.1:8000/admin/products/create');
    await expect(page.getByRole('heading', { name: 'Create Product Variant' })).toBeVisible();
    await page.locator('select[id="form.product_id"]').selectOption({ index: 1 });
    await page.fill('input[id="form.sku"]', uniqueSku);
    await page.fill('input[id="form.name"]', 'E2E Test Variant');
    await page.fill('input[id="form.base_unit_name"]', 'piece');
    await page.fill('input[id="form.reorder_point"]', '10');
    await page.click('button:has-text("Create")');
    await expect(page.getByRole('heading', { name: 'View ' + uniqueSku })).toBeVisible({ timeout: 10000 });
  });
});
