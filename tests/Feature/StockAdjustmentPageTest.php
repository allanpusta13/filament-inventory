<?php

declare(strict_types=1);

use App\Enums\MovementType;
use App\Enums\UserRole;
use App\Filament\Pages\StockAdjustment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->wh = Warehouse::factory()->create();

    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->admin->warehouses()->attach($this->wh->id);

    $this->manager = User::factory()->create(['role' => UserRole::BranchManager]);
    $this->manager->warehouses()->attach($this->wh->id);

    $this->staff = User::factory()->create(['role' => UserRole::WarehouseStaff]);
    $this->staff->warehouses()->attach($this->wh->id);

    $this->auditor = User::factory()->create(['role' => UserRole::Auditor]);
    $this->auditor->warehouses()->attach($this->wh->id);

    $this->product = Product::factory()->create();
    $this->variant = ProductVariant::factory()->create(['product_id' => $this->product->id]);
    WarehouseStock::create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->wh->id,
        'on_hand_quantity' => 100,
        'reserved_quantity' => 0,
    ]);
});

it('admin can render the page', function (): void {
    $this->actingAs($this->admin);

    livewire(StockAdjustment::class)
        ->assertSuccessful();
});

it('branch manager can render the page', function (): void {
    $this->actingAs($this->manager);

    livewire(StockAdjustment::class)
        ->assertSuccessful();
});

it('warehouse staff cannot access the page', function (): void {
    $this->actingAs($this->staff);

    $this->assertFalse(StockAdjustment::canAccess());
});

it('auditor cannot access the page', function (): void {
    $this->actingAs($this->auditor);

    $this->assertFalse(StockAdjustment::canAccess());
});

it('form has expected fields', function (): void {
    $this->actingAs($this->admin);

    livewire(StockAdjustment::class)
        ->assertFormComponentExists('warehouse_id')
        ->assertFormComponentExists('variant_id')
        ->assertFormComponentExists('quantity')
        ->assertFormComponentExists('reason');
});

it('adjustment with positive quantity increases stock', function (): void {
    $service = app(InventoryService::class);
    $service->recordMovement(
        variantId: $this->variant->id,
        warehouseId: $this->wh->id,
        type: MovementType::Adjustment,
        baseQuantity: 25,
        referenceCode: 'ADJ-TEST-001',
    );

    $stock = WarehouseStock::where('variant_id', $this->variant->id)
        ->where('warehouse_id', $this->wh->id)
        ->first();
    expect($stock->on_hand_quantity)->toBe(125);

    $this->assertDatabaseHas('stock_movements', [
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->wh->id,
        'type' => MovementType::Adjustment->value,
        'quantity' => 25,
    ]);
});

it('adjustment with negative quantity reduces stock', function (): void {
    $service = app(InventoryService::class);
    $service->recordMovement(
        variantId: $this->variant->id,
        warehouseId: $this->wh->id,
        type: MovementType::Adjustment,
        baseQuantity: -30,
        referenceCode: 'ADJ-TEST-002',
    );

    $stock = WarehouseStock::where('variant_id', $this->variant->id)
        ->where('warehouse_id', $this->wh->id)
        ->first();
    expect($stock->on_hand_quantity)->toBe(70);
});

it('adjustment with negative quantity exceeding stock throws exception', function (): void {
    $service = app(InventoryService::class);
    $service->recordMovement(
        variantId: $this->variant->id,
        warehouseId: $this->wh->id,
        type: MovementType::Adjustment,
        baseQuantity: -200,
        referenceCode: 'ADJ-TEST-003',
    );
})->throws(App\Exceptions\InsufficientStockException::class);
