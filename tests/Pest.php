<?php

declare(strict_types=1);

use App\Enums\InTransitStatus;
use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Enums\StockMovementType;
use App\Enums\TransferRequisitionStatus;
use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(
    TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature', 'Unit');

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/*
|--------------------------------------------------------------------------
| Shared Datasets
|--------------------------------------------------------------------------
|
| Load all shared datasets from dedicated file.
| Pest 4 dataset() definitions are file-scoped.
|
*/

require __DIR__ . '/Datasets/SharedDatasets.php';

/*
|--------------------------------------------------------------------------
| Role Helpers
|--------------------------------------------------------------------------
|
| Helper functions for creating authenticated users with specific roles.
| Use in tests instead of manually creating users.
|
*/

function actingAsAdmin(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'role' => UserRole::ADMIN->value,
    ], $attributes));

    actingAs($user);

    return $user;
}

function actingAsAuditor(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'role' => UserRole::AUDITOR->value,
    ], $attributes));

    actingAs($user);

    return $user;
}

function actingAsBranchManager(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'role' => UserRole::BRANCH_MANAGER->value,
    ], $attributes));

    actingAs($user);

    return $user;
}

function actingAsWarehouseStaff(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'role' => UserRole::WAREHOUSE_STAFF->value,
    ], $attributes));

    actingAs($user);

    return $user;
}

function actingAsUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    actingAs($user);

    return $user;
}

function actingAsGuest(): void
{
    auth()->logout();
}