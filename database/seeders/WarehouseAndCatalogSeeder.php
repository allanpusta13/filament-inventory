<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class WarehouseAndCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // 1. Seed Warehouses
        $warehouses = [
            ['code' => 'WH-MNL', 'name' => 'Main Manila Central Hub', 'location' => 'Metropolitan Manila', 'is_active' => true],
            ['code' => 'WH-CEB', 'name' => 'Cebu Regional Depot', 'location' => 'Mandaue City, Cebu', 'is_active' => true],
            ['code' => 'WH-DVO', 'name' => 'Davao Branch Store', 'location' => 'Davao City, Mindanao', 'is_active' => true],
        ];

        foreach ($warehouses as $warehouse) {
            if (DB::table('warehouses')->where('code', $warehouse['code'])->exists()) {
                DB::table('warehouses')
                    ->where('code', $warehouse['code'])
                    ->update(array_merge($warehouse, ['updated_at' => $now]));
            } else {
                DB::table('warehouses')->insert(array_merge($warehouse, ['created_at' => $now, 'updated_at' => $now]));
            }
        }

        // 2. Seed Base Products
        $products = [
            ['id' => 1, 'sku' => 'PROD-COF', 'name' => 'Premium Coffee Brand', 'category' => 'Beverage Ingredients', 'reorder_point' => 5000],
            ['id' => 2, 'sku' => 'PROD-CUP', 'name' => 'Eco-Friendly Cups', 'category' => 'Packaging Material', 'reorder_point' => 200],
        ];

        foreach ($products as $product) {
            if (DB::table('products')->where('id', $product['id'])->exists()) {
                DB::table('products')
                    ->where('id', $product['id'])
                    ->update(array_merge($product, ['updated_at' => $now]));
            } else {
                DB::table('products')->insert(array_merge($product, ['created_at' => $now, 'updated_at' => $now]));
            }
        }

        // 3. Seed Variants (Enforcing decimal 15,4 micro-pricing)
        $variants = [
            [
                'id' => 1,
                'product_id' => 1,
                'sku' => 'PROD-COF-001',
                'barcode' => '4800123456789',
                'name' => 'Premium Roasted Coffee Beans (Whole)',
                'attributes' => json_encode(['bean_type' => 'Arabica', 'roast' => 'Medium']),
                'base_unit_name' => 'gram',
                'cost_price' => 0.0150, // Fraction of a dollar/peso per gram
                'sale_price' => 0.0350,
            ],
            [
                'id' => 2,
                'product_id' => 2,
                'sku' => 'PROD-CUP-12OZ',
                'barcode' => '4800987654321',
                'name' => 'Takeout Paper Cups 12oz (Single)',
                'attributes' => json_encode(['size' => '12oz', 'material' => 'Recycled Paper']),
                'base_unit_name' => 'piece',
                'cost_price' => 0.1200, // 12 cents per unit
                'sale_price' => 0.2500,
            ],
        ];

        foreach ($variants as $variant) {
            if (DB::table('product_variants')->where('id', $variant['id'])->exists()) {
                DB::table('product_variants')
                    ->where('id', $variant['id'])
                    ->update(array_merge($variant, ['updated_at' => $now]));
            } else {
                DB::table('product_variants')->insert(array_merge($variant, ['created_at' => $now, 'updated_at' => $now]));
            }
        }

        // 4. Seed Unit Conversions
        $unitConversions = [
            // Coffee conversions
            ['variant_id' => 1, 'unit_name' => 'Bag', 'base_unit_ratio' => 1000, 'is_default_purchase' => true, 'is_default_transfer' => false],
            ['variant_id' => 1, 'unit_name' => 'Box', 'base_unit_ratio' => 10000, 'is_default_purchase' => false, 'is_default_transfer' => true],
            // Cup conversions
            ['variant_id' => 2, 'unit_name' => 'Sleeve', 'base_unit_ratio' => 50, 'is_default_purchase' => false, 'is_default_transfer' => true],
            ['variant_id' => 2, 'unit_name' => 'Carton', 'base_unit_ratio' => 500, 'is_default_purchase' => true, 'is_default_transfer' => false],
        ];

        foreach ($unitConversions as $uc) {
            $exists = DB::table('product_unit_conversions')
                ->where('variant_id', $uc['variant_id'])
                ->where('unit_name', $uc['unit_name'])
                ->exists();

            if ($exists) {
                DB::table('product_unit_conversions')
                    ->where('variant_id', $uc['variant_id'])
                    ->where('unit_name', $uc['unit_name'])
                    ->update(array_merge($uc, ['updated_at' => $now]));
            } else {
                DB::table('product_unit_conversions')->insert(array_merge($uc, ['created_at' => $now, 'updated_at' => $now]));
            }
        }

        // 5. Seed Initial stock levels (Warehouse Stock Cache)
        $warehouseStocks = [
            // Manila Branch (Well stocked)
            ['variant_id' => 1, 'warehouse_id' => 1, 'on_hand_quantity' => 100000, 'reserved_quantity' => 0], // 100kg
            ['variant_id' => 2, 'warehouse_id' => 1, 'on_hand_quantity' => 5000, 'reserved_quantity' => 0],   // 5,000 cups

            // Cebu Branch (Moderately stocked)
            ['variant_id' => 1, 'warehouse_id' => 2, 'on_hand_quantity' => 20000, 'reserved_quantity' => 0],  // 20kg
            ['variant_id' => 2, 'warehouse_id' => 2, 'on_hand_quantity' => 1000, 'reserved_quantity' => 0],   // 1,000 cups

            // Davao Branch (Empty / Low stock scenario)
            ['variant_id' => 1, 'warehouse_id' => 3, 'on_hand_quantity' => 0, 'reserved_quantity' => 0],
            ['variant_id' => 2, 'warehouse_id' => 3, 'on_hand_quantity' => 0, 'reserved_quantity' => 0],
        ];

        foreach ($warehouseStocks as $stock) {
            $exists = DB::table('warehouse_stock')
                ->where('variant_id', $stock['variant_id'])
                ->where('warehouse_id', $stock['warehouse_id'])
                ->exists();

            if ($exists) {
                DB::table('warehouse_stock')
                    ->where('variant_id', $stock['variant_id'])
                    ->where('warehouse_id', $stock['warehouse_id'])
                    ->update(array_merge($stock, ['updated_at' => $now]));
            } else {
                DB::table('warehouse_stock')->insert(array_merge($stock, ['created_at' => $now, 'updated_at' => $now]));
            }
        }
    }
}
