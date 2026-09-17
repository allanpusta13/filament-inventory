<?php

declare(strict_types=1);

use App\Filament\Resources\InTransits\Pages\ViewInTransit;
use App\Models\InTransit;
use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use App\Models\Warehouse;

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

describe('InTransitResource edge cases', function () {
    it('validates status enum', function () {
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create();

        $inTransit = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->create(['status' => 'in_transit']);
        expect($inTransit->status->value)->toBe('in_transit');

        $inTransit2 = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->create(['status' => 'cleared']);
        expect($inTransit2->status->value)->toBe('cleared');
    });

    it('can search by reference_code', function () {
        InTransit::truncate();
        $req1 = TransferRequisition::factory()->create(['reference_code' => 'DTR-001']);
        $req2 = TransferRequisition::factory()->create(['reference_code' => 'DTR-002']);
        $item1 = TransferRequisitionItem::factory()->for($req1)->create();
        $item2 = TransferRequisitionItem::factory()->for($req2)->create();
        $variant = ProductVariant::factory()->create();

        $inTransit1 = InTransit::factory()->for($req1, 'transferRequisition')->for($item1, 'item')->for($variant, 'productVariant')->create();
        InTransit::factory()->for($req2, 'transferRequisition')->for($item2, 'item')->for($variant, 'productVariant')->create();

        $results = InTransit::whereHas('transferRequisition', fn ($q) => $q->where('reference_code', 'like', '%DTR-001%'))->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($inTransit1->id);
    });

    it('can filter by status', function () {
        InTransit::truncate();
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create();

        $inTransit = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->inTransit()->create();
        InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->received()->create();

        $results = InTransit::where('status', 'in_transit')->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($inTransit->id);
    });

    it('can filter by product variant', function () {
        InTransit::truncate();
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant1 = ProductVariant::factory()->create(['sku' => 'VAR-001']);
        $variant2 = ProductVariant::factory()->create(['sku' => 'VAR-002']);

        $inTransit1 = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant1, 'productVariant')->create();
        InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant2, 'productVariant')->create();

        $results = InTransit::where('product_variant_id', $variant1->id)->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($inTransit1->id);
    });

    it('can sort by dispatched_base_qty', function () {
        InTransit::truncate();
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create();

        $it1 = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->create(['dispatched_base_qty' => 300]);
        $it2 = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->create(['dispatched_base_qty' => 100]);
        $it3 = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->create(['dispatched_base_qty' => 200]);

        $results = InTransit::orderBy('dispatched_base_qty', 'asc')->get();
        expect($results->pluck('id')->toArray())->toBe([$it2->id, $it3->id, $it1->id]);
    });

    it('can sort by dispatched_at', function () {
        InTransit::truncate();
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create();

        $it1 = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->create(['dispatched_at' => now()->subDays(2)]);
        $it2 = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->create(['dispatched_at' => now()->subDay()]);
        $it3 = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->create(['dispatched_at' => now()]);

        $results = InTransit::orderBy('dispatched_at', 'asc')->get();
        expect($results->pluck('id')->toArray())->toBe([$it1->id, $it2->id, $it3->id]);
    });

    it('renders view page with correct data', function () {
        $requisition = TransferRequisition::factory()->create(['reference_code' => 'DTR-VIEW']);
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create(['sku' => 'SKU-VIEW', 'name' => 'View Variant']);
        $inTransit = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->create(['dispatched_base_qty' => 50]);

        livewire(ViewInTransit::class, ['record' => $inTransit->id])
            ->assertOk()
            ->assertSchemaStateSet([
                'transferRequisition.reference_code' => 'DTR-VIEW',
                'productVariant.sku' => 'SKU-VIEW',
                'productVariant.name' => 'View Variant',
                'dispatched_base_qty' => '50',
            ]);
    });

    it('handles in transit with partially received status', function () {
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create();

        $inTransit = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->partiallyReceived()->create();
        expect($inTransit->status->value)->toBe('partially_received');

        livewire(ViewInTransit::class, ['record' => $inTransit->id])
            ->assertOk();
    });

    it('handles in transit with cleared status', function () {
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create();

        $inTransit = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->cleared()->create();
        expect($inTransit->status->value)->toBe('cleared');

        livewire(ViewInTransit::class, ['record' => $inTransit->id])
            ->assertOk();
    });

    it('shows origin and destination warehouses', function () {
        $fromWarehouse = Warehouse::factory()->create(['name' => 'Origin WH']);
        $toWarehouse = Warehouse::factory()->create(['name' => 'Dest WH']);
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
        ]);
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create();
        $inTransit = InTransit::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->create();

        livewire(ViewInTransit::class, ['record' => $inTransit->id])
            ->assertOk()
            ->assertSchemaStateSet([
                'transferRequisition.fromWarehouse.name' => 'Origin WH',
                'transferRequisition.toWarehouse.name' => 'Dest WH',
            ]);
    });
});
