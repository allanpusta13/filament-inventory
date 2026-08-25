<?php

declare(strict_types=1);

use App\Enums\MovementType;
use App\Enums\UserRole;
use App\Filament\Resources\CurrentStock\Pages\ListCurrentStock;
use App\Filament\Widgets\LowStockWidget;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create(['role' => UserRole::Admin->value]);
    $this->product = Product::factory()->create(['reorder_point' => 10]);
    $this->warehouse = Warehouse::factory()->create(['is_active' => true]);
});

it('can render current stock page', function (): void {
    $this->actingAs($this->user);

    livewire(ListCurrentStock::class)
        ->assertOk();
});

it('shows aggregated stock quantities', function (): void {
    $this->actingAs($this->user);

    app(InventoryService::class)->recordMovement(
        productId: $this->product->id,
        warehouseId: $this->warehouse->id,
        type: MovementType::Receive,
        quantity: 50,
    );

    app(InventoryService::class)->recordMovement(
        productId: $this->product->id,
        warehouseId: $this->warehouse->id,
        type: MovementType::Ship,
        quantity: -20,
    );

    livewire(ListCurrentStock::class)
        ->loadTable()
        ->assertSee($this->product->name)
        ->assertSee($this->warehouse->name)
        ->assertSee('30');
});

it('does not show zero-quantity rows', function (): void {
    $this->actingAs($this->user);

    app(InventoryService::class)->recordMovement(
        productId: $this->product->id,
        warehouseId: $this->warehouse->id,
        type: MovementType::Receive,
        quantity: 50,
    );

    app(InventoryService::class)->recordMovement(
        productId: $this->product->id,
        warehouseId: $this->warehouse->id,
        type: MovementType::Ship,
        quantity: -50,
    );

    livewire(ListCurrentStock::class)
        ->loadTable()
        ->assertDontSee($this->product->name);
});

it('can render low stock widget', function (): void {
    $this->actingAs($this->user);

    Livewire\Livewire::test(LowStockWidget::class)
        ->assertOk();
});

it('shows products at or below reorder point in widget', function (): void {
    $this->actingAs($this->user);

    app(InventoryService::class)->recordMovement(
        productId: $this->product->id,
        warehouseId: $this->warehouse->id,
        type: MovementType::Receive,
        quantity: 5,
    );

    Livewire\Livewire::test(LowStockWidget::class)
        ->loadTable()
        ->assertSee($this->product->name);
});

it('does not show products above reorder point in widget', function (): void {
    $this->actingAs($this->user);

    app(InventoryService::class)->recordMovement(
        productId: $this->product->id,
        warehouseId: $this->warehouse->id,
        type: MovementType::Receive,
        quantity: 50,
    );

    Livewire\Livewire::test(LowStockWidget::class)
        ->loadTable()
        ->assertDontSee($this->product->name);
});

it('scopes current stock for non-admin users', function (): void {
    $warehouse1 = Warehouse::factory()->create(['is_active' => true]);
    $warehouse2 = Warehouse::factory()->create(['is_active' => true]);

    $staff = User::factory()->create(['role' => UserRole::WarehouseStaff->value]);
    $staff->warehouses()->attach($warehouse1->id);

    app(InventoryService::class)->recordMovement(
        productId: $this->product->id,
        warehouseId: $warehouse1->id,
        type: MovementType::Receive,
        quantity: 25,
    );

    app(InventoryService::class)->recordMovement(
        productId: $this->product->id,
        warehouseId: $warehouse2->id,
        type: MovementType::Receive,
        quantity: 75,
    );

    $this->actingAs($staff);

    livewire(ListCurrentStock::class)
        ->loadTable()
        ->assertSee($warehouse1->name)
        ->assertDontSee($warehouse2->name);
});
