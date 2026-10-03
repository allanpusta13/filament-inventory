<?php

declare(strict_types=1);

use App\Models\InTransit;
use App\Models\User;
use App\Policies\InTransitPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->policy = new InTransitPolicy());

it('viewAny is universal', function () {
    foreach ([User::factory()->admin()->create(), User::factory()->auditor()->create(),
        User::factory()->create(), User::factory()->branchManager()->create()] as $user) {
        expect($this->policy->viewAny($user))->toBeTrue();
    }
});

it('view grants admin/auditor/assigned-staff', function () {
    $inTransit = InTransit::factory()->create();
    $from = $inTransit->transferRequisition->from_warehouse_id;

    expect($this->policy->view(User::factory()->admin()->create(), $inTransit))->toBeTrue();
    expect($this->policy->view(User::factory()->auditor()->create(), $inTransit))->toBeTrue();

    $staff = User::factory()->create();
    $staff->warehouses()->attach($from);
    expect($this->policy->view($staff, $inTransit))->toBeTrue();

    expect($this->policy->view(User::factory()->create(), $inTransit))->toBeFalse();
});

it('grants branch manager with either endpoint', function () {
    $inTransit = InTransit::factory()->create();
    $from = $inTransit->transferRequisition->from_warehouse_id;

    $bm = User::factory()->branchManager()->create();
    $bm->warehouses()->attach($from);
    expect($this->policy->view($bm, $inTransit))->toBeTrue();
});
