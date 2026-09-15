<?php

declare(strict_types=1);

use App\Enums\InTransitStatus;
use App\Enums\StockMovementType;
use App\Enums\TransferRequisitionStatus;
use App\Filament\Widgets\ActiveInTransitWidget;
use App\Models\InTransit;
use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Cache;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->service = new InventoryService();
    $this->user = User::factory()->admin()->create();
    $this->actingAs($this->user);

    Cache::flush();

    $this->origin = Warehouse::factory()->create();
    $this->destination = Warehouse::factory()->create();
    $this->variant = ProductVariant::factory()->create();

    $this->user->warehouses()->syncWithoutDetaching([$this->origin->id, $this->destination->id]);

    $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 1000);
});

function makeConfirmedRequisitionForWidget(InventoryService $service, Warehouse $origin, Warehouse $destination, ProductVariant $variant, int $approvedBaseQty = 240): TransferRequisition
{
    $requisition = TransferRequisition::factory()->create([
        'from_warehouse_id' => $origin->id,
        'to_warehouse_id' => $destination->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);

    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $requisition->id,
        'product_variant_id' => $variant->id,
        'requested_unit_name' => 'Box',
        'requested_unit_ratio' => 24,
        'requested_qty' => 10,
        'requested_base_qty' => 240,
        'approved_unit_name' => 'Box',
        'approved_unit_ratio' => 24,
        'approved_qty' => $approvedBaseQty / 24,
        'approved_base_qty' => $approvedBaseQty,
    ]);

    return $requisition->fresh('items');
}

