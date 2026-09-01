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
        // Create users
        $admin = User::factory()->create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'role' => UserRole::Admin->value,
            'password' => Hash::make('password'),
        ]);

        $managerMnl = User::factory()->create([
            'name' => 'Manila Branch Manager',
            'email' => 'manager.mnl@example.com',
            'role' => UserRole::BranchManager->value,
            'password' => Hash::make('password'),
        ]);

        $managerCeb = User::factory()->create([
            'name' => 'Cebu Branch Manager',
            'email' => 'manager.ceb@example.com',
            'role' => UserRole::BranchManager->value,
            'password' => Hash::make('password'),
        ]);

        $staffDvo = User::factory()->create([
            'name' => 'Davao Warehouse Staff',
            'email' => 'staff.dvo@example.com',
            'role' => UserRole::WarehouseStaff->value,
            'password' => Hash::make('password'),
        ]);

        $auditor = User::factory()->create([
            'name' => 'Inventory Auditor',
            'email' => 'auditor@example.com',
            'role' => UserRole::Auditor->value,
            'password' => Hash::make('password'),
        ]);

        // Attach warehouses to users (we assume warehouse IDs from WarehouseAndCatalogSeeder)
        // We'll get the warehouse IDs by code
        $warehouseMnl = DB::table('warehouses')->where('code', 'WH-MNL')->first()->id;
        $warehouseCeb = DB::table('warehouses')->where('code', 'WH-CEB')->first()->id;
        $warehouseDvo = DB::table('warehouses')->where('code', 'WH-DVO')->first()->id;

        // Admin: all warehouses
        $admin->warehouses()->attach([$warehouseMnl, $warehouseCeb, $warehouseDvo]);

        // Manila Manager: only WH-MNL
        $managerMnl->warehouses()->attach([$warehouseMnl]);

        // Cebu Manager: only WH-CEB
        $managerCeb->warehouses()->attach([$warehouseCeb]);

        // Davao Staff: only WH-DVO
        $staffDvo->warehouses()->attach([$warehouseDvo]);

        // Auditor: all warehouses (read-only)
        $auditor->warehouses()->attach([$warehouseMnl, $warehouseCeb, $warehouseDvo]);
    }
}
