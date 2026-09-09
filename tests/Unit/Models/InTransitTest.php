<?php

declare(strict_types=1);

use App\Enums\InTransitStatus;
use App\Models\InTransit;
use App\Models\ProductVariant;
use App\Models\TransferRequisitionItem;
use Carbon\CarbonImmutable;

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
        expect($case->getLabel())->toBeString()->not->toBeEmpty();
    }
});

it('belongs to a transfer requisition item', function () {
    $item = TransferRequisitionItem::factory()->create();
    $inTransit = InTransit::factory()->create(['transfer_requisition_item_id' => $item->id]);

    expect($inTransit->item->is($item))->toBeTrue();
});

it('belongs to a product variant', function () {
    $variant = ProductVariant::factory()->create();
    $inTransit = InTransit::factory()->create(['product_variant_id' => $variant->id]);

    expect($inTransit->variant->is($variant))->toBeTrue();
});

it('casts dispatched_base_qty to integer', function () {
    $inTransit = InTransit::factory()->create(['dispatched_base_qty' => 150]);

    expect($inTransit->dispatched_base_qty)->toBeInt()
        ->and($inTransit->dispatched_base_qty)->toBe(150);
});

it('casts dispatched_at to datetime', function () {
    $inTransit = InTransit::factory()->create(['dispatched_at' => '2026-01-15 10:30:00']);

    expect($inTransit->dispatched_at)->toBeInstanceOf(CarbonImmutable::class);
});
