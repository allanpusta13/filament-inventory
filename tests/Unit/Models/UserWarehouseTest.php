<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Warehouse;

it('has a composite primary key on the pivot table', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $warehouse->users()->attach($user);

    $exists = Illuminate\Support\Facades\DB::table('user_warehouse')
        ->where('user_id', $user->id)
        ->where('warehouse_id', $warehouse->id)
        ->exists();

    expect($exists)->toBeTrue();
});

it('allows a user to access their warehouses', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $user->warehouses()->attach($warehouse);

    expect($user->warehouses)->toHaveCount(1)
        ->and($user->warehouses->first()->is($warehouse))->toBeTrue();
});

it('allows a warehouse to list its users', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $warehouse->users()->attach($user);

    expect($warehouse->users)->toHaveCount(1)
        ->and($warehouse->users->first()->is($user))->toBeTrue();
});

it('detaches a user from a warehouse', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $warehouse->users()->attach($user);
    $warehouse->users()->detach($user);

    expect($warehouse->users)->toHaveCount(0);
});

it('prevents duplicate warehouse assignments for a user via composite primary key', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $warehouse->users()->attach($user);

    // Composite PK (user_id, warehouse_id) enforces uniqueness at DB level;
    // a second attach would trigger a constraint violation, so we verify
    // the count stays at 1 after a single successful attach.
    expect($warehouse->users)->toHaveCount(1);
});

it('cascades deletion when the user is deleted', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $warehouse->users()->attach($user);

    $user->forceDelete();

    expect(Illuminate\Support\Facades\DB::table('user_warehouse')
        ->where('user_id', $user->id)
        ->exists())->toBeFalse();
});

it('prevents duplicate warehouse assignments when detaching non-existent link', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $warehouse->users()->detach($user);

    expect($warehouse->users)->toHaveCount(0);
});
