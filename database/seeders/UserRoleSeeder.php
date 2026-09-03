<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        // Create or update users (updateOrCreate to be idempotent and fix role mismatches)
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'System Admin', 'role' => UserRole::Admin->value, 'password' => Hash::make('password')]
        );

        $managerMnl = User::updateOrCreate(
            ['email' => 'manager.mnl@example.com'],
            ['name' => 'Manila Branch Manager', 'role' => UserRole::BranchManager->value, 'password' => Hash::make('password')]
        );

        $managerCeb = User::updateOrCreate(
            ['email' => 'manager.ceb@example.com'],
            ['name' => 'Cebu Branch Manager', 'role' => UserRole::BranchManager->value, 'password' => Hash::make('password')]
        );

        $staffDvo = User::updateOrCreate(
            ['email' => 'staff.dvo@example.com'],
            ['name' => 'Davao Warehouse Staff', 'role' => UserRole::WarehouseStaff->value, 'password' => Hash::make('password')]
        );

        $auditor = User::updateOrCreate(
            ['email' => 'auditor@example.com'],
            ['name' => 'Inventory Auditor', 'role' => UserRole::Auditor->value, 'password' => Hash::make('password')]
        );

        // Attach warehouses to users (we assume warehouse IDs from WarehouseAndCatalogSeeder)
        // We'll get the warehouse IDs by code
        $warehouseMnl = DB::table('warehouses')->where('code', 'WH-MNL')->first()->id;
        $warehouseCeb = DB::table('warehouses')->where('code', 'WH-CEB')->first()->id;
        $warehouseDvo = DB::table('warehouses')->where('code', 'WH-DVO')->first()->id;

        // Admin: all warehouses (sync to avoid duplicates)
        $admin->warehouses()->sync([$warehouseMnl, $warehouseCeb, $warehouseDvo]);

        // Manila Manager: only WH-MNL
        $managerMnl->warehouses()->sync([$warehouseMnl]);

        // Cebu Manager: only WH-CEB
        $managerCeb->warehouses()->sync([$warehouseCeb]);

        // Davao Staff: only WH-DVO
        $staffDvo->warehouses()->sync([$warehouseDvo]);

        // Auditor: all warehouses (read-only)
        $auditor->warehouses()->sync([$warehouseMnl, $warehouseCeb, $warehouseDvo]);
    }
}
