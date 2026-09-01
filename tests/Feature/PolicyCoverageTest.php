<?php

declare(strict_types=1);

use App\Models\LossLedger;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->staff = User::factory()->create(['role' => 'warehouse_staff']);
    $this->auditor = User::factory()->create(['role' => 'auditor']);
    $this->manager = User::factory()->create(['role' => 'branch_manager']);

    $this->wh1 = Warehouse::factory()->create();
    $this->wh2 = Warehouse::factory()->create();

    $this->staff->warehouses()->attach($this->wh1->id);
    $this->manager->warehouses()->attach($this->wh1->id);
});

describe('ProductPolicy', function (): void {
    test('admin can view any products', function (): void {
        $policy = new App\Policies\ProductPolicy();
        expect($policy->viewAny($this->admin))->toBeTrue();
    });

    test('non-admin can view any products', function (): void {
        $policy = new App\Policies\ProductPolicy();
        expect($policy->viewAny($this->staff))->toBeTrue();
        expect($policy->viewAny($this->auditor))->toBeTrue();
        expect($policy->viewAny($this->manager))->toBeTrue();
    });

    test('admin can view a product', function (): void {
        $policy = new App\Policies\ProductPolicy();
        $product = Product::factory()->create();
        expect($policy->view($this->admin, $product))->toBeTrue();
    });

    test('non-admin can view a product', function (): void {
        $policy = new App\Policies\ProductPolicy();
        $product = Product::factory()->create();
        expect($policy->view($this->staff, $product))->toBeTrue();
        expect($policy->view($this->auditor, $product))->toBeTrue();
        expect($policy->view($this->manager, $product))->toBeTrue();
    });

    test('admin can create products', function (): void {
        $policy = new App\Policies\ProductPolicy();
        expect($policy->create($this->admin))->toBeTrue();
    });

    test('non-admin cannot create products', function (): void {
        $policy = new App\Policies\ProductPolicy();
        expect($policy->create($this->staff))->toBeFalse();
        expect($policy->create($this->auditor))->toBeFalse();
        expect($policy->create($this->manager))->toBeFalse();
    });

    test('admin can update products', function (): void {
        $policy = new App\Policies\ProductPolicy();
        $product = Product::factory()->create();
        expect($policy->update($this->admin, $product))->toBeTrue();
    });

    test('non-admin cannot update products', function (): void {
        $policy = new App\Policies\ProductPolicy();
        $product = Product::factory()->create();
        expect($policy->update($this->staff, $product))->toBeFalse();
        expect($policy->update($this->auditor, $product))->toBeFalse();
        expect($policy->update($this->manager, $product))->toBeFalse();
    });

    test('admin can delete products', function (): void {
        $policy = new App\Policies\ProductPolicy();
        $product = Product::factory()->create();
        expect($policy->delete($this->admin, $product))->toBeTrue();
    });

    test('non-admin cannot delete products', function (): void {
        $policy = new App\Policies\ProductPolicy();
        $product = Product::factory()->create();
        expect($policy->delete($this->staff, $product))->toBeFalse();
        expect($policy->delete($this->auditor, $product))->toBeFalse();
        expect($policy->delete($this->manager, $product))->toBeFalse();
    });
});

describe('StockMovementPolicy', function (): void {
    test('all roles can view any stock movements', function (): void {
        $policy = new App\Policies\StockMovementPolicy();
        expect($policy->viewAny($this->admin))->toBeTrue();
        expect($policy->viewAny($this->staff))->toBeTrue();
        expect($policy->viewAny($this->auditor))->toBeTrue();
        expect($policy->viewAny($this->manager))->toBeTrue();
    });

    test('admin can view any stock movement', function (): void {
        $policy = new App\Policies\StockMovementPolicy();
        $movement = StockMovement::factory()->create(['warehouse_id' => $this->wh2->id]);
        expect($policy->view($this->admin, $movement))->toBeTrue();
    });

    test('auditor can view any stock movement', function (): void {
        $policy = new App\Policies\StockMovementPolicy();
        $movement = StockMovement::factory()->create(['warehouse_id' => $this->wh2->id]);
        expect($policy->view($this->auditor, $movement))->toBeTrue();
    });

    test('staff can view stock movement for assigned warehouse', function (): void {
        $policy = new App\Policies\StockMovementPolicy();
        $movement = StockMovement::factory()->create(['warehouse_id' => $this->wh1->id]);
        expect($policy->view($this->staff, $movement))->toBeTrue();
    });

    test('staff cannot view stock movement for unassigned warehouse', function (): void {
        $policy = new App\Policies\StockMovementPolicy();
        $movement = StockMovement::factory()->create(['warehouse_id' => $this->wh2->id]);
        expect($policy->view($this->staff, $movement))->toBeFalse();
    });

    test('manager can view stock movement for assigned warehouse', function (): void {
        $policy = new App\Policies\StockMovementPolicy();
        $movement = StockMovement::factory()->create(['warehouse_id' => $this->wh1->id]);
        expect($policy->view($this->manager, $movement))->toBeTrue();
    });

    test('manager cannot view stock movement for unassigned warehouse', function (): void {
        $policy = new App\Policies\StockMovementPolicy();
        $movement = StockMovement::factory()->create(['warehouse_id' => $this->wh2->id]);
        expect($policy->view($this->manager, $movement))->toBeFalse();
    });

    test('admin can create stock movements', function (): void {
        $policy = new App\Policies\StockMovementPolicy();
        expect($policy->create($this->admin))->toBeTrue();
    });

    test('staff can create stock movements', function (): void {
        $policy = new App\Policies\StockMovementPolicy();
        expect($policy->create($this->staff))->toBeTrue();
    });

    test('manager can create stock movements', function (): void {
        $policy = new App\Policies\StockMovementPolicy();
        expect($policy->create($this->manager))->toBeTrue();
    });

    test('auditor cannot create stock movements', function (): void {
        $policy = new App\Policies\StockMovementPolicy();
        expect($policy->create($this->auditor))->toBeFalse();
    });
});

