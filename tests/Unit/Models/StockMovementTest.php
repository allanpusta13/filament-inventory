<?php

declare(strict_types=1);

// use App\Enums\StockMovementType;

use App\Enums\MovementType;
use App\Models\StockMovement;

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
