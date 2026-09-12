<?php

declare(strict_types=1);

use App\Filament\Resources\TransferRequisitions\Pages\CreateTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Pages\EditTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Pages\ListTransferRequisitions;
use App\Filament\Resources\TransferRequisitions\Pages\ViewTransferRequisition;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;

use function Pest\Laravel\assertDatabaseHas;
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
    it('allows same reference_code on edit', function () {
        $requisition = TransferRequisition::factory()->create(['reference_code' => 'TRQ-SAME']);

        livewire(EditTransferRequisition::class, ['record' => $requisition->id])
            ->fillForm(['reference_code' => 'TRQ-SAME'])
            ->call('save')
            ->assertNotified()
            ->assertHasNoFormErrors();

        assertDatabaseHas(TransferRequisition::class, [
            'id' => $requisition->id,
            'reference_code' => 'TRQ-SAME',
        ]);
    });

    it('allows optional notes', function () {
        $fromWarehouse = Warehouse::factory()->create();
        $toWarehouse = Warehouse::factory()->create();

        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'notes' => null,
        ]);

        expect($requisition->notes)->toBeNull();
    });

    it('defaults status to draft on create', function () {
        $requisition = TransferRequisition::factory()->create([
            'status' => 'draft',
        ]);

        expect($requisition->status->value)->toBe('draft');
    });

    it('can search by reference_code', function () {
        TransferRequisition::truncate();
        $req1 = TransferRequisition::factory()->create(['reference_code' => 'TRQ-001']);
        TransferRequisition::factory()->create(['reference_code' => 'TRQ-002']);

        $results = TransferRequisition::where('reference_code', 'like', '%TRQ-001%')->get();
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
        $req1 = TransferRequisition::factory()->create(['reference_code' => 'TRQ-C', 'created_at' => now()->subDays(2)]);
        $req2 = TransferRequisition::factory()->create(['reference_code' => 'TRQ-A', 'created_at' => now()->subDay()]);
        $req3 = TransferRequisition::factory()->create(['reference_code' => 'TRQ-B', 'created_at' => now()]);

        $results = TransferRequisition::orderBy('reference_code', 'asc')->get();
        expect($results->pluck('id')->toArray())->toBe([$req2->id, $req3->id, $req1->id]);
    });

    it('can sort by created_at', function () {
        TransferRequisition::truncate();
        $req1 = TransferRequisition::factory()->create(['reference_code' => 'TRQ-C', 'created_at' => now()->subDays(2)]);
        $req2 = TransferRequisition::factory()->create(['reference_code' => 'TRQ-A', 'created_at' => now()->subDay()]);
        $req3 = TransferRequisition::factory()->create(['reference_code' => 'TRQ-B', 'created_at' => now()]);

        $results = TransferRequisition::orderBy('created_at', 'asc')->get();
        expect($results->pluck('id')->toArray())->toBe([$req1->id, $req2->id, $req3->id]);
    });

    it('renders create page with defaults', function () {
        livewire(CreateTransferRequisition::class)
            ->assertOk();
    });

    it('renders edit page with correct data', function () {
        $requisition = TransferRequisition::factory()->create(['reference_code' => 'TRQ-TEST', 'notes' => 'Test notes']);

        livewire(EditTransferRequisition::class, ['record' => $requisition->id])
            ->assertOk()
            ->assertSchemaStateSet([
                'reference_code' => 'TRQ-TEST',
                'notes' => 'Test notes',
            ]);
    });

    it('renders view page with correct data', function () {
        $requisition = TransferRequisition::factory()->create(['reference_code' => 'TRQ-VIEW', 'notes' => 'View notes']);

        livewire(ViewTransferRequisition::class, ['record' => $requisition->id])
            ->assertOk()
            ->assertSchemaStateSet([
                'reference_code' => 'TRQ-VIEW',
            ]);
    });

    it('deletes transfer requisition via view page action', function () {
        $requisition = TransferRequisition::factory()->create();

        livewire(ViewTransferRequisition::class, ['record' => $requisition->id])
            ->callAction(DeleteAction::class)
            ->assertNotified()
            ->assertRedirect();

        $requisition->refresh();
        expect($requisition->deleted_at)->not->toBeNull();
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
        expect($items->count())->toBeGreaterThan(0);
    });

    it('handles transfer requisition with no items', function () {
        $requisition = TransferRequisition::factory()->create();

        livewire(ViewTransferRequisition::class, ['record' => $requisition->id])
            ->assertOk();

        $items = $requisition->items;
        expect($items->count())->toBe(0);
    });

    it('handles transfer requisition with in-transit records', function () {
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $productVariant = App\Models\ProductVariant::factory()->create();

        App\Models\InTransit::factory()
            ->for($requisition, 'transferRequisition')
            ->for($item, 'item')
            ->for($productVariant, 'productVariant')
            ->create();

        livewire(ViewTransferRequisition::class, ['record' => $requisition->id])
            ->assertOk();

        $inTransits = $requisition->inTransits;
        expect($inTransits->count())->toBe(1);
    });
});