<?php

declare(strict_types=1);

use App\Filament\Resources\LossLedgerResource;
use App\Filament\Resources\LossLedgerResource\Pages\ListLossLedgers;
use App\Models\LossLedger;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->staff = User::factory()->create(['role' => 'warehouse_staff']);
    $this->auditor = User::factory()->create(['role' => 'auditor']);
});

it('admin can render the index page', function (): void {
    $this->actingAs($this->admin);

    livewire(ListLossLedgers::class)
        ->assertOk();
});

it('warehouse staff can render the index page (data is warehouse-scoped)', function (): void {
    $this->actingAs($this->staff);

    livewire(ListLossLedgers::class)
        ->assertOk();
});

it('has column with reference_code', function (): void {
    $this->actingAs($this->admin);

    livewire(ListLossLedgers::class)
        ->assertTableColumnExists('requisition.reference_code');
});

it('has column with variant sku', function (): void {
    $this->actingAs($this->admin);

    livewire(ListLossLedgers::class)
        ->assertTableColumnExists('variant.sku');
});

it('has column with warehouse name', function (): void {
    $this->actingAs($this->admin);

    livewire(ListLossLedgers::class)
        ->assertTableColumnExists('warehouse.name');
});

it('has column with lost_base_qty', function (): void {
    $this->actingAs($this->admin);

    livewire(ListLossLedgers::class)
        ->assertTableColumnExists('lost_base_qty');
});

it('has column with damaged_base_qty', function (): void {
    $this->actingAs($this->admin);

    livewire(ListLossLedgers::class)
        ->assertTableColumnExists('damaged_base_qty');
});

it('has column with total_financial_loss', function (): void {
    $this->actingAs($this->admin);

    livewire(ListLossLedgers::class)
        ->assertTableColumnExists('total_financial_loss');
});

it('has column with loss_category', function (): void {
    $this->actingAs($this->admin);

    livewire(ListLossLedgers::class)
        ->assertTableColumnExists('loss_category');
});

it('has column with recorded_at', function (): void {
    $this->actingAs($this->admin);

    livewire(ListLossLedgers::class)
        ->assertTableColumnExists('recorded_at');
});

it('loss ledger resource is read-only', function (): void {
    expect(LossLedgerResource::canCreate())->toBeFalse();
});

it('loss ledger entries are visible to admin', function (): void {
    $this->actingAs($this->admin);

    $lossLedger = LossLedger::factory()->create();

    livewire(ListLossLedgers::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$lossLedger]);
});

it('loss ledger entries are visible to auditor', function (): void {
    $this->actingAs($this->auditor);

    $lossLedger = LossLedger::factory()->create();

    livewire(ListLossLedgers::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$lossLedger]);
});

it('loss ledger entries scoped to warehouse for staff', function (): void {
    $warehouse = App\Models\Warehouse::factory()->create();
    $this->staff->warehouses()->attach($warehouse->id);
    $this->actingAs($this->staff);

    $lossLedger = LossLedger::factory()->create(['warehouse_id' => $warehouse->id]);

    livewire(ListLossLedgers::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$lossLedger]);
});
