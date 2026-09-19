import { chromium } from 'playwright';
import fs from 'fs';

const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'laptop', width: 1024, height: 768 },
  { name: 'tablet', width: 768, height: 1024 },
  { name: 'mobile', width: 390, height: 844 }
];

const THEMES = ['light', 'dark'];

async function runSinglePass(runNumber) {
  console.log(`\n=== Executing Soak Pass ${runNumber}/10 ===`);
  const passData = {
    run: runNumber,
    timestamp: new Date().toISOString(),
    viewports: {},
    consoleErrors: [],
    networkFailures: [],
    livewireRequests: [],
    computedTokens: {},
    tabSequence: []
  };

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: VIEWPORTS[0] });
  const page = await context.newPage();

  page.on('console', msg => {
    if (msg.type() === 'error') {
      passData.consoleErrors.push(msg.text());
    }
  });

  page.on('requestfailed', req => {
    passData.networkFailures.push({ url: req.url(), failure: req.failure() });
  });

  page.on('request', req => {
    if (req.url().includes('/livewire/') || req.url().includes('livewire.js')) {
      passData.livewireRequests.push({ url: req.url(), method: req.method() });
    }
  });

  // 1. Login
  const t0 = Date.now();
  await page.goto('http://127.0.0.1:8000/admin/login');
  await page.fill('input[type="email"], input[name*="email"], #data\\.email', 'admin@example.com');
  await page.fill('input[type="password"], input[name*="password"], #data\\.password', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin**');
  const t1 = Date.now();
  passData.loginTimeMs = t1 - t0;

  // 2. Loop Viewports & Themes on S01 (Panel Shell & Sidebar)
  for (const vp of VIEWPORTS) {
    await page.setViewportSize({ width: vp.width, height: vp.height });
    await page.waitForTimeout(300);

    for (const theme of THEMES) {
      if (theme === 'dark') {
        await page.evaluate(() => document.documentElement.classList.add('dark'));
      } else {
        await page.evaluate(() => document.documentElement.classList.remove('dark'));
      }
      await page.waitForTimeout(200);

      const shotPath = `docs/audit_artifacts/s01_run${runNumber}_${vp.name}_${theme}.png`;
      await page.screenshot({ path: shotPath });
    }
  }

  // Restore desktop light
  await page.setViewportSize(VIEWPORTS[0]);
  await page.evaluate(() => document.documentElement.classList.remove('dark'));

  // 3. Measure Computed Design Tokens on S01 Navigation Sidebar
  passData.computedTokens = await page.evaluate(() => {
    const sidebar = document.querySelector('aside') || document.querySelector('nav') || document.body;
    const style = window.getComputedStyle(sidebar);
    const link = document.querySelector('a[href*="/admin/products"]') || document.querySelector('a');
    const linkStyle = link ? window.getComputedStyle(link) : {};

    return {
      sidebarWidth: style.width,
      sidebarBg: style.backgroundColor,
      linkColor: linkStyle.color,
      linkFontFamily: linkStyle.fontFamily,
      linkFontSize: linkStyle.fontSize,
      linkPadding: linkStyle.padding
    };
  });

  // 4. Keyboard Tab Focus Order Logging
  await page.keyboard.press('Tab');
  for (let i = 0; i < 10; i++) {
    const focused = await page.evaluate(() => {
      const el = document.activeElement;
      return el ? { tag: el.tagName, text: el.innerText ? el.innerText.trim().substring(0, 30) : '', role: el.getAttribute('role'), id: el.id } : null;
    });
    if (focused) passData.tabSequence.push(focused);
    await page.keyboard.press('Tab');
  }

  await browser.close();
  return passData;
}

(async () => {
  const allPasses = [];
  for (let i = 1; i <= 10; i++) {
    const res = await runSinglePass(i);
    allPasses.push(res);
  }

  fs.writeFileSync('docs/audit_artifacts/s01_soak_10x_raw.json', JSON.stringify(allPasses, null, 2));
  console.log("\nFinished 10x Soak test on S01. Saved raw data to docs/audit_artifacts/s01_soak_10x_raw.json");
})();
