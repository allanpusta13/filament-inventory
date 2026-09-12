import { test, expect } from '@playwright/test';

test.describe('UserResource E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/user.json' });

  test('can render user index page', async ({ page }) => {
    await page.goto('http://filament-inventory.test/admin/users');
    await expect(page.locator('text=Users')).toBeVisible();
  });

  test('can create a user', async ({ page }) => {
    await page.goto('http://filament-inventory.test/admin/users/create');
    await expect(page.locator('text=NEW USER')).toBeVisible();

    await page.fill('input[name="name"]', 'E2E Test User');
    await page.fill('input[name="email"]', 'e2e-test@test.com');
    await page.fill('input[name="password"]', 'password123');
    await page.selectOption('select[name="role"]', 'warehouse_staff');
    await page.click('button:has-text("CREATE")');

    await expect(page.locator('text=Created successfully')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('text=E2E Test User')).toBeVisible();
  });

  test('can edit a user', async ({ page }) => {
    await page.goto('http://filament-inventory.test/admin/users/create');
    await page.fill('input[name="name"]', 'Original User');
    await page.fill('input[name="email"]', 'original@test.com');
    await page.fill('input[name="password"]', 'password123');
    await page.selectOption('select[name="role"]', 'warehouse_staff');
    await page.click('button:has-text("CREATE")');
    await expect(page.locator('text=Created successfully')).toBeVisible({ timeout: 10000 });

    await page.click('text=Edit');
    await page.fill('input[name="name"]', 'Updated User');
    await page.click('button:has-text("SAVE")');
    await expect(page.locator('text=Saved successfully')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('text=Updated User')).toBeVisible();
  });

  test('can delete a user', async ({ page }) => {
    await page.goto('http://filament-inventory.test/admin/users/create');
    await page.fill('input[name="name"]', 'To Delete User');
    await page.fill('input[name="email"]', 'delete@test.com');
    await page.fill('input[name="password"]', 'password123');
    await page.selectOption('select[name="role"]', 'warehouse_staff');
    await page.click('button:has-text("CREATE")');
    await expect(page.locator('text=Created successfully')).toBeVisible({ timeout: 10000 });

    await page.click('text=Delete');
    await page.click('button:has-text("DELETE")');
    await expect(page.locator('text=Deleted successfully')).toBeVisible({ timeout: 10000 });
  });

  test('can filter users by search', async ({ page }) => {
    await page.goto('http://filament-inventory.test/admin/users');
    await page.fill('input[placeholder*="Search"]', 'E2E Test');
    await page.keyboard.press('Enter');
    await expect(page.locator('text=E2E Test User')).toBeVisible({ timeout: 5000 });
  });

  test('can filter users by role', async ({ page }) => {
    await page.goto('http://filament-inventory.test/admin/users');
    await page.selectOption('select[name*="role"]', 'warehouse_staff');
    await page.waitForTimeout(500);
    await expect(page.locator('text=E2E Test User')).toBeVisible({ timeout: 5000 });
  });
});
