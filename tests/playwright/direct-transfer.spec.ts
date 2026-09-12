import { test, expect } from '@playwright/test';

test.describe('DirectTransferResource E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/user.json' });

  test('can render direct transfer index page', async ({ page }) => {
    await page.goto('http://filament-inventory.test/admin/direct-transfers');
    await expect(page.locator('text=Direct Transfers')).toBeVisible();
  });

  test('can create a direct transfer', async ({ page }) => {
    await page.goto('http://filament-inventory.test/admin/direct-transfers/create');
    await expect(page.locator('text=INSTANT DIRECT TRANSFER')).toBeVisible();

    // Step 1: Location Mapping
    await page.selectOption('select[name="from_warehouse_id"]', { index: 1 });
    await page.selectOption('select[name="to_warehouse_id"]', { index: 2 });
    await page.click('button:has-text("NEXT")');

    // Step 2: Stock Allocation
    await expect(page.locator('text=STOCK ALLOCATION')).toBeVisible();
    await page.selectOption('select[name="product_variant_id"]', { index: 1 });
    await page.fill('input[name="quantity"]', '100');
    await page.fill('textarea[name="notes"]', 'Direct transfer test - minimum 15 characters');
    await page.click('button:has-text("NEXT")');

    // Step 3: Review & Verify
    await expect(page.locator('text=REVIEW & VERIFY')).toBeVisible();
    await page.click('button:has-text("EXECUTE TRANSFER")');

    await expect(page.locator('text=Transfer Executed')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('text=completed successfully')).toBeVisible({ timeout: 10000 });
  });
});
