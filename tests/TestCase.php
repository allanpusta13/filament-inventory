<?php

namespace Tests;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = ON;');

        $this->actingAs(User::factory()->create([
            'name' => config('app.default_user.name'),
            'email' => config('app.default_user.email'),
            'password' => config('app.default_user.password'),
        ]));

        $this->withoutVite();
    }

    protected function actingAsAdmin(User $user = null): User
    {
        return $user ?? actingAsAdmin();
    }

    protected function actingAsAuditor(User $user = null): User
    {
        return $user ?? actingAsAuditor();
    }

    protected function actingAsBranchManager(User $user = null): User
    {
        return $user ?? actingAsBranchManager();
    }

    protected function actingAsWarehouseStaff(User $user = null, ?Warehouse $warehouse = null): User
    {
        $user = $user ?? actingAsWarehouseStaff();

        if ($warehouse) {
            $user->warehouses()->syncWithoutDetaching([$warehouse->id]);
        }

        return $user;
    }
}