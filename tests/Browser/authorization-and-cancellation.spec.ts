import { test, expect } from "@playwright/test";
import { freshSeed, tinker } from "./helpers/artisan";
import {
    loginAsAdmin,
    loginAsAuditor,
    loginAsSingleWarehouse,
} from "./helpers/login";
import {
    makeWarehouse,
    makeVariant,
    makeConfirmedRequisition,
} from "./helpers/fixtures";

/**
 * Scenarios 5, 6, 11 — Authorization bypass, cancellation boundary,
 * sales cancellation boundary.
 */
test.describe("Authorization & Cancellation", () => {
    test.beforeAll(() => {
        freshSeed();
    });

    test("5 — auditor cannot mutate operational documents", async ({
        page,
    }) => {
        const from = makeWarehouse("Auth From");
        const to = makeWarehouse("Auth To");
        const variant = makeVariant("SKU-AUTH-AA");

        const { requisitionId } = makeConfirmedRequisition(
            from.id,
            to.id,
            variant.id,
            10,
        );

        await loginAsAuditor(page);

        // Auditor has no dispatch action (TransferRequisitionPolicy::dispatch denies).
        const result = tinker(`
      \\Illuminate\\Support\\Facades\\Auth::login(\\App\\Models\\User::where('email', 'auditor@example.test')->first());
      echo auth()->user()->can('dispatch', \\App\\Models\\TransferRequisition::find(${requisitionId})) ? 'ALLOWED' : 'DENIED';
    `);

        expect(result).toContain("DENIED");
    });

    test("6 — cancel is blocked for post-dispatch states", async ({ page }) => {
        const from = makeWarehouse("Cancel From");
        const to = makeWarehouse("Cancel To");
        const variant = makeVariant("SKU-CANCEL-AA");

        const { requisitionId } = makeConfirmedRequisition(
            from.id,
            to.id,
            variant.id,
            10,
        );

        // Dispatch to move to a post-cancel state.
        tinker(`
      \\Illuminate\\Support\\Facades\\Auth::login(\\App\\Models\\User::where('email', 'admin@example.test')->first());
      app(\\App\\Services\\InventoryService::class)->dispatchTransfer(\\App\\Models\\TransferRequisition::find(${requisitionId}));
    `);

        await loginAsAdmin(page);

        // canBeCancelled() returns false — the cancel action is hidden.
        const cancellable = tinker(`
      echo \\App\\Models\\TransferRequisition::find(${requisitionId})->canBeCancelled() ? 'TRUE' : 'FALSE';
    `);
        expect(cancellable).toContain("FALSE");
    });

    test("11 — sales order cancellation is blocked after dispatch", async ({
        page,
    }) => {
        // §3.16: canBeCancelled() permits Draft and Confirmed only.
        const cancelled = tinker(`
      $so = \\App\\Models\\SalesOrder::factory()->create(['status' => \\App\\Enums\\SalesOrderStatus::Dispatched]);
      echo $so->canBeCancelled() ? 'TRUE' : 'FALSE';
    `);
        expect(cancelled).toContain("FALSE");
    });
});
