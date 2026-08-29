import { test, expect } from '@playwright/test';

test.describe('Dashboard Visual Verification - Post Login', () => {
  test('Login and audit dashboard', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 720 });
    
    // Login first
    await page.goto('http://localhost:8000/admin/login');
    await page.waitForSelector('#form\\.email', { timeout: 15000 });
    
    // Fill login form
    await page.fill('#form\\.email', 'test@example.com');
    await page.fill('#form\\.password', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 15000 });
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(3000);
    
    // Now we're on the dashboard - take screenshot
    await page.screenshot({ 
      path: `playwright-screenshots/dashboard-post-login.png`, 
      fullPage: true 
    });
    
    // Verify dashboard loads
    const url = page.url();
    expect(url).toContain('/admin');
    
    const hasCanvas = await page.locator('canvas').count() > 0;
    const hasTables = await page.locator('table').count() > 0;
    const hasButtons = await page.locator('button').count() > 0;
    
    expect(hasCanvas || hasTables || hasButtons).toBeTruthy();
  });
});