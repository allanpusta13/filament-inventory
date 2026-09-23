<?php

declare(strict_types=1);

use App\Models\Supplier;
use App\Models\User;
use App\Policies\SupplierPolicy;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->policy = new SupplierPolicy();
    $this->admin = User::factory()->admin()->create();
    $this->auditor = User::factory()->auditor()->create();
    $this->branchManager = User::factory()->branchManager()->create();
    $this->warehouseStaff = User::factory()->warehouseStaff()->create();
});

describe('SupplierPolicy - viewAny', function () {
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

describe('SupplierPolicy - view', function () {
    it('admin_can_view_any_supplier', function () {
        $supplier = Supplier::factory()->create();
        expect($this->policy->view($this->admin, $supplier))->toBeTrue();
    });

    it('auditor_can_view_any_supplier', function () {
        $supplier = Supplier::factory()->create();
        expect($this->policy->view($this->auditor, $supplier))->toBeTrue();
    });

    it('branch_manager_can_view_any_supplier', function () {
        $supplier = Supplier::factory()->create();
        expect($this->policy->view($this->branchManager, $supplier))->toBeTrue();
    });

    it('warehouse_staff_can_view_any_supplier', function () {
        $supplier = Supplier::factory()->create();
        expect($this->policy->view($this->warehouseStaff, $supplier))->toBeTrue();
    });
});

describe('SupplierPolicy - create', function () {
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

describe('SupplierPolicy - update', function () {
    it('admin_can_update', function () {
        $supplier = Supplier::factory()->create();
        expect($this->policy->update($this->admin, $supplier))->toBeTrue();
    });

    it('branch_manager_can_update', function () {
        $supplier = Supplier::factory()->create();
        expect($this->policy->update($this->branchManager, $supplier))->toBeTrue();
    });

    it('warehouse_staff_can_update', function () {
        $supplier = Supplier::factory()->create();
        expect($this->policy->update($this->warehouseStaff, $supplier))->toBeTrue();
    });

    it('auditor_cannot_update', function () {
        $supplier = Supplier::factory()->create();
        expect($this->policy->update($this->auditor, $supplier))->toBeFalse();
    });
});

describe('SupplierPolicy - delete', function () {
    it('admin_can_delete', function () {
        $supplier = Supplier::factory()->create();
        expect($this->policy->delete($this->admin, $supplier))->toBeTrue();
    });

    it('non_admin_cannot_delete', function () {
        $supplier = Supplier::factory()->create();
        expect($this->policy->delete($this->auditor, $supplier))->toBeFalse();
        expect($this->policy->delete($this->branchManager, $supplier))->toBeFalse();
        expect($this->policy->delete($this->warehouseStaff, $supplier))->toBeFalse();
    });
});

describe('SupplierPolicy - restore', function () {
    it('admin_can_restore', function () {
        $supplier = Supplier::factory()->trashed()->create();
        expect($this->policy->restore($this->admin, $supplier))->toBeTrue();
    });

    it('auditor_can_restore', function () {
        $supplier = Supplier::factory()->trashed()->create();
        expect($this->policy->restore($this->auditor, $supplier))->toBeTrue();
    });

    it('branch_manager_cannot_restore', function () {
        $supplier = Supplier::factory()->trashed()->create();
        expect($this->policy->restore($this->branchManager, $supplier))->toBeFalse();
    });

    it('warehouse_staff_cannot_restore', function () {
        $supplier = Supplier::factory()->trashed()->create();
        expect($this->policy->restore($this->warehouseStaff, $supplier))->toBeFalse();
    });
});

describe('SupplierPolicy - forceDelete', function () {
    it('admin_can_force_delete', function () {
        $supplier = Supplier::factory()->trashed()->create();
        expect($this->policy->forceDelete($this->admin, $supplier))->toBeTrue();
    });

    it('non_admin_cannot_force_delete', function () {
        $supplier = Supplier::factory()->trashed()->create();
        expect($this->policy->forceDelete($this->auditor, $supplier))->toBeFalse();
        expect($this->policy->forceDelete($this->branchManager, $supplier))->toBeFalse();
        expect($this->policy->forceDelete($this->warehouseStaff, $supplier))->toBeFalse();
    });
});
