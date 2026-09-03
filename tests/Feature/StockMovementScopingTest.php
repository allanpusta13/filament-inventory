<?php

declare(strict_types=1);

use App\Enums\MovementType;
use App\Enums\UserRole;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create(['role' => UserRole::Admin->value]);
    $this->product = Product::factory()->create();
    $this->variant = App\Models\ProductVariant::factory()->create(['product_id' => $this->product->id]);
    $this->warehouse = Warehouse::factory()->create(['is_active' => true]);
});

it('has counterpart_warehouse column', function (): void {
    $this->actingAs($this->user);

    livewire(ListStockMovements::class)
        ->assertTableColumnExists('counterpart_warehouse');
});

it('admin sees all stock movements', function (): void {
    $this->actingAs($this->user);

    $warehouse1 = Warehouse::factory()->create(['is_active' => true]);
    $warehouse2 = Warehouse::factory()->create(['is_active' => true]);

    StockMovement::factory()->create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $warehouse1->id,
        'type' => MovementType::Receive,
        'quantity' => 100,
    ]);

    StockMovement::factory()->create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $warehouse2->id,
        'type' => MovementType::Receive,
        'quantity' => 50,
    ]);

    $movements = StockMovement::all();

    livewire(ListStockMovements::class)
        ->loadTable()
        ->assertCanSeeTableRecords($movements);
});

it('non-admin sees only assigned warehouse movements', function (): void {
    $warehouse1 = Warehouse::factory()->create(['is_active' => true]);
    $warehouse2 = Warehouse::factory()->create(['is_active' => true]);

    $staff = User::factory()->create(['role' => UserRole::WarehouseStaff->value]);
    $staff->warehouses()->attach($warehouse1->id);

    StockMovement::factory()->create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $warehouse1->id,
        'type' => MovementType::Receive,
        'quantity' => 100,
    ]);

    $unseen = StockMovement::factory()->create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $warehouse2->id,
        'type' => MovementType::Receive,
        'quantity' => 50,
    ]);

    $this->actingAs($staff);

    livewire(ListStockMovements::class)
        ->loadTable()
        ->assertCanNotSeeTableRecords([$unseen]);
});

it('returns counterpart warehouse for transfer_in', function (): void {
    $this->actingAs($this->user);

    $warehouse1 = Warehouse::factory()->create(['is_active' => true]);
    $warehouse2 = Warehouse::factory()->create(['is_active' => true]);

    // Create a transfer out movement from warehouse1 to warehouse2
    $outMovement = StockMovement::factory()->create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $warehouse1->id,
        'type' => MovementType::TransferOut,
        'quantity' => -50,
    ]);

    // Create the corresponding transfer in movement (linked by related_movement_id)
    $inMovement = StockMovement::factory()->create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $warehouse2->id,
        'type' => MovementType::TransferIn,
        'quantity' => 50,
        'related_movement_id' => $outMovement->id,
    ]);

    $this->assertEquals($warehouse1->id, $inMovement->counterpart_warehouse->id);
});

it('returns counterpart warehouse for transfer_out', function (): void {
    $this->actingAs($this->user);

    $warehouse1 = Warehouse::factory()->create(['is_active' => true]);
    $warehouse2 = Warehouse::factory()->create(['is_active' => true]);

    // Create the transfer out movement
    $outMovement = StockMovement::factory()->create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $warehouse1->id,
        'type' => MovementType::TransferOut,
        'quantity' => -50,
    ]);

    // Create the corresponding transfer in movement (linked by related_movement_id)
    $inMovement = StockMovement::factory()->create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $warehouse2->id,
        'type' => MovementType::TransferIn,
        'quantity' => 50,
        'related_movement_id' => $outMovement->id,
    ]);

    $this->assertEquals($warehouse2->id, $outMovement->counterpart_warehouse->id);
});

it('returns null counterpart for non-transfer movements', function (): void {
    $this->actingAs($this->user);

    $variant = App\Models\ProductVariant::factory()->create(['product_id' => $this->product->id]);

    $movement = StockMovement::factory()->create([
        'variant_id' => $variant->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => MovementType::Receive,
        'quantity' => 100,
    ]);

    $this->assertNull($movement->counterpart_warehouse);
});
