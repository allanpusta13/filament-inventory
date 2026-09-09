<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;

it('can be created via factory', function () {
    $user = User::factory()->create();

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->name)->not->toBeEmpty()
        ->and($user->email)->not->toBeEmpty();
});

it('has role cast to UserRole enum', function () {
    $user = User::factory()->create(['role' => UserRole::ADMIN->value]);

    expect($user->role)->toBeInstanceOf(UserRole::class)
        ->and($user->role)->toBe(UserRole::ADMIN);
});

it('isAdmin returns true for admin users', function () {
    $user = User::factory()->create(['role' => UserRole::ADMIN->value]);

    expect($user->isAdmin())->toBeTrue();
});

it('isAdmin returns false for non-admin users', function () {
    $user = User::factory()->create(['role' => UserRole::WAREHOUSE_STAFF->value]);

    expect($user->isAdmin())->toBeFalse();
});

it('isAuditor returns true for auditor users', function () {
    $user = User::factory()->create(['role' => UserRole::AUDITOR->value]);

    expect($user->isAuditor())->toBeTrue();
});

it('isBranchManager returns true for branch manager users', function () {
    $user = User::factory()->create(['role' => UserRole::BRANCH_MANAGER->value]);

    expect($user->isBranchManager())->toBeTrue();
});

it('isWarehouseStaff returns true for warehouse staff users', function () {
    $user = User::factory()->create(['role' => UserRole::WAREHOUSE_STAFF->value]);

    expect($user->isWarehouseStaff())->toBeTrue();
});

it('has access to warehouse when admin', function () {
    $user = User::factory()->create(['role' => UserRole::ADMIN->value]);

    expect($user->hasAccessToWarehouse(999))->toBeTrue();
});

it('has access to warehouse when auditor', function () {
    $user = User::factory()->create(['role' => UserRole::AUDITOR->value]);

    expect($user->hasAccessToWarehouse(999))->toBeTrue();
});

it('hides sensitive attributes from serialization', function () {
    $user = User::factory()->create();

    $array = $user->toArray();

    expect($array)->not->toHaveKey('password')
        ->and($array)->not->toHaveKey('remember_token');
});
