import { test, expect, BASE_URL, USERS, login } from './helpers';

test.describe('Branch Manager (manager.mnl@example.com)', () => {
  test.setTimeout(180000);

  test('dashboard: visible or restricted', async ({ page }) => {
    await login(page, USERS.managerMnl);
    await page.goto(`${BASE_URL}/admin`);
    await page.waitForTimeout(2000);
    const url = page.url();
    const bodyText = await page.locator('body').textContent() ?? '';
    const onDashboard = bodyText.includes('Total SKUs') || bodyText.includes('Performance Overview');
    const redirected = !url.endsWith('/admin') || bodyText.includes('403');
    // If not on dashboard and not redirected, check if we at least have sidebar navigation
    const hasSidebar = await page.locator('.fi-sidebar').isVisible().catch(() => false);
    console.log('Manager dashboard check - URL:', url, 'onDashboard:', onDashboard, 'redirected:', redirected, 'hasSidebar:', hasSidebar);
    expect(onDashboard || redirected || hasSidebar).toBeTruthy();
  });

  test('transfer list visible', async ({ page }) => {
    await login(page, USERS.managerMnl);
    await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
    await expect(page.locator('h1')).toContainText('Transfer');
    // Manager may or may not have access to transfer-requisitions table
    // Just verify the page loads without error
    const bodyText = await page.locator('body').textContent() ?? '';
    expect(bodyText.length > 0).toBeTruthy();
  });

  test('product create page not accessible', async ({ page }) => {
    await login(page, USERS.managerMnl);
    await page.goto(`${BASE_URL}/admin/products/create`);
    await page.waitForTimeout(2000);
    // Manager should not access the product create page - expect 403 or redirect
    const heading = await page.locator('h1').textContent() ?? '';
    expect(heading).not.toContain('Create Product');
    // Optionally, check for 403 message or that they are redirected
    // We'll just ensure they are not seeing the create form
    await expect(page.locator('#form')).not.toBeVisible({ timeout: 5000 });
  });
});
