<?php

declare(strict_types=1);

use App\Filament\Resources\TransferRequisitions\Pages\CreateTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Pages\EditTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Pages\ListTransferRequisitions;
use App\Filament\Resources\TransferRequisitions\Pages\ViewTransferRequisition;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;

use function Pest\Livewire\livewire;

beforeEach(function () {
    TransferRequisition::truncate();
    Warehouse::truncate();
    User::truncate();

    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('can render index page', function () {
    livewire(ListTransferRequisitions::class)
        ->assertOk();
});

it('can render create page', function () {
    livewire(CreateTransferRequisition::class)
        ->assertOk();
});

it('can render edit page', function () {
    $requisition = TransferRequisition::factory()->create();

    livewire(EditTransferRequisition::class, [
        'record' => $requisition->id,
    ])
        ->assertOk()
        ->assertSchemaStateSet([
            'reference_code' => $requisition->reference_code,
            'from_warehouse_id' => $requisition->from_warehouse_id,
            'to_warehouse_id' => $requisition->to_warehouse_id,
        ]);
});

it('can render view page', function () {
    $requisition = TransferRequisition::factory()->create();

    livewire(ViewTransferRequisition::class, [
        'record' => $requisition->id,
    ])
        ->assertOk()
        ->assertSchemaStateSet([
            'reference_code' => $requisition->reference_code,
        ]);
});

it('has column', function (string $column) {
    livewire(ListTransferRequisitions::class)
        ->assertTableColumnExists($column);
})->with(['reference_code', 'fromWarehouse.name', 'toWarehouse.name', 'status', 'requested_at']);

it('can sort by reference_code', function () {
    TransferRequisition::truncate();
    $req1 = TransferRequisition::factory()->create(['reference_code' => 'TRQ-C', 'requested_at' => now()->subDays(2)]);
    $req2 = TransferRequisition::factory()->create(['reference_code' => 'TRQ-A', 'requested_at' => now()->subDay()]);
    $req3 = TransferRequisition::factory()->create(['reference_code' => 'TRQ-B', 'requested_at' => now()]);

    livewire(ListTransferRequisitions::class)
        ->loadTable()
        ->sortTable('reference_code')
        ->assertCanSeeTableRecords([$req2, $req3, $req1], inOrder: true)
        ->sortTable('reference_code', 'desc')
        ->assertCanSeeTableRecords([$req1, $req3, $req2], inOrder: true);
});

it('can sort by requested_at', function () {
    TransferRequisition::truncate();
    $req1 = TransferRequisition::factory()->create(['reference_code' => 'TRQ-C', 'requested_at' => now()->subDays(2)]);
    $req2 = TransferRequisition::factory()->create(['reference_code' => 'TRQ-A', 'requested_at' => now()->subDay()]);
    $req3 = TransferRequisition::factory()->create(['reference_code' => 'TRQ-B', 'requested_at' => now()]);

    livewire(ListTransferRequisitions::class)
        ->loadTable()
        ->sortTable('requested_at')
        ->assertCanSeeTableRecords([$req1, $req2, $req3], inOrder: true)
        ->sortTable('requested_at', 'desc')
        ->assertCanSeeTableRecords([$req3, $req2, $req1], inOrder: true);
});

it('can delete transfer requisition', function () {
    $requisition = TransferRequisition::factory()->create();

    livewire(ViewTransferRequisition::class, [
        'record' => $requisition->id,
    ])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    $requisition->refresh();
    expect($requisition->deleted_at)->not->toBeNull();
});

it('can bulk delete transfer requisitions', function () {
    $requisitions = TransferRequisition::factory()->count(5)->create();

    livewire(ListTransferRequisitions::class)
        ->loadTable()
        ->assertCanSeeTableRecords($requisitions)
        ->selectTableRecords($requisitions)
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
        ->assertNotified()
        ->assertCanNotSeeTableRecords($requisitions);

    $requisitions->each(fn (TransferRequisition $r) => $r->refresh());
    $requisitions->each(fn (TransferRequisition $r) => expect($r->deleted_at)->not->toBeNull());
});

it('validates unique reference_code', function () {
    $existing = TransferRequisition::factory()->create(['reference_code' => 'TRQ-UNIQUE-TEST']);

    livewire(EditTransferRequisition::class, ['record' => TransferRequisition::factory()->create(['reference_code' => 'TRQ-OTHER'])->id])
        ->fillForm(['reference_code' => 'TRQ-UNIQUE-TEST'])
        ->call('save')
        ->assertHasFormErrors(['reference_code' => 'unique'])
        ->assertNotNotified();
});
