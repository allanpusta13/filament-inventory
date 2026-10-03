import { test, expect } from "@playwright/test";
import { freshSeed, tinker } from "./helpers/artisan";
import { loginAsAdmin } from "./helpers/login";
import {
    makeWarehouse,
    makeVariant,
    makeConfirmedRequisition,
} from "./helpers/fixtures";

/**
 * Scenarios 3, 4, 14 — Loss, soft-delete, base-unit deletion.
 */
test.describe("Loss, Soft-Delete, Base-Unit Deletion", () => {
    test.beforeAll(() => {
        freshSeed();
    });

    test("3 — loss write-off from the recordLoss modal", async ({ page }) => {
        const from = makeWarehouse("Loss From");
        const to = makeWarehouse("Loss To");
        const variant = makeVariant("SKU-LOSS-AA");

        const { requisitionId, itemId } = makeConfirmedRequisition(
            from.id,
            to.id,
            variant.id,
            10,
        );

        // Dispatch via service to move to Dispatched.
        tinker(`
      \\Illuminate\\Support\\Facades\\Auth::login(\\App\\Models\\User::where('email', 'admin@example.test')->first());
      app(\\App\\Services\\InventoryService::class)->dispatchTransfer(\\App\\Models\\TransferRequisition::find(${requisitionId}));
    `);

        await loginAsAdmin(page);

        // Loss via the recordLoss modal on the requisition view page.
        await page.goto(`/admin/transfer-requisitions/${requisitionId}`);
        await page.locator('button[aria-label="Actions"]').first().click();
        await page.locator("text=Record loss").first().click();

        await page.locator('[role="combobox"]').first().click();
        await page.locator(`text=SKU-LOSS-AA`).first().click();

        // Fill lost qty.
        const lostInput = page.locator('input[id="data.lost_base_qty"]');
        await lostInput.fill("10");

        await page.locator('button:has-text("Confirm")').click();

        // Verify the loss ledger row exists.
        const count = tinker(`
      echo \\App\\Models\\LossLedger::where('product_variant_id', ${variant.id})->count();
    `);
        expect(count).toContain("1");
    });

    test("4 — soft-delete guard blocks a product family with variants", async ({
        page,
    }) => {
        const variant = makeVariant("SKU-GUARD-AA");

        await loginAsAdmin(page);

        // Product deletion via the UI must throw (guard).
        const result = tinker(`
      try {
        \\App\\Models\\Product::find(${variant.id} ? \\App\\Models\\ProductVariant::find(${variant.id})->product_id : 1)->delete();
        echo 'DELETED';
      } catch (\\App\\Exceptions\\ProductFamilyHasVariantsException $e) {
        echo 'GUARDED';
      }
    `);

        expect(result).toContain("GUARDED");
    });

    test("14 — base-unit self-conversion row is undeletable via the UI", async ({
        page,
    }) => {
        const variant = makeVariant("SKU-BASE-AA", "pc");

        await loginAsAdmin(page);

        // Verify the observer created the base row.
        const baseRow = tinker(`
      echo \\App\\Models\\ProductVariantUnitConversion::where('product_variant_id', ${variant.id})
        ->where('unit_name', 'pc')
        ->where('base_unit_ratio', 1)
        ->count();
    `);
        expect(baseRow).toContain("1");

        // The action's UI hides the delete button on the base row.
        await page.goto(`/admin/products/${variant.id}`);
        await page.locator('button[aria-label="Actions"]').first().click();
        await page.locator("text=Manage units").first().click();

        // The base-unit row should not have a delete button.
        const deleteButtonsOnBaseRow = await page
            .locator("text=pc")
            .locator(
                'xpath=ancestor::div[contains(@class, "fi-fo-repeater-item")]',
            )
            .locator('button[aria-label*="Delete"]')
            .count();

        expect(deleteButtonsOnBaseRow).toBe(0);
    });
});
