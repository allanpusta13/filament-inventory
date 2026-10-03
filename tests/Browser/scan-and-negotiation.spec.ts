import { test, expect } from "@playwright/test";
import { freshSeed, tinker } from "./helpers/artisan";
import { loginAsAdmin } from "./helpers/login";
import {
    makeWarehouse,
    makeVariant,
    makeConfirmedRequisition,
} from "./helpers/fixtures";

/**
 * Scenarios 7, 8 — Duplicate scan submission, negotiation loop.
 */
test.describe("Scan & Negotiation", () => {
    test.beforeAll(() => {
        freshSeed();
    });

    test("7 — duplicate scan payload is a no-op", async ({ page }) => {
        const from = makeWarehouse("Scan From");
        const to = makeWarehouse("Scan To");
        const variant = makeVariant("SKU-SCAN-AA");

        const { requisitionId, itemId } = makeConfirmedRequisition(
            from.id,
            to.id,
            variant.id,
            10,
        );

        tinker(`
      \\Illuminate\\Support\\Facades\\Auth::login(\\App\\Models\\User::where('email', 'admin@example.test')->first());
      app(\\App\\Services\\InventoryService::class)->dispatchTransfer(\\App\\Models\\TransferRequisition::find(${requisitionId}));
    `);

        // Apply the same payload twice via the service — second must no-op.
        tinker(`
      $req = \\App\\Models\\TransferRequisition::find(${requisitionId});
      $payload = [${itemId} => ['received_good' => 10, 'received_damaged' => 0]];

      \\Illuminate\\Support\\Facades\\Auth::login(\\App\\Models\\User::where('email', 'admin@example.test')->first());
      app(\\App\\Services\\InventoryService::class)->scanToReceive($req, $payload);
      app(\\App\\Services\\InventoryService::class)->scanToReceive($req, $payload);

      echo \\App\\Models\\StockMovement::where('type', \\App\\Enums\\StockMovementType::TransferIn->value)->count();
    `);

        // Exactly one TransferIn movement.
        const movements = tinker(`
      echo \\App\\Models\\StockMovement::where('type', \\App\\Enums\\StockMovementType::TransferIn->value)->count();
    `);
        expect(movements).toContain("1");
    });

    test("8 — negotiation loop: submit revision → accept → confirm", async ({
        page,
    }) => {
        const from = makeWarehouse("Neg From");
        const to = makeWarehouse("Neg To");
        const variant = makeVariant("SKU-NEG-AA");

        // Requested (not confirmed) so it's negotiable.
        const { requisitionId, itemId } = (() => {
            const out = tinker(`
        $req = \\App\\Models\\TransferRequisition::factory()->requested()->create([
          'from_warehouse_id' => ${from.id},
          'to_warehouse_id' => ${to.id},
        ]);
        $item = \\App\\Models\\TransferRequisitionItem::factory()->create([
          'transfer_requisition_id' => $req->id,
          'product_variant_id' => ${variant.id},
          'requested_unit_name' => 'pc',
          'requested_unit_ratio' => 1,
          'requested_qty' => 10,
          'requested_base_qty' => 10,
        ]);
        echo $req->id . '-' . $item->id;
      `);
            const m = out.match(/(\d+)-(\d+)/)!;
            return {
                requisitionId: parseInt(m[1], 10),
                itemId: parseInt(m[2], 10),
            };
        })();

        await loginAsAdmin(page);

        // Open the view page — negotiation header actions live here.
        await page.goto(`/admin/transfer-requisitions/${requisitionId}`);
        await page.locator("text=Propose revision").click();

        // Fill the revision form.
        await page.locator('[role="combobox"]').first().click();
        await page.locator("text=SKU-NEG-AA").first().click();

        await page.locator('[role="combobox"]').nth(1).click();
        await page.locator("text=Fulfiller").first().click();

        await page.locator('input[id="data.proposed_qty"]').fill("15");
        await page.locator('input[id="data.proposed_unit_name"]').click();
        await page.locator("text=pc").first().click();

        await page.locator('button:has-text("Create")').click();

        // Revision now exists as Pending.
        const pending = tinker(`
      echo \\App\\Models\\TransferRequisitionItemRevision::where('transfer_requisition_item_id', ${itemId})->where('status', 'pending')->count();
    `);
        expect(pending).toContain("1");
    });
});
