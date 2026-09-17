<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductVariantUnitConversion;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Actions\Testing\TestAction;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    ProductVariant::truncate();
    Product::truncate();
    ProductVariantPrice::truncate();
    ProductVariantUnitConversion::truncate();
    StockMovement::truncate();
    Warehouse::truncate();
    User::truncate();

    $this->admin = User::factory()->admin()->create();
    $this->warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse', 'code' => 'MAIN']);
    $this->product = Product::factory()->create(['name' => 'Test Product']);
    $this->variant = ProductVariant::factory()->for($this->product)->create([
        'sku' => 'SKU-001',
        'name' => 'Test Variant',
        'base_unit_name' => 'piece',
        'reorder_point' => 10,
    ]);

    $this->actingAs($this->admin);
});

describe('setCurrentPrice action', function () {
    it('can set sale price for variant', function () {
        livewire(ListProducts::class)
            ->loadTable()
            ->callAction(TestAction::make('setCurrentPrice')->table($this->variant), [
                'price_type' => 'sale',
                'price' => 29.99,
                'effective_from' => now()->format('Y-m-d'),
                'notes' => 'Promotional price',
            ])
            ->assertNotified();

        assertDatabaseHas(ProductVariantPrice::class, [
            'product_variant_id' => $this->variant->id,
            'sale_price' => 29.99,
            'is_current' => true,
            'notes' => 'Promotional price',
        ]);
    });

    it('can set cost price for variant', function () {
        livewire(ListProducts::class)
            ->loadTable()
            ->callAction(TestAction::make('setCurrentPrice')->table($this->variant), [
                'price_type' => 'cost',
                'price' => 15.50,
                'effective_from' => now()->format('Y-m-d'),
                'notes' => 'Updated cost',
            ])
            ->assertNotified();

        assertDatabaseHas(ProductVariantPrice::class, [
            'product_variant_id' => $this->variant->id,
            'cost_price' => 15.50,
            'is_current' => true,
        ]);
    });

    it('deactivates previous current price of same type', function () {
        ProductVariantPrice::factory()->create([
            'product_variant_id' => $this->variant->id,
            'sale_price' => 20.00,
            'cost_price' => 10.00,
            'effective_from' => now()->subDays(10),
            'is_current' => true,
        ]);

        livewire(ListProducts::class)
            ->loadTable()
            ->callAction(TestAction::make('setCurrentPrice')->table($this->variant), [
                'price_type' => 'sale',
                'price' => 25.00,
                'effective_from' => now()->format('Y-m-d'),
            ])
            ->assertNotified();

        $oldPrice = ProductVariantPrice::where('product_variant_id', $this->variant->id)
            ->where('sale_price', 20.00)
            ->first();
        $newPrice = ProductVariantPrice::where('product_variant_id', $this->variant->id)
            ->where('sale_price', 25.00)
            ->first();

        expect($oldPrice->is_current)->toBeFalse();
        expect($newPrice->is_current)->toBeTrue();
    });

    it('validates required fields', function () {
        livewire(ListProducts::class)
            ->loadTable()
            ->callAction(TestAction::make('setCurrentPrice')->table($this->variant), [
                'price_type' => 'sale',
                'price' => '',
                'effective_from' => '',
            ])
            ->assertHasFormErrors([
                'price' => 'required',
                'effective_from' => 'required',
            ]);
    });

    it('validates price is numeric', function () {
        livewire(ListProducts::class)
            ->loadTable()
            ->callAction(TestAction::make('setCurrentPrice')->table($this->variant), [
                'price_type' => 'sale',
                'price' => 'not-a-number',
                'effective_from' => now()->format('Y-m-d'),
            ])
            ->assertHasFormErrors(['price' => 'numeric']);
    });
});

