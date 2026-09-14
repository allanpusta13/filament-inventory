<?php

declare(strict_types=1);

use App\Enums\InTransitStatus;
use App\Filament\Resources\InTransits\Pages\ListInTransits;
use App\Filament\Resources\InTransits\Pages\ViewInTransit;
use App\Models\InTransit;
use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;

use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

beforeEach(function () {
    InTransit::truncate();
    TransferRequisitionItem::truncate();
    TransferRequisition::truncate();
    ProductVariant::truncate();
    User::truncate();

    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('can render index page', function () {
    livewire(ListInTransits::class)
        ->assertOk();
});

it('can render view page', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $inTransit = InTransit::factory()
        ->for($requisition, 'transferRequisition')
        ->for($item, 'item')
        ->for($variant, 'productVariant')
        ->create();

    livewire(ViewInTransit::class, [
        'record' => $inTransit->id,
    ])
        ->assertOk()
        ->assertSchemaStateSet([
            'transferRequisition.reference_code' => $requisition->reference_code,
            'productVariant.sku' => $variant->sku,
        ]);
});

it('has column', function (string $column) {
    livewire(ListInTransits::class)
        ->assertTableColumnExists($column);
})->with(['transferRequisition.reference_code', 'productVariant.sku', 'productVariant.name', 'status', 'dispatched_base_qty', 'dispatched_at', 'created_at']);

it('can sort column', function (string $column) {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $records = InTransit::factory()->count(5)
        ->for($requisition, 'transferRequisition')
        ->for($item, 'item')
        ->for($variant, 'productVariant')
        ->create();

    livewire(ListInTransits::class)
        ->loadTable()
        ->sortTable($column)
        ->assertCanSeeTableRecords($records)
        ->sortTable($column, 'desc')
        ->assertCanSeeTableRecords($records);
})->with(['transferRequisition.reference_code', 'productVariant.sku', 'productVariant.name', 'status', 'dispatched_base_qty', 'dispatched_at']);

it('can search table', function () {
    $requisition = TransferRequisition::factory()->create(['reference_code' => 'TRQ-ABC-001']);
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create(['sku' => 'SKU-ABC-001']);
    $inTransit1 = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->create();

    $requisition2 = TransferRequisition::factory()->create(['reference_code' => 'TRQ-XYZ-002']);
    $item2 = TransferRequisitionItem::factory()->for($requisition2)->create();
    $variant2 = ProductVariant::factory()->create(['sku' => 'SKU-XYZ-002']);
    $inTransit2 = InTransit::factory()->for($requisition2, 'transferRequisition')->for($item2, 'item')->for($variant2, 'productVariant')->create();

    livewire(ListInTransits::class)
        ->loadTable()
        ->searchTable('TRQ-ABC')
        ->assertCanSeeTableRecords([$inTransit1])
        ->assertCanNotSeeTableRecords([$inTransit2]);
});

it('can filter table by status', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $inTransit = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->inTransit()->create();
    $cleared = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->received()->create();

    livewire(ListInTransits::class)
        ->loadTable()
        ->filterTable('status', InTransitStatus::InTransit->value)
        ->assertCanSeeTableRecords([$inTransit])
        ->assertCanNotSeeTableRecords([$cleared]);
});

it('can render table column state', function () {
    $requisition = TransferRequisition::factory()->create(['reference_code' => 'TRQ-TEST-001']);
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $inTransit = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->create();

    livewire(ListInTransits::class)
        ->loadTable()
        ->assertTableColumnStateSet('transferRequisition.reference_code', 'TRQ-TEST-001', record: $inTransit);
});

it('can render table column formatted state', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $inTransit = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->create(['dispatched_at' => now()->subDays(5)]);

    livewire(ListInTransits::class)
        ->loadTable()
        ->assertTableColumnFormattedStateSet('dispatched_at', $inTransit->dispatched_at->format('M d, Y H:i'), record: $inTransit);
});

it('can assert table column visibility', function () {
    livewire(ListInTransits::class)
        ->loadTable()
        ->assertTableColumnVisible('transferRequisition.reference_code')
        ->assertTableColumnVisible('productVariant.sku')
        ->assertTableColumnVisible('productVariant.name')
        ->assertTableColumnVisible('status')
        ->assertTableColumnVisible('dispatched_base_qty')
        ->assertTableColumnVisible('dispatched_at')
        ->assertTableColumnVisible('created_at');
});

it('can assert table column exists', function (string $column) {
    livewire(ListInTransits::class)
        ->loadTable()
        ->assertTableColumnExists($column);
})->with(['transferRequisition.reference_code', 'productVariant.sku', 'productVariant.name', 'status', 'dispatched_base_qty', 'dispatched_at', 'created_at']);

it('renders empty state correctly', function () {
    InTransit::truncate();
    TransferRequisitionItem::truncate();
    TransferRequisition::truncate();
    ProductVariant::truncate();
    User::truncate();

    livewire(ListInTransits::class)
        ->loadTable()
        ->assertCountTableRecords(0);
});

it('has view action on table row', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $inTransit = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->create();

    livewire(ListInTransits::class)
        ->loadTable()
        ->callAction(TestAction::make('view')->table($inTransit))
        ->assertHasNoFormErrors();
});

it('has edit action on table row', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $inTransit = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->create();

    livewire(ListInTransits::class)
        ->loadTable()
        ->callAction(TestAction::make('edit')->table($inTransit))
        ->assertHasNoFormErrors();
});

it('has delete action on table row', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $inTransit = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->create();

    livewire(ListInTransits::class)
        ->loadTable()
        ->callAction(TestAction::make('delete')->table($inTransit))
        ->assertNotified();

    assertDatabaseMissing($inTransit);
});

it('can delete in transit', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $inTransit = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->create();

    livewire(ViewInTransit::class, [
        'record' => $inTransit->id,
    ])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseMissing($inTransit);
});

it('can bulk delete in transits', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $inTransits = InTransit::factory()->count(5)
        ->for($requisition, 'transferRequisition')
        ->for($item, 'item')
        ->for($variant, 'productVariant')
        ->create();

    livewire(ListInTransits::class)
        ->loadTable()
        ->assertCanSeeTableRecords($inTransits)
        ->selectTableRecords($inTransits)
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
        ->assertNotified()
        ->assertCanNotSeeTableRecords($inTransits);

    $inTransits->each(fn (InTransit $it) => assertDatabaseMissing($it));
});

it('shows dispatched base qty correctly', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $inTransit = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->create(['dispatched_base_qty' => 100]);

    livewire(ViewInTransit::class, ['record' => $inTransit->id])
        ->assertSchemaStateSet(['dispatched_base_qty' => '100']);
});

it('shows status badge correctly', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();

    $inTransit = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->inTransit()->create();
    expect($inTransit->status->value)->toBe('in_transit');

    $inTransit2 = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->received()->create();
    expect($inTransit2->status->value)->toBe('cleared');
});
