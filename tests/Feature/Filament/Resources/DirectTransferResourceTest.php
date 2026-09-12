<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Filament\Resources\DirectTransfers\Pages\CreateDirectTransfer;
use App\Filament\Resources\DirectTransfers\Pages\ListDirectTransfers;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\Testing\TestAction;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

beforeEach(function () {
    StockMovement::truncate();
    ProductVariant::truncate();
    Warehouse::truncate();
    User::truncate();

    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('can render index page', function () {
    livewire(ListDirectTransfers::class)
        ->assertOk();
});

it('can render create page', function () {
    livewire(CreateDirectTransfer::class)
        ->assertOk();
});

it('has column', function (string $column) {
    livewire(ListDirectTransfers::class)
        ->assertTableColumnExists($column);
})->with(['reference_code', 'productVariant.sku', 'productVariant.name', 'warehouse.name', 'type', 'quantity', 'created_at']);

it('can sort column', function (string $column) {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $records = StockMovement::factory()->count(5)
        ->for($variant, 'productVariant')
        ->for($warehouse, 'warehouse')
        ->state(fn (array $attributes) => [
            'type' => fake()->randomElement([StockMovementType::TransferOut, StockMovementType::TransferIn]),
            'related_movement_id' => StockMovement::factory()->create()->id,
        ])
        ->create();

    livewire(ListDirectTransfers::class)
        ->loadTable()
        ->sortTable($column)
        ->assertCanSeeTableRecords($records->sortBy($column), inOrder: true)
        ->sortTable($column, 'desc')
        ->assertCanSeeTableRecords($records->sortByDesc($column), inOrder: true);
})->with(['reference_code', 'productVariant.sku', 'type', 'quantity', 'created_at']);

it('can search by reference_code', function () {
    StockMovement::truncate();
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $related = StockMovement::factory()->create();

    $movement1 = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
        ->state(['reference_code' => 'DT-SEARCH-001', 'type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id])
        ->create();
    StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
        ->state(['reference_code' => 'DT-SEARCH-002', 'type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id])
        ->create();

    livewire(ListDirectTransfers::class)
        ->searchTable('DT-SEARCH-001')
        ->assertCanSeeTableRecords($movement1)
        ->assertCanNotSeeTableRecords(StockMovement::where('reference_code', 'DT-SEARCH-002')->first());
});

it('can filter by type', function () {
    StockMovement::truncate();
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $related = StockMovement::factory()->create();

    $transferOut = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
        ->state(['type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id])
        ->create();
    StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
        ->state(['type' => StockMovementType::TransferIn, 'related_movement_id' => $related->id])
        ->create();

    livewire(ListDirectTransfers::class)
        ->filterTable('type', StockMovementType::TransferOut->value)
        ->assertCanSeeTableRecords($transferOut)
        ->assertCanNotSeeTableRecords(StockMovement::where('type', StockMovementType::TransferIn)->first());
});

it('can filter by warehouse', function () {
    StockMovement::truncate();
    $variant = ProductVariant::factory()->create();
    $warehouse1 = Warehouse::factory()->create();
    $warehouse2 = Warehouse::factory()->create();
    $related = StockMovement::factory()->create();

    $movement1 = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse1, 'warehouse')
        ->state(['type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id])
        ->create();
    StockMovement::factory()->for($variant, 'productVariant')->for($warehouse2, 'warehouse')
        ->state(['type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id])
        ->create();

    livewire(ListDirectTransfers::class)
        ->filterTable('warehouse_id', $warehouse1->id)
        ->assertCanSeeTableRecords($movement1)
        ->assertCanNotSeeTableRecords(StockMovement::where('warehouse_id', $warehouse2->id)->first());
});

it('can create direct transfer', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $related = StockMovement::factory()->create();

    livewire(CreateDirectTransfer::class)
        ->fillForm([
            'product_variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
            'type' => StockMovementType::TransferOut->value,
            'quantity' => 100,
            'related_movement_id' => $related->id,
            'reference_code' => 'DT-TEST-001',
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(StockMovement::class, [
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::TransferOut->value,
        'quantity' => 100,
        'reference_code' => 'DT-TEST-001',
    ]);
});

it('can delete direct transfer', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $related = StockMovement::factory()->create();

    $movement = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
        ->state(['type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id])
        ->create();

    livewire(ListDirectTransfers::class)
        ->loadTable()
        ->selectTableRecords($movement)
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
        ->assertNotified()
        ->assertCanNotSeeTableRecords($movement);

    assertDatabaseMissing($movement);
});

it('can bulk delete direct transfers', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $related = StockMovement::factory()->create();

    $movements = StockMovement::factory()->count(5)->for($variant, 'productVariant')->for($warehouse, 'warehouse')
        ->state(['type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id])
        ->create();

    livewire(ListDirectTransfers::class)
        ->loadTable()
        ->assertCanSeeTableRecords($movements)
        ->selectTableRecords($movements)
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
        ->assertNotified()
        ->assertCanNotSeeTableRecords($movements);

    $movements->each(fn (StockMovement $m) => assertDatabaseMissing($m));
});

it('can restore direct transfer', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $related = StockMovement::factory()->create();

    $movement = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
        ->state(['type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id])
        ->create();

    livewire(ListDirectTransfers::class)
        ->loadTable()
        ->selectTableRecords($movement)
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
        ->assertNotified();

    livewire(ListDirectTransfers::class)
        ->filterTable('trashed', 'trashed')
        ->selectTableRecords($movement)
        ->callAction(TestAction::make(RestoreBulkAction::class)->table()->bulk())
        ->assertNotified();

    $movement->refresh();
    expect($movement->deleted_at)->toBeNull();
    assertDatabaseHas(StockMovement::class, ['id' => $movement->id]);
});

it('shows transfer out as danger badge', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $related = StockMovement::factory()->create();

    $movement = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
        ->state(['type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id])
        ->create();

    livewire(ListDirectTransfers::class)
        ->loadTable()
        ->assertCanSeeTableRecords($movement);

    expect($movement->type)->toBe(StockMovementType::TransferOut);
});

it('shows transfer in as success badge', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $related = StockMovement::factory()->create();

    $movement = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
        ->state(['type' => StockMovementType::TransferIn, 'related_movement_id' => $related->id])
        ->create();

    expect($movement->type)->toBe(StockMovementType::TransferIn);
});
