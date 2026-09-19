import { chromium } from 'playwright';

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  console.log("Navigating to login...");
  await page.goto('http://127.0.0.1:8000/admin/login');
  await page.screenshot({ path: 'docs/audit_artifacts/login_page.png' });

  // Check login inputs
  const content = await page.content();
  console.log("Login page title:", await page.title());

  // Filament v5 login field selectors
  await page.fill('input[type="email"], input[name*="email"], #data\\.email', 'admin@example.com');
  await page.fill('input[type="password"], input[name*="password"], #data\\.password', 'password');
  await page.click('button[type="submit"]');

  await page.waitForTimeout(3000);
  console.log("Current URL after login:", page.url());
  await page.screenshot({ path: 'docs/audit_artifacts/after_login.png' });

  const navLinks = await page.evaluate(() => {
    const links = Array.from(document.querySelectorAll('a[href*="/admin"]'));
    return links.map(a => ({
      text: a.innerText.replace(/\n/g, ' ').trim(),
      href: a.href
    }));
  });

  console.log("Found nav links:\n", JSON.stringify(navLinks, null, 2));

  await browser.close();
})();
