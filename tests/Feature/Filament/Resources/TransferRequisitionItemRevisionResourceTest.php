<?php

declare(strict_types=1);

use App\Filament\Resources\TransferRequisitionItemRevisions\Pages\ListTransferRequisitionItemRevisions;
use App\Filament\Resources\TransferRequisitionItemRevisions\Pages\ViewTransferRequisitionItemRevision;
use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\TransferRequisitionItemRevision;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;

use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

beforeEach(function () {
    TransferRequisitionItemRevision::truncate();
    TransferRequisitionItem::truncate();
    TransferRequisition::truncate();
    ProductVariant::truncate();
    User::truncate();

    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('can render index page', function () {
    livewire(ListTransferRequisitionItemRevisions::class)
        ->assertOk();
});

it('can render view page', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $revision = TransferRequisitionItemRevision::factory()
        ->for($item, 'transferRequisitionItem')
        ->for($variant, 'productVariant')
        ->for($variant, 'substituteProductVariant')
        ->create();

    livewire(ViewTransferRequisitionItemRevision::class, [
        'record' => $revision->id,
    ])
        ->assertOk()
        ->assertSchemaStateSet([
            'transferRequisitionItem.transferRequisition.reference_code' => $requisition->reference_code,
            'productVariant.sku' => $variant->sku,
        ]);
});

it('has column', function (string $column) {
    livewire(ListTransferRequisitionItemRevisions::class)
        ->assertTableColumnExists($column);
})->with(['transferRequisitionItem.transferRequisition.reference_code', 'productVariant.sku', 'productVariant.name', 'side', 'status', 'proposed_unit_name', 'proposed_qty', 'proposed_base_qty', 'created_at']);

it('can delete revision', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();
    $revision = TransferRequisitionItemRevision::factory()->for($item, 'transferRequisitionItem')->for($variant, 'productVariant')->for($variant, 'substituteProductVariant')->create();

    livewire(ViewTransferRequisitionItemRevision::class, ['record' => $revision->id])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseMissing($revision);
});

it('can bulk delete revisions', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();

    $revisions = TransferRequisitionItemRevision::factory()->count(5)
        ->for($item, 'transferRequisitionItem')
        ->for($variant, 'productVariant')
        ->for($variant, 'substituteProductVariant')
        ->create();

    livewire(ListTransferRequisitionItemRevisions::class)
        ->loadTable()
        ->assertCanSeeTableRecords($revisions)
        ->selectTableRecords($revisions)
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
        ->assertNotified()
        ->assertCanNotSeeTableRecords($revisions);

    $revisions->each(fn (TransferRequisitionItemRevision $r) => assertDatabaseMissing($r));
});

it('shows status badge correctly', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();

    $revision = TransferRequisitionItemRevision::factory()->for($item, 'transferRequisitionItem')->for($variant, 'productVariant')->for($variant, 'substituteProductVariant')->create(['status' => 'pending']);
    expect($revision->status->value)->toBe('pending');

    $revision2 = TransferRequisitionItemRevision::factory()->for($item, 'transferRequisitionItem')->for($variant, 'productVariant')->for($variant, 'substituteProductVariant')->create(['status' => 'accepted']);
    expect($revision2->status->value)->toBe('accepted');
});

it('shows side badge correctly', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $variant = ProductVariant::factory()->create();

    $revision = TransferRequisitionItemRevision::factory()->for($item, 'transferRequisitionItem')->for($variant, 'productVariant')->for($variant, 'substituteProductVariant')->create(['side' => 'fulfiller']);
    expect($revision->side->value)->toBe('fulfiller');

    $revision2 = TransferRequisitionItemRevision::factory()->for($item, 'transferRequisitionItem')->for($variant, 'productVariant')->for($variant, 'substituteProductVariant')->create(['side' => 'requestor']);
    expect($revision2->side->value)->toBe('requestor');
});
