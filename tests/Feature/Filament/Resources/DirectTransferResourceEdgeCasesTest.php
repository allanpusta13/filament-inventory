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
use function Pest\Livewire\livewire;

beforeEach(function () {
    StockMovement::truncate();
    ProductVariant::truncate();
    Warehouse::truncate();
    User::truncate();

    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

describe('DirectTransferResource edge cases', function () {
    it('validates required fields on create', function (array $data, array $errors) {
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
                ...$data,
            ])
            ->call('create')
            ->assertHasFormErrors($errors)
            ->assertNotNotified();
    })->with([
        '`product_variant_id` required' => [['product_variant_id' => null], ['product_variant_id' => 'required']],
        '`warehouse_id` required' => [['warehouse_id' => null], ['warehouse_id' => 'required']],
        '`type` required' => [['type' => null], ['type' => 'required']],
        '`quantity` required' => [['quantity' => null], ['quantity' => 'required']],
        '`related_movement_id` required' => [['related_movement_id' => null], ['related_movement_id' => 'required']],
    ]);

    it('validates quantity is integer', function () {
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $related = StockMovement::factory()->create();

        livewire(CreateDirectTransfer::class)
            ->fillForm([
                'product_variant_id' => $variant->id,
                'warehouse_id' => $warehouse->id,
                'type' => StockMovementType::TransferOut->value,
                'quantity' => 'not-integer',
                'related_movement_id' => $related->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['quantity' => 'integer'])
            ->assertNotNotified();
    });

    it('validates type enum', function () {
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $related = StockMovement::factory()->create();

        livewire(CreateDirectTransfer::class)
            ->fillForm([
                'product_variant_id' => $variant->id,
                'warehouse_id' => $warehouse->id,
                'type' => 'invalid-type',
                'quantity' => 100,
                'related_movement_id' => $related->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['type' => 'in'])
            ->assertNotNotified();
    });

    it('allows transfer out type', function () {
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
            ])
            ->call('create')
            ->assertNotified()
            ->assertHasNoFormErrors();

        assertDatabaseHas(StockMovement::class, [
            'type' => StockMovementType::TransferOut->value,
        ]);
    });

    it('allows transfer in type', function () {
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $related = StockMovement::factory()->create();

        livewire(CreateDirectTransfer::class)
            ->fillForm([
                'product_variant_id' => $variant->id,
                'warehouse_id' => $warehouse->id,
                'type' => StockMovementType::TransferIn->value,
                'quantity' => 100,
                'related_movement_id' => $related->id,
            ])
            ->call('create')
            ->assertNotified()
            ->assertHasNoFormErrors();

        assertDatabaseHas(StockMovement::class, [
            'type' => StockMovementType::TransferIn->value,
        ]);
    });

    it('can search by reference_code', function () {
        StockMovement::truncate();
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $related = StockMovement::factory()->create();

        $movement1 = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->state(['reference_code' => 'DT-001', 'type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id])
            ->create();
        StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->state(['reference_code' => 'DT-002', 'type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id])
            ->create();

        $results = StockMovement::where('reference_code', 'like', '%DT-001%')->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($movement1->id);
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

        $results = StockMovement::where('type', StockMovementType::TransferOut->value)->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($transferOut->id);
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

        $results = StockMovement::where('warehouse_id', $warehouse1->id)->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($movement1->id);
    });

    it('can sort by quantity', function () {
        StockMovement::truncate();
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $related = StockMovement::factory()->create();

        $m1 = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->state(['type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id, 'quantity' => 300])
            ->create();
        $m2 = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->state(['type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id, 'quantity' => 100])
            ->create();
        $m3 = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->state(['type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id, 'quantity' => 200])
            ->create();

        $results = StockMovement::orderBy('quantity', 'asc')->get();
        expect($results->pluck('id')->toArray())->toBe([$m2->id, $m3->id, $m1->id]);
    });

    it('can sort by created_at', function () {
        StockMovement::truncate();
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $related = StockMovement::factory()->create();

        $m1 = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->state(['type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id, 'created_at' => now()->subDays(2)])
            ->create();
        $m2 = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->state(['type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id, 'created_at' => now()->subDay()])
            ->create();
        $m3 = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->state(['type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id, 'created_at' => now()])
            ->create();

        $results = StockMovement::orderBy('created_at', 'asc')->get();
        expect($results->pluck('id')->toArray())->toBe([$m1->id, $m2->id, $m3->id]);
    });

    it('handles soft delete gracefully', function () {
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

        $movement->refresh();
        expect($movement->deleted_at)->not->toBeNull();
    });

    it('handles restore gracefully', function () {
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

    it('shows negative quantity as danger color', function () {
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $related = StockMovement::factory()->create();

        $movement = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->state(['type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id, 'quantity' => -50])
            ->create();

        expect($movement->quantity)->toBeLessThan(0);
    });

    it('shows positive quantity as success color', function () {
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $related = StockMovement::factory()->create();

        $movement = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->state(['type' => StockMovementType::TransferIn, 'related_movement_id' => $related->id, 'quantity' => 50])
            ->create();

        expect($movement->quantity)->toBeGreaterThan(0);
    });

    it('handles related movement relationship', function () {
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $related = StockMovement::factory()->create();

        $movement = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->state(['type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id])
            ->create();

        expect($movement->relatedMovement->id)->toBe($related->id);
    });

    it('handles product variant relationship', function () {
        $variant = ProductVariant::factory()->create(['sku' => 'SKU-DT']);
        $warehouse = Warehouse::factory()->create();
        $related = StockMovement::factory()->create();

        $movement = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->state(['type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id])
            ->create();

        expect($movement->productVariant->sku)->toBe('SKU-DT');
    });

    it('handles warehouse relationship', function () {
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Test Warehouse']);
        $related = StockMovement::factory()->create();

        $movement = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->state(['type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id])
            ->create();

        expect($movement->warehouse->name)->toBe('Test Warehouse');
    });
});
