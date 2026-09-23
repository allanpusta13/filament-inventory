<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources;

use App\Filament\Resources\SalesOrders\Pages\ListSalesOrders;
use App\Models\User;

use function Pest\Livewire\livewire;

describe('SalesOrdersTable period filter visibility', function () {
    it('period_filter_hidden_from_non_admin_non_auditor_users', function () {
        $staff = User::factory()->warehouseStaff()->create();
        $this->actingAs($staff);

        livewire(ListSalesOrders::class)
            ->loadTable()
            ->assertTableFilterHidden('period');
    });

    it('period_filter_visible_to_admin', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        livewire(ListSalesOrders::class)
            ->loadTable()
            ->assertTableFilterVisible('period');
    });

    it('period_filter_visible_to_auditor', function () {
        $auditor = User::factory()->auditor()->create();
        $this->actingAs($auditor);

        livewire(ListSalesOrders::class)
            ->loadTable()
            ->assertTableFilterVisible('period');
    });
});
