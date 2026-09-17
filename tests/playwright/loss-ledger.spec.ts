import { test, expect } from '@playwright/test';

test.describe('LossLedgerResource E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/user.json' });

  test('loss write-off: record intra-warehouse loss via RecordWarehouseLossAction -> verify LossLedger row with transfer_requisition_id = NULL', async ({ page }) => {
    // Navigate to Warehouse resource to access Record Loss action
    await page.goto('http://127.0.0.1:8000/admin/warehouses');
    await expect(page.locator('text=Warehouses')).toBeVisible();

    // Click on Record Loss action for a warehouse (assuming it's a table action)
    await page.click('text=RECORD LOSS');

    // Fill in loss form
    await expect(page.locator('text=RECORD LOSS')).toBeVisible();
    await page.selectOption('select[name="product_variant_id"]', { index: 1 });
    await page.selectOption('select[name="loss_category"]', 'shortfall');
    await page.fill('input[name="lost_base_qty"]', '10');
    await page.fill('input[name="damaged_base_qty"]', '5');
    await page.fill('input[name="total_financial_loss"]', '150.00');
    await page.fill('textarea[name="notes"]', 'Intra-warehouse loss test - audit compliance reason minimum 15 chars');
    await page.click('button:has-text("RECORD LOSS")');

    await expect(page.locator('text=Recorded successfully')).toBeVisible({ timeout: 10000 });

    // Verify LossLedger row was created with transfer_requisition_id = NULL
    await page.goto('http://127.0.0.1:8000/admin/loss-ledgers');
    await expect(page.locator('text=Shortfall')).toBeVisible();
    await expect(page.locator('text=10')).toBeVisible(); // lost_base_qty
    await expect(page.locator('text=5')).toBeVisible(); // damaged_base_qty

    // Check that the requisition reference shows as empty/dash (NULL)
    await expect(page.locator('text=—')).toBeVisible();
  });

  test('record loss via TransferRequisition record action', async ({ page }) => {
    // First create and dispatch a requisition
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

    // Submit -> Confirm -> Dispatch
    await page.click('text=SUBMIT REQUEST');
    await page.click('button:has-text("SUBMIT")');
    await page.click('text=CONFIRM');
    await page.click('button:has-text("CONFIRM")');
    await page.click('text=DISPATCH');
    await page.click('button:has-text("DISPATCH")');
    await expect(page.locator('text=Dispatched')).toBeVisible();

    // Now record loss via the record action
    await page.click('text=RECORD LOSS');
    await expect(page.locator('text=RECORD LOSS')).toBeVisible();
    await page.selectOption('select[name="product_variant_id"]', { index: 1 });
    await page.selectOption('select[name="loss_category"]', 'damage');
    await page.fill('input[name="lost_base_qty"]', '0');
    await page.fill('input[name="damaged_base_qty"]', '10');
    await page.fill('input[name="total_financial_loss"]', '200.00');
    await page.fill('textarea[name="notes"]', 'Transit damage recorded during receive');
    await page.click('button:has-text("RECORD LOSS")');

    await expect(page.locator('text=Recorded successfully')).toBeVisible({ timeout: 10000 });

    // Verify LossLedger row was created with transfer_requisition_id set
    await page.goto('http://127.0.0.1:8000/admin/loss-ledgers');
    await expect(page.locator('text=Damage')).toBeVisible();
    await expect(page.locator('text=10')).toBeVisible(); // damaged_base_qty
    // Should have a requisition reference (not dash)
    await expect(page.locator('text=/TR-\\d+/')).toBeVisible();
  });
});