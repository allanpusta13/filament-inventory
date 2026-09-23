import { test, expect } from '@playwright/test';

test.describe('ScanToReceive E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/admin.json' });

  test('can create transfer requisition', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/transfer-requisitions');
    await expect(page.getByRole('heading', { name: 'Transfer Requisitions' })).toBeVisible({ timeout: 30000 });

    // Click NEW TRANSFER REQUEST - opens modal wizard
    await page.getByRole('button', { name: 'NEW TRANSFER REQUEST' }).click();

    // Wait modal render - wizard content directly in dialog (no iframe in Filament v5)
    await page.waitForTimeout(2000);

    // Verify wizard step 1 is visible
    await expect(page.getByText('Routing Pathways')).toBeVisible({ timeout: 15000 });

    // Step 1: Routing - interact elements in dialog
    await page.getByRole('combobox', { name: 'ORIGIN WAREHOUSE (FULFILLER)' }).click();
    await page.getByRole('option', { name: 'Manila Main Warehouse' }).click();
    await page.getByRole('combobox', { name: 'DESTINATION WAREHOUSE (REQUESTOR)' }).click();
    await page.getByRole('option', { name: 'Davao Branch Warehouse' }).click();
    // Next button in wizard dialog (not pagination)
    await page.getByRole('button', { name: 'Next' }).click();

    // Step 2: Items
    await expect(page.locator('text=REQUESTED MATERIAL MANIFEST')).toBeVisible();
    // Use select in repeater row - it's relationship select search
    const combobox = page.locator('table tr:first-child').locator('[role="combobox"]').first();
    await combobox.click();
    // Type in search box to filter options
    await page.getByRole('textbox', { name: 'Search' }).fill('PROD-ARB-500G');
    // Wait for filtered option and click
    await page.getByRole('option', { name: 'PROD-ARB-500G' }).click();
    // PACKAGING FORMAT textbox placeholder "Box", no accessible name
    await page.locator('table tr:first-child td:nth-child(2) input[placeholder="Box"]').fill('Box');
    // UNIT RATIO spinbutton - 3rd column
    await page.locator('table tr:first-child td:nth-child(3) input[type="number"]').fill('1');
    // ORDER QUANTITY spinbutton - 4th column
    await page.locator('table tr:first-child td:nth-child(4) input[type="number"]').fill('10');
    // Next button in wizard dialog (not pagination)
    await page.getByRole('button', { name: 'Next' }).click();

    // Step 3: Review & Submit
    await expect(page.getByRole('heading', { name: 'REVIEW & CONFIRM' })).toBeVisible();
    await page.getByRole('button', { name: 'SUBMIT REQUISITION' }).click();
    await expect(page.getByRole('heading', { name: 'Transfer Requisitions' })).toBeVisible({ timeout: 15000 });

    // Get created requisition reference code
    const refCode = await page.locator('text=TRQ-').first().textContent({ timeout: 5000 });
    console.log('Created requisition:', refCode?.trim());
  });

  test('full scan-to-receive flow: create -> confirm -> dispatch -> scan receive', async ({ page, context }) => {
    // Step 1: Create transfer requisition
    await page.goto('http://127.0.0.1:8000/admin/transfer-requisitions');
    await expect(page.getByRole('heading', { name: 'Transfer Requisitions' })).toBeVisible({ timeout: 30000 });

    await page.getByRole('button', { name: 'NEW TRANSFER REQUEST' }).click();

    // Wait modal render - wizard content directly in dialog (no iframe in Filament v5)
    await page.waitForTimeout(2000);

    // Verify wizard step 1 is visible
    await expect(page.getByText('Routing Pathways')).toBeVisible({ timeout: 15000 });

    // Now interact elements in dialog
    await page.getByRole('combobox', { name: 'ORIGIN WAREHOUSE (FULFILLER)' }).click();
    await page.getByRole('option', { name: 'Manila Main Warehouse' }).click();
    await page.getByRole('combobox', { name: 'DESTINATION WAREHOUSE (REQUESTOR)' }).click();
    await page.getByRole('option', { name: 'Davao Branch Warehouse' }).click();
    // Next button in wizard dialog (not pagination)
    await page.getByRole('button', { name: 'Next' }).click();

    await expect(page.locator('text=REQUESTED MATERIAL MANIFEST')).toBeVisible();
    // Use select in repeater row - it's relationship select search
    const combobox = page.locator('table tr:first-child').locator('[role="combobox"]').first();
    await combobox.click();
    // Type in search box to filter options
    await page.getByRole('textbox', { name: 'Search' }).fill('PROD-ARB-500G');
    // Wait for filtered option click
    await page.getByRole('option', { name: 'PROD-ARB-500G' }).click();
    // PACKAGING FORMAT textbox placeholder "Box", no accessible name
    await page.locator('table tr:first-child td:nth-child(2) input[placeholder="Box"]').fill('Box');
    // UNIT RATIO spinbutton - 3rd column
    await page.locator('table tr:first-child td:nth-child(3) input[type="number"]').fill('1');
    // ORDER QUANTITY spinbutton - 4th column
    await page.locator('table tr:first-child td:nth-child(4) input[type="number"]').fill('10');
    // Next button in wizard dialog (not pagination)
    await page.getByRole('button', { name: 'Next' }).click();

    await expect(page.getByRole('heading', { name: 'REVIEW & CONFIRM' })).toBeVisible();
    await page.getByRole('button', { name: 'SUBMIT REQUISITION' }).click();
    await expect(page.getByRole('heading', { name: 'Transfer Requisitions' })).toBeVisible({ timeout: 15000 });

    // Get created requisition reference code
    const refCodeLocator = page.locator('text=TRQ-').first();
    const refCode = await refCodeLocator.textContent({ timeout: 5000 });
    console.log('Created requisition:', refCode?.trim());

    // Step 2: View and confirm
    const viewButton = page.locator('table tr:first-child td:last-child a:has-text("View")').first();
    await viewButton.click();
    await expect(page.getByRole('heading', { name: /TRQ-/ })).toBeVisible({ timeout: 15000 });

    const confirmButton = page.getByRole('button', { name: 'CONFIRM' });
    await expect(confirmButton).toBeVisible({ timeout: 15000 });
    await confirmButton.click();
    // Handle confirmation dialog - it's alertdialog
    await page.getByRole('alertdialog', { name: 'CONFIRM' }).getByRole('button', { name: 'Confirm' }).click();
    await expect(page.locator('text=Confirmed')).toBeVisible({ timeout: 15000 });

    // Step 3: Dispatch requisition
    const dispatchButton = page.getByRole('button', { name: 'DISPATCH' });
    await expect(dispatchButton).toBeVisible({ timeout: 15000 });
    await dispatchButton.click();
    // Handle dispatch confirmation dialog - it's alertdialog with "Confirm" button
    await page.getByRole('alertdialog', { name: 'DISPATCH' }).getByRole('button', { name: 'Confirm' }).click();
    // Wait for status update to Dispatched
    await expect(page.locator('text=Dispatched')).toBeVisible({ timeout: 15000 });
    // Force page reload to re-render Livewire component and show SCAN TO RECEIVE button
    await page.reload();
    await expect(page.getByRole('heading', { name: /TRQ-/ })).toBeVisible({ timeout: 15000 });

    // Step 4: Click SCAN TO RECEIVE
    const scanToReceiveButton = page.getByRole('link', { name: 'SCAN TO RECEIVE' });
    await expect(scanToReceiveButton).toBeVisible({ timeout: 15000 });
    await scanToReceiveButton.click();

    // Step 5: Scan to receive page
    await expect(page.getByRole('heading', { name: 'SCAN TO RECEIVE' })).toBeVisible({ timeout: 15000 });

    // Verify it's a reconciliation page
    await expect(page.locator('text=/SCAN-TO-RECEIVE RECONCILIATION|RECEIVE RECONCILIATION/i')).toBeVisible({ timeout: 15000 });
  });
});