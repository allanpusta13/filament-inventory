const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  await page.goto('http://127.0.0.1:8000/admin/login');
  await page.fill('input[type="email"]', 'admin@example.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(2000);

  const navLinks = await page.evaluate(() => {
    const links = Array.from(document.querySelectorAll('a[href*="/admin"]'));
    return links.map(a => ({
      text: a.innerText.replace(/\n/g, ' ').trim(),
      href: a.href
    }));
  });

  console.log("Nav Links:", JSON.stringify(navLinks, null, 2));

  await browser.close();
})();
