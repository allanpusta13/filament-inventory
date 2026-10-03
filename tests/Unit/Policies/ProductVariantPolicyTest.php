<?php

declare(strict_types=1);

use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\ProductVariantPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->policy = new ProductVariantPolicy());
beforeEach(fn () => $this->variant = ProductVariant::factory()->create());

it('grants read universally', function () {
    foreach ([User::factory()->admin()->create(), User::factory()->auditor()->create(),
        User::factory()->create(), User::factory()->branchManager()->create()] as $user) {
        expect($this->policy->viewAny($user))->toBeTrue();
        expect($this->policy->view($user, $this->variant))->toBeTrue();
    }
});

it('grants catalog writes to admin only', function () {
    $admin = User::factory()->admin()->create();
    expect($this->policy->create($admin))->toBeTrue();
    expect($this->policy->update($admin, $this->variant))->toBeTrue();
    expect($this->policy->delete($admin, $this->variant))->toBeTrue();
});

it('denies catalog writes to non-admins', function (User $user) {
    expect($this->policy->create($user))->toBeFalse();
    expect($this->policy->update($user, $this->variant))->toBeFalse();
    expect($this->policy->delete($user, $this->variant))->toBeFalse();
})->with([
    'auditor' => fn () => User::factory()->auditor()->create(),
    'warehouse' => fn () => User::factory()->create(),
    'branch_manager' => fn () => User::factory()->branchManager()->create(),
]);

it('always denies forceDelete and forceDeleteAny', function () {
    $admin = User::factory()->admin()->create();
    expect($this->policy->forceDelete($admin, $this->variant))->toBeFalse();
    expect($this->policy->forceDeleteAny($admin))->toBeFalse();
});

it('grants adjustStock to admin without warehouse assignment', function () {
    $admin = User::factory()->admin()->create();
    expect($this->policy->adjustStock($admin, $this->variant))->toBeTrue();
});

it('grants adjustStock to warehouse staff with an assignment', function () {
    $staff = User::factory()->create();
    $staff->warehouses()->attach(Warehouse::factory()->create()->id);
    expect($this->policy->adjustStock($staff, $this->variant))->toBeTrue();
});

it('denies adjustStock to warehouse staff with no assignments', function () {
    $staff = User::factory()->create();
    expect($this->policy->adjustStock($staff, $this->variant))->toBeFalse();
});

it('denies adjustStock to auditor even when assigned', function () {
    $auditor = User::factory()->auditor()->create();
    $auditor->warehouses()->attach(Warehouse::factory()->create()->id);
    expect($this->policy->adjustStock($auditor, $this->variant))->toBeFalse();
});

it('treats branch manager as non-auditor operational (adjustStock)', function () {
    // ⚠ Interim: same tier as WarehouseStaff.
    $bm = User::factory()->branchManager()->create();
    $bm->warehouses()->attach(Warehouse::factory()->create()->id);
    expect($this->policy->adjustStock($bm, $this->variant))->toBeTrue();
});
