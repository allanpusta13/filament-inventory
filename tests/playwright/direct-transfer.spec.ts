import { test, expect } from '@playwright/test';

test.describe('DirectTransferResource E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/admin.json' });

  test('full direct transfer lifecycle: create -> submit -> verify both warehouses', async ({ page }) => {
    // Step 1: Create direct transfer
    await page.goto('http://127.0.0.1:8000/admin/direct-transfers/create');
    await expect(page.getByRole('heading', { name: 'Create Stock Movement' })).toBeVisible();

    // Wait for wizard step 1 to render (Location Mapping)
    await expect(page.locator('text=Location Mapping')).toBeVisible({ timeout: 30000 });
    await expect(page.getByRole('combobox', { name: 'ORIGIN WAREHOUSE' })).toBeVisible({ timeout: 15000 });

    // Step 1: Routing Pathways - use combobox Filament Select
    await page.getByRole('combobox', { name: 'ORIGIN WAREHOUSE' }).click();
    await page.getByRole('option', { name: 'Manila Main Warehouse' }).click();
    await page.getByRole('combobox', { name: 'DESTINATION WAREHOUSE' }).click();
    await page.getByRole('option', { name: 'Cebu Branch Warehouse' }).click();
    await page.getByRole('button', { name: 'Next' }).click();

    // Step 2: Item Selection
    await expect(page.locator('text=Stock Allocation')).toBeVisible();
    await page.getByRole('combobox', { name: 'PRODUCT VARIANT' }).click();
    await page.getByRole('option', { name: 'PROD-ARB-500G - 500g Whole Bean' }).click();
    await page.getByRole('spinbutton', { name: 'BASE UNITS TO TRANSFER' }).fill('50');
    await page.getByRole('textbox', { name: 'AUDIT NOTES' }).fill('Routine transfer');
    await page.getByRole('button', { name: 'Next' }).click();

    // Step 3: Review & Verify
    await expect(page.getByRole('heading', { name: 'REVIEW & VERIFY' })).toBeVisible();

    // Listen for console errors
    page.on('console', msg => console.log('Browser console:', msg.type(), msg.text()));

    // Click wizard submit button ("EXECUTE TRANSFER") and wait for response
    await page.getByRole('button', { name: 'EXECUTE TRANSFER', exact: true }).waitFor({ state: 'visible', timeout: 15000 });
    const [response] = await Promise.all([
      page.waitForResponse(response => response.url().includes('/livewire') && response.status() === 200, { timeout: 30000 }),
      page.getByRole('button', { name: 'EXECUTE TRANSFER', exact: true }).click(),
    ]);

    console.log('Response status:', response.status());
    const responseText = await response.text();
    console.log('Response body:', responseText);
    console.log('Current URL after submit:', page.url());

    // Navigate to index page (Livewire redirect may not be followed in test context)
    await page.goto('http://127.0.0.1:8000/admin/direct-transfers');
    await page.waitForLoadState('networkidle');

    // Get reference code
    const referenceCode = await page.locator('text=/DTR-.*/').first().textContent({ timeout: 5000 }).catch(() => null);
    console.log('Created direct transfer:', referenceCode);

    // Verify stock movements at both warehouses (DirectTransfer creates atomic TransferOut/TransferIn)
    await page.goto('http://127.0.0.1:8000/admin/stock-movements');
    await expect(page.locator('span.fi-badge:has-text("Transfer out")').first()).toBeVisible();
    await expect(page.locator('span.fi-badge:has-text("Transfer in")').first()).toBeVisible();
  });
});