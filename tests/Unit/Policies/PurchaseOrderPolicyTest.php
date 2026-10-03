<?php

declare(strict_types=1);

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\PurchaseOrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->policy = new PurchaseOrderPolicy());

function makePoPolicy(PurchaseOrderStatus $status = PurchaseOrderStatus::Draft): array
{
    $warehouse = Warehouse::factory()->create();
    $order = PurchaseOrder::factory()->create([
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
    ['order' => $order, 'warehouse' => $warehouse] = makePoPolicy();
    expect($this->policy->view(User::factory()->admin()->create(), $order))->toBeTrue();
    expect($this->policy->view(User::factory()->auditor()->create(), $order))->toBeTrue();
    $staff = User::factory()->create();
    $staff->warehouses()->attach($warehouse->id);
    expect($this->policy->view($staff, $order))->toBeTrue();
});

it('create requires non-auditor with an assignment', function () {
    expect($this->policy->create(User::factory()->admin()->create()))->toBeTrue();
    expect($this->policy->create(User::factory()->auditor()->create()))->toBeFalse();
    expect($this->policy->create(User::factory()->create()))->toBeFalse();
    $staff = User::factory()->create();
    $staff->warehouses()->attach(Warehouse::factory()->create()->id);
    expect($this->policy->create($staff))->toBeTrue();
});

it('update is draft + warehouse member', function () {
    ['order' => $draft, 'warehouse' => $w] = makePoPolicy(PurchaseOrderStatus::Draft);
    $staff = User::factory()->create();
    $staff->warehouses()->attach($w->id);
    expect($this->policy->update($staff, $draft))->toBeTrue();

    ['order' => $ordered, 'warehouse' => $w2] = makePoPolicy(PurchaseOrderStatus::Ordered);
    $staff2 = User::factory()->create();
    $staff2->warehouses()->attach($w2->id);
    expect($this->policy->update($staff2, $ordered))->toBeFalse();
});

it('delete is non-auditor + draft/cancelled + warehouse member', function () {
    ['order' => $draft, 'warehouse' => $w] = makePoPolicy(PurchaseOrderStatus::Draft);
    $staff = User::factory()->create();
    $staff->warehouses()->attach($w->id);
    expect($this->policy->delete($staff, $draft))->toBeTrue();

    ['order' => $received, 'warehouse' => $w2] = makePoPolicy(PurchaseOrderStatus::Received);
    $staff2 = User::factory()->create();
    $staff2->warehouses()->attach($w2->id);
    expect($this->policy->delete($staff2, $received))->toBeFalse();

    expect($this->policy->delete(User::factory()->auditor()->create(), $draft))->toBeFalse();
});

it('admin-only abilities are admin-only', function () {
    ['order' => $order] = makePoPolicy();
    $admin = User::factory()->admin()->create();
    foreach (['deleteAny', 'restore', 'restoreAny', 'forceDelete', 'forceDeleteAny'] as $ability) {
        expect($this->policy->{$ability}($admin, $order))->toBeTrue();
        expect($this->policy->{$ability}(User::factory()->create(), $order))->toBeFalse();
    }
});

it('orderPurchase is non-auditor + warehouse member', function () {
    ['order' => $order, 'warehouse' => $w] = makePoPolicy(PurchaseOrderStatus::Draft);
    $staff = User::factory()->create();
    $staff->warehouses()->attach($w->id);
    expect($this->policy->orderPurchase($staff, $order))->toBeTrue();
    expect($this->policy->orderPurchase(User::factory()->auditor()->create(), $order))->toBeFalse();
});

it('receivePurchase is non-auditor + warehouse member', function () {
    ['order' => $order, 'warehouse' => $w] = makePoPolicy(PurchaseOrderStatus::Ordered);
    $staff = User::factory()->create();
    $staff->warehouses()->attach($w->id);
    expect($this->policy->receivePurchase($staff, $order))->toBeTrue();
});

it('cancelPurchase honors canBeCancelled boundary', function () {
    ['order' => $order, 'warehouse' => $w] = makePoPolicy(PurchaseOrderStatus::Draft);
    $staff = User::factory()->create();
    $staff->warehouses()->attach($w->id);
    expect($this->policy->cancelPurchase($staff, $order))->toBeTrue();

    ['order' => $received, 'warehouse' => $w2] = makePoPolicy(PurchaseOrderStatus::Received);
    $staff2 = User::factory()->create();
    $staff2->warehouses()->attach($w2->id);
    expect($this->policy->cancelPurchase($staff2, $received))->toBeFalse();
});

it('viewAuditFilters is admin or auditor only', function () {
    expect($this->policy->viewAuditFilters(User::factory()->admin()->create(), 'X'))->toBeTrue();
    expect($this->policy->viewAuditFilters(User::factory()->auditor()->create(), 'X'))->toBeTrue();
    expect($this->policy->viewAuditFilters(User::factory()->create(), 'X'))->toBeFalse();
    expect($this->policy->viewAuditFilters(User::factory()->branchManager()->create(), 'X'))->toBeFalse();
});

it('grants operational abilities to branch manager with warehouse scope', function () {
    ['order' => $order, 'warehouse' => $w] = makePoPolicy(PurchaseOrderStatus::Draft);
    $bm = User::factory()->branchManager()->create();
    $bm->warehouses()->attach($w->id);

    expect($this->policy->create($bm))->toBeTrue();
    expect($this->policy->orderPurchase($bm, $order))->toBeTrue();
    expect($this->policy->update($bm, $order))->toBeTrue();
});