describe('editProductFamily action', function () {
    it('can change variant product family', function () {
        $newProduct = Product::factory()->create(['name' => 'New Product Family']);

        livewire(ListProducts::class)
            ->loadTable()
            ->callAction(TestAction::make('editProductFamily')->table($this->variant), [
                'product_id' => $newProduct->id,
                'name' => 'Updated Variant Name',
                'base_unit_name' => 'unit',
            ])
            ->assertNotified();

        $this->variant->refresh();
        expect($this->variant->product_id)->toBe($newProduct->id);
        expect($this->variant->name)->toBe('Updated Variant Name');
        expect($this->variant->base_unit_name)->toBe('unit');
    });

    it('validates required fields', function () {
        livewire(ListProducts::class)
            ->loadTable()
            ->callAction(TestAction::make('editProductFamily')->table($this->variant), [
                'product_id' => '',
                'name' => '',
                'base_unit_name' => '',
            ])
            ->assertHasFormErrors([
                'product_id' => 'required',
                'name' => 'required',
                'base_unit_name' => 'required',
            ]);
    });
});

describe('manageUnitConversions action', function () {
    it('can add unit conversions', function () {
        livewire(ListProducts::class)
            ->loadTable()
            ->callAction(TestAction::make('manageUnitConversions')->table($this->variant), [
                'unitConversions' => [
                    [
                        'unit_name' => 'dozen',
                        'ratio' => 12,
                        'is_default' => false,
                    ],
                    [
                        'unit_name' => 'case',
                        'ratio' => 24,
                        'is_default' => true,
                    ],
                ],
            ])
            ->assertNotified();

        $conversions = ProductVariantUnitConversion::where('product_variant_id', $this->variant->id)->get();
        expect($conversions)->toHaveCount(2);

        $dozen = $conversions->where('unit_name', 'dozen')->first();
        $case = $conversions->where('unit_name', 'case')->first();

        expect($dozen->base_unit_ratio)->toBe(12);
        expect($dozen->is_default_purchase)->toBeFalse();

        expect($case->base_unit_ratio)->toBe(24);
        expect($case->is_default_purchase)->toBeTrue();
    });

    it('replaces existing conversions', function () {
        ProductVariantUnitConversion::factory()->create([
            'product_variant_id' => $this->variant->id,
            'unit_name' => 'old_unit',
            'base_unit_ratio' => 10,
        ]);

        livewire(ListProducts::class)
            ->loadTable()
            ->callAction(TestAction::make('manageUnitConversions')->table($this->variant), [
                'unitConversions' => [
                    [
                        'unit_name' => 'new_unit',
                        'ratio' => 5,
                        'is_default' => false,
                    ],
                ],
            ])
            ->assertNotified();

        $conversions = ProductVariantUnitConversion::where('product_variant_id', $this->variant->id)->get();
        expect($conversions)->toHaveCount(1);
        expect($conversions->first()->unit_name)->toBe('new_unit');
        expect($conversions->first()->base_unit_ratio)->toBe(5);
    });

    it('validates ratio is minimum 1', function () {
        livewire(ListProducts::class)
            ->loadTable()
            ->callAction(TestAction::make('manageUnitConversions')->table($this->variant), [
                'unitConversions' => [
                    [
                        'unit_name' => 'invalid',
                        'ratio' => 0,
                    ],
                ],
            ])
            ->assertHasFormErrors(['unitConversions.0.ratio' => 'min']);
    });

    it('validates unit_name is required', function () {
        livewire(ListProducts::class)
            ->loadTable()
            ->callAction(TestAction::make('manageUnitConversions')->table($this->variant), [
                'unitConversions' => [
                    [
                        'unit_name' => '',
                        'ratio' => 10,
                    ],
                ],
            ])
            ->assertHasFormErrors(['unitConversions.0.unit_name' => 'required']);
    });
});

