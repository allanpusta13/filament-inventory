<?php

use App\Models\LossLedger;
use App\Models\ProductVariantPrice;
use App\Models\ProductVariant;

it('casts financial columns to decimal strings', function () {
    $ledger = LossLedger::factory()->create([
        'unit_cost_price' => 12.5,
        'total_financial_loss' => 125,
    ]);

    expect($ledger->fresh()->unit_cost_price)->toBe('12.5000')
        ->and($ledger->fresh()->total_financial_loss)->toBe('125.0000');
});

it('snapshots unit cost from the variant current price', function () {
    $variant = ProductVariant::factory()->create();
    ProductVariantPrice::factory()->for($variant, 'variant')->create(['cost_price' => 45.5]);

    expect(LossLedger::snapshotUnitCostFrom($variant->fresh()))->toBe('45.5000');
});

it('returns null when snapshotting a variant with no current price', function () {
    $variant = ProductVariant::factory()->create();

    expect(LossLedger::snapshotUnitCostFrom($variant))->toBeNull();
});

it('belongs to a warehouse that bears the loss', function () {
    $ledger = LossLedger::factory()->create();

    expect($ledger->warehouse)->not->toBeNull();
});

it('allows a null transfer_requisition_item_id for non-item-specific losses', function () {
    $ledger = LossLedger::factory()->create(['transfer_requisition_item_id' => null]);

    expect($ledger->item)->toBeNull();
});
