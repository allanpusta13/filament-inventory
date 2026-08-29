const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  // Login
  await page.goto('http://localhost:8000/admin/login');
  await page.fill('input[type="email"]', 'admin@test.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin', { timeout: 10000 });

  // Desktop 1920x1080
  await page.setViewportSize({ width: 1920, height: 1080 });
  await page.waitForTimeout(2000);
  await page.screenshot({ path: 'screenshots/auth-desktop-1920.png', fullPage: true });

  // Desktop 1280x800
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.waitForTimeout(1000);
  await page.screenshot({ path: 'screenshots/auth-desktop-1280.png', fullPage: true });

  // Tablet 768x1024
  await page.setViewportSize({ width: 768, height: 1024 });
  await page.waitForTimeout(1000);
  await page.screenshot({ path: 'screenshots/auth-tablet-768.png', fullPage: true });

  // Mobile 375x812
  await page.setViewportSize({ width: 375, height: 812 });
  await page.waitForTimeout(1000);
  await page.screenshot({ path: 'screenshots/auth-mobile-375.png', fullPage: true });

  await browser.close();
  console.log('Screenshots taken successfully');
})();
