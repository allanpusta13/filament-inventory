<?php

declare(strict_types=1);

use App\Models\DirectTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\DirectTransferPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->policy = new DirectTransferPolicy());

function makeDtPolicy(): array
{
    $from = Warehouse::factory()->create();
    $to = Warehouse::factory()->create();
    $dt = DirectTransfer::factory()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
    ]);

    return ['dt' => $dt, 'from' => $from, 'to' => $to];
}

it('viewAny is universal', function () {
    foreach ([User::factory()->admin()->create(), User::factory()->auditor()->create(),
        User::factory()->create(), User::factory()->branchManager()->create()] as $user) {
        expect($this->policy->viewAny($user))->toBeTrue();
    }
});

it('view requires admin/auditor or BOTH endpoints', function () {
    ['dt' => $dt, 'from' => $from, 'to' => $to] = makeDtPolicy();

    expect($this->policy->view(User::factory()->admin()->create(), $dt))->toBeTrue();
    expect($this->policy->view(User::factory()->auditor()->create(), $dt))->toBeTrue();

    $both = User::factory()->create();
    $both->warehouses()->attach([$from->id, $to->id]);
    expect($this->policy->view($both, $dt))->toBeTrue();

    // Only one endpoint — denied.
    $one = User::factory()->create();
    $one->warehouses()->attach($from->id);
    expect($this->policy->view($one, $dt))->toBeFalse();

    // Neither — denied.
    expect($this->policy->view(User::factory()->create(), $dt))->toBeFalse();
});

it('create requires admin or non-auditor with two assignments', function () {
    expect($this->policy->create(User::factory()->admin()->create()))->toBeTrue();

    $two = User::factory()->create();
    $two->warehouses()->attach(Warehouse::factory()->count(2)->create()->pluck('id'));
    expect($this->policy->create($two))->toBeTrue();

    $one = User::factory()->create();
    $one->warehouses()->attach(Warehouse::factory()->create()->id);
    expect($this->policy->create($one))->toBeFalse();

    $auditor = User::factory()->auditor()->create();
    $auditor->warehouses()->attach(Warehouse::factory()->count(2)->create()->pluck('id'));
    expect($this->policy->create($auditor))->toBeFalse();
});

it('update is denied for everyone (fire-and-forget)', function () {
    ['dt' => $dt] = makeDtPolicy();
    foreach ([User::factory()->admin()->create(), User::factory()->auditor()->create(),
        User::factory()->create(), User::factory()->branchManager()->create()] as $user) {
        expect($this->policy->update($user, $dt))->toBeFalse();
    }
});

it('delete and deleteAny are admin-only', function () {
    ['dt' => $dt] = makeDtPolicy();
    expect($this->policy->delete(User::factory()->admin()->create(), $dt))->toBeTrue();
    expect($this->policy->deleteAny(User::factory()->admin()->create()))->toBeTrue();

    foreach ([User::factory()->auditor()->create(), User::factory()->create(),
        User::factory()->branchManager()->create()] as $user) {
        expect($this->policy->delete($user, $dt))->toBeFalse();
        expect($this->policy->deleteAny($user))->toBeFalse();
    }
});

it('viewAuditFilters is admin or auditor only', function () {
    expect($this->policy->viewAuditFilters(User::factory()->admin()->create(), 'X'))->toBeTrue();
    expect($this->policy->viewAuditFilters(User::factory()->auditor()->create(), 'X'))->toBeTrue();
    expect($this->policy->viewAuditFilters(User::factory()->create(), 'X'))->toBeFalse();
    expect($this->policy->viewAuditFilters(User::factory()->branchManager()->create(), 'X'))->toBeFalse();
});

it('grants branch manager create with two assignments', function () {
    $bm = User::factory()->branchManager()->create();
    $bm->warehouses()->attach(Warehouse::factory()->count(2)->create()->pluck('id'));
    expect($this->policy->create($bm))->toBeTrue();
});
