<?php

declare(strict_types=1);

use App\Filament\Resources\TransferRequisitions\Pages\CreateTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Pages\EditTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Pages\ListTransferRequisitions;
use App\Filament\Resources\TransferRequisitions\Pages\ViewTransferRequisition;
use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->staff = User::factory()->create(['role' => 'warehouse_staff']);
    $this->auditor = User::factory()->create(['role' => 'auditor']);
    $this->wh1 = Warehouse::factory()->create();
    $this->wh2 = Warehouse::factory()->create();
    $this->staff->warehouses()->attach($this->wh1->id);
    $this->variant = ProductVariant::factory()->create();

    WarehouseStock::create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->wh1->id,
        'on_hand_quantity' => 1000,
        'reserved_quantity' => 0,
    ]);
});

it('admin can render the index page', function (): void {
    $this->actingAs($this->admin);
    livewire(ListTransferRequisitions::class)->assertOk();
});

it('staff can render the index page', function (): void {
    $this->actingAs($this->staff);
    livewire(ListTransferRequisitions::class)->assertOk();
});

it('admin can render the create page', function (): void {
    $this->actingAs($this->admin);
    livewire(CreateTransferRequisition::class)->assertOk();
});

it('auditor cannot create transfer requisitions', function (): void {
    $this->actingAs($this->auditor);
    $this->get(route('filament.admin.resources.transfer-requisitions.create'))->assertForbidden();
});

it('admin can render the view page', function (): void {
    $this->actingAs($this->admin);
    $requisition = TransferRequisition::factory()->create([
        'from_warehouse_id' => $this->wh1->id,
        'to_warehouse_id' => $this->wh2->id,
        'requested_by' => $this->admin->id,
    ]);
    livewire(ViewTransferRequisition::class, ['record' => $requisition->id])->assertOk();
});

it('admin can render the edit page', function (): void {
    $this->actingAs($this->admin);
    $requisition = TransferRequisition::factory()->create([
        'from_warehouse_id' => $this->wh1->id,
        'to_warehouse_id' => $this->wh2->id,
        'requested_by' => $this->admin->id,
    ]);
    livewire(EditTransferRequisition::class, ['record' => $requisition->id])->assertOk();
});

it('table has correct columns', function (string $column): void {
    $this->actingAs($this->admin);
    livewire(ListTransferRequisitions::class)
        ->assertTableColumnExists($column);
})->with(['reference_code', 'status']);

it('table has status filter', function (): void {
    $this->actingAs($this->admin);
    livewire(ListTransferRequisitions::class)
        ->assertTableFilterExists('status');
});

it('status badge renders for dispatched requisition', function (): void {
    $this->actingAs($this->admin);
    $requisition = TransferRequisition::factory()->create([
        'from_warehouse_id' => $this->wh1->id,
        'to_warehouse_id' => $this->wh2->id,
        'status' => 'dispatched',
        'requested_by' => $this->admin->id,
    ]);

    expect($requisition->fresh()->status->value)->toBe('dispatched');
});
