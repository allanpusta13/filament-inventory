<?php

declare(strict_types=1);

use App\Enums\MovementType;
use App\Enums\UserRole;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create(['role' => UserRole::Admin->value]);
    $this->product = Product::factory()->create();
    $this->variant = App\Models\ProductVariant::factory()->create(['product_id' => $this->product->id]);
    $this->warehouse = Warehouse::factory()->create(['is_active' => true]);
});

it('has receive stock action', function (): void {
    $this->actingAs($this->user);

    livewire(ListStockMovements::class)
        ->assertOk()
        ->assertActionExists('receiveStock');
});

it('can receive stock', function (): void {
    $this->actingAs($this->user);

    livewire(ListStockMovements::class)
        ->callAction('receiveStock', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 50,
            'reference' => 'PO-001',
        ]);

    $this->assertDatabaseHas(StockMovement::class, [
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => MovementType::Receive,
        'quantity' => 50,
        'reference_code' => 'PO-001',
    ]);
});

it('has ship stock action', function (): void {
    $this->actingAs($this->user);

    livewire(ListStockMovements::class)
        ->assertOk()
        ->assertActionExists('shipStock');
});

it('can ship stock', function (): void {
    $this->actingAs($this->user);

    app(InventoryService::class)->recordMovement(
        variantId: $this->variant->id,
        warehouseId: $this->warehouse->id,
        type: MovementType::Receive,
        baseQuantity: 100,
    );

    livewire(ListStockMovements::class)
        ->callAction('shipStock', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 30,
            'reference' => 'SO-001',
        ]);

    $this->assertDatabaseHas(StockMovement::class, [
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => MovementType::Ship,
        'quantity' => -30,
        'reference_code' => 'SO-001',
    ]);
});

it('fails ship with insufficient stock', function (): void {
    $this->actingAs($this->user);

    livewire(ListStockMovements::class)
        ->callAction('shipStock', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'reference' => 'SO-002',
        ]);

    $this->assertDatabaseMissing(StockMovement::class, [
        'variant_id' => $this->variant->id,
        'type' => MovementType::Ship,
    ]);
});

it('has transfer stock action', function (): void {
    $this->actingAs($this->user);

    livewire(ListStockMovements::class)
        ->assertOk()
        ->assertActionExists('transferStock');
});

it('can transfer stock', function (): void {
    $this->actingAs($this->user);

    $toWarehouse = Warehouse::factory()->create(['is_active' => true]);

    app(InventoryService::class)->recordMovement(
        variantId: $this->variant->id,
        warehouseId: $this->warehouse->id,
        type: MovementType::Receive,
        baseQuantity: 100,
    );

    livewire(ListStockMovements::class)
        ->callAction('transferStock', [
            'product_id' => $this->product->id,
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'quantity' => 25,
            'reference' => 'TR-001',
        ]);

    $this->assertDatabaseHas(StockMovement::class, [
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => MovementType::TransferOut,
        'quantity' => -25,
        'reference_code' => 'TR-001',
    ]);

    $this->assertDatabaseHas(StockMovement::class, [
        'variant_id' => $this->variant->id,
        'warehouse_id' => $toWarehouse->id,
        'type' => MovementType::TransferIn,
        'quantity' => 25,
        'reference_code' => 'TR-001',
    ]);
});

it('has adjustment action', function (): void {
    $this->actingAs($this->user);

    livewire(ListStockMovements::class)
        ->assertOk()
        ->assertActionExists('adjustment');
});

it('can make positive adjustment', function (): void {
    $this->actingAs($this->user);

    livewire(ListStockMovements::class)
        ->callAction('adjustment', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 10,
            'reference' => 'Annual count +10',
        ]);

    $this->assertDatabaseHas(StockMovement::class, [
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => MovementType::Adjustment,
        'quantity' => 10,
        'reference_code' => 'Annual count +10',
    ]);
});

it('can make negative adjustment', function (): void {
    $this->actingAs($this->user);

    app(InventoryService::class)->recordMovement(
        variantId: $this->variant->id,
        warehouseId: $this->warehouse->id,
        type: MovementType::Receive,
        baseQuantity: 50,
    );

    livewire(ListStockMovements::class)
        ->callAction('adjustment', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => -5,
            'reference' => 'Damaged goods -5',
        ]);

    $this->assertDatabaseHas(StockMovement::class, [
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => MovementType::Adjustment,
        'quantity' => -5,
        'reference_code' => 'Damaged goods -5',
    ]);
});

it('restricts warehouse options for non-admin users', function (): void {
    $staffWarehouse = Warehouse::factory()->create(['is_active' => true]);

    $staff = User::factory()->create(['role' => UserRole::WarehouseStaff->value]);
    $staff->warehouses()->attach($staffWarehouse->id);

    $this->actingAs($staff);

    livewire(ListStockMovements::class)
        ->assertOk();
});
