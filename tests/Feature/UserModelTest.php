<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Warehouse;

test('user has role attribute with default value', function () {
    $user = User::factory()->create();
    $user->refresh();

    expect($user->role)->toBe('warehouse_staff');
});

test('role is mass assignable via fillable', function () {
    $user = User::factory()->create(['role' => 'admin']);

    expect($user->role)->toBe('admin');
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
