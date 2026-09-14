<?php

declare(strict_types=1);

use App\Enums\TransferRequisitionStatus;
use App\Filament\Resources\TransferRequisitions\Pages\CreateTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Pages\EditTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Pages\ListTransferRequisitions;
use App\Filament\Resources\TransferRequisitions\Pages\ViewTransferRequisition;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Actions\DeleteAction;
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
})->with(['reference_code', 'fromWarehouse.name', 'toWarehouse.name', 'status', 'requested_at', 'completed_at']);

it('can sort column', function (string $column) {
    TransferRequisition::truncate();
    $req1 = TransferRequisition::factory()->create(['reference_code' => 'TRQ-C', 'requested_at' => now()->subDays(2)]);
    $req2 = TransferRequisition::factory()->create(['reference_code' => 'TRQ-A', 'requested_at' => now()->subDay()]);
    $req3 = TransferRequisition::factory()->create(['reference_code' => 'TRQ-B', 'requested_at' => now()]);

    livewire(ListTransferRequisitions::class)
        ->loadTable()
        ->sortTable($column)
        ->assertCanSeeTableRecords($column === 'reference_code' ? [$req2, $req3, $req1] : [$req1, $req2, $req3], inOrder: true)
        ->sortTable($column, 'desc')
        ->assertCanSeeTableRecords($column === 'reference_code' ? [$req1, $req3, $req2] : [$req3, $req2, $req1], inOrder: true);
})->with(['reference_code', 'requested_at']);

it('can search table', function () {
    $req1 = TransferRequisition::factory()->create(['reference_code' => 'TRQ-ABC-001']);
    $req2 = TransferRequisition::factory()->create(['reference_code' => 'TRQ-ABC-002']);
    $req3 = TransferRequisition::factory()->create(['reference_code' => 'TRQ-XYZ-003']);

    livewire(ListTransferRequisitions::class)
        ->loadTable()
        ->searchTable('TRQ-ABC')
        ->assertCanSeeTableRecords([$req1, $req2])
        ->assertCanNotSeeTableRecords([$req3]);
});

it('can filter table by status', function () {
    $draft = TransferRequisition::factory()->create(['status' => TransferRequisitionStatus::Draft]);
    $requested = TransferRequisition::factory()->create(['status' => TransferRequisitionStatus::Requested]);
    $confirmed = TransferRequisition::factory()->create(['status' => TransferRequisitionStatus::Confirmed]);

    livewire(ListTransferRequisitions::class)
        ->loadTable()
        ->filterTable('status', 'requested')
        ->assertCanSeeTableRecords([$requested])
        ->assertCanNotSeeTableRecords([$draft, $confirmed]);
});

it('can filter table by origin warehouse', function () {
    $from1 = Warehouse::factory()->create(['name' => 'Origin A']);
    $from2 = Warehouse::factory()->create(['name' => 'Origin B']);
    $req1 = TransferRequisition::factory()->create(['from_warehouse_id' => $from1->id]);
    $req2 = TransferRequisition::factory()->create(['from_warehouse_id' => $from2->id]);

    livewire(ListTransferRequisitions::class)
        ->loadTable()
        ->filterTable('from_warehouse_id', $from1->id)
        ->assertCanSeeTableRecords([$req1])
        ->assertCanNotSeeTableRecords([$req2]);
});

it('can filter table by receiving warehouse', function () {
    $to1 = Warehouse::factory()->create(['name' => 'Destination A']);
    $to2 = Warehouse::factory()->create(['name' => 'Destination B']);
    $req1 = TransferRequisition::factory()->create(['to_warehouse_id' => $to1->id]);
    $req2 = TransferRequisition::factory()->create(['to_warehouse_id' => $to2->id]);

    livewire(ListTransferRequisitions::class)
        ->loadTable()
        ->filterTable('to_warehouse_id', $to1->id)
        ->assertCanSeeTableRecords([$req1])
        ->assertCanNotSeeTableRecords([$req2]);
});

it('can render table column state', function () {
    $requisition = TransferRequisition::factory()->create(['reference_code' => 'TRQ-TEST-001']);

    livewire(ListTransferRequisitions::class)
        ->loadTable()
        ->assertTableColumnStateSet('reference_code', 'TRQ-TEST-001', record: $requisition);
});

it('can render table column formatted state', function () {
    $requisition = TransferRequisition::factory()->create(['requested_at' => now()->subDays(5)]);

    livewire(ListTransferRequisitions::class)
        ->loadTable()
        ->assertTableColumnFormattedStateSet('requested_at', $requisition->requested_at->format('M d, Y H:i'), record: $requisition);
});

it('can assert table column visibility', function () {
    livewire(ListTransferRequisitions::class)
        ->loadTable()
        ->assertTableColumnVisible('reference_code')
        ->assertTableColumnVisible('fromWarehouse.name')
        ->assertTableColumnVisible('toWarehouse.name')
        ->assertTableColumnVisible('status')
        ->assertTableColumnVisible('requested_at')
        ->assertTableColumnVisible('completed_at');
});

it('can assert table column exists', function (string $column) {
    livewire(ListTransferRequisitions::class)
        ->loadTable()
        ->assertTableColumnExists($column);
})->with(['reference_code', 'fromWarehouse.name', 'toWarehouse.name', 'status', 'requested_at', 'completed_at']);

it('renders empty state correctly', function () {
    TransferRequisition::truncate();
    Warehouse::truncate();
    User::truncate();

    livewire(ListTransferRequisitions::class)
        ->loadTable()
        ->assertCountTableRecords(0);
});

it('has view action on table row', function () {
    $requisition = TransferRequisition::factory()->create(['status' => TransferRequisitionStatus::Draft]);

    livewire(ListTransferRequisitions::class)
        ->loadTable()
        ->callAction(TestAction::make('view')->table($requisition))
        ->assertHasNoFormErrors();
});

it('can delete transfer requisition', function () {
    $requisition = TransferRequisition::factory()->create(['status' => TransferRequisitionStatus::Draft]);

    livewire(ViewTransferRequisition::class, [
        'record' => $requisition->id,
    ])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    $requisition->refresh();
    expect($requisition->deleted_at)->not->toBeNull();
});

it('renders infolist entries on view page', function () {
    $requisition = TransferRequisition::factory()->create();

    livewire(ViewTransferRequisition::class, ['record' => $requisition->id])
        ->assertSchemaComponentExists('reference_code')
        ->assertSchemaComponentExists('status')
        ->assertSchemaComponentExists('fromWarehouse.name')
        ->assertSchemaComponentExists('toWarehouse.name')
        ->assertSchemaComponentExists('requestedBy.name')
        ->assertSchemaComponentExists('items');
});
