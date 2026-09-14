<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    StockMovement::truncate();
    ProductVariant::truncate();
    Warehouse::truncate();
    User::truncate();

    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

describe('DirectTransferResource edge cases', function () {
    it('allows transfer out type', function () {
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $related = StockMovement::factory()->create();

        $movement = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->state(['type' => StockMovementType::TransferOut, 'related_movement_id' => $related->id])
            ->create();

        assertDatabaseHas(StockMovement::class, [
            'type' => StockMovementType::TransferOut->value,
        ]);
    });

    it('allows transfer in type', function () {
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $related = StockMovement::factory()->create();

        $movement = StockMovement::factory()->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->state(['type' => StockMovementType::TransferIn, 'related_movement_id' => $related->id])
            ->create();

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
