import { test, expect } from '@playwright/test';

async function selectComboboxOption(page: any, label: string, value: string) {
  await page.getByRole('combobox', { name: label }).click();
  await page.getByRole('option', { name: value }).click();
}

test.describe('UserResource E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/user.json' });

  test('can render user index page', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/users');
    await expect(page.getByRole('heading', { name: 'Users' })).toBeVisible();
  });

  test('can create a user', async ({ page }) => {
    const uniqueEmail = `e2e-test-${Date.now()}@test.com`;
    await page.goto('http://127.0.0.1:8000/admin/users/create');
    await expect(page.getByRole('heading', { name: 'Create User' })).toBeVisible();

    await page.fill('input[id="form.name"]', 'E2E Test User');
    await page.fill('input[id="form.email"]', uniqueEmail);
    await page.fill('input[id="form.password"]', 'password123');
    await selectComboboxOption(page, 'System Access Role', 'Warehouse Staff');
    await page.click('button:has-text("Create")');

    await expect(page.getByRole('heading', { name: 'Edit E2E Test User' })).toBeVisible({ timeout: 10000 });
  });

  test('can edit a user', async ({ page }) => {
    const uniqueEmail = `original-${Date.now()}@test.com`;
    await page.goto('http://127.0.0.1:8000/admin/users/create');
    await page.fill('input[id="form.name"]', 'Original User');
    await page.fill('input[id="form.email"]', uniqueEmail);
    await page.fill('input[id="form.password"]', 'password123');
    await selectComboboxOption(page, 'System Access Role', 'Warehouse Staff');
    await page.click('button:has-text("Create")');
    await expect(page.getByRole('heading', { name: 'Edit Original User' })).toBeVisible({ timeout: 10000 });

    await page.fill('input[id="form.name"]', 'Updated User');
    await page.click('button:has-text("Save changes")');
    await page.waitForLoadState('networkidle');
    await expect(page.getByRole('heading', { name: 'Edit Updated User' })).toBeVisible({ timeout: 10000 });
    await expect(page.locator('input[id="form.name"]')).toHaveValue('Updated User');
  });

  test('can delete a user', async ({ page }) => {
    const uniqueEmail = `delete-${Date.now()}@test.com`;
    await page.goto('http://127.0.0.1:8000/admin/users/create');
    await page.fill('input[id="form.name"]', 'Delete User');
    await page.fill('input[id="form.email"]', uniqueEmail);
    await page.fill('input[id="form.password"]', 'password123');
    await selectComboboxOption(page, 'System Access Role', 'Warehouse Staff');
    await page.click('button:has-text("Create")');
    await expect(page.getByRole('heading', { name: 'Edit Delete User' })).toBeVisible({ timeout: 10000 });

    await page.getByRole('button', { name: 'Delete' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click();
    await expect(page.getByRole('heading', { name: 'Users' })).toBeVisible({ timeout: 10000 });
  });

  test('can filter users by search', async ({ page }) => {
    const uniqueEmail = `filter-${Date.now()}@test.com`;
    await page.goto('http://127.0.0.1:8000/admin/users/create');
    await page.fill('input[id="form.name"]', 'Filter Test User');
    await page.fill('input[id="form.email"]', uniqueEmail);
    await page.fill('input[id="form.password"]', 'password123');
    await selectComboboxOption(page, 'System Access Role', 'Warehouse Staff');
    await page.click('button:has-text("Create")');
    await expect(page.getByRole('heading', { name: 'Edit Filter Test User' })).toBeVisible({ timeout: 10000 });

    await page.goto('http://127.0.0.1:8000/admin/users');
    const searchInput = page.getByRole('searchbox', { name: 'Search', exact: true });
    await searchInput.fill('Filter Test User');
    await page.keyboard.press('Enter');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    await expect(page.getByRole('row').nth(1)).toContainText('Filter Test User');
  });

  test('can filter users by role', async ({ page }) => {
    const uniqueEmail = `filter-role-${Date.now()}@test.com`;
    await page.goto('http://127.0.0.1:8000/admin/users/create');
    await page.fill('input[id="form.name"]', 'Role Filter User');
    await page.fill('input[id="form.email"]', uniqueEmail);
    await page.fill('input[id="form.password"]', 'password123');
    await selectComboboxOption(page, 'System Access Role', 'Warehouse Staff');
    await page.click('button:has-text("Create")');
    await expect(page.getByRole('heading', { name: 'Edit Role Filter User' })).toBeVisible({ timeout: 10000 });

    await page.goto('http://127.0.0.1:8000/admin/users');
    await page.getByRole('button', { name: 'Filter' }).click();
    await page.waitForTimeout(1000);
    await page.getByRole('combobox', { name: 'System role' }).selectOption({ label: 'Warehouse Staff' });
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    await expect(page.getByRole('row').nth(1)).toContainText('Warehouse Staff');
  });
});