describe('quickStockAdjustment action', function () {
    it('can perform general adjustment', function () {
        livewire(ListProducts::class)
            ->loadTable()
            ->callAction(TestAction::make('quickStockAdjustment')->table($this->variant), [
                'warehouse_id' => $this->warehouse->id,
                'adjustment_qty' => 50,
                'adjustment_type' => 'adjustment',
                'notes' => 'Cycle count adjustment',
            ])
            ->assertNotified();

        assertDatabaseHas(StockMovement::class, [
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 50,
            'type' => StockMovementType::Adjustment,
            'notes' => 'Cycle count adjustment',
        ]);
    });

    it('can perform receive adjustment', function () {
        livewire(ListProducts::class)
            ->loadTable()
            ->callAction(TestAction::make('quickStockAdjustment')->table($this->variant), [
                'warehouse_id' => $this->warehouse->id,
                'adjustment_qty' => 100,
                'adjustment_type' => 'receive',
                'notes' => 'Received from supplier',
            ])
            ->assertNotified();

        assertDatabaseHas(StockMovement::class, [
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'type' => StockMovementType::Receive,
        ]);
    });

    it('can perform ship adjustment', function () {
        livewire(ListProducts::class)
            ->loadTable()
            ->callAction(TestAction::make('quickStockAdjustment')->table($this->variant), [
                'warehouse_id' => $this->warehouse->id,
                'adjustment_qty' => 25,
                'adjustment_type' => 'ship',
                'notes' => 'Shipped to customer',
            ])
            ->assertNotified();

        assertDatabaseHas(StockMovement::class, [
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 25,
            'type' => StockMovementType::Ship,
        ]);
    });

    it('validates required fields', function () {
        livewire(ListProducts::class)
            ->loadTable()
            ->callAction(TestAction::make('quickStockAdjustment')->table($this->variant), [
                'warehouse_id' => '',
                'adjustment_qty' => '',
                'adjustment_type' => '',
            ])
            ->assertHasFormErrors([
                'warehouse_id' => 'required',
                'adjustment_qty' => 'required',
                'adjustment_type' => 'required',
            ]);
    });

    it('validates adjustment_qty is numeric', function () {
        livewire(ListProducts::class)
            ->loadTable()
            ->callAction(TestAction::make('quickStockAdjustment')->table($this->variant), [
                'warehouse_id' => $this->warehouse->id,
                'adjustment_qty' => 'not-a-number',
                'adjustment_type' => 'adjustment',
            ])
            ->assertHasFormErrors(['adjustment_qty' => 'numeric']);
    });
});

describe('authorization for custom actions', function () {
    it('non-admin with update permission can access setCurrentPrice', function () {
        $staff = User::factory()->warehouseStaff()->create();
        $staff->warehouses()->attach($this->warehouse->id);
        $this->actingAs($staff);

        livewire(ListProducts::class)
            ->loadTable()
            ->callAction(TestAction::make('setCurrentPrice')->table($this->variant), [
                'price_type' => 'sale',
                'price' => 10.00,
                'effective_from' => now()->format('Y-m-d'),
            ])
            ->assertNotified();
    });

    it('non-admin with update permission can access editProductFamily', function () {
        $staff = User::factory()->warehouseStaff()->create();
        $staff->warehouses()->attach($this->warehouse->id);
        $this->actingAs($staff);

        $newProduct = Product::factory()->create(['name' => 'New Family']);

        livewire(ListProducts::class)
            ->loadTable()
            ->callAction(TestAction::make('editProductFamily')->table($this->variant), [
                'product_id' => $newProduct->id,
                'name' => 'Updated',
                'base_unit_name' => 'piece',
            ])
            ->assertNotified();
    });

    it('non-admin with update permission can access manageUnitConversions', function () {
        $staff = User::factory()->warehouseStaff()->create();
        $staff->warehouses()->attach($this->warehouse->id);
        $this->actingAs($staff);

        livewire(ListProducts::class)
            ->loadTable()
            ->callAction(TestAction::make('manageUnitConversions')->table($this->variant), [
                'unitConversions' => [
                    ['unit_name' => 'pack', 'ratio' => 6, 'barcode' => null, 'is_default' => false],
                ],
            ])
            ->assertNotified();
    });

    it('non-admin without adjustStock permission cannot access quickStockAdjustment', function () {
        $staff = User::factory()->warehouseStaff()->create();
        $staff->warehouses()->attach($this->warehouse->id);
        $this->actingAs($staff);

        livewire(ListProducts::class)
            ->loadTable()
            ->assertTableActionHidden('quickStockAdjustment', $this->variant);
    });
});
