<?php

declare(strict_types=1);

use App\Filament\Resources\LossLedgers\Pages\ListLossLedgers;
use App\Filament\Resources\LossLedgers\Pages\ViewLossLedger;
use App\Models\LossLedger;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
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
    $loss = LossLedger::factory()
        ->for($requisition, 'transferRequisition')
        ->for($item, 'item')
        ->for($variant, 'productVariant')
        ->for($warehouse, 'warehouse')
        ->create();

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

it('can delete loss ledger record', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $loss = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')->create();

    livewire(ViewLossLedger::class, ['record' => $loss->id])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseMissing($loss);
});

it('can bulk delete loss ledger records', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $losses = LossLedger::factory()->count(5)
        ->for($requisition, 'transferRequisition')
        ->for($item, 'item')
        ->for($variant, 'productVariant')
        ->for($warehouse, 'warehouse')
        ->create();

    livewire(ListLossLedgers::class)
        ->loadTable()
        ->assertCanSeeTableRecords($losses)
        ->selectTableRecords($losses)
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
        ->assertNotified()
        ->assertCanNotSeeTableRecords($losses);

    $losses->each(fn (LossLedger $l) => assertDatabaseMissing($l));
});

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
