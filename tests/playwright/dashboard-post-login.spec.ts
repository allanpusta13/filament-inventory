import { test, expect, BASE_URL, USERS, login } from './helpers';

test.describe('Dashboard Visual Verification - Post Login', () => {
  test('Login and audit dashboard', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 720 });
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin`);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(3000);

    await page.screenshot({
      path: 'playwright-screenshots/dashboard-post-login.png',
      fullPage: true,
    });

    const url = page.url();
    expect(url).toContain('/admin');

    const hasCanvas = await page.locator('canvas').count() > 0;
    const hasTables = await page.locator('table').count() > 0;
    const hasButtons = await page.locator('button').count() > 0;

    expect(hasCanvas || hasTables || hasButtons).toBeTruthy();
  });
});
