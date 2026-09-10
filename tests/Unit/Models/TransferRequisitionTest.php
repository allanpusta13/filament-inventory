<?php

declare(strict_types=1);

use App\Enums\TransferRequisitionStatus;
use App\Models\TransferRequisition;

it('casts status to the TransferRequisitionStatus enum', function () {
    $requisition = TransferRequisition::factory()->create(['status' => TransferRequisitionStatus::Dispatched]);

    expect($requisition->fresh()->status)->toBe(TransferRequisitionStatus::Dispatched);
});

it('defaults status to draft', function () {
    $requisition = TransferRequisition::factory()->create();

    expect($requisition->status)->toBe(TransferRequisitionStatus::Draft);
});

it('enforces unique reference_code', function () {
    TransferRequisition::factory()->create(['reference_code' => 'DTR-DUPE']);

    expect(fn () => TransferRequisition::factory()->create(['reference_code' => 'DTR-DUPE']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('soft deletes without removing the row', function () {
    $requisition = TransferRequisition::factory()->create();
    $requisition->delete();

    $this->assertSoftDeleted($requisition);
});

describe('TransferRequisitionStatus enum', function () {
    it('identifies terminal states', function () {
        expect(TransferRequisitionStatus::Completed->isTerminal())->toBeTrue()
            ->and(TransferRequisitionStatus::ClosedWithLoss->isTerminal())->toBeTrue()
            ->and(TransferRequisitionStatus::Cancelled->isTerminal())->toBeTrue()
            ->and(TransferRequisitionStatus::Draft->isTerminal())->toBeFalse()
            ->and(TransferRequisitionStatus::Dispatched->isTerminal())->toBeFalse();
    });

    it('provides a human-readable label for every case', function () {
        foreach (TransferRequisitionStatus::cases() as $case) {
            expect($case->getLabel())->toBeString()->not->toBeEmpty();
        }
    });
});

it('isTerminal proxies to the status enum', function () {
    $requisition = TransferRequisition::factory()->create(['status' => TransferRequisitionStatus::Completed]);

    expect($requisition->isTerminal())->toBeTrue();
});
