import { test, expect } from "@playwright/test";
import { freshSeed } from "./helpers/artisan";
import { loginAsAdmin, expectNotification } from "./helpers/login";
import {
    makeWarehouse,
    makeVariant,
    makeConfirmedRequisition,
} from "./helpers/fixtures";

/**
 * Scenario 1 — Full Transfer Lifecycle.
 *
 * Confirm → Dispatch → Scan-to-Receive → Completed.
 * The upstream create/submit/confirm steps are seeded by the fixture
 * helper; this spec drives the two UI transitions that matter.
 */
test.describe("Scenario 1 — Full Transfer Lifecycle", () => {
    test.beforeAll(() => {
        freshSeed();
    });

    test("confirms, dispatches, receives a requisition end-to-end", async ({
        page,
    }) => {
        const from = makeWarehouse("Source WH");
        const to = makeWarehouse("Destination WH");
        const variant = makeVariant("SKU-LIFE-AA");

        const { requisitionId } = makeConfirmedRequisition(
            from.id,
            to.id,
            variant.id,
            10,
        );

        await loginAsAdmin(page);

        // Open the requisition view page.
        await page.goto(`/admin/transfer-requisitions/${requisitionId}`);
        await expect(page.locator("body")).toContainText("Confirmed");

        // Dispatch — table action, opens a confirmation modal.
        await page.goto("/admin/transfer-requisitions");
        await page.locator('button[aria-label="Actions"]').first().click();
        await page.locator("text=Dispatch").first().click();
        await page.locator('button:has-text("Confirm")').click();

        await expectNotification(page, /dispatch/i);

        // Status is now Dispatched.
        await page.goto(`/admin/transfer-requisitions/${requisitionId}`);
        await expect(page.locator("body")).toContainText("Dispatched");

        // Receive via the STN scan link — signed URL.
        // The scan route is on a signed URL; we need the print page which
        // renders the signed scan QR. Simplest path: hit the receive action
        // which redirects to the signed URL.
        await page.goto("/admin/transfer-requisitions");
        await page.locator('button[aria-label="Actions"]').first().click();
        await page.locator("text=Receive").first().click();

        // Now on the scan page. Fill the good qty input and submit.
        await page.waitForURL(/\/stn\/.+\/scan/);
        const firstQtyInput = page.locator('input[type="number"]').first();
        await firstQtyInput.fill("10");
        await page.locator('button:has-text("Submit scan")').click();

        // Back to the requisition — status is Completed.
        await page.goto(`/admin/transfer-requisitions/${requisitionId}`);
        await expect(page.locator("body")).toContainText("Completed");
    });
});
