<?php

declare(strict_types=1);

use App\Filament\Resources\DirectTransfers\Pages\ListDirectTransfers;
use App\Filament\Resources\DirectTransfers\Pages\CreateDirectTransfer;
use App\Models\StockMovement;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;
use App\Enums\StockMovementType;

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

it('can delete direct transfer', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $related = StockMovement::factory()->create();

    $movement = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
        ->state(['type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id])
        ->create();

    livewire(ListDirectTransfers::class)
        ->loadTable()
        ->selectTableRecords([$movement])
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
        ->assertNotified()
        ->assertCanNotSeeTableRecords([$movement]);

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

it('shows transfer out as danger badge', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $related = StockMovement::factory()->create();

    $movement = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
        ->state(['type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id])
        ->create();

    livewire(ListDirectTransfers::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$movement]);

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
