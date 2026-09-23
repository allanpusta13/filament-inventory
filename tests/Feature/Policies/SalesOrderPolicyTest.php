<?php

declare(strict_types=1);

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\SalesOrderPolicy;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->policy = new SalesOrderPolicy();
    $this->admin = User::factory()->admin()->create();
    $this->auditor = User::factory()->auditor()->create();
    $this->branchManager = User::factory()->branchManager()->create();
    $this->warehouseStaff = User::factory()->warehouseStaff()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->branchManager->warehouses()->attach($this->warehouse);
    $this->warehouseStaff->warehouses()->attach($this->warehouse);
});

describe('SalesOrderPolicy - viewAny', function () {
    it('admin_can_view_any', function () {
        expect($this->policy->viewAny($this->admin))->toBeTrue();
    });

    it('auditor_can_view_any', function () {
        expect($this->policy->viewAny($this->auditor))->toBeTrue();
    });

    it('branch_manager_can_view_any', function () {
        expect($this->policy->viewAny($this->branchManager))->toBeTrue();
    });

    it('warehouse_staff_can_view_any', function () {
        expect($this->policy->viewAny($this->warehouseStaff))->toBeTrue();
    });
});

describe('SalesOrderPolicy - view', function () {
    it('admin_can_view_any_so', function () {
        $so = SalesOrder::factory()->create();
        expect($this->policy->view($this->admin, $so))->toBeTrue();
    });

    it('auditor_can_view_any_so', function () {
        $so = SalesOrder::factory()->create();
        expect($this->policy->view($this->auditor, $so))->toBeTrue();
    });

    it('branch_manager_can_view_so_for_accessible_warehouse', function () {
        $so = SalesOrder::factory()->create(['warehouse_id' => $this->warehouse->id]);
        expect($this->policy->view($this->branchManager, $so))->toBeTrue();
    });

    it('branch_manager_cannot_view_so_for_inaccessible_warehouse', function () {
        $otherWarehouse = Warehouse::factory()->create();
        $so = SalesOrder::factory()->create(['warehouse_id' => $otherWarehouse->id]);
        expect($this->policy->view($this->branchManager, $so))->toBeFalse();
    });

    it('warehouse_staff_can_view_so_for_accessible_warehouse', function () {
        $so = SalesOrder::factory()->create(['warehouse_id' => $this->warehouse->id]);
        expect($this->policy->view($this->warehouseStaff, $so))->toBeTrue();
    });

    it('warehouse_staff_cannot_view_so_for_inaccessible_warehouse', function () {
        $otherWarehouse = Warehouse::factory()->create();
        $so = SalesOrder::factory()->create(['warehouse_id' => $otherWarehouse->id]);
        expect($this->policy->view($this->warehouseStaff, $so))->toBeFalse();
    });
});

describe('SalesOrderPolicy - create', function () {
    it('admin_can_create', function () {
        expect($this->policy->create($this->admin))->toBeTrue();
    });

    it('branch_manager_can_create', function () {
        expect($this->policy->create($this->branchManager))->toBeTrue();
    });

    it('warehouse_staff_can_create', function () {
        expect($this->policy->create($this->warehouseStaff))->toBeTrue();
    });

    it('auditor_cannot_create', function () {
        expect($this->policy->create($this->auditor))->toBeFalse();
    });
});

describe('SalesOrderPolicy - update', function () {
    it('admin_can_update_any_so', function () {
        $so = SalesOrder::factory()->create(['status' => SalesOrderStatus::Draft]);
        expect($this->policy->update($this->admin, $so))->toBeTrue();
    });

    it('branch_manager_can_update_draft_so_for_accessible_warehouse', function () {
        $so = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::Draft,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->update($this->branchManager, $so))->toBeTrue();
    });

    it('branch_manager_cannot_update_confirmed_so', function () {
        $so = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::Confirmed,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->update($this->branchManager, $so))->toBeFalse();
    });

    it('branch_manager_cannot_update_so_for_inaccessible_warehouse', function () {
        $otherWarehouse = Warehouse::factory()->create();
        $so = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::Draft,
            'warehouse_id' => $otherWarehouse->id,
        ]);
        expect($this->policy->update($this->branchManager, $so))->toBeFalse();
    });

    it('warehouse_staff_can_update_draft_so_for_accessible_warehouse', function () {
        $so = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::Draft,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->update($this->warehouseStaff, $so))->toBeTrue();
    });

    it('warehouse_staff_cannot_update_confirmed_so', function () {
        $so = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::Confirmed,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->update($this->warehouseStaff, $so))->toBeFalse();
    });
});

describe('SalesOrderPolicy - delete', function () {
    it('admin_can_delete_any_so', function () {
        $so = SalesOrder::factory()->create(['status' => SalesOrderStatus::Draft]);
        expect($this->policy->delete($this->admin, $so))->toBeTrue();
    });

    it('branch_manager_can_delete_draft_so_for_accessible_warehouse', function () {
        $so = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::Draft,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->delete($this->branchManager, $so))->toBeTrue();
    });

    it('branch_manager_can_delete_cancelled_so_for_accessible_warehouse', function () {
        $so = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::Cancelled,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->delete($this->branchManager, $so))->toBeTrue();
    });

    it('branch_manager_cannot_delete_confirmed_so', function () {
        $so = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::Confirmed,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->delete($this->branchManager, $so))->toBeFalse();
    });

    it('branch_manager_cannot_delete_dispatched_so', function () {
        $so = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::Dispatched,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->delete($this->branchManager, $so))->toBeFalse();
    });
});

