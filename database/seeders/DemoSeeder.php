<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => UserRole::Admin->value,
        ]);

        $warehouseStaff = User::factory()->create([
            'name' => 'Warehouse Staff',
            'email' => 'staff@example.com',
            'role' => UserRole::WarehouseStaff->value,
        ]);

        $warehouses = collect([
            Warehouse::factory()->create(['name' => 'Main Warehouse', 'location' => 'Building A, Floor 1', 'is_active' => true]),
            Warehouse::factory()->create(['name' => 'East Storage', 'location' => 'Building B, Ground Floor', 'is_active' => true]),
            Warehouse::factory()->create(['name' => 'Cold Storage', 'location' => 'Building C, Basement', 'is_active' => true]),
            Warehouse::factory()->create(['name' => 'Overflow Lot', 'location' => 'External Yard', 'is_active' => false]),
        ]);

        $warehouseStaff->warehouses()->attach([$warehouses[0]->id, $warehouses[1]->id]);

        $categories = ['Electronics', 'Hardware', 'Packaging', 'Raw Materials', 'Finished Goods'];

        $products = collect([
            Product::factory()->create(['sku' => 'SKU-001', 'name' => 'Widget Alpha', 'category' => 'Electronics', 'reorder_point' => 20]),
            Product::factory()->create(['sku' => 'SKU-002', 'name' => 'Widget Beta', 'category' => 'Electronics', 'reorder_point' => 15]),
            Product::factory()->create(['sku' => 'SKU-003', 'name' => 'Bolt M8x30', 'category' => 'Hardware', 'reorder_point' => 100]),
            Product::factory()->create(['sku' => 'SKU-004', 'name' => 'Nut M8', 'category' => 'Hardware', 'reorder_point' => 100]),
            Product::factory()->create(['sku' => 'SKU-005', 'name' => 'Cardboard Box L', 'category' => 'Packaging', 'reorder_point' => 50]),
            Product::factory()->create(['sku' => 'SKU-006', 'name' => 'Bubble Wrap Roll', 'category' => 'Packaging', 'reorder_point' => 10]),
            Product::factory()->create(['sku' => 'SKU-007', 'name' => 'Steel Sheet 2mm', 'category' => 'Raw Materials', 'reorder_point' => 25]),
            Product::factory()->create(['sku' => 'SKU-008', 'name' => 'Aluminum Rod', 'category' => 'Raw Materials', 'reorder_point' => 30]),
            Product::factory()->create(['sku' => 'SKU-009', 'name' => 'PCB Assembly v2', 'category' => 'Finished Goods', 'reorder_point' => 10]),
            Product::factory()->create(['sku' => 'SKU-010', 'name' => 'Motor Controller', 'category' => 'Finished Goods', 'reorder_point' => 8]),
            Product::factory()->create(['sku' => 'SKU-011', 'name' => 'Capacitor 100uF', 'category' => 'Electronics', 'reorder_point' => 200]),
            Product::factory()->create(['sku' => 'SKU-012', 'name' => 'Resistor Pack', 'category' => 'Electronics', 'reorder_point' => 150]),
            Product::factory()->create(['sku' => 'SKU-013', 'name' => 'Washer M8', 'category' => 'Hardware', 'reorder_point' => 200]),
            Product::factory()->create(['sku' => 'SKU-014', 'name' => 'Thread Seal Tape', 'category' => 'Hardware', 'reorder_point' => 40]),
            Product::factory()->create(['sku' => 'SKU-015', 'name' => 'Pallet Wrap', 'category' => 'Packaging', 'is_active' => true, 'reorder_point' => 15]),
            Product::factory()->create(['sku' => 'SKU-016', 'name' => 'Copper Wire Spool', 'category' => 'Raw Materials', 'reorder_point' => 20]),
            Product::factory()->create(['sku' => 'SKU-017', 'name' => 'Servo Unit', 'category' => 'Finished Goods', 'reorder_point' => 5]),
            Product::factory()->create(['sku' => 'SKU-018', 'name' => 'Heatsink Block', 'category' => 'Hardware', 'reorder_point' => 35]),
            Product::factory()->create(['sku' => 'SKU-019', 'name' => 'Thermal Paste', 'category' => 'Electronics', 'reorder_point' => 25]),
            Product::factory()->create(['sku' => 'SKU-020', 'name' => 'Label Roll', 'category' => 'Packaging', 'reorder_point' => 30]),
        ]);

        $movementTypes = [
            MovementType::Receive,
            MovementType::Ship,
            MovementType::TransferIn,
            MovementType::TransferOut,
            MovementType::Adjustment,
        ];

        $now = Carbon::now();

        foreach ($products as $product) {
            $baseQty = $product->reorder_point > 50 ? (int) ($product->reorder_point * 1.8) : (int) ($product->reorder_point * 4);

            StockMovement::factory()->create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouses[0]->id,
                'type' => MovementType::Receive,
                'quantity' => $baseQty,
                'reference' => 'INIT-001',
                'created_at' => $now->copy()->subDays(25),
            ]);

            $lowStockProducts = ['SKU-003', 'SKU-004', 'SKU-011', 'SKU-012', 'SKU-013'];
            $outOfStockProducts = ['SKU-017'];

            if (in_array($product->sku, $outOfStockProducts)) {
                StockMovement::factory()->create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouses[0]->id,
                    'type' => MovementType::Ship,
                    'quantity' => -$baseQty,
                    'reference' => 'SHIP-OOS',
                    'created_at' => $now->copy()->subDays(20),
                ]);
            } elseif (in_array($product->sku, $lowStockProducts)) {
                StockMovement::factory()->create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouses[0]->id,
                    'type' => MovementType::Ship,
                    'quantity' => -(int) ($baseQty * 0.92),
                    'reference' => 'SHIP-LOW',
                    'created_at' => $now->copy()->subDays(18),
                ]);
            } else {
                StockMovement::factory()->create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouses[0]->id,
                    'type' => MovementType::Ship,
                    'quantity' => -(int) ($baseQty * 0.4),
                    'reference' => 'SHIP-STD',
                    'created_at' => $now->copy()->subDays(15),
                ]);
            }

            for ($day = 20; $day >= 1; $day--) {
                if (rand(1, 100) <= 60) {
                    $type = $movementTypes[array_rand($movementTypes)];
                    $qty = (int) max(1, $product->reorder_point * rand(5, 30) / 100);

                    if (in_array($type, [MovementType::Ship, MovementType::TransferOut])) {
                        $qty = -$qty;
                    }

                    StockMovement::factory()->create([
                        'product_id' => $product->id,
                        'warehouse_id' => $warehouses[array_rand([0, 1, 2])]->id,
                        'type' => $type,
                        'quantity' => $qty,
                        'reference' => mb_strtoupper($type->value).'-'.mb_str_pad((string) $day, 3, '0', STR_PAD_LEFT),
                        'created_at' => $now->copy()->subDays($day)->addHours(rand(8, 17)),
                    ]);
                }
            }
        }

        $this->command?->info('Demo seeded: 4 warehouses, 20 products, '.StockMovement::count().' movements, 2 users.');
    }
}
