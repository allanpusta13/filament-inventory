<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        // Create or update users (updateOrCreate to be idempotent and fix role mismatches)
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'System Admin', 'role' => UserRole::ADMIN->value, 'password' => Hash::make('password')]
        );

        $managerMnl = User::updateOrCreate(
            ['email' => 'manager.mnl@example.com'],
            ['name' => 'Manila Branch Manager', 'role' => UserRole::BRANCH_MANAGER->value, 'password' => Hash::make('password')]
        );

        $managerCeb = User::updateOrCreate(
            ['email' => 'manager.ceb@example.com'],
            ['name' => 'Cebu Branch Manager', 'role' => UserRole::BRANCH_MANAGER->value, 'password' => Hash::make('password')]
        );

        $staffDvo = User::updateOrCreate(
            ['email' => 'staff.dvo@example.com'],
            ['name' => 'Davao Warehouse Staff', 'role' => UserRole::WAREHOUSE_STAFF->value, 'password' => Hash::make('password')]
        );

        $auditor = User::updateOrCreate(
            ['email' => 'auditor@example.com'],
            ['name' => 'Inventory Auditor', 'role' => UserRole::AUDITOR->value, 'password' => Hash::make('password')]
        );

        $guest = User::updateOrCreate(
            ['email' => 'guest@example.com'],
            ['name' => 'Guest User', 'role' => UserRole::GUEST->value, 'password' => Hash::make('password')]
        );

        // Warehouse attachment is handled by DemoWorkflowSeeder (runs after this seeder)
        // because warehouses don't exist yet at this point.
    }
}
