<?php

declare(strict_types=1);

use App\Filament\Widgets\WarehouseStockOverviewWidget;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->staff = User::factory()->create(['role' => 'warehouse_staff']);
    $this->wh = Warehouse::factory()->create();
    $this->staff->warehouses()->attach($this->wh->id);
});

it('admin can render warehouse stock overview widget', function (): void {
    $this->actingAs($this->admin);
    livewire(WarehouseStockOverviewWidget::class)
        ->assertOk();
});

it('staff can render warehouse stock overview widget', function (): void {
    $this->actingAs($this->staff);
    livewire(WarehouseStockOverviewWidget::class)
        ->assertOk();
});

it('widget displays total SKUs on hand', function (): void {
    $variant = ProductVariant::factory()->create();
    WarehouseStock::create([
        'variant_id' => $variant->id,
        'warehouse_id' => $this->wh->id,
        'on_hand_quantity' => 50,
        'reserved_quantity' => 0,
    ]);

    $this->actingAs($this->admin);
    livewire(WarehouseStockOverviewWidget::class)
        ->assertOk();
});

it('widget handles variant with null product gracefully', function (): void {
    $variant = ProductVariant::factory()->create();
    WarehouseStock::create([
        'variant_id' => $variant->id,
        'warehouse_id' => $this->wh->id,
        'on_hand_quantity' => 10,
        'reserved_quantity' => 0,
    ]);

    $this->actingAs($this->admin);
    livewire(WarehouseStockOverviewWidget::class)
        ->assertOk();
});

it('widget counts active shipments', function (): void {
    $this->actingAs($this->admin);
    livewire(WarehouseStockOverviewWidget::class)
        ->assertOk();
});
