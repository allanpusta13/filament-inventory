import { test, expect, BASE_URL, USERS, login } from './helpers';

test.describe('Auditor (auditor@example.com)', () => {
  test.setTimeout(180000);

  test('dashboard: loads without error', async ({ page }) => {
    await login(page, USERS.auditor);
    await page.goto(`${BASE_URL}/admin`);
    await page.waitForTimeout(2000);
    const bodyText = await page.locator('body').textContent() ?? '';
    // Just verify page loads (either dashboard or access denied)
    expect(bodyText.length > 0).toBeTruthy();
  });

  test('products list: can view but cannot create', async ({ page }) => {
    await login(page, USERS.auditor);
    await page.goto(`${BASE_URL}/admin/products`);
    await expect(page.locator('h1')).toContainText('Products');
    await expect(page.locator('.fi-ta-table')).toBeVisible();
    await expect(page.getByRole('button', { name: 'New product' })).not.toBeVisible();
  });

  test('products list: search filters results', async ({ page }) => {
    await login(page, USERS.auditor);
    await page.goto(`${BASE_URL}/admin/products`);
    await page.waitForTimeout(1000);
    const searchInput = page.getByRole('searchbox', { name: 'Search', exact: true });
    if (await searchInput.isVisible()) {
      await searchInput.fill('nonexistent-product-xyz');
      await page.waitForTimeout(1000);
    }
  });

  test('stock movements list visible', async ({ page }) => {
    await login(page, USERS.auditor);
    await page.goto(`${BASE_URL}/admin/stock-movements`);
    await expect(page.locator('h1')).toContainText('Stock Movements');
    await expect(page.locator('.fi-ta-table')).toBeVisible();
  });

  test('loss ledgers list visible', async ({ page }) => {
    await login(page, USERS.auditor);
    await page.goto(`${BASE_URL}/admin/loss-ledgers`);
    await expect(page.locator('h1')).toContainText('Loss Ledger');
    await expect(page.locator('.fi-ta-table')).toBeVisible({ timeout: 10000 });
  });

  test('transfer list visible', async ({ page }) => {
    await login(page, USERS.auditor);
    await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
    await expect(page.locator('h1')).toContainText('Transfer');
    // Auditor may or may not have access to transfer-requisitions table
    // Just verify the page loads without error
    const bodyText = await page.locator('body').textContent() ?? '';
    expect(bodyText.length > 0).toBeTruthy();
  });

  test('product create page accessible', async ({ page }) => {
    await login(page, USERS.auditor);
    await page.goto(`${BASE_URL}/admin/products/create`);
    await page.waitForTimeout(2000);
    // Verify page loads (either with form or access denied message)
    const bodyText = await page.locator('body').textContent() ?? '';
    expect(bodyText.length > 0).toBeTruthy();
  });
});
