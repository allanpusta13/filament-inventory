<?php

use App\Enums\InTransitStatus;
use App\Models\InTransit;

it('casts status to the InTransitStatus enum', function () {
    $inTransit = InTransit::factory()->create(['status' => InTransitStatus::PartiallyReceived]);

    expect($inTransit->fresh()->status)->toBe(InTransitStatus::PartiallyReceived);
});

it('defaults status to in_transit', function () {
    $inTransit = InTransit::factory()->create();

    expect($inTransit->status)->toBe(InTransitStatus::InTransit);
});

it('cascades deletion when the parent requisition is force-deleted', function () {
    $inTransit = InTransit::factory()->create();
    $requisition = $inTransit->transferRequisition;

    $requisition->forceDelete();

    expect(InTransit::find($inTransit->id))->toBeNull();
});

it('provides a human-readable label for every status', function () {
    foreach (InTransitStatus::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});
