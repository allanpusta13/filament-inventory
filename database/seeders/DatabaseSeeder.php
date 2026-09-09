<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserRoleSeeder::class,      // creates users (admin, managers, staff)
            DemoWorkflowSeeder::class,  // creates warehouses, products, variants, stock movements, transfers
        ]);
    }
}
