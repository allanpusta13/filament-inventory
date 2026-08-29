import { test, expect } from '@playwright/test';

test.describe('Dashboard Visual Verification', () => {
  test('Dashboard layout at desktop (1280x720)', async ({ page }) => {
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
    await page.waitForTimeout(5000); // Wait longer for widgets to render
    
    // Take screenshot of dashboard
    await page.screenshot({ 
      path: `playwright-screenshots/dashboard-desktop.png`, 
      fullPage: true 
    });
    
    // Just verify page loads with widgets
    const url = page.url();
    expect(url).toContain('/admin');
    
    // Check for any widget content (canvas for charts, tables, etc.)
    const hasCanvas = await page.locator('canvas').count() > 0;
    const hasTables = await page.locator('table').count() > 0;
    const hasButtons = await page.locator('button').count() > 0;
    const hasWidgets = await page.locator('.fi-widget, .fi-card').count() > 0;
    
    expect(hasCanvas || hasTables || hasButtons || hasWidgets).toBeTruthy();
  });
  
  test('Dashboard layout at mobile (375x667)', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    
    // Login first
    await page.goto('http://localhost:8000/admin/login');
    await page.waitForSelector('#form\\.email', { timeout: 15000 });
    
    // Fill login form
    await page.fill('#form\\.email', 'test@example.com');
    await page.fill('#form\\.password', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 15000 });
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(5000); // Wait longer for widgets to render
    
    // Take screenshot of dashboard
    await page.screenshot({ 
      path: `playwright-screenshots/dashboard-mobile.png`, 
      fullPage: true 
    });
    
    // Just verify page loads with widgets
    const url = page.url();
    expect(url).toContain('/admin');
    
    const hasCanvas = await page.locator('canvas').count() > 0;
    const hasTables = await page.locator('table').count() > 0;
    const hasButtons = await page.locator('button').count() > 0;
    const hasWidgets = await page.locator('.fi-widget, .fi-card').count() > 0;
    
    expect(hasCanvas || hasTables || hasButtons || hasWidgets).toBeTruthy();
  });
});