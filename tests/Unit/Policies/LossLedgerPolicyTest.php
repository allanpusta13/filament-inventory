<?php

declare(strict_types=1);

use App\Models\LossLedger;
use App\Models\User;
use App\Policies\LossLedgerPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->policy = new LossLedgerPolicy());

it('viewAny is universal', function () {
    foreach ([User::factory()->admin()->create(), User::factory()->auditor()->create(),
        User::factory()->create(), User::factory()->branchManager()->create()] as $user) {
        expect($this->policy->viewAny($user))->toBeTrue();
    }
});

it('view grants admin/auditor/assigned-staff', function () {
    $loss = LossLedger::factory()->create();
    $warehouse = $loss->warehouse_id;

    expect($this->policy->view(User::factory()->admin()->create(), $loss))->toBeTrue();
    expect($this->policy->view(User::factory()->auditor()->create(), $loss))->toBeTrue();

    $staff = User::factory()->create();
    $staff->warehouses()->attach($warehouse);
    expect($this->policy->view($staff, $loss))->toBeTrue();

    expect($this->policy->view(User::factory()->create(), $loss))->toBeFalse();
});

it('denies every write ability', function () {
    $loss = LossLedger::factory()->create();
    $admin = User::factory()->admin()->create();
    expect($this->policy->create($admin))->toBeFalse();
    expect($this->policy->update($admin, $loss))->toBeFalse();
    expect($this->policy->delete($admin, $loss))->toBeFalse();
});

it('viewAuditFilters is admin or auditor only', function () {
    expect($this->policy->viewAuditFilters(User::factory()->admin()->create(), 'X'))->toBeTrue();
    expect($this->policy->viewAuditFilters(User::factory()->auditor()->create(), 'X'))->toBeTrue();
    expect($this->policy->viewAuditFilters(User::factory()->branchManager()->create(), 'X'))->toBeFalse();
});

it('grants branch manager warehouse-scoped view', function () {
    $loss = LossLedger::factory()->create();
    $bm = User::factory()->branchManager()->create();
    $bm->warehouses()->attach($loss->warehouse_id);
    expect($this->policy->view($bm, $loss))->toBeTrue();
});
