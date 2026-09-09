<?php

declare(strict_types=1);

// use App\Enums\StockMovementType;

use App\Enums\MovementType;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;

it('casts type to the StockMovementType enum', function () {
    $movement = StockMovement::factory()->create(['type' => MovementType::TransferOut]);

    expect($movement->fresh()->type)->toBe(MovementType::TransferOut)
        ->and($movement->fresh()->type)->toBeInstanceOf(MovementType::class);
});

it('links a related movement for transfer pairs', function () {
    $out = StockMovement::factory()->create(['type' => MovementType::TransferOut, 'quantity' => -50]);
    $in = StockMovement::factory()->create([
        'type' => MovementType::TransferIn,
        'quantity' => 50,
        'related_movement_id' => $out->id,
    ]);

    expect($in->relatedMovement->is($out))->toBeTrue();
});

it('nulls related_movement_id when the linked movement is deleted', function () {
    $out = StockMovement::factory()->create();
    $in = StockMovement::factory()->create(['related_movement_id' => $out->id]);

    $out->delete();

    expect($in->fresh()->related_movement_id)->toBeNull();
});

describe('MovementType enum', function () {
    it('classifies inbound types correctly', function () {
        expect(MovementType::Receive->isInbound())->toBeTrue()
            ->and(MovementType::TransferIn->isInbound())->toBeTrue()
            ->and(MovementType::Ship->isInbound())->toBeFalse();
    });

    it('classifies outbound types correctly', function () {
        expect(MovementType::Ship->isOutbound())->toBeTrue()
            ->and(MovementType::Loss->isOutbound())->toBeTrue()
            ->and(MovementType::Receive->isOutbound())->toBeFalse();
    });

    it('treats adjustment as neither strictly inbound nor outbound', function () {
        expect(MovementType::Adjustment->isInbound())->toBeFalse()
            ->and(MovementType::Adjustment->isOutbound())->toBeFalse();
    });
});

it('belongs to a warehouse', function () {
    $warehouse = Warehouse::factory()->create();
    $movement = StockMovement::factory()->create(['warehouse_id' => $warehouse->id]);

    expect($movement->warehouse->is($warehouse))->toBeTrue();
});

it('belongs to a variant', function () {
    $variant = ProductVariant::factory()->create();
    $movement = StockMovement::factory()->create(['product_variant_id' => $variant->id]);

    expect($movement->variant->is($variant))->toBeTrue();
});

it('belongs to a created by user', function () {
    $user = User::factory()->create();
    $movement = StockMovement::factory()->create(['created_by' => $user->id]);

    expect($movement->createdBy->is($user))->toBeTrue();
});

it('has a polymorphic reference', function () {
    $variant = ProductVariant::factory()->create();
    $movement = StockMovement::factory()->create([
        'reference_type' => ProductVariant::class,
        'reference_id' => $variant->id,
    ]);

    expect($movement->reference)->toBeInstanceOf(ProductVariant::class)
        ->and($movement->reference->is($variant))->toBeTrue();
});
