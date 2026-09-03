<?php

declare(strict_types=1);

use App\Filament\Resources\InTransitResource;
use App\Filament\Resources\InTransitResource\Pages\ListInTransits;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->staff = User::factory()->create(['role' => 'warehouse_staff']);
    $this->auditor = User::factory()->create(['role' => 'auditor']);
});

it('admin can render the index page', function (): void {
    $this->actingAs($this->admin);

    livewire(ListInTransits::class)
        ->assertOk();
});

it('warehouse staff can render the index page', function (): void {
    $this->actingAs($this->staff);

    livewire(ListInTransits::class)
        ->assertOk();
});

it('auditor can render the index page', function (): void {
    $this->actingAs($this->auditor);

    livewire(ListInTransits::class)
        ->assertOk();
});

it('has column with requisition reference_code', function (): void {
    $this->actingAs($this->admin);

    livewire(ListInTransits::class)
        ->assertTableColumnExists('requisition.reference_code');
});

it('has column with from warehouse name', function (): void {
    $this->actingAs($this->admin);

    livewire(ListInTransits::class)
        ->assertTableColumnExists('requisition.fromWarehouse.name');
});

it('has column with to warehouse name', function (): void {
    $this->actingAs($this->admin);

    livewire(ListInTransits::class)
        ->assertTableColumnExists('requisition.toWarehouse.name');
});

it('has column with variant sku', function (): void {
    $this->actingAs($this->admin);

    livewire(ListInTransits::class)
        ->assertTableColumnExists('variant.sku');
});

it('has column with dispatched_base_qty', function (): void {
    $this->actingAs($this->admin);

    livewire(ListInTransits::class)
        ->assertTableColumnExists('dispatched_base_qty');
});

it('has column with dispatched_at', function (): void {
    $this->actingAs($this->admin);

    livewire(ListInTransits::class)
        ->assertTableColumnExists('dispatched_at');
});

it('in transit resource is read-only by configuration', function (): void {
    $this->actingAs($this->admin);

    $reflection = new ReflectionClass(InTransitResource::class);
    $prop = $reflection->getProperty('canCreate');
    $prop->setAccessible(true);

    expect($prop->getValue())->toBeFalse();
});
