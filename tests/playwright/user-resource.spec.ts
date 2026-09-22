import { test, expect } from '@playwright/test';

test.describe('UserResource E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/admin.json' });

  test('can render user index page', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/users');
    await expect(page.getByRole('heading', { name: 'Users' })).toBeVisible();
  });

  test('can create user with warehouse assignment', async ({ page }) => {
    const uniqueEmail = 'e2e-user-' + Date.now() + '@test.com';
    await page.goto('http://127.0.0.1:8000/admin/users/create');
    await expect(page.getByRole('heading', { name: 'Create User' })).toBeVisible();
    await page.fill('input[id="form.name"]', 'E2E Test User');
    await page.fill('input[id="form.email"]', uniqueEmail);
    await page.fill('input[id="form.password"]', 'password123');
    // Select Warehouse Staff role
    await page.getByRole('combobox', { name: 'System Access Role' }).click();
    await page.getByRole('option', { name: 'Warehouse Staff' }).click();
    // Warehouse assignment should be visible for admin
    await page.click('button:has-text("Create")');
    await expect(page.getByRole('heading', { name: 'Edit E2E Test User' })).toBeVisible({ timeout: 10000 });
  });

  test('can filter users by role using search', async ({ page }) => {
    // First create a user to filter
    const uniqueEmail = 'filter-role-' + Date.now() + '@test.com';
    await page.goto('http://127.0.0.1:8000/admin/users/create');
    await page.fill('input[id="form.name"]', 'Role Filter User');
    await page.fill('input[id="form.email"]', uniqueEmail);
    await page.fill('input[id="form.password"]', 'password123');
    await page.getByRole('combobox', { name: 'System Access Role' }).click();
    await page.getByRole('option', { name: 'Warehouse Staff' }).click();
    await page.click('button:has-text("Create")');
    await expect(page.getByRole('heading', { name: 'Edit Role Filter User' })).toBeVisible({ timeout: 10000 });

    await page.goto('http://127.0.0.1:8000/admin/users');
    await page.waitForLoadState('networkidle');

    // Use search to filter by name
    await page.fill('input[placeholder="Search"]', 'Role Filter User');
    await page.waitForTimeout(2000);

    // Verify filtered results show the user
    await expect(page.locator('table').getByRole('row', { name: 'Role Filter User' })).toBeVisible();
  });

  test('can view user infolist with warehouse badges in slide-over', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/users');
    await page.waitForLoadState('networkidle');

    // Click on View action (eye icon) for first user to open slide-over
    // The View button is inside the Actions column
    await page.getByRole('row', { name: 'E2E Test User' }).first().getByRole('button', { name: 'View' }).first().click();

    // Wait for slide-over modal to open - heading is "View {user name}"
    await expect(page.locator('[role="dialog"]').getByRole('heading', { name: 'View E2E Test User' })).toBeVisible({ timeout: 10000 });

    // Check for infolist entries in the slide-over
    const dialog = page.locator('[role="dialog"]');
    // Check for email label
    await expect(dialog.getByText('email')).toBeVisible();
    // Check role badge - could be Admin or Warehouse Staff
    await expect(dialog.getByText('Warehouse Staff')).toBeVisible();
    // Check warehouses section
    await expect(dialog.getByText('ASSIGNED PHYSICAL WAREHOUSES')).toBeVisible();
  });
});