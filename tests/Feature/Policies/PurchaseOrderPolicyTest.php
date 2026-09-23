<?php

declare(strict_types=1);

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\PurchaseOrderPolicy;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->policy = new PurchaseOrderPolicy();
    $this->admin = User::factory()->admin()->create();
    $this->auditor = User::factory()->auditor()->create();
    $this->branchManager = User::factory()->branchManager()->create();
    $this->warehouseStaff = User::factory()->warehouseStaff()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->branchManager->warehouses()->attach($this->warehouse);
    $this->warehouseStaff->warehouses()->attach($this->warehouse);
});

describe('PurchaseOrderPolicy - viewAny', function () {
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

describe('PurchaseOrderPolicy - view', function () {
    it('admin_can_view_any_po', function () {
        $po = PurchaseOrder::factory()->create();
        expect($this->policy->view($this->admin, $po))->toBeTrue();
    });

    it('auditor_can_view_any_po', function () {
        $po = PurchaseOrder::factory()->create();
        expect($this->policy->view($this->auditor, $po))->toBeTrue();
    });

    it('branch_manager_can_view_po_for_accessible_warehouse', function () {
        $po = PurchaseOrder::factory()->create(['warehouse_id' => $this->warehouse->id]);
        expect($this->policy->view($this->branchManager, $po))->toBeTrue();
    });

    it('branch_manager_cannot_view_po_for_inaccessible_warehouse', function () {
        $otherWarehouse = Warehouse::factory()->create();
        $po = PurchaseOrder::factory()->create(['warehouse_id' => $otherWarehouse->id]);
        expect($this->policy->view($this->branchManager, $po))->toBeFalse();
    });

    it('warehouse_staff_can_view_po_for_accessible_warehouse', function () {
        $po = PurchaseOrder::factory()->create(['warehouse_id' => $this->warehouse->id]);
        expect($this->policy->view($this->warehouseStaff, $po))->toBeTrue();
    });

    it('warehouse_staff_cannot_view_po_for_inaccessible_warehouse', function () {
        $otherWarehouse = Warehouse::factory()->create();
        $po = PurchaseOrder::factory()->create(['warehouse_id' => $otherWarehouse->id]);
        expect($this->policy->view($this->warehouseStaff, $po))->toBeFalse();
    });
});

describe('PurchaseOrderPolicy - create', function () {
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

describe('PurchaseOrderPolicy - update', function () {
    it('admin_can_update_any_po', function () {
        $po = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Draft]);
        expect($this->policy->update($this->admin, $po))->toBeTrue();
    });

    it('branch_manager_can_update_draft_po_for_accessible_warehouse', function () {
        $po = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Draft,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->update($this->branchManager, $po))->toBeTrue();
    });

    it('branch_manager_cannot_update_ordered_po', function () {
        $po = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Ordered,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->update($this->branchManager, $po))->toBeFalse();
    });

    it('branch_manager_cannot_update_po_for_inaccessible_warehouse', function () {
        $otherWarehouse = Warehouse::factory()->create();
        $po = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Draft,
            'warehouse_id' => $otherWarehouse->id,
        ]);
        expect($this->policy->update($this->branchManager, $po))->toBeFalse();
    });

    it('warehouse_staff_can_update_draft_po_for_accessible_warehouse', function () {
        $po = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Draft,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->update($this->warehouseStaff, $po))->toBeTrue();
    });

    it('warehouse_staff_cannot_update_ordered_po', function () {
        $po = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Ordered,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->update($this->warehouseStaff, $po))->toBeFalse();
    });
});

describe('PurchaseOrderPolicy - delete', function () {
    it('admin_can_delete_any_po', function () {
        $po = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Draft]);
        expect($this->policy->delete($this->admin, $po))->toBeTrue();
    });

    it('branch_manager_can_delete_draft_po_for_accessible_warehouse', function () {
        $po = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Draft,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->delete($this->branchManager, $po))->toBeTrue();
    });

    it('branch_manager_can_delete_cancelled_po_for_accessible_warehouse', function () {
        $po = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Cancelled,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->delete($this->branchManager, $po))->toBeTrue();
    });

    it('branch_manager_cannot_delete_ordered_po', function () {
        $po = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Ordered,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->delete($this->branchManager, $po))->toBeFalse();
    });

    it('branch_manager_cannot_delete_partially_received_po', function () {
        $po = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::PartiallyReceived,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->delete($this->branchManager, $po))->toBeFalse();
    });
});

