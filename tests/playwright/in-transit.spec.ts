import { test, expect } from '@playwright/test';

test.describe('InTransitResource E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/user.json' });

  test('can render in-transit index page', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/in-transits');
    await expect(page.locator('text=In Transit')).toBeVisible();
  });

  test('can view in-transit details', async ({ page }) => {
    // Create a requisition and dispatch it first
    await page.goto('http://127.0.0.1:8000/admin/transfer-requisitions/create');
    await page.selectOption('select[name="from_warehouse_id"]', { index: 1 });
    await page.selectOption('select[name="to_warehouse_id"]', { index: 2 });
    await page.click('button:has-text("NEXT")');
    await page.selectOption('select[name="items.0.product_variant_id"]', { index: 1 });
    await page.fill('input[name="items.0.requested_unit_name"]', 'piece');
    await page.fill('input[name="items.0.requested_unit_ratio"]', '1');
    await page.fill('input[name="items.0.requested_qty"]', '100');
    await page.click('button:has-text("NEXT")');
    await page.click('button:has-text("CREATE REQUISITION")');
    await expect(page.locator('text=Created successfully')).toBeVisible({ timeout: 10000 });

    await page.click('text=SUBMIT REQUEST');
    await page.click('button:has-text("SUBMIT")');
    await page.click('text=CONFIRM');
    await page.click('button:has-text("CONFIRM")');
    await page.click('text=DISPATCH');
    await page.click('button:has-text("DISPATCH")');
    await expect(page.locator('text=Dispatched')).toBeVisible();

    // Now check InTransit resource
    await page.goto('http://127.0.0.1:8000/admin/in-transits');
    await expect(page.locator('text=In Transit')).toBeVisible();

    // Click view on first record
    await page.click('text=View');
    await expect(page.locator('text=In Transit')).toBeVisible();
  });

  test('can filter by status', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/in-transits');
    await page.selectOption('select[name*="status"]', 'in_transit');
    await page.keyboard.press('Enter');
    await expect(page.locator('text=In Transit')).toBeVisible();
  });

  test('can filter by product variant', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/in-transits');
    await page.selectOption('select[name*="product_variant"]', { index: 1 });
    await page.keyboard.press('Enter');
    await page.waitForTimeout(500);
  });
});