<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

uses(
    TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature', 'Unit');

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function actingAsAdmin()
{
    $user = User::factory()->create([
        'role' => App\Enums\UserRole::ADMIN->value,
    ]);

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