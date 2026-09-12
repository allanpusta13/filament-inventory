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
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreAction;
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

it('can sort column', function (string $column) {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $records = LossLedger::factory()->count(5)
        ->for($requisition, 'transferRequisition')
        ->for($item, 'item')
        ->for($variant, 'productVariant')
        ->for($warehouse, 'warehouse')
        ->create();

    livewire(ListLossLedgers::class)
        ->loadTable()
        ->sortTable($column)
        ->assertCanSeeTableRecords($records->sortBy($column), inOrder: true)
        ->sortTable($column, 'desc')
        ->assertCanSeeTableRecords($records->sortByDesc($column), inOrder: true);
})->with(['transferRequisition.reference_code', 'productVariant.sku', 'loss_category', 'lost_base_qty', 'total_financial_loss', 'recorded_at']);

it('can search by reference_code', function () {
    LossLedger::truncate();
    $req1 = TransferRequisition::factory()->create(['reference_code' => 'DTR-SEARCH-001']);
    $req2 = TransferRequisition::factory()->create(['reference_code' => 'DTR-SEARCH-002']);
    $item1 = TransferRequisitionItem::factory()->for($req1)->create();
    $item2 = TransferRequisitionItem::factory()->for($req2)->create();
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $loss1 = LossLedger::factory()->for($req1, 'transferRequisition')->for($item1, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')->create();
    LossLedger::factory()->for($req2, 'transferRequisition')->for($item2, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')->create();

    livewire(ListLossLedgers::class)
        ->searchTable('DTR-SEARCH-001')
        ->assertCanSeeTableRecords($loss1)
        ->assertCanNotSeeTableRecords(LossLedger::whereHas('transferRequisition', fn ($q) => $q->where('reference_code', 'DTR-SEARCH-002'))->first());
});

it('can filter by loss_category', function () {
    LossLedger::truncate();
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $shortfall = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')->create(['loss_category' => 'shortfall']);
    LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')->create(['loss_category' => 'damage']);

    livewire(ListLossLedgers::class)
        ->filterTable('loss_category', 'shortfall')
        ->assertCanSeeTableRecords($shortfall)
        ->assertCanNotSeeTableRecords(LossLedger::where('loss_category', 'damage')->first());
});

it('can filter by warehouse', function () {
    LossLedger::truncate();
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $warehouse1 = Warehouse::factory()->create();
    $warehouse2 = Warehouse::factory()->create();

    $loss1 = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse1, 'warehouse')->create();
    LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse2, 'warehouse')->create();

    livewire(ListLossLedgers::class)
        ->filterTable('warehouse_id', $warehouse1->id)
        ->assertCanSeeTableRecords($loss1)
        ->assertCanNotSeeTableRecords(LossLedger::where('warehouse_id', $warehouse2->id)->first());
});

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

it('can restore loss ledger record', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $loss = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')->create();

    livewire(ViewLossLedger::class, ['record' => $loss->id])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    livewire(ViewLossLedger::class, ['record' => $loss->id])
        ->callAction(RestoreAction::class)
        ->assertNotified();

    $loss->refresh();
    expect($loss->deleted_at)->toBeNull();
    assertDatabaseHas(LossLedger::class, ['id' => $loss->id]);
});

it('shows financial loss correctly', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $loss = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')
        ->create(['total_financial_loss' => 1500.50]);

    livewire(ViewLossLedger::class, ['record' => $loss->id])
        ->assertSchemaStateSet(['total_financial_loss' => '1,500.50']);
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
