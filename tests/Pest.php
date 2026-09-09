<?php

declare(strict_types=1);

use App\Models\User;
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

function actingAsAdmin()
{
    $user = User::factory()->create([
        'role' => App\Enums\UserRole::ADMIN->value,
    ]);

    actingAs($user);

    return $user;
}
