import { test, expect } from '@playwright/test';

test.describe('ProductResource E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/user.json' });

  test('can render product index page', async ({ page }) => {
    await page.goto('http://filament-inventory.test/admin/products');
    await expect(page.locator('text=Product Variants')).toBeVisible();
  });

  test('can create a product variant', async ({ page }) => {
    await page.goto('http://filament-inventory.test/admin/products/create');
    await expect(page.locator('text=CREATE PRODUCT VARIANT')).toBeVisible();

    await page.fill('input[name="sku"]', 'E2E-TEST-001');
    await page.fill('input[name="name"]', 'E2E Test Variant');
    await page.fill('input[name="base_unit_name"]', 'piece');
    await page.fill('input[name="reorder_point"]', '10');
    await page.click('button:has-text("CREATE")');

    await expect(page.locator('text=Created successfully')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('text=E2E-TEST-001')).toBeVisible();
  });

  test('can edit a product variant', async ({ page }) => {
    await page.goto('http://filament-inventory.test/admin/products/create');
    await page.fill('input[name="sku"]', 'E2E-EDIT-001');
    await page.fill('input[name="name"]', 'Original Name');
    await page.fill('input[name="base_unit_name"]', 'piece');
    await page.fill('input[name="reorder_point"]', '5');
    await page.click('button:has-text("CREATE")');
    await expect(page.locator('text=Created successfully')).toBeVisible({ timeout: 10000 });

    await page.click('text=Edit');
    await page.fill('input[name="name"]', 'Updated Name');
    await page.click('button:has-text("SAVE")');
    await expect(page.locator('text=Saved successfully')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('text=Updated Name')).toBeVisible();
  });

  test('can delete a product variant', async ({ page }) => {
    await page.goto('http://filament-inventory.test/admin/products/create');
    await page.fill('input[name="sku"]', 'E2E-DELETE-001');
    await page.fill('input[name="name"]', 'To Delete');
    await page.fill('input[name="base_unit_name"]', 'piece');
    await page.fill('input[name="reorder_point"]', '1');
    await page.click('button:has-text("CREATE")');
    await expect(page.locator('text=Created successfully')).toBeVisible({ timeout: 10000 });

    await page.click('text=Delete');
    await page.click('button:has-text("DELETE")');
    await expect(page.locator('text=Deleted successfully')).toBeVisible({ timeout: 10000 });
  });

  test('can filter products by search', async ({ page }) => {
    await page.goto('http://filament-inventory.test/admin/products');
    await page.fill('input[placeholder*="Search"]', 'E2E-TEST');
    await page.keyboard.press('Enter');
    await expect(page.locator('text=E2E-TEST-001')).toBeVisible({ timeout: 5000 });
  });

  test('can sort products by column', async ({ page }) => {
    await page.goto('http://filament-inventory.test/admin/products');
    await page.click('text=SKU');
    await page.waitForTimeout(500);
    await expect(page.locator('tbody tr').first()).toContainText('E2E');
  });
});
