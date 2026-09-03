<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Enums\TransferRequisitionStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class DemoWorkflowSeeder extends Seeder
{
    private User $admin;
    private User $managerMnl;
    private User $managerCeb;
    private User $staffDvo;

    /** @var Collection<int, Warehouse> */
    private $warehouses;

    /** @var Collection<int, ProductVariant> */
    private $variants;

    public function run(): void
    {
        $this->createUsers();
        $this->createWarehouses();
        $this->createProducts();
        $this->seedStock();
        $this->createCompletedRequisition();
        $this->createPartiallyReceivedRequisition();
        $this->createClosedWithLossRequisition();

        $this->command?->info(
            'DemoWorkflowSeeder: ' . TransferRequisition::count() . ' requisitions, '
            . TransferRequisitionItem::count() . ' items, '
            . StockMovement::count() . ' movements.'
        );
    }

    private function createUsers(): void
    {
        $this->admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'role' => UserRole::Admin->value,
                'password' => bcrypt('password'),
            ]
        );

        $this->managerMnl = User::firstOrCreate(
            ['email' => 'manager.mnl@example.com'],
            [
                'name' => 'Manager Manila',
                'role' => UserRole::BranchManager->value,
                'password' => bcrypt('password'),
            ]
        );

        $this->managerCeb = User::firstOrCreate(
            ['email' => 'manager.ceb@example.com'],
            [
                'name' => 'Manager Cebu',
                'role' => UserRole::BranchManager->value,
                'password' => bcrypt('password'),
            ]
        );

        $this->staffDvo = User::firstOrCreate(
            ['email' => 'staff.dvo@example.com'],
            [
                'name' => 'Staff Davao',
                'role' => UserRole::WarehouseStaff->value,
                'password' => bcrypt('password'),
            ]
        );
    }

    private function createWarehouses(): void
    {
        $this->warehouses = collect([
            Warehouse::firstOrCreate(
                ['code' => 'WH-MNL'],
                ['name' => 'Manila Warehouse', 'location' => 'Makati City, Metro Manila', 'is_active' => true]
            ),
            Warehouse::firstOrCreate(
                ['code' => 'WH-CEB'],
                ['name' => 'Cebu Warehouse', 'location' => 'Cebu City, Central Visayas', 'is_active' => true]
            ),
            Warehouse::firstOrCreate(
                ['code' => 'WH-DVO'],
                ['name' => 'Davao Warehouse', 'location' => 'Davao City, Davao Region', 'is_active' => true]
            ),
        ]);

        $this->managerMnl->warehouses()->syncWithoutDetaching($this->warehouses[0]->id);
        $this->managerCeb->warehouses()->syncWithoutDetaching($this->warehouses[1]->id);
        $this->staffDvo->warehouses()->syncWithoutDetaching($this->warehouses[2]->id);
    }

    private function createProducts(): void
    {
        $products = collect([
            Product::factory()->create(['sku' => 'WF-001', 'name' => 'Widget A', 'category' => 'Electronics', 'reorder_point' => 20]),
            Product::factory()->create(['sku' => 'WF-002', 'name' => 'Widget B', 'category' => 'Electronics', 'reorder_point' => 15]),
            Product::factory()->create(['sku' => 'WF-003', 'name' => 'Bolt M10x40', 'category' => 'Hardware', 'reorder_point' => 100]),
            Product::factory()->create(['sku' => 'WF-004', 'name' => 'Cardboard Box M', 'category' => 'Packaging', 'reorder_point' => 50]),
            Product::factory()->create(['sku' => 'WF-005', 'name' => 'Steel Rod 6mm', 'category' => 'Raw Materials', 'reorder_point' => 25]),
        ]);

        $this->variants = $products->map(fn (Product $product) => $product->variants()->firstOrCreate(
            ['sku' => $product->sku . '-001'],
            [
                'name' => $product->name . ' Default',
                'attributes' => '{}',
                'base_unit_name' => 'piece',
                'cost_price' => 15.00,
                'sale_price' => 30.00,
            ]
        ));
    }

    private function seedStock(): void
    {
        $now = Carbon::now();

        // Seed 500 units of each variant in WH-MNL and WH-CEB
        foreach ($this->variants as $index => $variant) {
            StockMovement::factory()->create([
                'variant_id' => $variant->id,
                'warehouse_id' => $this->warehouses[0]->id,
                'type' => MovementType::Receive,
                'quantity' => 500,
                'reference_code' => 'INIT-MNL-' . ($index + 1),
                'created_by' => $this->admin->id,
                'created_at' => $now->copy()->subDays(30),
            ]);

            StockMovement::factory()->create([
                'variant_id' => $variant->id,
                'warehouse_id' => $this->warehouses[1]->id,
                'type' => MovementType::Receive,
                'quantity' => 300,
                'reference_code' => 'INIT-CEB-' . ($index + 1),
                'created_by' => $this->admin->id,
                'created_at' => $now->copy()->subDays(30),
            ]);
        }
    }

    private function createCompletedRequisition(): void
    {
        $now = Carbon::now();

        // Create requisition: WH-MNL -> WH-CEB
        $requisition = TransferRequisition::create([
            'reference_code' => 'TRQ-WF-0001',
            'from_warehouse_id' => $this->warehouses[0]->id,
            'to_warehouse_id' => $this->warehouses[1]->id,
            'status' => TransferRequisitionStatus::Requested,
            'requested_by' => $this->managerCeb->id,
            'requested_at' => $now->copy()->subDays(10),
            'approved_by' => $this->managerMnl->id,
            'approved_at' => $now->copy()->subDays(9),
            'dispatched_by' => $this->managerMnl->id,
            'dispatched_at' => $now->copy()->subDays(7),
            'completed_at' => $now->copy()->subDays(5),
            'status' => TransferRequisitionStatus::Completed,
            'notes' => 'Routine restock transfer for Cebu warehouse',
        ]);

        $items = [
            ['variant_index' => 0, 'qty' => 50],
            ['variant_index' => 1, 'qty' => 30],
            ['variant_index' => 2, 'qty' => 100],
        ];

        foreach ($items as $itemData) {
            TransferRequisitionItem::create([
                'requisition_id' => $requisition->id,
                'variant_id' => $this->variants[$itemData['variant_index']]->id,
                'requested_unit_name' => 'piece',
                'requested_unit_ratio' => 1,
                'requested_qty' => $itemData['qty'],
                'requested_base_qty' => $itemData['qty'],
                'approved_unit_name' => 'piece',
                'approved_unit_ratio' => 1,
                'approved_qty' => $itemData['qty'],
                'approved_base_qty' => $itemData['qty'],
                'shipped_base_qty' => $itemData['qty'],
                'received_good_base_qty' => $itemData['qty'],
                'received_damaged_base_qty' => 0,
            ]);
        }

        // Create corresponding stock movements for the completed transfer
        foreach ($items as $itemData) {
            $variant = $this->variants[$itemData['variant_index']];

            StockMovement::factory()->create([
                'variant_id' => $variant->id,
                'warehouse_id' => $this->warehouses[0]->id,
                'type' => MovementType::TransitOut,
                'quantity' => -$itemData['qty'],
                'reference_code' => 'TRQ-WF-0001',
                'created_by' => $this->managerMnl->id,
                'created_at' => $now->copy()->subDays(7),
            ]);

            StockMovement::factory()->create([
                'variant_id' => $variant->id,
                'warehouse_id' => $this->warehouses[1]->id,
                'type' => MovementType::TransitIn,
                'quantity' => $itemData['qty'],
                'reference_code' => 'TRQ-WF-0001',
                'created_by' => $this->staffDvo->id,
                'created_at' => $now->copy()->subDays(5),
            ]);
        }
    }

    private function createPartiallyReceivedRequisition(): void
    {
        $now = Carbon::now();

        $requisition = TransferRequisition::create([
            'reference_code' => 'TRQ-WF-0002',
            'from_warehouse_id' => $this->warehouses[0]->id,
            'to_warehouse_id' => $this->warehouses[2]->id,
            'status' => TransferRequisitionStatus::Dispatched,
            'requested_by' => $this->staffDvo->id,
            'requested_at' => $now->copy()->subDays(5),
            'approved_by' => $this->managerMnl->id,
            'approved_at' => $now->copy()->subDays(4),
            'dispatched_by' => $this->managerMnl->id,
            'dispatched_at' => $now->copy()->subDays(2),
            'notes' => 'Urgent restock for Davao — partial delivery expected',
        ]);

        $items = [
            ['variant_index' => 0, 'qty' => 40],
            ['variant_index' => 3, 'qty' => 60],
        ];

        foreach ($items as $itemData) {
            TransferRequisitionItem::create([
                'requisition_id' => $requisition->id,
                'variant_id' => $this->variants[$itemData['variant_index']]->id,
                'requested_unit_name' => 'piece',
                'requested_unit_ratio' => 1,
                'requested_qty' => $itemData['qty'],
                'requested_base_qty' => $itemData['qty'],
                'approved_unit_name' => 'piece',
                'approved_unit_ratio' => 1,
                'approved_qty' => $itemData['qty'],
                'approved_base_qty' => $itemData['qty'],
                'shipped_base_qty' => $itemData['qty'],
                'received_good_base_qty' => 0,
                'received_damaged_base_qty' => 0,
            ]);
        }

        // TransitOut movements from WH-MNL
        foreach ($items as $itemData) {
            StockMovement::factory()->create([
                'variant_id' => $this->variants[$itemData['variant_index']]->id,
                'warehouse_id' => $this->warehouses[0]->id,
                'type' => MovementType::TransitOut,
                'quantity' => -$itemData['qty'],
                'reference_code' => 'TRQ-WF-0002',
                'created_by' => $this->managerMnl->id,
                'created_at' => $now->copy()->subDays(2),
            ]);
        }
    }

    private function createClosedWithLossRequisition(): void
    {
        $now = Carbon::now();

        $requisition = TransferRequisition::create([
            'reference_code' => 'TRQ-WF-0003',
            'from_warehouse_id' => $this->warehouses[1]->id,
            'to_warehouse_id' => $this->warehouses[2]->id,
            'status' => TransferRequisitionStatus::ClosedWithLoss,
            'requested_by' => $this->staffDvo->id,
            'requested_at' => $now->copy()->subDays(15),
            'approved_by' => $this->managerCeb->id,
            'approved_at' => $now->copy()->subDays(14),
            'dispatched_by' => $this->managerCeb->id,
            'dispatched_at' => $now->copy()->subDays(12),
            'completed_at' => $now->copy()->subDays(10),
            'notes' => 'Transfer with damaged goods — closed with loss record',
        ]);

        // Item: 100 dispatched, 80 received good, 10 damaged, 10 lost
        TransferRequisitionItem::create([
            'requisition_id' => $requisition->id,
            'variant_id' => $this->variants[4]->id,
            'requested_unit_name' => 'piece',
            'requested_unit_ratio' => 1,
            'requested_qty' => 100,
            'requested_base_qty' => 100,
            'approved_unit_name' => 'piece',
            'approved_unit_ratio' => 1,
            'approved_qty' => 100,
            'approved_base_qty' => 100,
            'shipped_base_qty' => 100,
            'received_good_base_qty' => 80,
            'received_damaged_base_qty' => 10,
        ]);

        // TransitOut from WH-CEB
        StockMovement::factory()->create([
            'variant_id' => $this->variants[4]->id,
            'warehouse_id' => $this->warehouses[1]->id,
            'type' => MovementType::TransitOut,
            'quantity' => -100,
            'reference_code' => 'TRQ-WF-0003',
            'created_by' => $this->managerCeb->id,
            'created_at' => $now->copy()->subDays(12),
        ]);

        // TransitIn to WH-DVO (only 80 good units)
        StockMovement::factory()->create([
            'variant_id' => $this->variants[4]->id,
            'warehouse_id' => $this->warehouses[2]->id,
            'type' => MovementType::TransitIn,
            'quantity' => 80,
            'reference_code' => 'TRQ-WF-0003',
            'created_by' => $this->staffDvo->id,
            'created_at' => $now->copy()->subDays(10),
        ]);

        // Loss movement for damaged items
        StockMovement::factory()->create([
            'variant_id' => $this->variants[4]->id,
            'warehouse_id' => $this->warehouses[2]->id,
            'type' => MovementType::Loss,
            'quantity' => -10,
            'reference_code' => 'TRQ-WF-0003-DAMAGE',
            'created_by' => $this->staffDvo->id,
            'created_at' => $now->copy()->subDays(10),
        ]);
    }
}
