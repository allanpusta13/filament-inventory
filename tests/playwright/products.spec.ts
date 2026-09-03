import { test, expect, BASE_URL, USERS, login } from './helpers';

test.describe('Products Resource', () => {
  test.setTimeout(180000);

  test('admin can view products list', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/products`);
    await expect(page.locator('h1')).toContainText('Product');
  });

  test('admin can access create product page', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/products/create`);
    await expect(page.locator('h1')).toContainText('Create');
  });

  test('staff can view products list', async ({ page }) => {
    await login(page, USERS.staffDvo);
    await page.goto(`${BASE_URL}/admin/products`);
    await expect(page.locator('h1')).toContainText('Product');
  });

  test('auditor can view products list', async ({ page }) => {
    await login(page, USERS.auditor);
    await page.goto(`${BASE_URL}/admin/products`);
    await expect(page.locator('h1')).toContainText('Product');
  });

  test('products page has no JS errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (err) => {
      const msg = typeof err === 'string' ? err : (err as Error).message || '';
      if (!msg.includes('PhpDebugBar')) {
        errors.push(msg);
      }
    });

    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/products`);
    await page.waitForTimeout(2000);

    expect(errors).toHaveLength(0);
  });

  test('products list shows data table', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/products`);

    await expect(page.locator('.fi-ta-table, .fi-ta-empty-state').first()).toBeVisible({ timeout: 15000 });
  });
});
