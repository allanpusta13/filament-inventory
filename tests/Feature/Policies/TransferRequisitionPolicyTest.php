<?php

declare(strict_types=1);

use App\Enums\TransferRequisitionStatus;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\TransferRequisitionPolicy;

beforeEach(function () {
    $this->policy = new TransferRequisitionPolicy();
    $this->admin = User::factory()->admin()->create();
    $this->auditor = User::factory()->auditor()->create();
    $this->branchManager = User::factory()->branchManager()->create();
    $this->warehouseStaff = User::factory()->warehouseStaff()->create();
});

describe('TransferRequisitionPolicy - cancel', function () {
    it('cancel_is_permitted_while_confirmed', function () {
        $warehouse = Warehouse::factory()->create();
        $this->branchManager->warehouses()->attach($warehouse);
        $this->warehouseStaff->warehouses()->attach($warehouse);

        $requisition = TransferRequisition::factory()->create([
            'status' => TransferRequisitionStatus::Confirmed,
            'from_warehouse_id' => $warehouse->id,
            'to_warehouse_id' => Warehouse::factory()->create()->id,
        ]);

        expect($this->policy->cancel($this->admin, $requisition))->toBeTrue();
        expect($this->policy->cancel($this->auditor, $requisition))->toBeTrue();
        expect($this->policy->cancel($this->branchManager, $requisition))->toBeTrue();
        expect($this->policy->cancel($this->warehouseStaff, $requisition))->toBeTrue();
    });

    it('cancel_is_rejected_once_dispatched', function () {
        $warehouse = Warehouse::factory()->create();
        $requisition = TransferRequisition::factory()->create([
            'status' => TransferRequisitionStatus::Dispatched,
            'from_warehouse_id' => $warehouse->id,
            'to_warehouse_id' => Warehouse::factory()->create()->id,
        ]);

        expect($this->policy->cancel($this->admin, $requisition))->toBeFalse();
        expect($this->policy->cancel($this->auditor, $requisition))->toBeFalse();
        expect($this->policy->cancel($this->branchManager, $requisition))->toBeFalse();
        expect($this->policy->cancel($this->warehouseStaff, $requisition))->toBeFalse();
    });

    it('cancel_is_rejected_while_partially_received', function () {
        $warehouse = Warehouse::factory()->create();
        $requisition = TransferRequisition::factory()->create([
            'status' => TransferRequisitionStatus::PartiallyReceived,
            'from_warehouse_id' => $warehouse->id,
            'to_warehouse_id' => Warehouse::factory()->create()->id,
        ]);

        expect($this->policy->cancel($this->admin, $requisition))->toBeFalse();
        expect($this->policy->cancel($this->auditor, $requisition))->toBeFalse();
        expect($this->policy->cancel($this->branchManager, $requisition))->toBeFalse();
        expect($this->policy->cancel($this->warehouseStaff, $requisition))->toBeFalse();
    });
});
