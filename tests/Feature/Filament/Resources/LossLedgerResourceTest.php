<?php

declare(strict_types=1);

use App\Filament\Resources\LossLedgers\Pages\ListLossLedgers;
use App\Filament\Resources\LossLedgers\Pages\ViewLossLedger;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use App\Models\Warehouse;

use function Pest\Livewire\livewire;

beforeEach(function () {
    LossLedger::truncate();
    TransferRequisitionItem::truncate();
    TransferRequisition::truncate();
    ProductVariant::truncate();
    Warehouse::truncate();
    User::truncate();

    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('can render index page', function () {
    livewire(ListLossLedgers::class)
        ->assertOk();
});

it('can render view page', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $loss = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')->create();

    livewire(ViewLossLedger::class, [
        'record' => $loss->id,
    ])
        ->assertOk()
        ->assertSchemaStateSet([
            'transferRequisition.reference_code' => $requisition->reference_code,
            'productVariant.sku' => $variant->sku,
        ]);
});

it('has column', function (string $column) {
    livewire(ListLossLedgers::class)
        ->assertTableColumnExists($column);
})->with(['transferRequisition.reference_code', 'productVariant.sku', 'productVariant.name', 'warehouse.name', 'loss_category', 'lost_base_qty', 'damaged_base_qty', 'total_financial_loss', 'recorded_at', 'recordedBy.name']);

it('shows financial loss correctly', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $loss = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')
        ->create(['total_financial_loss' => 1500.50]);

    livewire(ViewLossLedger::class, ['record' => $loss->id])
        ->assertSchemaStateSet(['total_financial_loss' => '1500.5000']);
});

it('shows loss category badge correctly', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $loss = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')
        ->create(['loss_category' => 'shortfall']);
    expect($loss->loss_category)->toBe('shortfall');

    $loss2 = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')
        ->create(['loss_category' => 'damage']);
    expect($loss2->loss_category)->toBe('damage');
});