describe('TransferRequisitionPolicy', function (): void {
    test('all roles can view any transfer requisitions', function (): void {
        $policy = new App\Policies\TransferRequisitionPolicy();
        expect($policy->viewAny($this->admin))->toBeTrue();
        expect($policy->viewAny($this->staff))->toBeTrue();
        expect($policy->viewAny($this->auditor))->toBeTrue();
        expect($policy->viewAny($this->manager))->toBeTrue();
    });

    test('admin can view any transfer requisition', function (): void {
        $policy = new App\Policies\TransferRequisitionPolicy();
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->wh2->id,
            'to_warehouse_id' => $this->wh2->id,
        ]);
        expect($policy->view($this->admin, $requisition))->toBeTrue();
    });

    test('auditor can view any transfer requisition', function (): void {
        $policy = new App\Policies\TransferRequisitionPolicy();
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->wh2->id,
            'to_warehouse_id' => $this->wh2->id,
        ]);
        expect($policy->view($this->auditor, $requisition))->toBeTrue();
    });

    test('staff can view transfer requisition involving assigned warehouse', function (): void {
        $policy = new App\Policies\TransferRequisitionPolicy();
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->wh1->id,
            'to_warehouse_id' => $this->wh2->id,
        ]);
        expect($policy->view($this->staff, $requisition))->toBeTrue();
    });

    test('staff cannot view transfer requisition for unassigned warehouses', function (): void {
        $policy = new App\Policies\TransferRequisitionPolicy();
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->wh2->id,
            'to_warehouse_id' => $this->wh2->id,
        ]);
        expect($policy->view($this->staff, $requisition))->toBeFalse();
    });

    test('admin can create transfer requisitions', function (): void {
        $policy = new App\Policies\TransferRequisitionPolicy();
        expect($policy->create($this->admin))->toBeTrue();
    });

    test('staff can create transfer requisitions', function (): void {
        $policy = new App\Policies\TransferRequisitionPolicy();
        expect($policy->create($this->staff))->toBeTrue();
    });

    test('manager can create transfer requisitions', function (): void {
        $policy = new App\Policies\TransferRequisitionPolicy();
        expect($policy->create($this->manager))->toBeTrue();
    });

    test('auditor cannot create transfer requisitions', function (): void {
        $policy = new App\Policies\TransferRequisitionPolicy();
        expect($policy->create($this->auditor))->toBeFalse();
    });

    test('admin can update any transfer requisition', function (): void {
        $policy = new App\Policies\TransferRequisitionPolicy();
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->wh1->id,
            'to_warehouse_id' => $this->wh2->id,
        ]);
        expect($policy->update($this->admin, $requisition))->toBeTrue();
    });

