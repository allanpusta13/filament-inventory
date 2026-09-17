import { test, expect } from '@playwright/test';

test.describe('ScanToReceive E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/warehouse-staff.json' });

  test('can access scan-to-receive as warehouse staff', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/scan-receive');
    await expect(page.getByRole('heading', { name: 'Scan to Receive' })).toBeVisible();
  });

  test('can submit scan form with good and damaged qty', async ({ page }) => {
    // Create a transfer first
    await page.goto('http://127.0.0.1:8000/admin/transfer-requisitions/create');
    await page.selectOption('select[name="from_warehouse_id"]', { index: 1 });
    await page.selectOption('select[name="to_warehouse_id"]', { index: 2 });
    await page.click('button:has-text("NEXT")');
    await page.selectOption('select[name="items.0.product_variant_id"]', { index: 1 });
    await page.fill('input[name="items.0.requested_unit_name"]', 'piece');
    await page.fill('input[name="items.0.requested_unit_ratio"]', '1');
    await page.fill('input[name="items.0.requested_qty"]', '100');
    await page.click('button:has-text("CREATE REQUISITION")');
    await page.click('text=SUBMIT REQUEST');
    await page.click('button:has-text("SUBMIT")');
    await page.click('text=CONFIRM');
    await page.click('button:has-text("CONFIRM")');
    await page.click('text=DISPATCH');
    await page.click('button:has-text("DISPATCH")');

    // Now scan to receive
    await page.goto('http://127.0.0.1:8000/admin/scan-receive');
    await page.fill('input[name*="good_qty"]', '70');
    await page.fill('input[name*="damaged_qty"]', '15');
    await page.selectOption('select[name="loss_reason"]', { index: 0 });
    await page.click('button:has-text("CONFIRM INTAKE")');
    await expect(page.locator('text=Partially Received')).toBeVisible({ timeout: 10000 });
  });

  test('can submit final scan to complete transfer', async ({ page }) => {
    // Create and dispatch a requisition first
    await page.goto('http://127.0.0.1:8000/admin/transfer-requisitions/create');
    await page.selectOption('select[name="from_warehouse_id"]', { index: 1 });
    await page.selectOption('select[name="to_warehouse_id"]', { index: 2 });
    await page.click('button:has-text("NEXT")');
    await page.selectOption('select[name="items.0.product_variant_id"]', { index: 1 });
    await page.fill('input[name="items.0.requested_unit_name"]', 'piece');
    await page.fill('input[name="items.0.requested_unit_ratio"]', '1');
    await page.fill('input[name="items.0.requested_qty"]', '100');
    await page.click('button:has-text("CREATE REQUISITION")');
    await page.click('text=SUBMIT REQUEST');
    await page.click('button:has-text("SUBMIT")');
    await page.click('text=CONFIRM');
    await page.click('button:has-text("CONFIRM")');
    await page.click('text=DISPATCH');
    await page.click('button:has-text("DISPATCH")');

    // Now complete the scan
    await page.goto('http://127.0.0.1:8000/admin/scan-receive');
    await page.fill('input[name*="good_qty"]', '100');
    await page.fill('input[name*="damaged_qty"]', '0');
    await page.selectOption('select[name="loss_reason"]', { index: -1 }); // No loss
    await page.click('button:has-text("CONFIRM INTAKE")');
    await expect(page.locator('text=Completed')).toBeVisible({ timeout: 10000 });
  });
});
