import { test, expect } from '@playwright/test';

test.describe('DirectTransfer E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/admin.json' });

  test('can create direct transfer', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/direct-transfers');
    await expect(page.getByRole('heading', { name: 'Direct Transfers' })).toBeVisible({ timeout: 30000 });

    // Click NEW DIRECT TRANSFER - opens modal wizard
    await page.getByRole('button', { name: 'NEW DIRECT TRANSFER' }).click();

    // Wait modal render - wizard content directly in dialog (no iframe in Filament v5)
    await page.waitForTimeout(2000);

    // Verify wizard step 1 is visible
    await expect(page.getByText('Location Mapping')).toBeVisible({ timeout: 15000 });

    // Step 1: Location Mapping - interact elements in dialog
    await page.getByRole('combobox', { name: 'ORIGIN WAREHOUSE' }).click();
    await page.getByRole('option', { name: 'Manila Main Warehouse' }).click();
    await page.getByRole('combobox', { name: 'DESTINATION WAREHOUSE' }).click();
    await page.getByRole('option', { name: 'Davao Branch Warehouse' }).click();
    // Next button in wizard dialog
    await page.getByRole('button', { name: 'Next' }).click();

    // Step 2: Stock Allocation
    await expect(page.getByText('Stock Allocation')).toBeVisible();
    const combobox = page.locator('[role="combobox"][name="PRODUCT VARIANT"]').first();
    await combobox.click();
    // Type in search box to filter options
    await page.getByRole('textbox', { name: 'Search' }).fill('PROD-ARB-500G');
    // Wait for filtered option and click
    await page.getByRole('option', { name: 'PROD-ARB-500G' }).click();
    // Quantity input
    await page.getByRole('spinbutton', { name: 'BASE UNITS TO TRANSFER' }).fill('10');
    // Notes textarea
    await page.getByRole('textbox', { name: 'AUDIT NOTES' }).fill('Test transfer for E2E testing');
    // Next button in wizard dialog
    await page.getByRole('button', { name: 'Next' }).click();

    // Step 3: Review & Confirm
    await expect(page.getByRole('heading', { name: 'REVIEW & VERIFY' })).toBeVisible();
    await page.getByRole('button', { name: 'EXECUTE TRANSFER' }).click();
    await expect(page.getByRole('heading', { name: 'Direct Transfers' })).toBeVisible({ timeout: 15000 });

    // Verify transfer created - look for DTR- reference code
    const refCode = await page.locator('text=DTR-').first().textContent({ timeout: 5000 });
    console.log('Created direct transfer:', refCode?.trim());
  });

  test('full direct transfer lifecycle: create -> view', async ({ page }) => {
    // Step 1: Create direct transfer
    await page.goto('http://127.0.0.1:8000/admin/direct-transfers');
    await expect(page.getByRole('heading', { name: 'Direct Transfers' })).toBeVisible({ timeout: 30000 });

    await page.getByRole('button', { name: 'NEW DIRECT TRANSFER' }).click();
    await page.waitForTimeout(2000);
    await expect(page.getByText('Location Mapping')).toBeVisible({ timeout: 15000 });

    // Step 1: Location Mapping
    await page.getByRole('combobox', { name: 'ORIGIN WAREHOUSE' }).click();
    await page.getByRole('option', { name: 'Manila Main Warehouse' }).click();
    await page.getByRole('combobox', { name: 'DESTINATION WAREHOUSE' }).click();
    await page.getByRole('option', { name: 'Davao Branch Warehouse' }).click();
    await page.getByRole('button', { name: 'Next' }).click();

    // Step 2: Stock Allocation
    await expect(page.getByText('Stock Allocation')).toBeVisible();
    const combobox = page.locator('[role="combobox"][name="PRODUCT VARIANT"]').first();
    await combobox.click();
    await page.getByRole('textbox', { name: 'Search' }).fill('PROD-ARB-500G');
    await page.getByRole('option', { name: 'PROD-ARB-500G' }).click();
    await page.getByRole('spinbutton', { name: 'BASE UNITS TO TRANSFER' }).fill('5');
    await page.getByRole('textbox', { name: 'AUDIT NOTES' }).fill('Lifecycle test transfer');
    await page.getByRole('button', { name: 'Next' }).click();

    // Step 3: Review & Confirm
    await expect(page.getByRole('heading', { name: 'REVIEW & VERIFY' })).toBeVisible();
    await page.getByRole('button', { name: 'EXECUTE TRANSFER' }).click();
    await expect(page.getByRole('heading', { name: 'Direct Transfers' })).toBeVisible({ timeout: 15000 });

    // Get created transfer reference code
    const refCodeLocator = page.locator('text=DTR-').first();
    const refCode = await refCodeLocator.textContent({ timeout: 5000 });
    console.log('Created direct transfer:', refCode?.trim());

    // Step 2: View the transfer
    const viewButton = page.locator('table tr:first-child td:last-child a:has-text("View")').first();
    await viewButton.click();
    await expect(page.getByRole('heading', { name: /DTR-/ })).toBeVisible({ timeout: 15000 });

    // Verify view page shows transfer details
    await expect(page.locator('text=Manila Main Warehouse')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('text=Davao Branch Warehouse')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('text=PROD-ARB-500G')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('text=Lifecycle test transfer')).toBeVisible({ timeout: 15000 });
  });
});