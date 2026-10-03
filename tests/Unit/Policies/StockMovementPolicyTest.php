<?php

declare(strict_types=1);

use App\Models\StockMovement;
use App\Models\User;
use App\Policies\StockMovementPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->policy = new StockMovementPolicy());

it('viewAny is universal', function () {
    foreach ([User::factory()->admin()->create(), User::factory()->auditor()->create(),
        User::factory()->create(), User::factory()->branchManager()->create()] as $user) {
        expect($this->policy->viewAny($user))->toBeTrue();
    }
});

it('view grants admin/auditor/assigned-staff', function () {
    $movement = StockMovement::factory()->create();
    $warehouse = $movement->warehouse_id;

    expect($this->policy->view(User::factory()->admin()->create(), $movement))->toBeTrue();
    expect($this->policy->view(User::factory()->auditor()->create(), $movement))->toBeTrue();

    $staff = User::factory()->create();
    $staff->warehouses()->attach($warehouse);
    expect($this->policy->view($staff, $movement))->toBeTrue();

    expect($this->policy->view(User::factory()->create(), $movement))->toBeFalse();
});

it('denies every write ability', function () {
    $movement = StockMovement::factory()->create();
    $admin = User::factory()->admin()->create();
    expect($this->policy->create($admin))->toBeFalse();
    expect($this->policy->update($admin, $movement))->toBeFalse();
    expect($this->policy->delete($admin, $movement))->toBeFalse();
});

it('viewAuditFilters is admin or auditor only', function () {
    expect($this->policy->viewAuditFilters(User::factory()->admin()->create(), 'X'))->toBeTrue();
    expect($this->policy->viewAuditFilters(User::factory()->auditor()->create(), 'X'))->toBeTrue();
    expect($this->policy->viewAuditFilters(User::factory()->create(), 'X'))->toBeFalse();
    expect($this->policy->viewAuditFilters(User::factory()->branchManager()->create(), 'X'))->toBeFalse();
});

it('removed createDirectTransfer — no such method', function () {
    expect(method_exists($this->policy, 'createDirectTransfer'))->toBeFalse();
});

it('grants branch manager warehouse-scoped view', function () {
    $movement = StockMovement::factory()->create();
    $bm = User::factory()->branchManager()->create();
    $bm->warehouses()->attach($movement->warehouse_id);

    expect($this->policy->view($bm, $movement))->toBeTrue();
});
