import { test, expect } from "@playwright/test";
import { freshSeed, tinker } from "./helpers/artisan";
import { loginAsAdmin } from "./helpers/login";
import {
    makeWarehouse,
    makeVariant,
    makeOrderedPurchaseOrder,
    makeConfirmedSalesOrder,
} from "./helpers/fixtures";

/**
 * Scenarios 9, 10, 19 — Purchase lifecycle (over-receive), sales
 * lifecycle (over-return), sales dispatch self-reservation.
 */
test.describe("Purchase & Sales", () => {
    test.beforeAll(() => {
        freshSeed();
    });

    test("9 — purchase receive blocks over-receive", async ({ page }) => {
        const warehouse = makeWarehouse("PO WH");
        const variant = makeVariant("SKU-PO-AA");

        const { orderId, itemId } = makeOrderedPurchaseOrder(
            warehouse.id,
            variant.id,
            10,
        );

        await loginAsAdmin(page);
        await page.goto("/admin/purchase-orders");
        await page.locator('button[aria-label="Actions"]').first().click();
        await page.locator("text=Receive").first().click();

        // The receive input is capped by `->maxValue($outstanding)` — fill
        // with an over-receive and verify the client validation blocks it.
        const input = page.locator('input[type="number"]').first();
        await input.fill("999");

        // Assert the value was clamped.
        const value = await input.inputValue();
        expect(parseInt(value, 10)).toBeLessThanOrEqual(10);
    });

    test("10 — sales return blocks over-return", async ({ page }) => {
        const warehouse = makeWarehouse("SO WH");
        const variant = makeVariant("SKU-SO-AA");

        const { orderId, itemId } = makeConfirmedSalesOrder(
            warehouse.id,
            variant.id,
            10,
        );

        // Dispatch 10.
        tinker(`
      \\Illuminate\\Support\\Facades\\Auth::login(\\App\\Models\\User::where('email', 'admin@example.test')->first());
      app(\\App\\Services\\SalesService::class)->dispatchSale(${orderId}, [${itemId} => 10]);
    `);

        await loginAsAdmin(page);
        await page.goto("/admin/sales-orders");
        await page.locator('button[aria-label="Actions"]').first().click();
        await page.locator("text=Return").first().click();

        // Fill an over-return — maxValue caps at 10.
        const input = page.locator('input[type="number"]').first();
        await input.fill("999");

        const value = await input.inputValue();
        expect(parseInt(value, 10)).toBeLessThanOrEqual(10);
    });

    test("19 — sales dispatch succeeds for own-reserved stock", async ({
        page,
    }) => {
        // §6.5: dispatch excludes the order's own reservation from availability.
        // On-hand 5, order reserves 5. Dispatch of 5 succeeds.
        const warehouse = makeWarehouse("Self-Res WH");
        const variant = makeVariant("SKU-SR-AA");

        tinker(`
      \\App\\Models\\StockMovement::factory()->create([
        'product_variant_id' => ${variant.id},
        'warehouse_id' => ${warehouse.id},
        'type' => \\App\\Enums\\StockMovementType::Adjustment,
        'quantity' => 5,
      ]);
    `);

        const { orderId, itemId } = makeConfirmedSalesOrder(
            warehouse.id,
            variant.id,
            5,
        );

        const result = tinker(`
      \\Illuminate\\Support\\Facades\\Auth::login(\\App\\Models\\User::where('email', 'admin@example.test')->first());
      try {
        app(\\App\\Services\\SalesService::class)->dispatchSale(${orderId}, [${itemId} => 5]);
        echo 'DISPATCHED';
      } catch (\\Throwable $e) {
        echo 'FAILED:' . $e->getMessage();
      }
    `);

        expect(result).toContain("DISPATCHED");
    });
});
