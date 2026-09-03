import { test as setup, expect } from '@playwright/test';
import { execSync } from 'child_process';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';
import { existsSync, mkdirSync } from 'fs';

const __filename = fileURLToPathToPath(import.meta.url);
const __dirname = dirname(__filename);

const BASE_URL = process.env.APP_URL || 'http://filament-inventory.test';
const CWD = 'D:\\\\Personal\\\\filament-inventory';

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
    await page.locator('input[id=\\\"form.email\\\"]').waitFor({ state: 'visible', timeout: 30_000 });
    
    await page.locator('input[id=\\\"form.email\\\"]').fill(user.email);
    await page.locator('input[type=\\\"password\\\"]').first().fill('password');
    await page.getByRole('button', { name: 'Sign in' }).click();

    try {
      await page.waitForURL('**/admin', { timeout: 60_000 });
    } catch {
      // Throttled — clear cache and retry once
      console.log('Throttled, clearing cache and retrying');
      run('php artisan cache:clear --no-interaction');
      await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded', timeout: 60_000 });
      await page.locator('input[id=\\\"form.email\\\"]').waitFor({ state: 'visible', timeout: 30_000 });
      await page.locator('input[id=\\\"form.email\\\"]').fill(user.email);
      await page.locator('input[type=\\\"password\\\"]').first().fill('password');
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

// Additionally, we can add a setup step that adds route blocking and CSS injection to every context.
// However, the setup function above is for creating storage state.
// We need to apply route blocking and CSS injection to each test's context.
// We can do this by adding a global setup that modifies the browser context? 
// Actually, we can use the `setup` to return a function that modifies the context? 
// Alternatively, we can use the `test.use` or `test.extend` to apply these to every test.
// But we are already using a setup for authentication. We can change the setup to return a new context with route blocking and CSS injection.
// Let's change the approach: we will keep the authentication setup as is, and then create a new global setup that runs before each test? Not possible.
// Instead, we can use the `test.beforeEach` in the test file, but we want to apply to all tests.
// Let's create a new setup file that is used as the `setup` in the config, and in that setup we:
// 1. Do the authentication (as before)
// 2. Then, for each context we create, we add route blocking and CSS injection.
// However, the setup function is called to produce storage state, not to modify the context for tests.
// We can instead use the `globalSetup` to do nothing and then use the `setup` to create a context that is used for tests? 
// Actually, the `setup` in the config is for setting up storage state, and then each test uses that storage state.
// We cannot modify the context of the test from the setup function because the setup function runs before the tests and only produces storage state.
// We need to apply route blocking and CSS injection to each test's context. We can do this by:
// - Using `test.use` to set up a function that creates a context with route blocking and CSS injection.
// But we are already using storageState. We can combine by:
//   In the test file, we can extend the test to use a custom context that adds route blocking and CSS injection.
// However, we want to avoid modifying every test file.
// Let's use the `globalSetup` to inject a script into every page? That is possible by using the `globalSetup` to add a route handler? 
// Actually, we can use the `globalSetup` to launch a browser and then close it? Not helpful.
// Alternatively, we can use the `webServer` feature? Not needed.
// Let's step back: the requirement is to refactor the config for asset blocking and animation disabling.
// We can do this by adding a `globalSetup` that creates a browser context with route blocking and CSS injection and then stores it? 
// But we need per-test isolation.
// The best way is to use the `test.use` to configure the browser context with route blocking and CSS injection.
// We can do this by creating a custom test extension in a file that is required by all test files? 
// We can create a base test file that all tests import? But we don't want to change every test file.
// We can use the `globalSetup` to modify the Playwright test environment? 
// Actually, we can use the `globalSetup` to register a beforeEach hook? Not directly.
// Given the constraints, let's update the existing `auth.setup.ts` to also export a function that can be used in `test.use`? 
// But the config's `setup` is for storage state, not for test configuration.
// We are allowed to change the config. Let's change the config to not use a setup file for storage state, but instead use `test.use` to set up the context with storage state, route blocking, and CSS injection.
// However, we want to keep the authentication setup to generate the storage state file.
// We can keep the globalSetup for seeding and then use `test.use` to set the storageState and also add route blocking and CSS injection.
// Let's do:
//   - Keep globalSetup for seeding (database, cache, etc.)
//   - Remove the `setup` from the config (or set it to undefined)
//   - In `test.use`, set the storageState to the admin user's json, and also add a function to inject route blocking and CSS injection.
// But note: the storageState is set per test, and we want each test to start from the admin state? 
// Actually, the tests use different users (admin, manager, staff, auditor). 
// We have multiple storage state files. We cannot set a single storageState in `test.use` because different tests need different users.
// We can instead keep the current setup (which creates multiple storage state files) and then in each test, we load the appropriate storageState.
// But we want to apply route blocking and CSS injection to every test context, regardless of the user.
// We can do this by creating a base test that extends the Playwright test and adds the route blocking and CSS injection, and then have all test files use that base test.
// However, we are not allowed to change every test file? The task says we can modify test specs if needed.
// Let's change the config to use a global setup that does nothing, and then create a new setup file that is used as `setup` (for storage state) and then we also create a base test file that extends the test and adds the route blocking and CSS injection, and then we change every test file to import from that base test.
// That would be a lot of changes.
// Alternatively, we can use the `globalSetup` to inject a script into every page by using the `page.route` from within the globalSetup? 
// But the globalSetup runs only once, and we need to inject into every page of every test.
// We can use the `globalSetup` to modify the browser context prototype? Not possible.
// Let's look at the Playwright documentation: we can use `test.use` to set up a function that modifies the context.
// We can do:
//   test.use({
//     storageState: ... // but we need to choose based on the test
//   });
// We cannot set storageState dynamically in `test.use` because it is static.
// We can set the storageState to null and then in each test, we call `context.addCookies` or `context.storageState`? 
// That would be messy.
// Given the time, let's stick to the original plan: we will update the setup file (auth.setup.ts) to also add route blocking and CSS injection to the context when it is created for the storage state.
// But note: the context used in the setup function is not the same as the context used in the tests.
// However, we can save the storage state and then in the tests, we can use that storage state and then apply route blocking and CSS injection in the test's `beforeEach`.
// We can create a base test file that does the `beforeEach` for route blocking and CSS injection, and then change each test file to extend that base test.
// Since we are allowed to modify test specs, let's do that.
// Steps:
// 1. Create a base test file: tests/playwright/base.test.ts
// 2. In that file, we extend the test to add a `beforeEach` that blocks assets and disables animations.
// 3. Then, we change each test file to import from base.test.ts instead of from '@playwright/test'.
// 4. We keep the existing setup (auth.setup.ts) for authentication only.
// 5. We update the config to point to the auth.setup.ts (unchanged) and keep the globalSetup.
// 6. We output the updated config (which is unchanged) and then output the base test file and the changed test specs.
// However, the task says to output the modified test specs in full if changed.
// Let's do it.
// First, create the base test file.