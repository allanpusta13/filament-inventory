<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
final class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    private static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::WarehouseStaff->value,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model is a warehouse staff.
     */
    public function warehouseStaff(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::WarehouseStaff->value,
        ])->afterCreating(function (\App\Models\User $user) {
            $user->warehouses()->attach(Warehouse::factory()->create());
        });
    }

    /**
     * Indicate that the model is an admin.
     */
    public function admin(): static
    {
        // §5.6 / §1B.1a: Admin badge authority is global and independent of
        // the `user_warehouse` pivot, so the state sets the role only — it
        // must NOT auto-attach a warehouse. An attached row would be visible
        // to pivot-reading code and contradict the empty-pivot contract.
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Admin->value,
        ]);
    }

    /**
     * Indicate that the model is an auditor.
     */
    public function auditor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Auditor->value,
        ]);
    }

    /**
     * Indicate that the model is a branch manager.
     */
    public function branchManager(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::BranchManager->value,
        ]);
    }
}
