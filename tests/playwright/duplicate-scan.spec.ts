import { test, expect } from '@playwright/test';

test.describe('Duplicate Scan Submission E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/user.json' });

  test('duplicate scan submission: submit identical scan-to-receive payload twice rapidly -> verify only one set of stock movements and loss ledger rows created', async ({ page }) => {
    // Create and dispatch a requisition
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

    // Navigate to scan page
    await page.click('text=SCAN TO RECEIVE');
    await expect(page.locator('text=SCAN TO RECEIVE')).toBeVisible();

    // Fill receive form
    await page.fill('input[name*="good_qty"]', '50');
    await page.fill('input[name*="damaged_qty"]', '5');

    // Submit first time
    await page.click('button:has-text("CONFIRM INTAKE")');
    await expect(page.locator('text=Partially Received')).toBeVisible({ timeout: 10000 });

    // Record the stock movement count before duplicate
    await page.goto('http://127.0.0.1:8000/admin/stock-movements');
    const initialTransitInCount = await page.locator('text=TransitIn').count();
    const initialLossLedgerCount = await page.locator('text=Shortfall').count();

    // Go back to scan page and submit identical payload again (simulating double-tap)
    await page.goto('http://127.0.0.1:8000/admin/transfer-requisitions');
    await page.click('text=SCAN TO RECEIVE');

    // Fill same values again
    await page.fill('input[name*="good_qty"]', '50');
    await page.fill('input[name*="damaged_qty"]', '5');
    await page.click('button:has-text("CONFIRM INTAKE")');
    await expect(page.locator('text=Partially Received')).toBeVisible({ timeout: 10000 });

    // Verify stock movements count didn't increase (idempotent)
    await page.goto('http://127.0.0.1:8000/admin/stock-movements');
    const finalTransitInCount = await page.locator('text=TransitIn').count();
    const finalLossLedgerCount = await page.locator('text=Shortfall').count();

    // Should be the same - idempotent
    expect(finalTransitInCount).toBe(initialTransitInCount);
    expect(finalLossLedgerCount).toBe(initialLossLedgerCount);
  });

  test('duplicate scan with different quantities should create new movements', async ({ page }) => {
    // Create and dispatch a requisition
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

    // First scan: receive 30 good, 0 damaged
    await page.click('text=SCAN TO RECEIVE');
    await page.fill('input[name*="good_qty"]', '30');
    await page.fill('input[name*="damaged_qty"]', '0');
    await page.click('button:has-text("CONFIRM INTAKE")');
    await expect(page.locator('text=Partially Received')).toBeVisible({ timeout: 10000 });

    // Record counts
    await page.goto('http://127.0.0.1:8000/admin/stock-movements');
    const afterFirstCount = await page.locator('text=TransitIn').count();

    // Second scan: receive 40 good, 10 damaged (different from first)
    await page.goto('http://127.0.0.1:8000/admin/transfer-requisitions');
    await page.click('text=SCAN TO RECEIVE');
    await page.fill('input[name*="good_qty"]', '40');
    await page.fill('input[name*="damaged_qty"]', '10');
    await page.click('button:has-text("CONFIRM INTAKE")');
    await expect(page.locator('text=Partially Received')).toBeVisible({ timeout: 10000 });

    // Verify new movements created
    await page.goto('http://127.0.0.1:8000/admin/stock-movements');
    const afterSecondCount = await page.locator('text=TransitIn').count();

    // Should have more movements now
    expect(afterSecondCount).toBeGreaterThan(afterFirstCount);
  });
});