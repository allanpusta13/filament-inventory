import { test, expect } from '@playwright/test';

test.describe('DirectTransferResource E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/admin.json' });

  test('full direct transfer lifecycle: create -> submit -> receive at both warehouses', async ({ page }) => {
    // Step 1: Create direct transfer
    await page.goto('http://127.0.0.1:8000/admin/direct-transfers/create');
    await expect(page.locator('text=CREATE DIRECT TRANSFER')).toBeVisible();

    // Step 1: Routing Pathways
    await page.selectOption('select[name="from_warehouse_id"]', { index: 1 });
    await page.selectOption('select[name="to_warehouse_id"]', { index: 2 });
    await page.click('button:has-text("NEXT")');

    // Step 2: Item Selection
    await expect(page.locator('text=ITEM SELECTION')).toBeVisible();
    await page.selectOption('select[name="items.0.product_variant_id"]', { index: 1 });
    await page.fill('input[name="items.0.requested_qty"]', '50');
    await page.fill('input[name="items.0.audit_reason"]', 'Routine transfer');
    await page.click('button:has-text("NEXT")');

    // Step 3: Review & Confirm
    await expect(page.locator('text=REVIEW & CONFIRM')).toBeVisible();
    await page.click('button:has-text("CONFIRM TRANSFER")');
    await expect(page.locator('text=Transfer Created')).toBeVisible({ timeout: 10000 });

    // Get reference code
    const referenceCode = await page.locator('text=/DR-\d+/').first().textContent();
    console.log('Created direct transfer:', referenceCode);

    // Step 4: Receive at destination warehouse
    await page.click('text=RECEIVE AT DESTINATION');
    await page.fill('input[name*="good_qty"]', '50');
    await page.fill('input[name*="audit_reason"]', 'Received successfully');
    await page.click('button:has-text("CONFIRM INTAKE")');
    await expect(page.locator('text=Received')).toBeVisible({ timeout: 10000 });

    // Verify stock movements at both warehouses
    await page.goto('http://127.0.0.1:8000/admin/stock-movements');
    await expect(page.locator('text=TransferOut')).toBeVisible();
    await expect(page.locator('text=TransferIn')).toBeVisible();
  });
});
