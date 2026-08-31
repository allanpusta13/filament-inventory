<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Warehouse;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->manager = User::factory()->create(['role' => 'branch_manager']);
    $this->staff = User::factory()->create(['role' => 'warehouse_staff']);
    $this->auditor = User::factory()->create(['role' => 'auditor']);

    $this->wh = Warehouse::factory()->create();
    $this->staff->warehouses()->attach($this->wh->id);
    $this->manager->warehouses()->attach($this->wh->id);
});

it('admin can access stock adjustment page', function (): void {
    $this->actingAs($this->admin);
    $this->get(route('filament.admin.pages.stock-adjustment'))->assertOk();
});

it('branch manager can access stock adjustment page', function (): void {
    $this->actingAs($this->manager);
    $this->get(route('filament.admin.pages.stock-adjustment'))->assertOk();
});

it('warehouse staff cannot access stock adjustment page', function (): void {
    $this->actingAs($this->staff);
    $this->get(route('filament.admin.pages.stock-adjustment'))->assertForbidden();
});

it('auditor cannot access stock adjustment page', function (): void {
    $this->actingAs($this->auditor);
    $this->get(route('filament.admin.pages.stock-adjustment'))->assertForbidden();
});
