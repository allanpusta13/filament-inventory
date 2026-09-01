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
            ['id' => 1, 'code' => 'WH-MNL', 'name' => 'Main Manila Central Hub', 'location' => 'Metropolitan Manila', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'code' => 'WH-CEB', 'name' => 'Cebu Regional Depot', 'location' => 'Mandaue City, Cebu', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'code' => 'WH-DVO', 'name' => 'Davao Branch Store', 'location' => 'Davao City, Mindanao', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ];
        DB::table('warehouses')->insert($warehouses);

        // 2. Seed Base Products
        DB::table('products')->insert([
            ['id' => 1, 'sku' => 'PROD-COF', 'name' => 'Premium Coffee Brand', 'category' => 'Beverage Ingredients', 'reorder_point' => 5000, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'sku' => 'PROD-CUP', 'name' => 'Eco-Friendly Cups', 'category' => 'Packaging Material', 'reorder_point' => 200, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 3. Seed Variants (Enforcing decimal 15,4 micro-pricing)
        DB::table('product_variants')->insert([
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
                'created_at' => $now,
                'updated_at' => $now,
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
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // 4. Seed Unit Conversions
        DB::table('product_unit_conversions')->insert([
            // Coffee conversions
            ['variant_id' => 1, 'unit_name' => 'Bag', 'base_unit_ratio' => 1000, 'is_default_purchase' => true, 'is_default_transfer' => false, 'created_at' => $now, 'updated_at' => $now],
            ['variant_id' => 1, 'unit_name' => 'Box', 'base_unit_ratio' => 10000, 'is_default_purchase' => false, 'is_default_transfer' => true, 'created_at' => $now, 'updated_at' => $now],
            // Cup conversions
            ['variant_id' => 2, 'unit_name' => 'Sleeve', 'base_unit_ratio' => 50, 'is_default_purchase' => false, 'is_default_transfer' => true, 'created_at' => $now, 'updated_at' => $now],
            ['variant_id' => 2, 'unit_name' => 'Carton', 'base_unit_ratio' => 500, 'is_default_purchase' => true, 'is_default_transfer' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 5. Seed Initial stock levels (Warehouse Stock Cache)
        DB::table('warehouse_stock')->insert([
            // Manila Branch (Well stocked)
            ['variant_id' => 1, 'warehouse_id' => 1, 'on_hand_quantity' => 100000, 'reserved_quantity' => 0, 'created_at' => $now, 'updated_at' => $now], // 100kg
            ['variant_id' => 2, 'warehouse_id' => 1, 'on_hand_quantity' => 5000, 'reserved_quantity' => 0, 'created_at' => $now, 'updated_at' => $now],   // 5,000 cups

            // Cebu Branch (Moderately stocked)
            ['variant_id' => 1, 'warehouse_id' => 2, 'on_hand_quantity' => 20000, 'reserved_quantity' => 0, 'created_at' => $now, 'updated_at' => $now],  // 20kg
            ['variant_id' => 2, 'warehouse_id' => 2, 'on_hand_quantity' => 1000, 'reserved_quantity' => 0, 'created_at' => $now, 'updated_at' => $now],   // 1,000 cups

            // Davao Branch (Empty / Low stock scenario)
            ['variant_id' => 1, 'warehouse_id' => 3, 'on_hand_quantity' => 0, 'reserved_quantity' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['variant_id' => 2, 'warehouse_id' => 3, 'on_hand_quantity' => 0, 'reserved_quantity' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
