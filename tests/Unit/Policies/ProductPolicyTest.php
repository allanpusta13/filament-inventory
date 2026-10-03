<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\User;
use App\Policies\ProductPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->policy = new ProductPolicy());
beforeEach(fn () => $this->product = Product::factory()->create());

it('grants viewAny and view universally', function () {
    foreach ([User::factory()->admin()->create(),
        User::factory()->auditor()->create(),
        User::factory()->create(),
        User::factory()->branchManager()->create()] as $user) {
        expect($this->policy->viewAny($user))->toBeTrue();
        expect($this->policy->view($user, $this->product))->toBeTrue();
    }
});

it('grants write abilities to admin only', function () {
    $admin = User::factory()->admin()->create();

    expect($this->policy->create($admin))->toBeTrue();
    expect($this->policy->update($admin, $this->product))->toBeTrue();
    expect($this->policy->delete($admin, $this->product))->toBeTrue();
    expect($this->policy->deleteAny($admin))->toBeTrue();
    expect($this->policy->restore($admin, $this->product))->toBeTrue();
    expect($this->policy->restoreAny($admin))->toBeTrue();
    expect($this->policy->forceDelete($admin, $this->product))->toBeTrue();
    expect($this->policy->forceDeleteAny($admin))->toBeTrue();
});

it('denies write abilities to non-admins', function (User $user) {
    expect($this->policy->create($user))->toBeFalse();
    expect($this->policy->update($user, $this->product))->toBeFalse();
    expect($this->policy->delete($user, $this->product))->toBeFalse();
})->with([
    'auditor' => fn () => User::factory()->auditor()->create(),
    'warehouse' => fn () => User::factory()->create(),
    'branch_manager' => fn () => User::factory()->branchManager()->create(),
]);
