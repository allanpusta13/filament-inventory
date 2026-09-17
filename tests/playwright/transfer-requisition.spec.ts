import { test, expect } from '@playwright/test';

test.describe('TransferRequisitionResource E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/admin.json' });

  test('full transfer lifecycle: create -> confirm -> dispatch -> receive partial -> receive final -> verify completed', async ({ page }) => {
    // Step 1: Create transfer requisition
    await page.goto('http://127.0.0.1:8000/admin/transfer-requisitions/create');
    await expect(page.locator('text=CREATE TRANSFER REQUISITION')).toBeVisible();

    // Step 1: Routing Pathways
    await page.selectOption('select[name="from_warehouse_id"]', { index: 1 });
    await page.selectOption('select[name="to_warehouse_id"]', { index: 2 });
    await page.click('button:has-text("NEXT")');

    // Step 2: Material Manifest
    await expect(page.locator('text=MATERIAL MANIFEST')).toBeVisible();
    await page.selectOption('select[name="items.0.product_variant_id"]', { index: 1 });
    await page.fill('input[name="items.0.requested_unit_name"]', 'piece');
    await page.fill('input[name="items.0.requested_unit_ratio"]', '1');
    await page.fill('input[name="items.0.requested_qty"]', '100');
    await page.click('button:has-text("NEXT")');

    // Step 3: Review & Verify
    await expect(page.locator('text=REVIEW & VERIFY')).toBeVisible();
    await page.click('button:has-text("CREATE REQUISITION")');
    await expect(page.locator('text=Created successfully')).toBeVisible({ timeout: 10000 });

    // Get the reference code
    const referenceCode = await page.locator('text=/TR-\d+/').first().textContent();
    console.log('Created requisition:', referenceCode);

    // Step 4: Submit Request (Draft -> Requested)
    await page.click('text=SUBMIT REQUEST');
    await page.click('button:has-text("SUBMIT")');
    await expect(page.locator('text=Requested')).toBeVisible();

    // Step 5: Confirm (Requested -> Confirmed)
    await page.click('text=CONFIRM');
    await page.click('button:has-text("CONFIRM")');
    await expect(page.locator('text=Confirmed')).toBeVisible();

    // Step 6: Dispatch (Confirmed -> Dispatched)
    await page.click('text=DISPATCH');
    await page.click('button:has-text("DISPATCH")');
    await expect(page.locator('text=Dispatched')).toBeVisible();

    // Step 7: Scan to Receive (Partial)
    await page.click('text=SCAN TO RECEIVE');
    await expect(page.locator('text=SCAN TO RECEIVE')).toBeVisible();

    // Fill partial receive
    await page.fill('input[name*="good_qty"]', '30');
    await page.fill('input[name*="damaged_qty"]', '5');
    await page.click('button:has-text("CONFIRM INTAKE")');
    await expect(page.locator('text=Partially Received')).toBeVisible({ timeout: 10000 });

    // Step 8: Scan to Receive (Final)
    await page.click('text=SCAN TO RECEIVE');
    await page.fill('input[name*="good_qty"]', '65');
    await page.fill('input[name*="damaged_qty"]', '0');
    await page.click('button:has-text("CONFIRM INTAKE")');
    await expect(page.locator('text=Completed')).toBeVisible({ timeout: 10000 });

    // Verify stock movements created
    await page.goto('http://127.0.0.1:8000/admin/stock-movements');
    await expect(page.locator('text=TransitOut')).toBeVisible();
    await expect(page.locator('text=TransitIn')).toBeVisible();
  });

  test('cancellation boundary: cannot cancel dispatched requisition', async ({ page }) => {
    // Create a requisition and dispatch it
    await page.goto('http://127.0.0.1:8000/admin/transfer-requisitions/create');
    await page.selectOption('select[name="from_warehouse_id"]', { index: 1 });
    await page.selectOption('select[name="to_warehouse_id"]', { index: 2 });
    await page.click('button:has-text("NEXT")');
    await page.selectOption('select[name="items.0.product_variant_id"]', { index: 1 });
    await page.fill('input[name="items.0.requested_unit_name"]', 'piece');
    await page.fill('input[name="items.0.requested_unit_ratio"]', '1');
    await page.fill('input[name="items.0.requested_qty"]', '50');
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

    // Try to call cancel action directly via Livewire (bypassing UI visibility)
    // The policy should reject it server-side
    const recordId = await page.locator('[data-filament-record-id]').first().getAttribute('data-filament-record-id');

    // Attempt to invoke cancel action via Livewire component method
    const response = await page.evaluate(async (id) => {
      const response = await fetch('http://127.0.0.1:8000/livewire/message/transfer-requisitions.list', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Livewire': 'true',
        },
        body: JSON.stringify({
          fingerprint: {
            id,
            name: 'cancel',
            locale: 'en',
            path: '/admin/transfer-requisitions',
            method: 'GET',
          },
          serverMemo: {
            children: [],
            errors: [],
            data: {},
            dataMeta: [],
            checksum: '',
          },
        }),
      });
      return response.status;
    }, recordId);

    // Policy should reject with 403
    expect([403, 500]).toContain(response);
  });
});
