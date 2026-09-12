<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Models\StockMovement;

it('casts type to the StockMovementType enum', function () {
    $movement = StockMovement::factory()->create(['type' => StockMovementType::TransferOut]);

    expect($movement->fresh()->type)->toBe(StockMovementType::TransferOut)
        ->and($movement->fresh()->type)->toBeInstanceOf(StockMovementType::class);
});

it('links a related movement for transfer pairs', function () {
    $out = StockMovement::factory()->create(['type' => StockMovementType::TransferOut, 'quantity' => -50]);
    $in = StockMovement::factory()->create([
        'type' => StockMovementType::TransferIn,
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

describe('StockMovementType enum', function () {
    it('classifies inbound types correctly', function () {
        expect(StockMovementType::Receive->isInbound())->toBeTrue()
            ->and(StockMovementType::TransferIn->isInbound())->toBeTrue()
            ->and(StockMovementType::Ship->isInbound())->toBeFalse();
    });

    it('classifies outbound types correctly', function () {
        expect(StockMovementType::Ship->isOutbound())->toBeTrue()
            ->and(StockMovementType::Loss->isOutbound())->toBeTrue()
            ->and(StockMovementType::Receive->isOutbound())->toBeFalse();
    });

    it('treats adjustment as neither strictly inbound nor outbound', function () {
        expect(StockMovementType::Adjustment->isInbound())->toBeFalse()
            ->and(StockMovementType::Adjustment->isOutbound())->toBeFalse();
    });
});
