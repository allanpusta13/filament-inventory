import { defineConfig, devices } from "@playwright/test";

/**
 * Playwright E2E configuration (§12).
 *
 * Runs against the live app on the configured APP_URL. Serial execution
 * (workers: 1) because scenarios share a database; each spec seeds its
 * own fixtures via artisan/tinker helpers.
 */
export default defineConfig({
    testDir: "./tests/Browser",
    timeout: 90_000,
    expect: { timeout: 15_000 },
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: [
        ["list"],
        ["html", { open: "never", outputFolder: "storage/playwright-report" }],
    ],
    use: {
        baseURL: process.env.APP_URL ?? "https://filament-inventory.test",
        ignoreHTTPSErrors: true,
        trace: "on-first-retry",
        screenshot: "only-on-failure",
        video: "retain-on-failure",
        actionTimeout: 15_000,
        navigationTimeout: 30_000,
    },
    projects: [
        { name: "chromium", use: { ...devices["Desktop Chrome"] } },
        { name: "firefox", use: { ...devices["Desktop Firefox"] } },
        { name: "webkit", use: { ...devices["Desktop Safari"] } },
    ],
});
