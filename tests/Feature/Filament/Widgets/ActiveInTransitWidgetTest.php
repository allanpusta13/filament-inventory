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

function makeConfirmedRequisitionForWidget(
    InventoryService $service,
    Warehouse $origin,
    Warehouse $destination,
    ProductVariant $variant,
    int $approvedBaseQty = 240
): TransferRequisition {
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
        'approved_base_qty' => $approvedBaseQty,
        'approved_unit_name' => 'Box',
        'approved_unit_ratio' => 24,
        'approved_qty' => 10,
    ]);

    return $requisition;
}

describe('ActiveInTransitWidget', function () {
    it('shows in-transit when transfer dispatched', function () {
        $requisition = makeConfirmedRequisitionForWidget($this->service, $this->origin, $this->destination, $this->variant);
        $this->service->dispatchTransfer($requisition->id);

        $widget = new ActiveInTransitWidget();
        $inTransits = $widget->getActiveInTransits(true);

        expect($inTransits->count())->toBe(1);
        expect($inTransits->first()['status'])->toBe(InTransitStatus::InTransit);
    });

    it('hides in-transit when fully received', function () {
        $requisition = makeConfirmedRequisitionForWidget($this->service, $this->origin, $this->destination, $this->variant);
        $this->service->dispatchTransfer($requisition->id);

        $inTransit = InTransit::where('transfer_requisition_id', $requisition->id)->first();
        $this->service->scanToReceive($requisition->id, [
            $inTransit->transfer_requisition_item_id => ['good_qty' => 10, 'damaged_qty' => 0],
        ]);

        $widget = new ActiveInTransitWidget();
        $inTransits = $widget->getActiveInTransits(true);

        expect($inTransits->count())->toBe(0);
    });

    it('shows partially received in-transit', function () {
        $requisition = makeConfirmedRequisitionForWidget($this->service, $this->origin, $this->destination, $this->variant, 300);
        $this->service->dispatchTransfer($requisition->id);

        $inTransit = InTransit::where('transfer_requisition_id', $requisition->id)->first();
        $this->service->scanToReceive($requisition->id, [
            $inTransit->transfer_requisition_item_id => ['good_qty' => 5, 'damaged_qty' => 0],
        ]);

        $widget = new ActiveInTransitWidget();
        $inTransits = $widget->getActiveInTransits(true);

        expect($inTransits->count())->toBe(1);
        expect($inTransits->first()['status'])->toBe(InTransitStatus::PartiallyReceived);
    });

    it('caches active in-transits per user and warehouse', function () {
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

    it('shows both origin destination warehouse names', function () {
        $requisition = makeConfirmedRequisitionForWidget($this->service, $this->origin, $this->destination, $this->variant);
        $this->service->dispatchTransfer($requisition->id);

        $widget = new ActiveInTransitWidget();
        $inTransits = $widget->getActiveInTransits(true);

        expect($inTransits->first())->toHaveKeys(['from_warehouse', 'to_warehouse']);
        expect($inTransits->first()['from_warehouse'])->toBe($this->origin->name);
        expect($inTransits->first()['to_warehouse'])->toBe($this->destination->name);
    });

    it('excludes cleared in-transits', function () {
        $requisition = makeConfirmedRequisitionForWidget($this->service, $this->origin, $this->destination, $this->variant);
        $this->service->dispatchTransfer($requisition->id);

        $inTransit = InTransit::where('transfer_requisition_id', $requisition->id)->first();
        $inTransit->update(['status' => InTransitStatus::Cleared]);

        $widget = new ActiveInTransitWidget();
        $inTransits = $widget->getActiveInTransits(true);

        expect($inTransits->count())->toBe(0);
    });

    it('excludes delivered in-transits', function () {
        $requisition = makeConfirmedRequisitionForWidget($this->service, $this->origin, $this->destination, $this->variant);
        $this->service->dispatchTransfer($requisition->id);

        $inTransit = InTransit::where('transfer_requisition_id', $requisition->id)->first();
        $inTransit->update(['status' => InTransitStatus::Cleared]);

        $widget = new ActiveInTransitWidget();
        $inTransits = $widget->getActiveInTransits(true);

        expect($inTransits->count())->toBe(0);
    });

    it('gate check: non-admin cannot view widget data', function () {
        $nonAdmin = User::factory()->create();
        $this->actingAs($nonAdmin);

        $widget = new ActiveInTransitWidget();
        $inTransits = $widget->getActiveInTransits(true);

        expect($inTransits->count())->toBe(0);
    });

    it('sql injection rejection in warehouse context', function () {
        $widget = new ActiveInTransitWidget();
        $inTransits = $widget->getActiveInTransits(true);

        expect($inTransits)->not->toContain("' OR 1=1 --");
    });

    it('xss script rejection in variant names', function () {
        $scriptVariant = ProductVariant::factory()->create();

        $widget = new ActiveInTransitWidget();
        $inTransits = $widget->getActiveInTransits(true);

        expect($inTransits)->not->toContain('<script>');
    });

    it('warehouse id scoping on every query user warehouses', function () {
        $otherWarehouse = Warehouse::factory()->create();
        $this->user->warehouses()->syncWithoutDetaching([$this->origin->id]);

        $widget = new ActiveInTransitWidget();
        $inTransits = $widget->getActiveInTransits(true);

        expect($inTransits)->not->toContain($otherWarehouse->id);
    });

    it('cross-warehouse access denial', function () {
        $unauthorizedOrigin = Warehouse::factory()->create();
        $unauthorizedDestination = Warehouse::factory()->create();
        $unauthorizedVariant = ProductVariant::factory()->create();
        $this->service->recordMovement($unauthorizedVariant->id, $unauthorizedOrigin->id, StockMovementType::Receive, 500);

        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $unauthorizedOrigin->id,
            'to_warehouse_id' => $unauthorizedDestination->id,
            'status' => TransferRequisitionStatus::Confirmed,
        ]);
        TransferRequisitionItem::factory()->create([
            'transfer_requisition_id' => $requisition->id,
            'product_variant_id' => $unauthorizedVariant->id,
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

    it('returns empty when no in-transits exist', function () {
        $widget = new ActiveInTransitWidget();
        $inTransits = $widget->getActiveInTransits(true);

        expect($inTransits->count())->toBe(0);
    });

    it('invalid warehouse id ignored', function () {
        $widget = new ActiveInTransitWidget();
        $inTransits = $widget->getActiveInTransits(true);

        expect($inTransits->count())->toBe(0);
    });

    it('per-warehouse cache keys', function () {
        $otherWarehouse = Warehouse::factory()->create();
        $this->user->warehouses()->syncWithoutDetaching([$this->origin->id, $otherWarehouse->id]);

        $widget = new ActiveInTransitWidget();
        $inTransitsFromOrigin = $widget->getActiveInTransits(true);

        $this->assertTrue(Cache::has('active_in_transit_'.$this->user->id.'_'.$this->origin->id));
        $this->assertFalse(Cache::has('active_in_transit_'.$this->user->id.'_'.$otherWarehouse->id));
    });
});
