import { test, expect } from '@playwright/test';

test.describe('DirectTransferResource E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/admin.json' });

  test('full direct transfer lifecycle: create -> submit -> receive both warehouses', async ({ page }) => {
    // Step 1: Create direct transfer
    await page.goto('http://127.0.0.1:8000/admin/direct-transfers/create');
    await expect(page.getByRole('heading', { name: 'Create Stock Movement' })).toBeVisible();

    // Step 1: Routing Pathways - use combobox Filament Select
    await page.getByRole('combobox', { name: 'ORIGIN WAREHOUSE*' }).click();
    await page.getByRole('option', { name: 'Manila Main Warehouse' }).click();
    await page.getByRole('combobox', { name: 'DESTINATION WAREHOUSE*' }).click();
    await page.getByRole('option', { name: 'Cebu Branch Warehouse' }).click();
    await page.getByRole('button', { name: 'Next' }).click();

    // Step 2: Item Selection
    await expect(page.locator('text=Stock Allocation')).toBeVisible();
    await page.getByRole('combobox', { name: 'PRODUCT VARIANT*' }).click();
    await page.getByRole('option', { name: 'PROD-ARB-500G - 500g Whole Bean' }).click();
    await page.getByRole('spinbutton', { name: 'BASE UNITS TO TRANSFER*' }).fill('50');
    await page.getByRole('textbox', { name: 'AUDIT NOTES' }).fill('Routine transfer');
    await page.getByRole('button', { name: 'Next' }).click();

    // Step 3: Review & Confirm
    await expect(page.locator('text=Review & Confirm')).toBeVisible();

    // Listen for console errors
    page.on('console', msg => console.log('Browser console:', msg.type(), msg.text()));

    // Click wizard submit button ("EXECUTE TRANSFER") and wait for response
    await page.getByRole('button', { name: 'EXECUTE TRANSFER', exact: true }).waitFor({ state: 'visible', timeout: 15000 });
    const [response] = await Promise.all([
      page.waitForResponse(response => response.url().includes('/livewire') && response.status() === 200, { timeout: 30000 }),
      page.getByRole('button', { name: 'EXECUTE TRANSFER', exact: true }).click(),
    ]);

    console.log('Response status:', response.status());
    console.log('Response body:', await response.text());

    // Check for notification - wait for redirect first as notification might appear after
    await page.waitForURL('**/admin/direct-transfers', { timeout: 15000 }).catch(() => console.log('No redirect, checking current page'));
    await page.waitForTimeout(2000); // allow notification to render
    await expect(page.getByText('Transfer Executed')).toBeVisible({ timeout: 15000 });

    // Check if page redirects
    await page.waitForURL('**/admin/direct-transfers', { timeout: 5000 }).catch(() => console.log('No redirect'));

    // Get reference code
    const referenceCode = await page.locator('text=/DR-\d+/').first().textContent({ timeout: 5000 }).catch(() => null);
    console.log('Created direct transfer:', referenceCode);

    // Step 4: Receive at destination warehouse
    await page.click('text=RECEIVE AT DESTINATION');
    await page.fill('input[name*="good_qty"]', '50');
    await page.fill('input[name*="audit_reason"]', 'Received successfully');
    await page.getByRole('button', { name: 'CONFIRM INTAKE' }).click();
    await expect(page.locator('text=Received')).toBeVisible({ timeout: 10000 });

    // Verify stock movements at both warehouses
    await page.goto('http://127.0.0.1:8000/admin/stock-movements');
    await expect(page.locator('text=TransferOut')).toBeVisible();
    await expect(page.locator('text=TransferIn')).toBeVisible();
  });
});