describe('SalesOrderPolicy - restore', function () {
    it('admin_can_restore', function () {
        $so = SalesOrder::factory()->trashed()->create();
        expect($this->policy->restore($this->admin, $so))->toBeTrue();
    });

    it('non_admin_cannot_restore', function () {
        $so = SalesOrder::factory()->trashed()->create();
        expect($this->policy->restore($this->auditor, $so))->toBeFalse();
        expect($this->policy->restore($this->branchManager, $so))->toBeFalse();
        expect($this->policy->restore($this->warehouseStaff, $so))->toBeFalse();
    });
});

describe('SalesOrderPolicy - forceDelete', function () {
    it('admin_can_force_delete', function () {
        $so = SalesOrder::factory()->trashed()->create();
        expect($this->policy->forceDelete($this->admin, $so))->toBeTrue();
    });

    it('non_admin_cannot_force_delete', function () {
        $so = SalesOrder::factory()->trashed()->create();
        expect($this->policy->forceDelete($this->auditor, $so))->toBeFalse();
        expect($this->policy->forceDelete($this->branchManager, $so))->toBeFalse();
        expect($this->policy->forceDelete($this->warehouseStaff, $so))->toBeFalse();
    });
});

describe('SalesOrderPolicy - confirm', function () {
    it('admin_can_confirm', function () {
        $so = SalesOrder::factory()->create(['status' => SalesOrderStatus::Draft]);
        expect($this->policy->confirm($this->admin, $so))->toBeTrue();
    });

    it('branch_manager_can_confirm_draft_so_for_accessible_warehouse', function () {
        $so = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::Draft,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->confirm($this->branchManager, $so))->toBeTrue();
    });

    it('branch_manager_cannot_confirm_non_draft_so', function () {
        $so = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::Confirmed,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->confirm($this->branchManager, $so))->toBeFalse();
    });

    it('warehouse_staff_can_confirm_draft_so_for_accessible_warehouse', function () {
        $so = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::Draft,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->confirm($this->warehouseStaff, $so))->toBeTrue();
    });
});

describe('SalesOrderPolicy - dispatch', function () {
    it('admin_can_dispatch', function () {
        $so = SalesOrder::factory()->create(['status' => SalesOrderStatus::Confirmed]);
        expect($this->policy->dispatch($this->admin, $so))->toBeTrue();
    });

    it('branch_manager_can_dispatch_confirmed_or_partially_dispatched_so_for_accessible_warehouse', function () {
        $so = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::Confirmed,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->dispatch($this->branchManager, $so))->toBeTrue();

        $so2 = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::PartiallyDispatched,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->dispatch($this->branchManager, $so2))->toBeTrue();
    });

    it('branch_manager_cannot_dispatch_draft_so', function () {
        $so = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::Draft,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->dispatch($this->branchManager, $so))->toBeFalse();
    });

    it('warehouse_staff_can_dispatch_confirmed_or_partially_dispatched_so_for_accessible_warehouse', function () {
        $so = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::Confirmed,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->dispatch($this->warehouseStaff, $so))->toBeTrue();
    });
});

describe('SalesOrderPolicy - cancel', function () {
    it('admin_can_cancel_draft_confirmed_partially_dispatched', function () {
        $so = SalesOrder::factory()->create(['status' => SalesOrderStatus::Draft]);
        expect($this->policy->cancel($this->admin, $so))->toBeTrue();

        $so2 = SalesOrder::factory()->create(['status' => SalesOrderStatus::Confirmed]);
        expect($this->policy->cancel($this->admin, $so2))->toBeTrue();

        $so3 = SalesOrder::factory()->create(['status' => SalesOrderStatus::PartiallyDispatched]);
        expect($this->policy->cancel($this->admin, $so3))->toBeTrue();
    });

    it('branch_manager_can_cancel_draft_confirmed_partially_dispatched_for_accessible_warehouse', function () {
        $so = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::Draft,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->cancel($this->branchManager, $so))->toBeTrue();

        $so2 = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::Confirmed,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->cancel($this->branchManager, $so2))->toBeTrue();

        $so3 = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::PartiallyDispatched,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->cancel($this->branchManager, $so3))->toBeTrue();
    });

    it('branch_manager_cannot_cancel_dispatched', function () {
        $so = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::Dispatched,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->cancel($this->branchManager, $so))->toBeFalse();
    });

    it('warehouse_staff_can_cancel_draft_confirmed_partially_dispatched_for_accessible_warehouse', function () {
        $so = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::Draft,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->cancel($this->warehouseStaff, $so))->toBeTrue();

        $so2 = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::Confirmed,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->cancel($this->warehouseStaff, $so2))->toBeTrue();

        $so3 = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::PartiallyDispatched,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->cancel($this->warehouseStaff, $so3))->toBeTrue();
    });
});
