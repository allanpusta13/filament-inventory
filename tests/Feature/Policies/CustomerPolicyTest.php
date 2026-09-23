<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\User;
use App\Policies\CustomerPolicy;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->policy = new CustomerPolicy();
    $this->admin = User::factory()->admin()->create();
    $this->auditor = User::factory()->auditor()->create();
    $this->branchManager = User::factory()->branchManager()->create();
    $this->warehouseStaff = User::factory()->warehouseStaff()->create();
});

describe('CustomerPolicy - viewAny', function () {
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

describe('CustomerPolicy - view', function () {
    it('admin_can_view_any_customer', function () {
        $customer = Customer::factory()->create();
        expect($this->policy->view($this->admin, $customer))->toBeTrue();
    });

    it('auditor_can_view_any_customer', function () {
        $customer = Customer::factory()->create();
        expect($this->policy->view($this->auditor, $customer))->toBeTrue();
    });

    it('branch_manager_can_view_any_customer', function () {
        $customer = Customer::factory()->create();
        expect($this->policy->view($this->branchManager, $customer))->toBeTrue();
    });

    it('warehouse_staff_can_view_any_customer', function () {
        $customer = Customer::factory()->create();
        expect($this->policy->view($this->warehouseStaff, $customer))->toBeTrue();
    });
});

describe('CustomerPolicy - create', function () {
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

describe('CustomerPolicy - update', function () {
    it('admin_can_update', function () {
        $customer = Customer::factory()->create();
        expect($this->policy->update($this->admin, $customer))->toBeTrue();
    });

    it('branch_manager_can_update', function () {
        $customer = Customer::factory()->create();
        expect($this->policy->update($this->branchManager, $customer))->toBeTrue();
    });

    it('warehouse_staff_can_update', function () {
        $customer = Customer::factory()->create();
        expect($this->policy->update($this->warehouseStaff, $customer))->toBeTrue();
    });

    it('auditor_cannot_update', function () {
        $customer = Customer::factory()->create();
        expect($this->policy->update($this->auditor, $customer))->toBeFalse();
    });
});

describe('CustomerPolicy - delete', function () {
    it('admin_can_delete', function () {
        $customer = Customer::factory()->create();
        expect($this->policy->delete($this->admin, $customer))->toBeTrue();
    });

    it('non_admin_cannot_delete', function () {
        $customer = Customer::factory()->create();
        expect($this->policy->delete($this->auditor, $customer))->toBeFalse();
        expect($this->policy->delete($this->branchManager, $customer))->toBeFalse();
        expect($this->policy->delete($this->warehouseStaff, $customer))->toBeFalse();
    });
});

describe('CustomerPolicy - restore', function () {
    it('admin_can_restore', function () {
        $customer = Customer::factory()->trashed()->create();
        expect($this->policy->restore($this->admin, $customer))->toBeTrue();
    });

    it('auditor_can_restore', function () {
        $customer = Customer::factory()->trashed()->create();
        expect($this->policy->restore($this->auditor, $customer))->toBeTrue();
    });

    it('branch_manager_cannot_restore', function () {
        $customer = Customer::factory()->trashed()->create();
        expect($this->policy->restore($this->branchManager, $customer))->toBeFalse();
    });

    it('warehouse_staff_cannot_restore', function () {
        $customer = Customer::factory()->trashed()->create();
        expect($this->policy->restore($this->warehouseStaff, $customer))->toBeFalse();
    });
});

describe('CustomerPolicy - forceDelete', function () {
    it('admin_can_force_delete', function () {
        $customer = Customer::factory()->trashed()->create();
        expect($this->policy->forceDelete($this->admin, $customer))->toBeTrue();
    });

    it('non_admin_cannot_force_delete', function () {
        $customer = Customer::factory()->trashed()->create();
        expect($this->policy->forceDelete($this->auditor, $customer))->toBeFalse();
        expect($this->policy->forceDelete($this->branchManager, $customer))->toBeFalse();
        expect($this->policy->forceDelete($this->warehouseStaff, $customer))->toBeFalse();
    });
});
