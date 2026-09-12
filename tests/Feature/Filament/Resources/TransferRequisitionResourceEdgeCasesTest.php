<?php

declare(strict_types=1);

use App\Filament\Resources\TransferRequisitions\Pages\CreateTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Pages\EditTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Pages\ViewTransferRequisition;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Actions\DeleteAction;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

beforeEach(function () {
    TransferRequisition::truncate();
    Warehouse::truncate();
    User::truncate();
    TransferRequisitionItem::truncate();

    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

describe('TransferRequisitionResource edge cases', function () {
    it('validates unique reference_code on create', function () {
        $existing = TransferRequisition::factory()->create(['reference_code' => 'TRQ-EXISTING']);

        livewire(CreateTransferRequisition::class)
            ->fillForm([
                'reference_code' => 'TRQ-EXISTING',
                'from_warehouse_id' => Warehouse::factory()->create()->id,
                'to_warehouse_id' => Warehouse::factory()->create()->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['reference_code' => 'unique'])
            ->assertNotNotified();
    });

    it('validates unique reference_code on edit', function () {
        $existing = TransferRequisition::factory()->create(['reference_code' => 'TRQ-EXISTING']);
        $requisition = TransferRequisition::factory()->create(['reference_code' => 'DTR-OTHER']);

        livewire(EditTransferRequisition::class, ['record' => $requisition->id])
            ->fillForm(['reference_code' => 'TRQ-EXISTING'])
            ->call('save')
            ->assertHasFormErrors(['reference_code' => 'unique'])
            ->assertNotNotified();
    });

    it('allows same reference_code on edit', function () {
        $requisition = TransferRequisition::factory()->create(['reference_code' => 'DTR-SAME']);

        livewire(EditTransferRequisition::class, ['record' => $requisition->id])
            ->fillForm(['reference_code' => 'DTR-SAME'])
            ->call('save')
            ->assertNotified()
            ->assertHasNoFormErrors();

        assertDatabaseHas(TransferRequisition::class, [
            'id' => $requisition->id,
            'reference_code' => 'DTR-SAME',
        ]);
    });

    it('requires from_warehouse_id on create', function () {
        $toWarehouse = Warehouse::factory()->create();

        livewire(CreateTransferRequisition::class)
            ->fillForm([
                'to_warehouse_id' => $toWarehouse->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['from_warehouse_id' => 'required'])
            ->assertNotNotified();
    });

    it('requires to_warehouse_id on create', function () {
        $fromWarehouse = Warehouse::factory()->create();

        livewire(CreateTransferRequisition::class)
            ->fillForm([
                'from_warehouse_id' => $fromWarehouse->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['to_warehouse_id' => 'required'])
            ->assertNotNotified();
    });

    it('validates different warehouses', function () {
        $warehouse = Warehouse::factory()->create();

        livewire(CreateTransferRequisition::class)
            ->fillForm([
                'from_warehouse_id' => $warehouse->id,
                'to_warehouse_id' => $warehouse->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['to_warehouse_id' => 'different'])
            ->assertNotNotified();
    });

    it('allows optional notes', function () {
        $fromWarehouse = Warehouse::factory()->create();
        $toWarehouse = Warehouse::factory()->create();

        livewire(CreateTransferRequisition::class)
            ->fillForm([
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'notes' => null,
            ])
            ->call('create')
            ->assertNotified()
            ->assertHasNoFormErrors();

        assertDatabaseHas(TransferRequisition::class, [
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'notes' => null,
        ]);
    });

    it('defaults status to draft on create', function () {
        $fromWarehouse = Warehouse::factory()->create();
        $toWarehouse = Warehouse::factory()->create();

        livewire(CreateTransferRequisition::class)
            ->fillForm([
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
            ])
            ->call('create')
            ->assertNotified()
            ->assertHasNoFormErrors();

        $requisition = TransferRequisition::where('from_warehouse_id', $fromWarehouse->id)
            ->where('to_warehouse_id', $toWarehouse->id)
            ->first();
        expect($requisition->status)->toBe('draft');
    });

    it('allows updating status', function () {
        $requisition = TransferRequisition::factory()->create(['status' => 'draft']);

        livewire(EditTransferRequisition::class, ['record' => $requisition->id])
            ->fillForm(['status' => 'requested'])
            ->call('save')
            ->assertNotified()
            ->assertHasNoFormErrors();

        $requisition->refresh();
        expect($requisition->status)->toBe('requested');
    });

    it('can search by reference_code', function () {
        TransferRequisition::truncate();
        $req1 = TransferRequisition::factory()->create(['reference_code' => 'DTR-001']);
        TransferRequisition::factory()->create(['reference_code' => 'DTR-002']);

        $results = TransferRequisition::where('reference_code', 'like', '%DTR-001%')->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($req1->id);
    });

    it('can filter by status', function () {
        TransferRequisition::truncate();
        $draft = TransferRequisition::factory()->create(['status' => 'draft']);
        TransferRequisition::factory()->create(['status' => 'requested']);

        $results = TransferRequisition::where('status', 'draft')->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($draft->id);
    });

    it('can filter by warehouse', function () {
        TransferRequisition::truncate();
        $wh1 = Warehouse::factory()->create();
        $wh2 = Warehouse::factory()->create();
        $req1 = TransferRequisition::factory()->create(['from_warehouse_id' => $wh1->id]);
        TransferRequisition::factory()->create(['from_warehouse_id' => $wh2->id]);

        $results = TransferRequisition::where('from_warehouse_id', $wh1->id)->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($req1->id);
    });

    it('can sort by reference_code', function () {
        TransferRequisition::truncate();
        $req1 = TransferRequisition::factory()->create(['reference_code' => 'DTR-C', 'created_at' => now()->subDays(2)]);
        $req2 = TransferRequisition::factory()->create(['reference_code' => 'DTR-A', 'created_at' => now()->subDay()]);
        $req3 = TransferRequisition::factory()->create(['reference_code' => 'DTR-B', 'created_at' => now()]);

        $results = TransferRequisition::orderBy('reference_code', 'asc')->get();
        expect($results->pluck('id')->toArray())->toBe([$req2->id, $req3->id, $req1->id]);
    });

    it('can sort by created_at', function () {
        TransferRequisition::truncate();
        $req1 = TransferRequisition::factory()->create(['reference_code' => 'DTR-C', 'created_at' => now()->subDays(2)]);
        $req2 = TransferRequisition::factory()->create(['reference_code' => 'DTR-A', 'created_at' => now()->subDay()]);
        $req3 = TransferRequisition::factory()->create(['reference_code' => 'DTR-B', 'created_at' => now()]);

        $results = TransferRequisition::orderBy('created_at', 'asc')->get();
        expect($results->pluck('id')->toArray())->toBe([$req1->id, $req2->id, $req3->id]);
    });

    it('renders create page with defaults', function () {
        livewire(CreateTransferRequisition::class)
            ->assertOk();
    });

    it('renders edit page with correct data', function () {
        $requisition = TransferRequisition::factory()->create(['reference_code' => 'DTR-TEST', 'notes' => 'Test notes']);

        livewire(EditTransferRequisition::class, ['record' => $requisition->id])
            ->assertOk()
            ->assertSchemaStateSet([
                'reference_code' => 'DTR-TEST',
                'notes' => 'Test notes',
            ]);
    });

    it('renders view page with correct data', function () {
        $requisition = TransferRequisition::factory()->create(['reference_code' => 'DTR-VIEW', 'notes' => 'View notes']);

        livewire(ViewTransferRequisition::class, ['record' => $requisition->id])
            ->assertOk()
            ->assertSchemaStateSet([
                'reference_code' => 'DTR-VIEW',
                'notes' => 'View notes',
            ]);
    });

    it('creates transfer requisition via form', function () {
        $fromWarehouse = Warehouse::factory()->create();
        $toWarehouse = Warehouse::factory()->create();

        livewire(CreateTransferRequisition::class)
            ->fillForm([
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'notes' => 'Created via form',
            ])
            ->call('create')
            ->assertNotified()
            ->assertHasNoFormErrors();

        assertDatabaseHas(TransferRequisition::class, [
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'notes' => 'Created via form',
        ]);
    });

    it('updates transfer requisition via form', function () {
        $requisition = TransferRequisition::factory()->create(['notes' => 'Original']);

        livewire(EditTransferRequisition::class, ['record' => $requisition->id])
            ->fillForm(['notes' => 'Updated'])
            ->call('save')
            ->assertNotified()
            ->assertHasNoFormErrors();

        assertDatabaseHas(TransferRequisition::class, [
            'id' => $requisition->id,
            'notes' => 'Updated',
        ]);
    });

    it('deletes transfer requisition via view page action', function () {
        $requisition = TransferRequisition::factory()->create();

        livewire(ViewTransferRequisition::class, ['record' => $requisition->id])
            ->callAction(DeleteAction::class)
            ->assertNotified()
            ->assertRedirect();

        assertDatabaseMissing($requisition);
    });

    it('handles soft delete gracefully', function () {
        $requisition = TransferRequisition::factory()->create();

        livewire(ViewTransferRequisition::class, ['record' => $requisition->id])
            ->callAction(DeleteAction::class)
            ->assertNotified()
            ->assertRedirect();

        $requisition->refresh();
        expect($requisition->deleted_at)->not->toBeNull();
    });

    it('handles transfer requisition with items', function () {
        $requisition = TransferRequisition::factory()->withItems()->create();

        livewire(ViewTransferRequisition::class, ['record' => $requisition->id])
            ->assertOk();

        $items = $requisition->items;
        expect($items)->toHaveCountGreaterThan(0);
    });

    it('handles transfer requisition with no items', function () {
        $requisition = TransferRequisition::factory()->create();

        livewire(ViewTransferRequisition::class, ['record' => $requisition->id])
            ->assertOk();

        $items = $requisition->items;
        expect($items)->toBeEmpty();
    });

    it('handles transfer requisition with in-transit records', function () {
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $productVariant = App\Models\ProductVariant::factory()->create();

        App\Models\InTransit::factory()
            ->for($requisition, 'transferRequisition')
            ->for($item, 'transferRequisitionItem')
            ->for($productVariant, 'productVariant')
            ->create();

        livewire(ViewTransferRequisition::class, ['record' => $requisition->id])
            ->assertOk();

        $inTransits = $requisition->inTransits;
        expect($inTransits)->toHaveCount(1);
    });
});
