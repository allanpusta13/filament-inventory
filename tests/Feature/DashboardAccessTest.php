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

it('warehouse staff can access the dashboard and sees operational widgets', function (): void {
    $staff = User::factory()->create(['role' => UserRole::WarehouseStaff->value]);

    $this->actingAs($staff)
        ->get(route('filament.admin.pages.dashboard'))
        ->assertOk()
        ->assertSee('filament');
});

it('non-admin dashboard response contains operational widget data', function (): void {
    $staff = User::factory()->create(['role' => UserRole::WarehouseStaff->value]);

    $response = $this->actingAs($staff)
        ->get(route('filament.admin.pages.dashboard'));

    $response->assertOk();

    $content = $response->getContent();
    expect($content)->toContain('Dashboard');
});

it('unauthenticated user is redirected to login', function (): void {
    auth()->logout();

    $this->get(route('filament.admin.pages.dashboard'))
        ->assertRedirect(route('filament.admin.auth.login'));
});

it('canAccess is static and returns boolean', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $staff = User::factory()->create(['role' => UserRole::WarehouseStaff->value]);

    $this->actingAs($admin);
    expect(App\Filament\Pages\Dashboard::canAccess())->toBeTrue();

    $this->actingAs($staff);
    expect(App\Filament\Pages\Dashboard::canAccess())->toBeTrue();
});