test('staff can update transfer requisition for assigned warehouse', function (): void {
        $policy = new App\Policies\TransferRequisitionPolicy();
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->wh1->id,
            'to_warehouse_id' => $this->wh2->id,
            'status' => 'draft',
            'requested_by' => $this->staff->id,
        ]);
        expect($policy->update($this->staff, $requisition))->toBeTrue();
    });

    test('auditor cannot update transfer requisitions', function (): void {
        $policy = new App\Policies\TransferRequisitionPolicy();
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->wh1->id,
            'to_warehouse_id' => $this->wh2->id,
        ]);
        expect($policy->update($this->auditor, $requisition))->toBeFalse();
    });

    test('owner can delete draft transfer requisition', function (): void {
        $policy = new App\Policies\TransferRequisitionPolicy();
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->wh1->id,
            'to_warehouse_id' => $this->wh2->id,
            'requested_by' => $this->staff->id,
            'status' => 'draft',
        ]);
        expect($policy->delete($this->staff, $requisition))->toBeTrue();
    });

    test('non-owner cannot delete draft transfer requisition', function (): void {
        $policy = new App\Policies\TransferRequisitionPolicy();
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->wh1->id,
            'to_warehouse_id' => $this->wh2->id,
            'requested_by' => $this->admin->id,
            'status' => 'draft',
        ]);
        expect($policy->delete($this->staff, $requisition))->toBeFalse();
    });

    test('cannot delete non-draft transfer requisition', function (): void {
        $policy = new App\Policies\TransferRequisitionPolicy();
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->wh1->id,
            'to_warehouse_id' => $this->wh2->id,
            'requested_by' => $this->staff->id,
            'status' => 'dispatched',
        ]);
        expect($policy->delete($this->staff, $requisition))->toBeFalse();
    });

    test('auditor cannot delete transfer requisitions', function (): void {
        $policy = new App\Policies\TransferRequisitionPolicy();
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->wh1->id,
            'to_warehouse_id' => $this->wh2->id,
            'requested_by' => $this->auditor->id,
            'status' => 'draft',
        ]);
        expect($policy->delete($this->auditor, $requisition))->toBeFalse();
    });

    test('admin can delete their own draft transfer requisition', function (): void {
        $policy = new App\Policies\TransferRequisitionPolicy();
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->wh1->id,
            'to_warehouse_id' => $this->wh2->id,
            'requested_by' => $this->admin->id,
            'status' => 'draft',
        ]);
        expect($policy->delete($this->admin, $requisition))->toBeTrue();
    });

    test('admin cannot delete draft transfer requisition created by another user', function (): void {
        $policy = new App\Policies\TransferRequisitionPolicy();
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->wh1->id,
            'to_warehouse_id' => $this->wh2->id,
            'requested_by' => $this->staff->id,
            'status' => 'draft',
        ]);
        expect($policy->delete($this->admin, $requisition))->toBeFalse();
    });
});

describe('LossLedgerPolicy', function (): void {
    test('all roles can view any loss ledger entries', function (): void {
        $policy = new App\Policies\LossLedgerPolicy();
        expect($policy->viewAny($this->admin))->toBeTrue();
        expect($policy->viewAny($this->staff))->toBeTrue();
        expect($policy->viewAny($this->auditor))->toBeTrue();
        expect($policy->viewAny($this->manager))->toBeTrue();
    });

    test('admin can view any loss ledger entry', function (): void {
        $policy = new App\Policies\LossLedgerPolicy();
        $entry = LossLedger::factory()->create(['warehouse_id' => $this->wh2->id]);
        expect($policy->view($this->admin, $entry))->toBeTrue();
    });

    test('auditor can view any loss ledger entry', function (): void {
        $policy = new App\Policies\LossLedgerPolicy();
        $entry = LossLedger::factory()->create(['warehouse_id' => $this->wh2->id]);
        expect($policy->view($this->auditor, $entry))->toBeTrue();
    });

    test('staff can view loss ledger entry for assigned warehouse', function (): void {
        $policy = new App\Policies\LossLedgerPolicy();
        $entry = LossLedger::factory()->create(['warehouse_id' => $this->wh1->id]);
        expect($policy->view($this->staff, $entry))->toBeTrue();
    });

    test('staff cannot view loss ledger entry for unassigned warehouse', function (): void {
        $policy = new App\Policies\LossLedgerPolicy();
        $entry = LossLedger::factory()->create(['warehouse_id' => $this->wh2->id]);
        expect($policy->view($this->staff, $entry))->toBeFalse();
    });

    test('manager can view loss ledger entry for assigned warehouse', function (): void {
        $policy = new App\Policies\LossLedgerPolicy();
        $entry = LossLedger::factory()->create(['warehouse_id' => $this->wh1->id]);
        expect($policy->view($this->manager, $entry))->toBeTrue();
    });

    test('admin can create loss ledger entries', function (): void {
        $policy = new App\Policies\LossLedgerPolicy();
        expect($policy->create($this->admin))->toBeTrue();
    });

    test('manager can create loss ledger entries', function (): void {
        $policy = new App\Policies\LossLedgerPolicy();
        expect($policy->create($this->manager))->toBeTrue();
    });

    test('staff cannot create loss ledger entries', function (): void {
        $policy = new App\Policies\LossLedgerPolicy();
        expect($policy->create($this->staff))->toBeFalse();
    });

    test('auditor cannot create loss ledger entries', function (): void {
        $policy = new App\Policies\LossLedgerPolicy();
        expect($policy->create($this->auditor))->toBeFalse();
    });
});
