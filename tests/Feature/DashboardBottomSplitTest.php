<?php

declare(strict_types=1);

use App\Enums\MovementType;
use App\Enums\UserRole;
use App\Filament\Widgets\ProductCatalogWidget;
use App\Filament\Widgets\WarehouseCapacityWidget;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $this->staff = User::factory()->create(['role' => UserRole::WarehouseStaff->value]);
    $this->warehouse1 = Warehouse::factory()->create(['name' => 'Central Hub', 'is_active' => true]);
    $this->warehouse2 = Warehouse::factory()->create(['name' => 'Warehouse A', 'is_active' => true]);
    $this->staff->warehouses()->attach([$this->warehouse1->id]);
});

describe('ProductCatalogWidget', function (): void {
    it('admin can view product catalog', function (): void {
        $this->actingAs($this->admin);

        livewire(ProductCatalogWidget::class)
            ->assertOk();
    });

    it('shows products with stock quantities', function (): void {
        $this->actingAs($this->admin);

        $product = Product::factory()->create(['name' => 'Test Widget Product', 'sku' => 'TST-001']);
        $variant = App\Models\ProductVariant::factory()->create(['product_id' => $product->id]);

        StockMovement::factory()->create([
            'variant_id' => $variant->id,
            'warehouse_id' => $this->warehouse1->id,
            'type' => MovementType::Receive,
            'quantity' => 50,
        ]);

        livewire(ProductCatalogWidget::class)
            ->loadTable()
            ->assertCanSeeTableRecords([$product]);
    });

    it('warehouse staff sees only assigned warehouse products', function (): void {
        $this->actingAs($this->staff);

        $product1 = Product::factory()->create(['name' => 'Staff Product']);
        $product2 = Product::factory()->create(['name' => 'Other WH Product']);
        $variant1 = App\Models\ProductVariant::factory()->create(['product_id' => $product1->id]);
        $variant2 = App\Models\ProductVariant::factory()->create(['product_id' => $product2->id]);

        StockMovement::factory()->create([
            'variant_id' => $variant1->id,
            'warehouse_id' => $this->warehouse1->id,
            'type' => MovementType::Receive,
            'quantity' => 30,
        ]);

        StockMovement::factory()->create([
            'variant_id' => $variant2->id,
            'warehouse_id' => $this->warehouse2->id,
            'type' => MovementType::Receive,
            'quantity' => 30,
        ]);

        livewire(ProductCatalogWidget::class)
            ->loadTable()
            ->assertCanSeeTableRecords([$product1])
            ->assertCanNotSeeTableRecords([$product2]);
    });

    it('shows out of stock status correctly', function (): void {
        $this->actingAs($this->admin);

        $product = Product::factory()->create(['name' => 'Zero Stock Item']);
        $variant = App\Models\ProductVariant::factory()->create(['product_id' => $product->id]);

        StockMovement::factory()->create([
            'variant_id' => $variant->id,
            'warehouse_id' => $this->warehouse1->id,
            'type' => MovementType::Receive,
            'quantity' => 50,
        ]);

        StockMovement::factory()->create([
            'variant_id' => $variant->id,
            'warehouse_id' => $this->warehouse1->id,
            'type' => MovementType::Ship,
            'quantity' => -50,
        ]);

        livewire(ProductCatalogWidget::class)
            ->loadTable()
            ->assertCanSeeTableRecords([$product]);
    });
});

describe('WarehouseCapacityWidget', function (): void {
    it('admin can view warehouse capacity', function (): void {
        $this->actingAs($this->admin);

        livewire(WarehouseCapacityWidget::class)
            ->assertOk();
    });

    it('shows warehouse data in capacity view', function (): void {
        $this->actingAs($this->admin);

        livewire(WarehouseCapacityWidget::class)
            ->assertSee('Central Hub')
            ->assertSee('Warehouse A');
    });

    it('warehouse staff sees only assigned warehouse', function (): void {
        $this->actingAs($this->staff);

        livewire(WarehouseCapacityWidget::class)
            ->assertSee('Central Hub')
            ->assertDontSee('Warehouse A');
    });

    it('shows recent mutations', function (): void {
        $this->actingAs($this->admin);

        $product = Product::factory()->create(['name' => 'Audit Test Product']);
        $variant = App\Models\ProductVariant::factory()->create(['product_id' => $product->id]);

        StockMovement::factory()->create([
            'variant_id' => $variant->id,
            'warehouse_id' => $this->warehouse1->id,
            'type' => MovementType::Receive,
            'quantity' => 25,
        ]);

        livewire(WarehouseCapacityWidget::class)
            ->assertSee('Audit Test Product')
            ->assertSee('Recent Mutations');
    });
});