describe('PurchaseOrderPolicy - restore', function () {
    it('admin_can_restore', function () {
        $po = PurchaseOrder::factory()->trashed()->create();
        expect($this->policy->restore($this->admin, $po))->toBeTrue();
    });

    it('non_admin_cannot_restore', function () {
        $po = PurchaseOrder::factory()->trashed()->create();
        expect($this->policy->restore($this->auditor, $po))->toBeFalse();
        expect($this->policy->restore($this->branchManager, $po))->toBeFalse();
        expect($this->policy->restore($this->warehouseStaff, $po))->toBeFalse();
    });
});

describe('PurchaseOrderPolicy - forceDelete', function () {
    it('admin_can_force_delete', function () {
        $po = PurchaseOrder::factory()->trashed()->create();
        expect($this->policy->forceDelete($this->admin, $po))->toBeTrue();
    });

    it('non_admin_cannot_force_delete', function () {
        $po = PurchaseOrder::factory()->trashed()->create();
        expect($this->policy->forceDelete($this->auditor, $po))->toBeFalse();
        expect($this->policy->forceDelete($this->branchManager, $po))->toBeFalse();
        expect($this->policy->forceDelete($this->warehouseStaff, $po))->toBeFalse();
    });
});

describe('PurchaseOrderPolicy - order', function () {
    it('admin_can_order', function () {
        $po = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Draft]);
        expect($this->policy->order($this->admin, $po))->toBeTrue();
    });

    it('branch_manager_can_order_draft_po_for_accessible_warehouse', function () {
        $po = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Draft,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->order($this->branchManager, $po))->toBeTrue();
    });

    it('branch_manager_cannot_order_non_draft_po', function () {
        $po = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Ordered,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->order($this->branchManager, $po))->toBeFalse();
    });

    it('warehouse_staff_can_order_draft_po_for_accessible_warehouse', function () {
        $po = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Draft,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->order($this->warehouseStaff, $po))->toBeTrue();
    });
});

describe('PurchaseOrderPolicy - receive', function () {
    it('admin_can_receive', function () {
        $po = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Ordered]);
        expect($this->policy->receive($this->admin, $po))->toBeTrue();
    });

    it('branch_manager_can_receive_ordered_or_partially_received_po_for_accessible_warehouse', function () {
        $po = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Ordered,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->receive($this->branchManager, $po))->toBeTrue();

        $po2 = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::PartiallyReceived,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->receive($this->branchManager, $po2))->toBeTrue();
    });

    it('branch_manager_cannot_receive_draft_po', function () {
        $po = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Draft,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->receive($this->branchManager, $po))->toBeFalse();
    });

    it('warehouse_staff_can_receive_ordered_or_partially_received_po_for_accessible_warehouse', function () {
        $po = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Ordered,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->receive($this->warehouseStaff, $po))->toBeTrue();
    });
});

describe('PurchaseOrderPolicy - cancel', function () {
    it('admin_can_cancel_draft_or_ordered', function () {
        $po = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Draft]);
        expect($this->policy->cancel($this->admin, $po))->toBeTrue();

        $po2 = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Ordered]);
        expect($this->policy->cancel($this->admin, $po2))->toBeTrue();
    });

    it('branch_manager_can_cancel_draft_or_ordered_for_accessible_warehouse', function () {
        $po = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Draft,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->cancel($this->branchManager, $po))->toBeTrue();

        $po2 = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Ordered,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->cancel($this->branchManager, $po2))->toBeTrue();
    });

    it('branch_manager_cannot_cancel_partially_received', function () {
        $po = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::PartiallyReceived,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->cancel($this->branchManager, $po))->toBeFalse();
    });

    it('warehouse_staff_can_cancel_draft_or_ordered_for_accessible_warehouse', function () {
        $po = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Draft,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->cancel($this->warehouseStaff, $po))->toBeTrue();

        $po2 = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Ordered,
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($this->policy->cancel($this->warehouseStaff, $po2))->toBeTrue();
    });
});
