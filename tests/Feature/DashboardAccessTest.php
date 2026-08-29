<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;

it('admin can access the dashboard page', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value]);

    $this->actingAs($admin)
        ->get(route('filament.admin.pages.dashboard'))
        ->assertOk()
        ->assertSee('filament');
});

it('warehouse staff is denied dashboard access with 403', function (): void {
    $staff = User::factory()->create(['role' => UserRole::WarehouseStaff->value]);

    $this->actingAs($staff)
        ->get(route('filament.admin.pages.dashboard'))
        ->assertForbidden();
});

it('non-admin dashboard response contains no dashboard widget data', function (): void {
    $staff = User::factory()->create(['role' => UserRole::WarehouseStaff->value]);

    $response = $this->actingAs($staff)
        ->get(route('filament.admin.pages.dashboard'));

    $response->assertForbidden();

    $content = $response->getContent();
    expect($content)->not->toContain('Performance Overview');
    expect($content)->not->toContain('Total SKUs');
    expect($content)->not->toContain('Stock by Warehouse');
    expect($content)->not->toContain('stock-movement-trend');
});

it('unauthenticated user cannot access the dashboard', function (): void {
    $this->get(route('filament.admin.pages.dashboard'))
        ->assertForbidden();
});

it('canAccess is static and returns boolean', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $staff = User::factory()->create(['role' => UserRole::WarehouseStaff->value]);

    $this->actingAs($admin);
    expect(App\Filament\Pages\Dashboard::canAccess())->toBeTrue();

    $this->actingAs($staff);
    expect(App\Filament\Pages\Dashboard::canAccess())->toBeFalse();
});
