import { test, expect } from "@playwright/test";
import { freshSeed, tinker } from "./helpers/artisan";
import { loginAsAdmin, loginAsSingleWarehouse } from "./helpers/login";
import { makeWarehouse } from "./helpers/fixtures";

/**
 * Scenarios 15, 15b, 15c — Navigation badges.
 */
test.describe("Navigation Badges", () => {
    test.beforeAll(() => {
        freshSeed();
    });

    test("15 — badge count visible on Transfer Requisitions sidebar item", async ({
        page,
    }) => {
        // Seed one Requested requisition.
        tinker(`
      $from = \\App\\Models\\Warehouse::factory()->create();
      $to = \\App\\Models\\Warehouse::factory()->create();
      \\App\\Models\\TransferRequisition::factory()->requested()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
      ]);
    `);

        await loginAsAdmin(page);

        const sidebarEntry = page.locator(
            'a:has-text("Transfer Requisitions")',
        );
        await expect(sidebarEntry.locator("text=1")).toBeVisible();
    });

    test("15b — single-warehouse staff sees only their warehouse in the badge", async ({
        page,
    }) => {
        // Create a staff user assigned to one warehouse, plus requisitions
        // in two warehouses.
        const warehouseA = makeWarehouse("Badge A");
        const warehouseB = makeWarehouse("Badge B");

        tinker(`
      $staff = \\App\\Models\\User::factory()->create(['email' => 'badge.test@example.test']);
      $staff->warehouses()->attach(${warehouseA.id});

      // Requisition touching A — counted.
      \\App\\Models\\TransferRequisition::factory()->requested()->create([
        'from_warehouse_id' => ${warehouseA.id},
        'to_warehouse_id' => ${warehouseB.id},
      ]);
      // Requisition touching B only — not counted.
      $other = \\App\\Models\\Warehouse::factory()->create();
      \\App\\Models\\TransferRequisition::factory()->requested()->create([
        'from_warehouse_id' => ${warehouseB.id},
        'to_warehouse_id' => $other->id,
      ]);
    `);

        await page.goto("/admin/login");
        await page.fill('input[id="data.email"]', "badge.test@example.test");
        await page.fill('input[id="data.password"]', "password");
        await page.click('button[type="submit"]');
        await page.waitForURL(/\/admin(?!\/login)/);

        const sidebarEntry = page.locator(
            'a:has-text("Transfer Requisitions")',
        );
        // Badge should be 1 — only the A-touching requisition.
        await expect(sidebarEntry.locator("text=1")).toBeVisible();
    });

    test("15c — admin with empty pivot sees the full count", async ({
        page,
    }) => {
        tinker(`
      $a = \\App\\Models\\Warehouse::factory()->create();
      $b = \\App\\Models\\Warehouse::factory()->create();
      \\App\\Models\\TransferRequisition::factory()->count(3)->requested()->create([
        'from_warehouse_id' => $a->id,
        'to_warehouse_id' => $b->id,
      ]);
    `);

        await loginAsAdmin(page);

        const sidebarEntry = page.locator(
            'a:has-text("Transfer Requisitions")',
        );
        await expect(sidebarEntry.locator("text=3")).toBeVisible();
    });
});
