<?php

declare(strict_types=1);

use App\Models\Supplier;
use App\Models\User;
use App\Policies\SupplierPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->policy = new SupplierPolicy());
beforeEach(fn () => $this->supplier = Supplier::factory()->create());

it('grants read universally', function () {
    foreach ([User::factory()->admin()->create(), User::factory()->auditor()->create(),
        User::factory()->create(), User::factory()->branchManager()->create()] as $user) {
        expect($this->policy->viewAny($user))->toBeTrue();
        expect($this->policy->view($user, $this->supplier))->toBeTrue();
    }
});

it('grants writes to admin only', function () {
    $admin = User::factory()->admin()->create();
    expect($this->policy->create($admin))->toBeTrue();
    expect($this->policy->update($admin, $this->supplier))->toBeTrue();
    expect($this->policy->delete($admin, $this->supplier))->toBeTrue();
});

it('denies writes to non-admins', function (User $user) {
    expect($this->policy->create($user))->toBeFalse();
    expect($this->policy->update($user, $this->supplier))->toBeFalse();
})->with([
    'auditor' => fn () => User::factory()->auditor()->create(),
    'warehouse' => fn () => User::factory()->create(),
    'branch_manager' => fn () => User::factory()->branchManager()->create(),
]);
