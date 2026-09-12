<?php

declare(strict_types=1);

use App\Filament\Resources\InTransits\Pages\ListInTransits;
use App\Filament\Resources\InTransits\Pages\ViewInTransit;
use App\Models\InTransit;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;

use function Pest\Laravel\assertDatabaseHas;
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
    expect($inTransit->status->value)->toBe('in_transit');

    $inTransit2 = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->received()->create();
    expect($inTransit2->status->value)->toBe('cleared');
});
