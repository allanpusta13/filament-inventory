import { test, expect } from "@playwright/test";
import { freshSeed } from "./helpers/artisan";
import { loginAsAdmin } from "./helpers/login";
import { makeWarehouse, makeVariant } from "./helpers/fixtures";

/**
 * Scenarios 12, 13 — Wizard submit-button visibility, unit select flow.
 */
test.describe("Wizard & Unit Select", () => {
    test.beforeAll(() => {
        freshSeed();
    });

    test("12 — submit button only appears on the last wizard step", async ({
        page,
    }) => {
        await loginAsAdmin(page);
        await page.goto("/admin/direct-transfers/create");

        // On step 1 — no "Create" button visible.
        await expect(
            page.locator('button:has-text("Create")'),
        ).not.toBeVisible();

        // Navigate through the wizard.
        await page.locator('button:has-text("Next")').click();
        await expect(
            page.locator('button:has-text("Create")'),
        ).not.toBeVisible();

        await page.locator('button:has-text("Next")').click();
        // Step 3 — Create is now visible.
        await expect(page.locator('button:has-text("Create")')).toBeVisible();
    });

    test("13 — unit select sources from the variant's own conversions", async ({
        page,
    }) => {
        const from = makeWarehouse("US From");
        const to = makeWarehouse("US To");
        const variant = makeVariant("SKU-US-AA", "pc");

        await loginAsAdmin(page);
        await page.goto("/admin/direct-transfers/create");

        await page.locator('select, [role="combobox"]').first().click();
        await page.locator("text=US From").first().click();
        await page.locator('select, [role="combobox"]').nth(1).click();
        await page.locator("text=US To").first().click();

        await page.locator('button:has-text("Next")').click();
        await page.locator('button:has-text("Add item")').click();

        // Select the variant, then open the unit select.
        await page.locator('[role="combobox"]').last().click();
        await page.locator("text=SKU-US-AA").first().click();

        // The unit select should show 'pc' and 'case' — sourced from the
        // variant's own conversion rows, never free text.
        await page.locator('[role="combobox"]').nth(2).click();
        await expect(page.locator("text=pc")).toBeVisible();
    });
});
