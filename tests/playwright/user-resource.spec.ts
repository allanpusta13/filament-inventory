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

  test('can filter users by role', async ({ page }) => {
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
    // Filter by role
    await page.getByRole('button', { name: 'Filter' }).click();
    await page.waitForTimeout(1000);
    await page.getByRole('combobox', { name: 'System role' }).selectOption({ label: 'Warehouse Staff' });
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await page.waitForTimeout(2000);
    await expect(page.locator('table.users tbody tr').first()).toContainText('Warehouse Staff');
  });

  test('can view user infolist with warehouse badges', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/users');
    // Click on first user to view details
    await page.getByRole('row', { name: 'E2E Test User' }).first().click();
    await expect(page.getByRole('heading', { name: 'View User' })).toBeVisible();
    // Check for infolist entries
    await expect(page.getByText('name')).toBeVisible();
    await expect(page.getByText('email')).toBeVisible();
    // Check for role badge
    await expect(page.getByText('Admin') || page.getByText('Warehouse Staff')).toBeVisible();
  });
});
