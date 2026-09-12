<?php

declare(strict_types=1);

use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;

it('enforces unique warehouse code', function () {
    Warehouse::factory()->create(['code' => 'WH-MNL']);

    expect(fn () => Warehouse::factory()->create(['code' => 'WH-MNL']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('separates outgoing and incoming transfers', function () {
    $manila = Warehouse::factory()->create();
    $cebu = Warehouse::factory()->create();

    $out = TransferRequisition::factory()->create([
        'from_warehouse_id' => $manila->id,
        'to_warehouse_id' => $cebu->id,
    ]);
    $in = TransferRequisition::factory()->create([
        'from_warehouse_id' => $cebu->id,
        'to_warehouse_id' => $manila->id,
    ]);

    expect($manila->outgoingTransfers->pluck('id'))->toContain($out->id)
        ->and($manila->outgoingTransfers->pluck('id'))->not->toContain($in->id)
        ->and($manila->incomingTransfers->pluck('id'))->toContain($in->id);
});

it('prevents deleting a warehouse referenced by a transfer requisition', function () {
    $warehouse = Warehouse::factory()->create();
    TransferRequisition::factory()->create(['from_warehouse_id' => $warehouse->id]);

    expect(fn () => $warehouse->delete())
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('attaches users through the pivot table', function () {
    $warehouse = Warehouse::factory()->create();
    $user = User::factory()->create();

    $warehouse->users()->attach($user);

    expect($warehouse->users)->toHaveCount(1)
        ->and($warehouse->users->first()->is($user))->toBeTrue();
});
