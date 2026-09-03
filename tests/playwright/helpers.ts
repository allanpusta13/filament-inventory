import { test as base, expect, type Page, type BrowserContext } from '@playwright/test';
import { execSync } from 'child_process';

const BASE_URL = process.env.APP_URL || 'http://filament-inventory.test';
const CWD = 'D:\\\\Personal\\\\filament-inventory';

function clearThrottle(): void {
  try {
    execSync('php artisan cache:clear --no-interaction', {
      cwd: CWD,
      encoding: 'utf-8',
      timeout: 20_000,
      stdio: 'pipe',
    });
  } catch {
    // ignore
  }
}

async function login(page: Page, email: string, password = 'password'): Promise<void> {
  // First check if already authenticated by visiting dashboard directly
  await page.goto(`${BASE_URL}/admin`, { waitUntil: 'domcontentloaded', timeout: 120_000 });

  // If we're already on dashboard (not login page), we're authenticated via storageState
  if (!page.url().includes('/login')) {
    return;
  }

  // Need to log in - wait for email input and fill using ID selector
  await page.locator('input[id="form.email"]').waitFor({ state: 'visible', timeout: 30_000 });
  await page.locator('input[id="form.email"]').fill(email);
  await page.locator('input[type="password"]').first().fill(password);
  await page.getByRole('button', { name: 'Sign in' }).click();

  try {
    await page.waitForURL('**/admin', { timeout: 120_000 });
  } catch {
    // Throttled — clear cache and retry once
    clearThrottle();
    await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded', timeout: 120_000 });
    if (!page.url().includes('/login')) return;
    await page.locator('input[id="form.email"]').waitFor({ state: 'visible', timeout: 30_000 });
    await page.locator('input[id="form.email"]').fill(email);
    await page.locator('input[type="password"]').first().fill(password);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.waitForURL('**/admin', { timeout: 120_000 });
  }
}

async function logout(page: Page): Promise<void> {
  await page.goto(`${BASE_URL}/admin`);
  await page.getByRole('button', { name: 'User menu' }).click();
  await page.waitForTimeout(500);
  await page.getByText('Sign out').click();
  await page.waitForURL('**/admin/login', { timeout: 15_000 });
}

async function selectFilamentOption(page: Page, fieldLabel: string, optionText: string): Promise<void> {
  const field = page.getByLabel(fieldLabel);
  await field.click();
  await page.waitForTimeout(500);
  await page.getByRole('option', { name: optionText }).click();
  await page.waitForTimeout(300);
}

async function openFirstRowAction(page: Page): Promise<void> {
  await page.locator('.fi-ta-table tbody tr').first().locator('.fi-ac-link-action').first().click();
  await page.waitForTimeout(300);
}

const TEST_PASSWORD = 'password';

const USERS = {
  admin: 'admin@example.com',
  managerMnl: 'manager.mnl@example.com',
  managerCeb: 'manager.ceb@example.com',
  staffDvo: 'staff.dvo@example.com',
  auditor: 'auditor@example.com',
} as const;

const USER_ROLES: Record<string, string> = {
  [USERS.admin]: 'admin',
  [USERS.managerMnl]: 'managerMnl',
  [USERS.managerCeb]: 'managerCeb',
  [USERS.staffDvo]: 'staffDvo',
  [USERS.auditor]: 'auditor',
};

export const test = base.extend<{ adminPage: Page }>({
  adminPage: async ({ browser }, use) => {
    const page = await browser.newPage();
    await login(page, USERS.admin);
    await use(page);
    await page.close();
  },
});

export {
  BASE_URL,
  USERS,
  TEST_PASSWORD,
  login,
  logout,
  selectFilamentOption,
  openFirstRowAction,
};
export { expect };