<?php

declare(strict_types=1);

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\SalesOrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->policy = new SalesOrderPolicy());

function makeSoPolicy(SalesOrderStatus $status = SalesOrderStatus::Draft): array
{
    $warehouse = Warehouse::factory()->create();
    $order = SalesOrder::factory()->create([
        'warehouse_id' => $warehouse->id,
        'status' => $status,
    ]);

    return ['order' => $order, 'warehouse' => $warehouse];
}

it('viewAny is universal', function () {
    foreach ([User::factory()->admin()->create(), User::factory()->auditor()->create(),
        User::factory()->create(), User::factory()->branchManager()->create()] as $user) {
        expect($this->policy->viewAny($user))->toBeTrue();
    }
});

it('view grants admin/auditor/assigned-staff', function () {
    ['order' => $order, 'warehouse' => $w] = makeSoPolicy();
    expect($this->policy->view(User::factory()->admin()->create(), $order))->toBeTrue();
    expect($this->policy->view(User::factory()->auditor()->create(), $order))->toBeTrue();
    $staff = User::factory()->create();
    $staff->warehouses()->attach($w->id);
    expect($this->policy->view($staff, $order))->toBeTrue();
});

it('create requires non-auditor with an assignment', function () {
    expect($this->policy->create(User::factory()->admin()->create()))->toBeTrue();
    expect($this->policy->create(User::factory()->auditor()->create()))->toBeFalse();
    $staff = User::factory()->create();
    $staff->warehouses()->attach(Warehouse::factory()->create()->id);
    expect($this->policy->create($staff))->toBeTrue();
});

it('update is draft + warehouse member', function () {
    ['order' => $draft, 'warehouse' => $w] = makeSoPolicy(SalesOrderStatus::Draft);
    $staff = User::factory()->create();
    $staff->warehouses()->attach($w->id);
    expect($this->policy->update($staff, $draft))->toBeTrue();

    ['order' => $confirmed, 'warehouse' => $w2] = makeSoPolicy(SalesOrderStatus::Confirmed);
    $staff2 = User::factory()->create();
    $staff2->warehouses()->attach($w2->id);
    expect($this->policy->update($staff2, $confirmed))->toBeFalse();
});

it('delete is admin + draft/cancelled only', function () {
    $admin = User::factory()->admin()->create();
    ['order' => $draft] = makeSoPolicy(SalesOrderStatus::Draft);
    ['order' => $dispatched] = makeSoPolicy(SalesOrderStatus::Dispatched);

    expect($this->policy->delete($admin, $draft))->toBeTrue();
    expect($this->policy->delete($admin, $dispatched))->toBeFalse();
    expect($this->policy->delete(User::factory()->create(), $draft))->toBeFalse();
});

it('admin-only abilities are admin-only', function () {
    ['order' => $order] = makeSoPolicy();
    $admin = User::factory()->admin()->create();
    foreach (['deleteAny', 'restore', 'restoreAny', 'forceDelete', 'forceDeleteAny'] as $ability) {
        expect($this->policy->{$ability}($admin, $order))->toBeTrue();
        expect($this->policy->{$ability}(User::factory()->create(), $order))->toBeFalse();
    }
});

it('confirmSalesOrder is non-auditor + warehouse member', function () {
    ['order' => $order, 'warehouse' => $w] = makeSoPolicy(SalesOrderStatus::Draft);
    $staff = User::factory()->create();
    $staff->warehouses()->attach($w->id);
    expect($this->policy->confirmSalesOrder($staff, $order))->toBeTrue();
    expect($this->policy->confirmSalesOrder(User::factory()->auditor()->create(), $order))->toBeFalse();
});

it('dispatchSale is non-auditor + warehouse member', function () {
    ['order' => $order, 'warehouse' => $w] = makeSoPolicy(SalesOrderStatus::Confirmed);
    $staff = User::factory()->create();
    $staff->warehouses()->attach($w->id);
    expect($this->policy->dispatchSale($staff, $order))->toBeTrue();
});

it('recordSalesReturn is non-auditor + warehouse member', function () {
    ['order' => $order, 'warehouse' => $w] = makeSoPolicy(SalesOrderStatus::Dispatched);
    $staff = User::factory()->create();
    $staff->warehouses()->attach($w->id);
    expect($this->policy->recordSalesReturn($staff, $order))->toBeTrue();
});

it('cancelSalesOrder honors canBeCancelled boundary', function () {
    ['order' => $order, 'warehouse' => $w] = makeSoPolicy(SalesOrderStatus::Confirmed);
    $staff = User::factory()->create();
    $staff->warehouses()->attach($w->id);
    expect($this->policy->cancelSalesOrder($staff, $order))->toBeTrue();

    ['order' => $dispatched, 'warehouse' => $w2] = makeSoPolicy(SalesOrderStatus::Dispatched);
    $staff2 = User::factory()->create();
    $staff2->warehouses()->attach($w2->id);
    expect($this->policy->cancelSalesOrder($staff2, $dispatched))->toBeFalse();
});

it('viewAuditFilters is admin or auditor only', function () {
    expect($this->policy->viewAuditFilters(User::factory()->admin()->create(), 'X'))->toBeTrue();
    expect($this->policy->viewAuditFilters(User::factory()->auditor()->create(), 'X'))->toBeTrue();
    expect($this->policy->viewAuditFilters(User::factory()->branchManager()->create(), 'X'))->toBeFalse();
});
