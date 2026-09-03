import { test as setup, expect } from '@playwright/test';
import { execSync } from 'child_process';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';
import { existsSync, mkdirSync } from 'fs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const BASE_URL = process.env.APP_URL || 'http://filament-inventory.test';
const CWD = 'D:\\Personal\\filament-inventory';

function run(cmd: string): void {
  try {
    execSync(cmd, { cwd: CWD, encoding: 'utf-8', timeout: 30_000, stdio: 'pipe' });
  } catch (error) {
    console.error(`Warning: Setup command failed: ${cmd}`);
    console.error(error);
    // We don't exit, but we log the error.
  }
}

// Ensure the .auth directory exists
const authDir = resolve(__dirname, '.auth');
if (!existsSync(authDir)) {
  console.log(`Creating auth directory: ${authDir}`);
  mkdirSync(authDir, { recursive: true });
}

// Clear cache and throttle once before any logins
console.log('Clearing cache');
run('php artisan cache:clear --no-interaction');

interface UserDef {
  name: string;
  email: string;
}

const users: UserDef[] = [
  { name: 'admin', email: 'admin@example.com' },
  { name: 'managerMnl', email: 'manager.mnl@example.com' },
  { name: 'managerCeb', email: 'manager.ceb@example.com' },
  { name: 'staffDvo', email: 'staff.dvo@example.com' },
  { name: 'auditor', email: 'auditor@example.com' },
];

for (const user of users) {
  setup(`authenticate ${user.name}`, async ({ page }) => {
    console.log(`Setting up user: ${user.name}`);
    // Navigate with longer timeout and wait for domcontentloaded (not networkidle - Livewire takes too long)
    await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded', timeout: 60_000 });
    
    // Wait for the email input to be visible with longer timeout - use ID selector
    await page.locator('input[id=\"form.email\"]').waitFor({ state: 'visible', timeout: 30_000 });
    
    await page.locator('input[id=\"form.email\"]').fill(user.email);
    await page.locator('input[type=\"password\"]').first().fill('password');
    await page.getByRole('button', { name: 'Sign in' }).click();

    try {
      await page.waitForURL('**/admin', { timeout: 60_000 });
    } catch {
      // Throttled — clear cache and retry once
      console.log('Throttled, clearing cache and retrying');
      run('php artisan cache:clear --no-interaction');
      await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded', timeout: 60_000 });
      await page.locator('input[id=\"form.email\"]').waitFor({ state: 'visible', timeout: 30_000 });
      await page.locator('input[id=\"form.email\"]').fill(user.email);
      await page.locator('input[type=\"password\"]').first().fill('password');
      await page.getByRole('button', { name: 'Sign in' }).click();
      await page.waitForURL('**/admin', { timeout: 60_000 });
    }

    // Verify we're NOT on the login page
    expect(page.url()).not.toContain('/login');

    // Save storage state
    await page.context().storageState({
      path: resolve(__dirname, `.auth/${user.name}.json`),
    });
    console.log(`Saved state for ${user.name} to .auth/${user.name}.json`);
    
    // Also save admin user state to user.json for shared use
    if (user.name === 'admin') {
      await page.context().storageState({
        path: resolve(__dirname, `.auth/user.json`),
      });
      console.log('Saved admin state to .auth/user.json');
    }
  });
}