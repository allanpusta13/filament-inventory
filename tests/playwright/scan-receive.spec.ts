import { test, expect } from '@playwright/test';

test.describe('ScanToReceive E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/admin.json' });

  test('can create transfer requisition', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/transfer-requisitions');
    await expect(page.getByRole('heading', { name: 'Transfer Requisitions' })).toBeVisible({ timeout: 30000 });

    // Click NEW TRANSFER REQUEST
    await page.getByRole('button', { name: 'NEW TRANSFER REQUEST' }).click();
    await expect(page.locator('text=Routing Pathways')).toBeVisible({ timeout: 15000 });

    // Step 1: Routing
    await page.getByRole('combobox', { name: 'ORIGIN WAREHOUSE (FULFILLER)' }).click();
    await page.getByRole('option', { name: 'Manila Main Warehouse' }).click();
    await page.getByRole('combobox', { name: 'DESTINATION WAREHOUSE (REQUESTOR)' }).click();
    await page.getByRole('option', { name: 'Davao Branch Warehouse' }).click();
    // Next button in wizard dialog (not pagination)
    await page.getByRole('dialog', { name: 'CREATE INTER-WAREHOUSE REQUISITION' }).getByRole('button', { name: 'Next' }).click();

    // Step 2: Items
    await expect(page.locator('text=REQUESTED MATERIAL MANIFEST')).toBeVisible();
    // Use the select in the repeater row - it's a relationship select with search
    const combobox = page.locator('table tr:first-child').locator('[role="combobox"]').first();
    await combobox.click();
    // Type in the search box to filter options
    await page.getByRole('textbox', { name: 'Search' }).fill('PROD-ARB-500G');
    // Wait for filtered option and click
    await page.getByRole('option', { name: 'PROD-ARB-500G' }).click();
    // PACKAGING FORMAT is a textbox with placeholder "Box", no accessible name
    await page.locator('table tr:first-child td:nth-child(2) input[placeholder="Box"]').fill('Box');
    // UNIT RATIO spinbutton - 3rd column
    await page.locator('table tr:first-child td:nth-child(3) input[type="number"]').fill('1');
    // ORDER QUANTITY spinbutton - 4th column
    await page.locator('table tr:first-child td:nth-child(4) input[type="number"]').fill('10');
    // Next button in wizard dialog (not pagination)
    await page.getByRole('dialog', { name: 'CREATE INTER-WAREHOUSE REQUISITION' }).getByRole('button', { name: 'Next' }).click();

    // Step 3: Review & Submit
    await expect(page.getByRole('heading', { name: 'REVIEW & CONFIRM' })).toBeVisible();
    await page.getByRole('button', { name: 'SUBMIT REQUISITION' }).click();
    await expect(page.getByRole('heading', { name: 'Transfer Requisitions' })).toBeVisible({ timeout: 15000 });

    // Get the created requisition reference code
    const refCode = await page.locator('text=TRQ-').first().textContent({ timeout: 5000 });
    console.log('Created requisition:', refCode?.trim());
  });

  test('full scan-to-receive flow: create -> confirm -> dispatch -> scan receive', async ({ page, context }) => {
    // Step 1: Create transfer requisition
    await page.goto('http://127.0.0.1:8000/admin/transfer-requisitions');
    await expect(page.getByRole('heading', { name: 'Transfer Requisitions' })).toBeVisible({ timeout: 30000 });

    await page.getByRole('button', { name: 'NEW TRANSFER REQUEST' }).click();
    await expect(page.locator('text=Routing Pathways')).toBeVisible({ timeout: 15000 });

    await page.getByRole('combobox', { name: 'ORIGIN WAREHOUSE (FULFILLER)' }).click();
    await page.getByRole('option', { name: 'Manila Main Warehouse' }).click();
    await page.getByRole('combobox', { name: 'DESTINATION WAREHOUSE (REQUESTOR)' }).click();
    await page.getByRole('option', { name: 'Davao Branch Warehouse' }).click();
    // Next button in wizard dialog (not pagination)
    await page.getByRole('dialog', { name: 'CREATE INTER-WAREHOUSE REQUISITION' }).getByRole('button', { name: 'Next' }).click();

    await expect(page.locator('text=REQUESTED MATERIAL MANIFEST')).toBeVisible();
    // Use the select in the repeater row - it's a relationship select with search
    const combobox = page.locator('table tr:first-child').locator('[role="combobox"]').first();
    await combobox.click();
    // Type in the search box to filter options
    await page.getByRole('textbox', { name: 'Search' }).fill('PROD-ARB-500G');
    // Wait for filtered option and click
    await page.getByRole('option', { name: 'PROD-ARB-500G' }).click();
    // PACKAGING FORMAT is a textbox with placeholder "Box", no accessible name
    await page.locator('table tr:first-child td:nth-child(2) input[placeholder="Box"]').fill('Box');
    // UNIT RATIO spinbutton - 3rd column
    await page.locator('table tr:first-child td:nth-child(3) input[type="number"]').fill('1');
    // ORDER QUANTITY spinbutton - 4th column
    await page.locator('table tr:first-child td:nth-child(4) input[type="number"]').fill('10');
    // Next button in wizard dialog (not pagination)
    await page.getByRole('dialog', { name: 'CREATE INTER-WAREHOUSE REQUISITION' }).getByRole('button', { name: 'Next' }).click();

    await expect(page.getByRole('heading', { name: 'REVIEW & CONFIRM' })).toBeVisible();
    await page.getByRole('button', { name: 'SUBMIT REQUISITION' }).click();
    await expect(page.getByRole('heading', { name: 'Transfer Requisitions' })).toBeVisible({ timeout: 15000 });

    // Get the created requisition reference code
    const refCodeLocator = page.locator('text=TRQ-').first();
    const refCode = await refCodeLocator.textContent({ timeout: 5000 });
    console.log('Created requisition:', refCode?.trim());

    // Click on the View button to view the requisition (first row, actions column)
    const viewButton = page.locator('table tr:first-child td:last-child a:has-text("View")').first();
    await viewButton.click();
    await expect(page.getByRole('heading', { name: 'TRQ-' })).toBeVisible({ timeout: 15000 });

    // Step 2: Confirm the requisition
    const confirmButton = page.getByRole('button', { name: 'CONFIRM' });
    await expect(confirmButton).toBeVisible({ timeout: 15000 });
    await confirmButton.click();
    // Handle confirmation dialog - it's an alertdialog
    await page.getByRole('alertdialog', { name: 'CONFIRM' }).getByRole('button', { name: 'Confirm' }).click();
    await expect(page.locator('text=Confirmed')).toBeVisible({ timeout: 15000 });

    // Step 3: Dispatch the requisition
    const dispatchButton = page.getByRole('button', { name: 'DISPATCH' });
    await expect(dispatchButton).toBeVisible({ timeout: 15000 });
    await dispatchButton.click();
    // Handle dispatch confirmation dialog - it's an alertdialog with "Confirm" button
    await page.getByRole('alertdialog', { name: 'DISPATCH' }).getByRole('button', { name: 'Confirm' }).click();
    // Wait for status to update to Dispatched
    await expect(page.locator('text=Dispatched')).toBeVisible({ timeout: 15000 });
    // Force page reload to re-render Livewire component and show SCAN TO RECEIVE button
    await page.reload();
    await expect(page.getByRole('heading', { name: 'TRQ-' })).toBeVisible({ timeout: 15000 });

    // Step 4: Get the signed URL for scan-to-receive (it's a link, not a button)
    const scanToReceiveLink = page.getByRole('link', { name: 'SCAN TO RECEIVE' });
    await expect(scanToReceiveLink).toBeVisible({ timeout: 15000 });

    const scanUrl = await scanToReceiveLink.getAttribute('href');
    console.log('Scan URL:', scanUrl);

    // Step 5: Switch to warehouse-staff auth state and test scan-receive page
    const staffContext = await context.browser()?.newContext({
      storageState: './tests/playwright/.auth/warehouse-staff.json'
    });

    if (!staffContext) {
      throw new Error('Could not create warehouse-staff context');
    }

    const staffPage = await staffContext.newPage();

    if (scanUrl) {
      await staffPage.goto(scanUrl);
      await expect(staffPage.getByRole('heading', { name: 'SCAN-TO-RECEIVE RECONCILIATION' })).toBeVisible({ timeout: 30000 });

      // Verify the page shows the correct warehouses
      await expect(staffPage.locator('text=Manila Main Warehouse')).toBeVisible();
      await expect(staffPage.locator('text=Davao Branch Warehouse')).toBeVisible();

      // Verify the item is displayed
      await expect(staffPage.locator('text=PROD-ARB-500G')).toBeVisible();
      await expect(staffPage.locator('text=10')).toBeVisible(); // Expected quantity

      console.log('Scan-to-receive page loaded successfully with signed URL');

      // Test filling in good and damaged quantities
      const goodQtyInput = staffPage.locator('input[name="received_items[0][good_qty]"]').first();
      const damagedQtyInput = staffPage.locator('input[name="received_items[0][damaged_qty]"]').first();

      await goodQtyInput.fill('8');
      await damagedQtyInput.fill('1');

      // Lost qty should auto-calculate to 1
      await expect(staffPage.locator('.lost-qty-0')).toContainText('1');

      // Select loss reason
      const lossReasonSelect = staffPage.locator('select[name="received_items[0][loss_category]"]').first();
      await lossReasonSelect.selectOption('Damaged in Transit');

      // Submit the form
      await staffPage.getByRole('button', { name: 'Process Receiving' }).click();

      // Should redirect to dashboard with success notification
      await expect(staffPage.locator('text=Receiving Complete')).toBeVisible({ timeout: 15000 });

      console.log('Scan-to-receive flow completed successfully');
    }

    await staffContext.close();
  });

  test('can submit scan form with good and damaged qty', async ({ page }) => {
    test.skip('covered by full scan-to-receive flow test');
  });

  test('can submit final scan to complete transfer', async ({ page }) => {
    test.skip('covered by full scan-to-receive flow test');
  });
});