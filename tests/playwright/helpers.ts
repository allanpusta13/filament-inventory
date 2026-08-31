import { test as base, expect, type Page } from '@playwright/test';
import { execSync } from 'child_process';

const BASE_URL = 'http://localhost:8000';

function clearLoginThrottle(): void {
  try {
    execSync('php artisan cache:clear --no-interaction', {
      cwd: 'D:\\Personal\\filament-inventory',
      encoding: 'utf-8',
      timeout: 10000,
      stdio: 'pipe',
    });
  } catch {
    // ignore
  }
}

async function login(page: Page, email: string, password = 'password'): Promise<void> {
  clearLoginThrottle();
  await page.goto(`${BASE_URL}/admin/login`);
  await page.getByLabel('Email address').fill(email);
  await page.locator('input[type="password"]').first().fill(password);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await page.waitForURL('**/admin', { timeout: 15000 });
}

async function logout(page: Page): Promise<void> {
  await page.goto(`${BASE_URL}/admin`);
  await page.getByRole('button', { name: 'User menu' }).click();
  await page.waitForTimeout(500);
  await page.getByText('Sign out').click();
  await page.waitForURL('**/admin/login', { timeout: 15000 });
}

async function selectFilamentOption(page: Page, fieldLabel: string, optionText: string): Promise<void> {
  const field = page.locator('.fi-fo-select-wrp').filter({ hasText: fieldLabel }).first();
  const button = field.locator('button');
  await button.click();
  await page.waitForTimeout(800);
  await page.getByRole('option', { name: optionText }).click();
  await page.waitForTimeout(300);
}

async function openFirstRowAction(page: Page): Promise<void> {
  await page.locator('.fi-ta-table tbody tr').first().locator('.fi-ta-actions').first().click();
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
