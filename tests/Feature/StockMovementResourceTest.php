<?php

declare(strict_types=1);

use App\Enums\MovementType;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->product = Product::factory()->create();
    $this->variant = App\Models\ProductVariant::factory()->create(['product_id' => $this->product->id]);
    $this->warehouse = Warehouse::factory()->create();
});

it('can render the index page', function (): void {
    $this->actingAs($this->user);

    livewire(ListStockMovements::class)
        ->assertOk();
});

it('has column', function (string $column): void {
    $this->actingAs($this->user);

    livewire(ListStockMovements::class)
        ->assertTableColumnExists($column);
})->with(['type', 'quantity', 'reference', 'created_at']);

it('can render column', function (string $column): void {
    $this->actingAs($this->user);

    livewire(ListStockMovements::class)
        ->assertCanRenderTableColumn($column);
})->with(['type', 'quantity', 'created_at']);

it('displays movement type as badge', function (): void {
    $this->actingAs($this->user);

    StockMovement::factory()->create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => MovementType::Receive,
        'quantity' => 100,
    ]);

    livewire(ListStockMovements::class)
        ->assertOk();
});

it('can filter by direction incoming', function (): void {
    $this->actingAs($this->user);

    $receive = StockMovement::factory()->create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => MovementType::Receive,
        'quantity' => 100,
    ]);

    $ship = StockMovement::factory()->create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => MovementType::Ship,
        'quantity' => -50,
    ]);

    livewire(ListStockMovements::class)
        ->loadTable()
        ->filterTable('direction', 'incoming')
        ->assertCanSeeTableRecords([$receive->fresh()])
        ->assertCanNotSeeTableRecords([$ship->fresh()]);
});

it('can filter by direction outgoing', function (): void {
    $this->actingAs($this->user);

    $receive = StockMovement::factory()->create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => MovementType::Receive,
        'quantity' => 100,
    ]);

    $ship = StockMovement::factory()->create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => MovementType::Ship,
        'quantity' => -50,
    ]);

    livewire(ListStockMovements::class)
        ->loadTable()
        ->filterTable('direction', 'outgoing')
        ->assertCanSeeTableRecords([$ship->fresh()])
        ->assertCanNotSeeTableRecords([$receive->fresh()]);
});

it('does not have create action', function (): void {
    $this->actingAs($this->user);

    livewire(ListStockMovements::class)
        ->assertOk()
        ->assertActionDoesNotExist('create');
});

it('can sort by type', function (): void {
    $this->actingAs($this->user);

    $records = collect([
        StockMovement::factory()->create([
            'variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => MovementType::Receive,
            'quantity' => 100,
        ]),
        StockMovement::factory()->create([
            'variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => MovementType::Ship,
            'quantity' => -50,
        ]),
    ]);

    livewire(ListStockMovements::class)
        ->loadTable()
        ->sortTable('type')
        ->assertCanSeeTableRecords($records);
});
