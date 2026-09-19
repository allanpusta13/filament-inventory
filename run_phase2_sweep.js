import { chromium } from 'playwright';
import fs from 'fs';

const SURFACES = [
  { id: 'S01', name: 'Panel Shell & Navigation', url: 'http://127.0.0.1:8000/admin' },
  { id: 'S02', name: 'Admin Dashboard', url: 'http://127.0.0.1:8000/admin' },
  { id: 'S03', name: 'Product Variants Resource', url: 'http://127.0.0.1:8000/admin/products' },
  { id: 'S04', name: 'Stock Movements Resource', url: 'http://127.0.0.1:8000/admin/stock-movements' },
  { id: 'S05', name: 'Direct Transfers Resource', url: 'http://127.0.0.1:8000/admin/direct-transfers' },
  { id: 'S06', name: 'Transfer Requisitions Resource', url: 'http://127.0.0.1:8000/admin/transfer-requisitions' },
  { id: 'S07', name: 'In-Transits Resource', url: 'http://127.0.0.1:8000/admin/in-transits' },
  { id: 'S08', name: 'Loss Ledgers Resource', url: 'http://127.0.0.1:8000/admin/loss-ledgers' },
  { id: 'S09', name: 'Warehouses Resource', url: 'http://127.0.0.1:8000/admin/warehouses' },
  { id: 'S10', name: 'Users Resource', url: 'http://127.0.0.1:8000/admin/users' }
];

const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'mobile', width: 390, height: 844 }
];

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: VIEWPORTS[0] });
  const page = await context.newPage();

  // Login
  console.log("Navigating to login...");
  await page.goto('http://127.0.0.1:8000/admin/login');
  await page.fill('input[type="email"], input[name*="email"], #data\\.email', 'admin@example.com');
  await page.fill('input[type="password"], input[name*="password"], #data\\.password', 'password');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(2000);
  console.log("Login submitted, current URL:", page.url());

  const auditResults = [];

  for (const surf of SURFACES) {
    console.log(`Auditing ${surf.id} — ${surf.name}...`);
    try {
      if (page.url() !== surf.url) {
        await page.goto(surf.url, { waitUntil: 'domcontentloaded', timeout: 15000 });
      }
      await page.waitForTimeout(1000);

      // Desktop Light
      await page.setViewportSize(VIEWPORTS[0]);
      await page.evaluate(() => document.documentElement.classList.remove('dark'));
      await page.screenshot({ path: `docs/audit_artifacts/${surf.id}_desktop_light.png` });

      // Desktop Dark
      await page.evaluate(() => document.documentElement.classList.add('dark'));
      await page.screenshot({ path: `docs/audit_artifacts/${surf.id}_desktop_dark.png` });

      // Mobile Light
      await page.setViewportSize(VIEWPORTS[1]);
      await page.evaluate(() => document.documentElement.classList.remove('dark'));
      await page.screenshot({ path: `docs/audit_artifacts/${surf.id}_mobile_light.png` });

      // Inspect Surface Metrics & Token Compliance
      const surfaceMetrics = await page.evaluate(() => {
        const buttons = Array.from(document.querySelectorAll('button, a.fi-btn')).map(b => {
          const style = window.getComputedStyle(b);
          return {
            text: b.innerText.trim().substring(0, 20),
            bg: style.backgroundColor,
            height: style.height,
            borderRadius: style.borderRadius
          };
        });

        const tables = Array.from(document.querySelectorAll('table')).map(t => {
          const header = t.querySelector('th');
          const hStyle = header ? window.getComputedStyle(header) : {};
          const cell = t.querySelector('td');
          const cStyle = cell ? window.getComputedStyle(cell) : {};
          return {
            headerBg: hStyle.backgroundColor,
            cellPadding: cStyle.padding
          };
        });

        return {
          buttonCount: buttons.length,
          buttonsSample: buttons.slice(0, 5),
          tableMetrics: tables
        };
      });

      auditResults.push({
        surface: surf,
        metrics: surfaceMetrics
      });
    } catch (err) {
      console.error(`Error auditing ${surf.id}:`, err.message);
    }
  }

  fs.writeFileSync('docs/audit_artifacts/phase2_sweep_metrics.json', JSON.stringify(auditResults, null, 2));
  console.log("Full surface sweep complete! Saved to docs/audit_artifacts/phase2_sweep_metrics.json");

  await browser.close();
})();
