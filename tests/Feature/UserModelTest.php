<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Warehouse;

test('user has role attribute with default value', function () {
    $user = User::factory()->create();
    $user->refresh();

    expect($user->role)->toBe(UserRole::WarehouseStaff);
});

test('role is mass assignable via fillable', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    expect($user->role)->toBe(UserRole::Admin);
});

test('user belongs to many warehouses', function () {
    $user = User::factory()->create();
    $warehouses = Warehouse::factory()->count(3)->create();

    $user->warehouses()->attach($warehouses);

    expect($user->warehouses)->toHaveCount(3);
    expect($user->warehouses->first())->toBeInstanceOf(Warehouse::class);
});

test('warehouse users relationship is inverse of user warehouses', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $warehouse->users()->attach($user);

    expect($warehouse->users->first()->id)->toBe($user->id);
    expect($user->warehouses->first()->id)->toBe($warehouse->id);
});

test('admin user isAdmin returns true', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    expect($user->isAdmin())->toBeTrue();
});

test('warehouse staff user isAdmin returns false', function () {
    $user = User::factory()->create(['role' => UserRole::WarehouseStaff]);

    expect($user->isAdmin())->toBeFalse();
});

test('admin can access any warehouse', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $warehouse = Warehouse::factory()->create();

    expect($admin->canAccessWarehouse($warehouse))->toBeTrue();
});

test('warehouse staff can access assigned warehouse', function () {
    $staff = User::factory()->create(['role' => UserRole::WarehouseStaff]);
    $warehouse = Warehouse::factory()->create();

    $staff->warehouses()->attach($warehouse);

    expect($staff->canAccessWarehouse($warehouse))->toBeTrue();
});

test('warehouse staff cannot access unassigned warehouse', function () {
    $staff = User::factory()->create(['role' => UserRole::WarehouseStaff]);
    $warehouse = Warehouse::factory()->create();

    expect($staff->canAccessWarehouse($warehouse))->toBeFalse();
});
