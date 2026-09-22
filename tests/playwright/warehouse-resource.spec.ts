import { test, expect } from '@playwright/test';

test.describe('WarehouseResource E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/admin.json' });

  test('can render warehouse index page', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/warehouses');
    await expect(page.getByRole('heading', { name: 'Warehouses' })).toBeVisible();
  });

  test('can create warehouse', async ({ page }) => {
    const uniqueCode = 'WH-E2E-' + Date.now();
    await page.goto('http://127.0.0.1:8000/admin/warehouses/create');
    await expect(page.getByRole('heading', { name: 'Create Warehouse' })).toBeVisible();
    await page.fill('input[id="form.code"]', uniqueCode);
    await page.fill('input[id="form.name"]', 'E2E Test Warehouse');
    await page.fill('input[id="form.location"]', 'Test Location');
    // Skip toggle click - it defaults true clicking triggers Livewire request
    await page.click('button:has-text("Create")');
    await page.waitForLoadState('networkidle');
    // View page shows record title (name) as heading
    await expect(page.getByRole('heading', { name: 'E2E Test Warehouse' })).toBeVisible({ timeout: 15000 });
  });

  test('can edit warehouse', async ({ page }) => {
    // Create warehouse first with unique name
    const uniqueName = 'E2E Test Warehouse Edit ' + Date.now();
    await page.goto('http://127.0.0.1:8000/admin/warehouses/create');
    await page.fill('input[id="form.code"]', 'WH-E2E-EDIT-' + Date.now());
    await page.fill('input[id="form.name"]', uniqueName);
    await page.fill('input[id="form.location"]', 'Edit Location');
    await page.click('button:has-text("Create")');
    await page.waitForLoadState('networkidle');

    // Go back to index and search for our warehouse
    await page.goto('http://127.0.0.1:8000/admin/warehouses');
    await page.waitForLoadState('networkidle');

    // Search for the warehouse by name
    await page.fill('input[placeholder="Search"]', uniqueName);
    await page.waitForLoadState('networkidle');

    // Click the warehouse name link in the table to go to view page
    await page.getByRole('link', { name: uniqueName }).first().click();
    await page.waitForLoadState('networkidle');

    // Wait for view page to load - heading should be "View {name}"
    await expect(page.getByRole('heading', { name: 'View ' + uniqueName })).toBeVisible({ timeout: 10000 });

    // Click Edit link in view page header actions - scope to header area to avoid global search/table Edit links
    // Use exact: true to match only the "Edit" button, not breadcrumb link containing "Edit" in warehouse name
    await page.locator('header').getByRole('link', { name: 'Edit', exact: true }).click();
    await page.waitForLoadState('networkidle');

    // Now on edit page (slide-over or page)
    await expect(page.getByRole('heading', { name: 'Edit ' + uniqueName })).toBeVisible({ timeout: 10000 });

    await page.fill('input[id="form.name"]', 'Updated ' + uniqueName);
    await page.click('button:has-text("Save")');
    await page.waitForLoadState('networkidle');

    // Verify update
    await page.goto('http://127.0.0.1:8000/admin/warehouses');
    await page.waitForLoadState('networkidle');
    await expect(page.getByRole('heading', { name: 'Warehouses' })).toBeVisible();

    // Search for the updated name
    await page.fill('input[placeholder="Search"]', 'Updated ' + uniqueName);
    await page.waitForLoadState('networkidle');
    // Wait for Livewire to finish filtering - wait for the search input to have the value
    await page.waitForFunction(
      (searchTerm) => {
        const input = document.querySelector('input[placeholder="Search"]');
        return input && input.value === searchTerm;
      },
      'Updated ' + uniqueName
    );
    // Wait a bit more for table to update
    await page.waitForTimeout(1000);

    await expect(page.getByText('Updated ' + uniqueName)).toBeVisible({ timeout: 10000 });
  });
});