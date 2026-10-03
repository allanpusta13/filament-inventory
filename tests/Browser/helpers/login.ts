import { Page, expect } from "@playwright/test";

/**
 * Filament v5 login flow.
 *
 * The login page is at `/admin/login`. Field IDs are `data.email` and
 * `data.password` — Filament namespaces its form under `data`.
 */
export async function loginAs(
    page: Page,
    email: string,
    password = "password",
): Promise<void> {
    await page.goto("/admin/login");
    await page.fill('input[id="data.email"]', email);
    await page.fill('input[id="data.password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.startsWith("/admin/login"), {
        timeout: 30_000,
    });
}

export const loginAsAdmin = (page: Page) => loginAs(page, "admin@example.test");
export const loginAsAuditor = (page: Page) =>
    loginAs(page, "auditor@example.test");
export const loginAsBranchManager = (page: Page) =>
    loginAs(page, "branch.manager@example.test");
export const loginAsMultiWarehouse = (page: Page) =>
    loginAs(page, "staff.multi@example.test");
export const loginAsSingleWarehouse = (page: Page) =>
    loginAs(page, "staff.wh0@example.test");

/** Filament's logout is a POST — can't `goto()` it. */
export async function logout(page: Page): Promise<void> {
    await page.request.post("/admin/logout");
    await page.goto("/admin/login");
}

/** Assert the page shows the given text somewhere in the body. */
export async function expectText(
    page: Page,
    text: string | RegExp,
): Promise<void> {
    await expect(page.locator("body")).toContainText(text);
}

/** Assert a Filament notification/toast is visible with the given text. */
export async function expectNotification(
    page: Page,
    text: string | RegExp,
): Promise<void> {
    const notification = page.locator('[role="status"], .fi-no-notification');
    await expect(notification.first()).toContainText(text);
}
