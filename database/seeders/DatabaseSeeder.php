<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\TransferRequisitionStatus;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductVariantUnitConversion;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Supplier;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Support\GeneratesReferenceCodes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * DatabaseSeeder — the canonical opening test bed (§11 Phase 02).
 *
 * Seeds, in FK-dependency order:
 *   1. Warehouses              — 3
 *   2. Users                   — admin, auditor, branch manager, warehouse staff
 *   3. Products & Variants     — 10 variants + current price + unit conversion
 *   4. Suppliers & Customers   — 3 each
 *   5. Opening ledger          — 100 base units per (variant, warehouse) pair
 *   6. Direct transfers        — 2 completed (via InventoryService)
 *   7. Purchase orders         — draft, ordered, and partially-received
 *   8. Sales orders            — draft, confirmed, and dispatched
 *   9. Transfer requisitions   — draft, requested, and confirmed
 *
 * Every ledger write goes through a service:
 *   - Opening balance:  InventoryService::adjustment()
 *   - Direct transfers: InventoryService::directTransfer()
 *   - Purchase receipt: PurchaseService::receivePurchase()
 *   - Sales dispatch:   SalesService::dispatchSale()
 *
 * No direct StockMovement / LossLedger / InTransit inserts.
 *
 * Authentication: some services (adjustment, directTransfer) read
 * `auth()->user()` for actor scope and audit. We log in as the admin
 * before those calls. The admin's empty `user_warehouse` pivot is fine
 * — §6.2 skips the membership check for admins.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // -----------------------------------------------------------------
        // 1. Warehouses — 3 factory-made
        // -----------------------------------------------------------------
        $warehouses = Warehouse::factory()
            ->count(3)
            ->create();

        // -----------------------------------------------------------------
        // 2. Users
        // -----------------------------------------------------------------
        $admin = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $auditor = User::factory()->auditor()->create([
            'name' => 'Auditor User',
            'email' => 'auditor@example.com',
        ]);

        $branchManager = User::factory()->branchManager()->create([
            'name' => 'Branch Manager',
            'email' => 'branch.manager@example.com',
        ]);

        // Multi-warehouse staff — can create transfer requisitions and
        // direct transfers (§8.3 / §8.13 need ≥2 assignments).
        $multiWarehouseStaff = User::factory()->create([
            'name' => 'Multi-Warehouse Staff',
            'email' => 'staff.multi@example.com',
        ]);
        $multiWarehouseStaff->warehouses()->attach($warehouses->pluck('id'));

        // One staff member per warehouse — single-warehouse operational scope.
        foreach ($warehouses as $index => $warehouse) {
            $staff = User::factory()->create([
                'name' => "Warehouse {$warehouse->code} Staff",
                'email' => "staff.wh{$index}@example.com",
            ]);
            $staff->warehouses()->attach($warehouse->id);
        }

        // Authenticate as admin for the rest of the seed.
        Auth::login($admin);

        // -----------------------------------------------------------------
        // 3. Products & Variants — 10 variants, each with a current price
        //    and one non-base unit conversion.
        // -----------------------------------------------------------------
        $variants = ProductVariant::factory()
            ->count(10)
            ->create();

        foreach ($variants as $variant) {
            ProductVariantPrice::factory()->create([
                'product_variant_id' => $variant->id,
                'is_current' => true,
            ]);

            // One non-base unit per variant — 'case' at ratio 24. The
            // base-unit self-conversion row is observer-materialized
            // (F19, §3.19), so we only seed the additional unit.
            ProductVariantUnitConversion::factory()->create([
                'product_variant_id' => $variant->id,
                'unit_name' => 'case',
                'base_unit_ratio' => 24,
                'is_default_purchase' => true,
            ]);
        }

        // -----------------------------------------------------------------
        // 4. Suppliers & Customers
        // -----------------------------------------------------------------
        $suppliers = Supplier::factory()
            ->count(3)
            ->create();

        $customers = Customer::factory()
            ->count(3)
            ->create();

        // -----------------------------------------------------------------
        // 5. Opening ledger — 100 base units per (warehouse, variant).
        //    Everything goes through InventoryService::adjustment().
        // -----------------------------------------------------------------
        $inventory = app(InventoryService::class);

        foreach ($warehouses as $warehouse) {
            foreach ($variants as $variant) {
                $inventory->adjustment(
                    productVariantId: $variant->id,
                    warehouseId: $warehouse->id,
                    signedBaseQuantity: 100,
                    notes: 'Opening balance (Phase 02 seed)',
                );
            }
        }

        // -----------------------------------------------------------------
        // 6. Direct transfers — two completed transfers via the service.
        // -----------------------------------------------------------------

        // DT-1: 2 lines, 5 units each, from warehouse[0] → warehouse[1]
        $inventory->directTransfer(
            fromWarehouseId: $warehouses[0]->id,
            toWarehouseId: $warehouses[1]->id,
            items: [
                [
                    'product_variant_id' => $variants[0]->id,
                    'unit_name' => 'pc',
                    'unit_ratio' => 1,
                    'qty' => 5,
                ],
                [
                    'product_variant_id' => $variants[1]->id,
                    'unit_name' => 'pc',
                    'unit_ratio' => 1,
                    'qty' => 5,
                ],
            ],
            referenceCode: GeneratesReferenceCodes::generateReferenceCode('DT', 101),
            notes: 'Sample direct transfer — 2 lines.',
        );

        // DT-2: 3 lines, 10 units each, from warehouse[1] → warehouse[2]
        $inventory->directTransfer(
            fromWarehouseId: $warehouses[1]->id,
            toWarehouseId: $warehouses[2]->id,
            items: [
                [
                    'product_variant_id' => $variants[2]->id,
                    'unit_name' => 'pc',
                    'unit_ratio' => 1,
                    'qty' => 10,
                ],
                [
                    'product_variant_id' => $variants[3]->id,
                    'unit_name' => 'pc',
                    'unit_ratio' => 1,
                    'qty' => 10,
                ],
                [
                    'product_variant_id' => $variants[4]->id,
                    'unit_name' => 'pc',
                    'unit_ratio' => 1,
                    'qty' => 10,
                ],
            ],
            referenceCode: GeneratesReferenceCodes::generateReferenceCode('DT', 102),
            notes: 'Sample direct transfer — 3 lines.',
        );

        // -----------------------------------------------------------------
        // 7. Purchase orders — draft, ordered, partially-received
        // -----------------------------------------------------------------

        // PO-1: Draft — editable sample
        $poDraft = PurchaseOrder::factory()->create([
            'supplier_id' => $suppliers[0]->id,
            'warehouse_id' => $warehouses[0]->id,
            'ordered_by' => $admin->id,
            'status' => PurchaseOrderStatus::Draft,
        ]);
        foreach ($variants->take(2) as $variant) {
            PurchaseOrderItem::factory()->create([
                'purchase_order_id' => $poDraft->id,
                'product_variant_id' => $variant->id,
                'ordered_unit_name' => 'case',
                'ordered_unit_ratio' => 24,
                'ordered_qty' => 2,
                'ordered_base_qty' => 48,
                'unit_cost_price' => '15.0000',
            ]);
        }

        // PO-2: Ordered — awaiting receipt
        $poOrdered = PurchaseOrder::factory()->ordered()->create([
            'supplier_id' => $suppliers[1]->id,
            'warehouse_id' => $warehouses[1]->id,
            'ordered_by' => $admin->id,
        ]);
        foreach ($variants->skip(2)->take(2) as $variant) {
            PurchaseOrderItem::factory()->create([
                'purchase_order_id' => $poOrdered->id,
                'product_variant_id' => $variant->id,
                'ordered_unit_name' => 'pc',
                'ordered_unit_ratio' => 1,
                'ordered_qty' => 30,
                'ordered_base_qty' => 30,
                'unit_cost_price' => '12.5000',
            ]);
        }

        // PO-3: Ordered → partially received via the service.
        $poReceiving = PurchaseOrder::factory()->ordered()->create([
            'supplier_id' => $suppliers[2]->id,
            'warehouse_id' => $warehouses[2]->id,
            'ordered_by' => $admin->id,
        ]);
        $poReceivingItems = [];
        foreach ($variants->skip(4)->take(2) as $variant) {
            $poReceivingItems[$variant->id] = PurchaseOrderItem::factory()->create([
                'purchase_order_id' => $poReceiving->id,
                'product_variant_id' => $variant->id,
                'ordered_unit_name' => 'pc',
                'ordered_unit_ratio' => 1,
                'ordered_qty' => 20,
                'ordered_base_qty' => 20,
                'unit_cost_price' => '8.0000',
            ]);
        }

        app(\App\Services\PurchaseService::class)->receivePurchase(
            $poReceiving->id,
            $poReceivingItems[$variants[4]->id]->id === null ? [] :
                [
                    $poReceivingItems[$variants[4]->id]->id => 10, // 10 of 20 received
                    $poReceivingItems[$variants[5]->id]->id => 20, // 20 of 20 received
                ],
        );

        // -----------------------------------------------------------------
        // 8. Sales orders — draft, confirmed, dispatched
        // -----------------------------------------------------------------

        // SO-1: Draft — editable sample
        $soDraft = SalesOrder::factory()->create([
            'customer_id' => $customers[0]->id,
            'warehouse_id' => $warehouses[0]->id,
            'ordered_by' => $admin->id,
            'status' => SalesOrderStatus::Draft,
        ]);
        foreach ($variants->take(2) as $variant) {
            SalesOrderItem::factory()->create([
                'sales_order_id' => $soDraft->id,
                'product_variant_id' => $variant->id,
                'unit_name' => 'pc',
                'unit_ratio' => 1,
                'qty' => 5,
                'base_qty' => 5,
                'unit_sale_price_snapshot' => '22.5000',
            ]);
        }

        // SO-2: Confirmed — awaiting dispatch
        $soConfirmed = SalesOrder::factory()->confirmed()->create([
            'customer_id' => $customers[1]->id,
            'warehouse_id' => $warehouses[1]->id,
            'ordered_by' => $admin->id,
        ]);
        foreach ($variants->skip(2)->take(2) as $variant) {
            SalesOrderItem::factory()->create([
                'sales_order_id' => $soConfirmed->id,
                'product_variant_id' => $variant->id,
                'unit_name' => 'pc',
                'unit_ratio' => 1,
                'qty' => 10,
                'base_qty' => 10,
                'unit_sale_price_snapshot' => '18.7500',
            ]);
        }

        // SO-3: Confirmed → dispatched via the service
        $soDispatch = SalesOrder::factory()->confirmed()->create([
            'customer_id' => $customers[2]->id,
            'warehouse_id' => $warehouses[2]->id,
            'ordered_by' => $admin->id,
        ]);
        $soDispatchItems = [];
        foreach ($variants->skip(6)->take(2) as $variant) {
            $soDispatchItems[] = SalesOrderItem::factory()->create([
                'sales_order_id' => $soDispatch->id,
                'product_variant_id' => $variant->id,
                'unit_name' => 'pc',
                'unit_ratio' => 1,
                'qty' => 15,
                'base_qty' => 15,
                'unit_sale_price_snapshot' => '25.0000',
            ]);
        }

        app(\App\Services\SalesService::class)->dispatchSale(
            $soDispatch->id,
            collect($soDispatchItems)
                ->mapWithKeys(fn ($item) => [$item->id => 15])
                ->all(),
        );

        // -----------------------------------------------------------------
        // 9. Transfer requisitions — draft, requested, confirmed
        // -----------------------------------------------------------------

        // TR-1: Draft
        $trDraft = TransferRequisition::factory()->create([
            'from_warehouse_id' => $warehouses[0]->id,
            'to_warehouse_id' => $warehouses[1]->id,
            'requested_by' => $multiWarehouseStaff->id,
            'status' => TransferRequisitionStatus::Draft,
        ]);
        foreach ($variants->take(2) as $variant) {
            TransferRequisitionItem::factory()->create([
                'transfer_requisition_id' => $trDraft->id,
                'product_variant_id' => $variant->id,
                'requested_unit_name' => 'pc',
                'requested_unit_ratio' => 1,
                'requested_qty' => 8,
                'requested_base_qty' => 8,
            ]);
        }

        // TR-2: Requested — awaiting review
        $trRequested = TransferRequisition::factory()->requested()->create([
            'from_warehouse_id' => $warehouses[1]->id,
            'to_warehouse_id' => $warehouses[2]->id,
            'requested_by' => $multiWarehouseStaff->id,
        ]);
        foreach ($variants->skip(2)->take(2) as $variant) {
            TransferRequisitionItem::factory()->create([
                'transfer_requisition_id' => $trRequested->id,
                'product_variant_id' => $variant->id,
                'requested_unit_name' => 'pc',
                'requested_unit_ratio' => 1,
                'requested_qty' => 12,
                'requested_base_qty' => 12,
            ]);
        }

        // TR-3: Confirmed — approved and awaiting dispatch.
        // Confirm via the service so materialization runs canonically.
        $trConfirmed = TransferRequisition::factory()->requested()->create([
            'from_warehouse_id' => $warehouses[2]->id,
            'to_warehouse_id' => $warehouses[0]->id,
            'requested_by' => $multiWarehouseStaff->id,
        ]);
        foreach ($variants->skip(4)->take(2) as $variant) {
            TransferRequisitionItem::factory()->create([
                'transfer_requisition_id' => $trConfirmed->id,
                'product_variant_id' => $variant->id,
                'requested_unit_name' => 'pc',
                'requested_unit_ratio' => 1,
                'requested_qty' => 6,
                'requested_base_qty' => 6,
            ]);
        }

        app(\App\Services\TransferRequisitionService::class)->confirm($trConfirmed);

        // -----------------------------------------------------------------
        // 10. Summary output
        // -----------------------------------------------------------------

        $this->command->info('✅ Seeded:');
        $this->command->line('   Warehouses:        '.Warehouse::count());
        $this->command->line('   Users:             '.User::count());
        $this->command->line('   Variants:          '.ProductVariant::count());
        $this->command->line('   Suppliers:         '.Supplier::count());
        $this->command->line('   Customers:         '.Customer::count());
        $this->command->line('   Purchase Orders:   '.PurchaseOrder::count());
        $this->command->line('   Sales Orders:      '.SalesOrder::count());
        $this->command->line('   Requisitions:      '.TransferRequisition::count());
        $this->command->line('   Direct Transfers:  '.\App\Models\DirectTransfer::count());
        $this->command->line('   Stock Movements:   '.\App\Models\StockMovement::count());
    }
}
