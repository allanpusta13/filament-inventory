<?php

declare(strict_types=1);

use App\Filament\Resources\InTransits\Pages\ListInTransits;
use App\Filament\Resources\InTransits\Pages\ViewInTransit;
use App\Models\InTransit;
use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use App\Models\Warehouse;
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
    Warehouse::truncate();
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

it('can sort by reference_code', function () {
    InTransit::truncate();
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
        ->sortTable('transferRequisition.reference_code')
        ->assertCanSeeTableRecords($records->sortBy('transferRequisition.reference_code'), inOrder: true)
        ->sortTable('transferRequisition.reference_code', 'desc')
        ->assertCanSeeTableRecords($records->sortByDesc('transferRequisition.reference_code'), inOrder: true);
});

it('can sort by dispatched_base_qty', function () {
    InTransit::truncate();
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
        ->sortTable('dispatched_base_qty')
        ->assertCanSeeTableRecords($records->sortBy('dispatched_base_qty'), inOrder: true)
        ->sortTable('dispatched_base_qty', 'desc')
        ->assertCanSeeTableRecords($records->sortByDesc('dispatched_base_qty'), inOrder: true);
});

it('can search by reference_code', function () {
    InTransit::truncate();
    $req1 = TransferRequisition::factory()->create(['reference_code' => 'DTR-SEARCH-001']);
    $req2 = TransferRequisition::factory()->create(['reference_code' => 'DTR-SEARCH-002']);
    $item1 = TransferRequisitionItem::factory()->for($req1)->create();
    $item2 = TransferRequisitionItem::factory()->for($req2)->create();
    $variant = ProductVariant::factory()->create();

    $inTransit1 = InTransit::factory()->for($req1, 'transferRequisition')->for($item1, 'item')->for($variant, 'productVariant')->create();
    InTransit::factory()->for($req2, 'transferRequisition')->for($item2, 'item')->for($variant, 'productVariant')->create();

    livewire(ListInTransits::class)
        ->searchTable('DTR-SEARCH-001')
        ->assertCanSeeTableRecords([$inTransit1])
        ->assertCanNotSeeTableRecords([InTransit::whereHas('transferRequisition', fn ($q) => $q->where('reference_code', 'DTR-SEARCH-002'))->first()]);
});

it('can filter by status', function () {
    InTransit::truncate();
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();

    $inTransit = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->inTransit()->create();
    InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->received()->create();

    livewire(ListInTransits::class)
        ->filterTable('status', 'in_transit')
        ->assertCanSeeTableRecords([$inTransit])
        ->assertCanNotSeeTableRecords([InTransit::where('status', 'cleared')->first()]);
});

it('can delete in transit record', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $inTransit = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->create();

    livewire(ViewInTransit::class, ['record' => $inTransit->id])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseMissing($inTransit);
});

it('can bulk delete in transit records', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();

    $inTransits = InTransit::factory()->count(5)->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->create();

    livewire(ListInTransits::class)
        ->loadTable()
        ->assertCanSeeTableRecords($inTransits)
        ->selectTableRecords($inTransits)
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
        ->assertNotified()
        ->assertCanNotSeeTableRecords($inTransits);

    $inTransits->each(fn (InTransit $it) => assertDatabaseMissing($it));
});

it('shows dispatched quantity', function () {
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
    expect($inTransit->status)->toBe('in_transit');

    $inTransit2 = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->received()->create();
    expect($inTransit2->status)->toBe('cleared');
});