describe('ActiveInTransitWidget', function () {
    it('returns active in-transit items for user warehouses', function () {
        $requisition = makeConfirmedRequisitionForWidget($this->service, $this->origin, $this->destination, $this->variant);
        $this->service->dispatchTransfer($requisition->id);

        $widget = new ActiveInTransitWidget();
        $inTransits = $widget->getActiveInTransits(true);

        expect($inTransits->count())->toBe(1);
        expect($inTransits->first()['requisition_ref'])->toBe($requisition->reference_code);
        expect($inTransits->first()['status'])->toBe(InTransitStatus::InTransit);
    });

    it('excludes cleared in-transit items', function () {
        $requisition = makeConfirmedRequisitionForWidget($this->service, $this->origin, $this->destination, $this->variant);
        $this->service->dispatchTransfer($requisition->id);
        $item = $requisition->fresh('items')->items->first();

        $this->service->scanToReceive($requisition->id, [
            $item->id => ['good_qty' => 10, 'damaged_qty' => 0],
        ]);

        $widget = new ActiveInTransitWidget();
        $inTransits = $widget->getActiveInTransits(true);

        expect($inTransits->count())->toBe(0);
    });

    it('includes partially received in-transit items', function () {
        $requisition = makeConfirmedRequisitionForWidget($this->service, $this->origin, $this->destination, $this->variant);
        $this->service->dispatchTransfer($requisition->id);
        $item = $requisition->fresh('items')->items->first();

        $this->service->scanToReceive($requisition->id, [
            $item->id => ['good_qty' => 5, 'damaged_qty' => 0],
        ]);

        $widget = new ActiveInTransitWidget();
        $inTransits = $widget->getActiveInTransits(true);

        expect($inTransits->count())->toBe(1);
        expect($inTransits->first()['status'])->toBe(InTransitStatus::PartiallyReceived);
    });

    it('caches results for 300 seconds', function () {
        $requisition = makeConfirmedRequisitionForWidget($this->service, $this->origin, $this->destination, $this->variant);
        $this->service->dispatchTransfer($requisition->id);

        $widget = new ActiveInTransitWidget();
        $first = $widget->getActiveInTransits();
        $second = $widget->getActiveInTransits();

        expect($second)->toBe($first)
            ->and(Cache::has('active_in_transit_'.$this->user->id.'_'.$this->origin->id))->toBeTrue();
    });

    it('excludes in-transits from warehouses user cannot access', function () {
        $otherOrigin = Warehouse::factory()->create();
        $otherDestination = Warehouse::factory()->create();
        $otherVariant = ProductVariant::factory()->create();
        $this->service->recordMovement($otherVariant->id, $otherOrigin->id, StockMovementType::Receive, 500);

        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $otherOrigin->id,
            'to_warehouse_id' => $otherDestination->id,
            'status' => TransferRequisitionStatus::Confirmed,
        ]);
        TransferRequisitionItem::factory()->create([
            'transfer_requisition_id' => $requisition->id,
            'product_variant_id' => $otherVariant->id,
            'requested_unit_name' => 'Box',
            'requested_unit_ratio' => 24,
            'requested_qty' => 5,
            'requested_base_qty' => 120,
            'approved_base_qty' => 100,
            'approved_unit_name' => 'Box',
            'approved_unit_ratio' => 24,
            'approved_qty' => 5,
        ]);
        $this->service->dispatchTransfer($requisition->fresh('items')->id);

        $widget = new ActiveInTransitWidget();
        $inTransits = $widget->getActiveInTransits(true);

        expect($inTransits->count())->toBe(0);
    });

    it('shows both origin and destination warehouse names', function () {
        $requisition = makeConfirmedRequisitionForWidget($this->service, $this->origin, $this->destination, $this->variant);
        $this->service->dispatchTransfer($requisition->id);

        $widget = new ActiveInTransitWidget();
        $inTransits = $widget->getActiveInTransits(true);

        expect($inTransits->first()['from_warehouse'])->toBe($this->origin->name);
        expect($inTransits->first()['to_warehouse'])->toBe($this->destination->name);
    });

    it('orders by dispatched_at descending', function () {
        $requisition1 = makeConfirmedRequisitionForWidget($this->service, $this->origin, $this->destination, $this->variant);
        $this->service->dispatchTransfer($requisition1->id);

        sleep(1);

        $variant2 = ProductVariant::factory()->create();
        $this->service->recordMovement($variant2->id, $this->origin->id, StockMovementType::Receive, 500);
        $requisition2 = makeConfirmedRequisitionForWidget($this->service, $this->origin, $this->destination, $variant2);
        $this->service->dispatchTransfer($requisition2->id);

        $widget = new ActiveInTransitWidget();
        $inTransits = $widget->getActiveInTransits(true);

        expect($inTransits->first()['requisition_ref'])->toBe($requisition2->reference_code);
    });

    it('handles empty in-transits gracefully', function () {
        $widget = new ActiveInTransitWidget();
        $inTransits = $widget->getActiveInTransits(true);

        expect($inTransits->count())->toBe(0);
    });

    it('includes substitute variant when dispatched', function () {
        $substitute = ProductVariant::factory()->create();
        $this->service->recordMovement($substitute->id, $this->origin->id, StockMovementType::Receive, 500);

        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->origin->id,
            'to_warehouse_id' => $this->destination->id,
            'status' => TransferRequisitionStatus::Confirmed,
        ]);
        TransferRequisitionItem::factory()->create([
            'transfer_requisition_id' => $requisition->id,
            'product_variant_id' => $this->variant->id,
            'substitute_product_variant_id' => $substitute->id,
            'requested_unit_name' => 'Piece',
            'requested_unit_ratio' => 1,
            'requested_qty' => 100,
            'requested_base_qty' => 100,
            'approved_base_qty' => 100,
            'approved_unit_name' => 'Piece',
            'approved_unit_ratio' => 1,
            'approved_qty' => 100,
        ]);
        $this->service->dispatchTransfer($requisition->fresh('items')->id);

        $widget = new ActiveInTransitWidget();
        $inTransits = $widget->getActiveInTransits(true);

        expect($inTransits->first()['variant_sku'])->toBe($substitute->sku);
    });
});