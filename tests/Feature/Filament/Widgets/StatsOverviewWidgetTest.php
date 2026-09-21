<?php

declare(strict_types=1);

use App\Enums\InTransitStatus;
use App\Enums\StockMovementType;
use App\Enums\TransferRequisitionStatus;
use App\Filament\Widgets\StatsOverview;
use App\Models\InTransit;
use App\Models\LossLedger;
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
    $this->variant = ProductVariant::factory()->withPrice()->create(['reorder_point' => 10]);
    $this->variant2 = ProductVariant::factory()->withPrice()->create(['reorder_point' => 5]);

    $this->user->warehouses()->syncWithoutDetaching([$this->origin->id, $this->destination->id]);
});

function makeConfirmedRequisitionForStats(
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
        'approved_unit_name' => 'Box',
        'approved_unit_ratio' => 24,
        'approved_qty' => 10,
        'approved_base_qty' => 240,
    ]);

    // Seed stock at origin warehouse to allow dispatch
    $service->recordMovement($variant->id, $origin->id, StockMovementType::Receive, $approvedBaseQty);

    return $requisition;
}

describe('StatsOverviewWidget', function () {
    describe('Total On-Hand Base Stock', function () {
        it('returns sum of on-hand stock across user warehouses', function () {
            $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 500);
            $this->service->recordMovement($this->variant2->id, $this->destination->id, StockMovementType::Receive, 300);

            $widget = new StatsOverview();
            $stats = $widget->getStats();

            $onHandStat = collect($stats)->firstWhere(fn ($stat) => $stat->getLabel() === 'Total On-Hand Base Stock');
            expect($onHandStat->getValue())->toBe('800');
        });

        it('excludes stock from warehouses user cannot access', function () {
            $otherWarehouse = Warehouse::factory()->create();
            $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 500);
            $this->service->recordMovement($this->variant->id, $otherWarehouse->id, StockMovementType::Receive, 1000);

            $widget = new StatsOverview();
            $stats = $widget->getStats();

            $onHandStat = collect($stats)->firstWhere(fn ($stat) => $stat->getLabel() === 'Total On-Hand Base Stock');
            expect($onHandStat->getValue())->toBe('500');
        });

        it('returns 0 when no stock movements exist', function () {
            $widget = new StatsOverview();
            $stats = $widget->getStats();

            $onHandStat = collect($stats)->firstWhere(fn ($stat) => $stat->getLabel() === 'Total On-Hand Base Stock');
            expect($onHandStat->getValue())->toBe('0');
        });

        it('caches result for 300 seconds', function () {
            $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 500);

            $widget = new StatsOverview();
            $firstStats = $widget->getStats();
            $secondStats = $widget->getStats();

            $cacheKey = 'stats_overview_'.$this->user->id.'_'.$this->origin->id;
            expect(Cache::has($cacheKey))->toBeTrue();
        });
    });

    describe('Pending Requisitions', function () {
        it('counts requisitions in pre-dispatch states', function () {
            TransferRequisition::factory()->create(['from_warehouse_id' => $this->origin->id, 'to_warehouse_id' => $this->destination->id, 'status' => TransferRequisitionStatus::Draft]);
            TransferRequisition::factory()->create(['from_warehouse_id' => $this->origin->id, 'to_warehouse_id' => $this->destination->id, 'status' => TransferRequisitionStatus::Requested]);
            TransferRequisition::factory()->create(['from_warehouse_id' => $this->origin->id, 'to_warehouse_id' => $this->destination->id, 'status' => TransferRequisitionStatus::UnderReviewFulfiller]);
            TransferRequisition::factory()->create(['from_warehouse_id' => $this->origin->id, 'to_warehouse_id' => $this->destination->id, 'status' => TransferRequisitionStatus::UnderReviewRequestor]);
            TransferRequisition::factory()->create(['from_warehouse_id' => $this->origin->id, 'to_warehouse_id' => $this->destination->id, 'status' => TransferRequisitionStatus::Confirmed]);
            // Dispatched and beyond should NOT be counted
            TransferRequisition::factory()->create(['from_warehouse_id' => $this->origin->id, 'to_warehouse_id' => $this->destination->id, 'status' => TransferRequisitionStatus::Dispatched]);
            TransferRequisition::factory()->create(['from_warehouse_id' => $this->origin->id, 'to_warehouse_id' => $this->destination->id, 'status' => TransferRequisitionStatus::Completed]);

            $widget = new StatsOverview();
            $stats = $widget->getStats();

            $pendingStat = collect($stats)->firstWhere(fn ($stat) => $stat->getLabel() === 'Pending Requisitions');
            expect($pendingStat->getValue())->toBe('5');
        });

        it('excludes requisitions from warehouses user cannot access', function () {
            $otherOrigin = Warehouse::factory()->create();
            $otherDestination = Warehouse::factory()->create();
            TransferRequisition::factory()->create(['from_warehouse_id' => $this->origin->id, 'to_warehouse_id' => $this->destination->id, 'status' => TransferRequisitionStatus::Confirmed]);
            TransferRequisition::factory()->create(['from_warehouse_id' => $otherOrigin->id, 'to_warehouse_id' => $otherDestination->id, 'status' => TransferRequisitionStatus::Confirmed]);

            $widget = new StatsOverview();
            $stats = $widget->getStats();

            $pendingStat = collect($stats)->firstWhere(fn ($stat) => $stat->getLabel() === 'Pending Requisitions');
            expect($pendingStat->getValue())->toBe('1');
        });
    });

    describe('Active In-Transit Cargo', function () {
        it('counts in-transit items not yet cleared', function () {
            $requisition = makeConfirmedRequisitionForStats($this->service, $this->origin, $this->destination, $this->variant, 240);
            $this->service->dispatchTransfer($requisition->id);

            $widget = new StatsOverview();
            $stats = $widget->getStats();

            $inTransitStat = collect($stats)->firstWhere(fn ($stat) => $stat->getLabel() === 'Active In-Transit Cargo');
            expect($inTransitStat->getValue())->toBe('1');
        });

        it('excludes cleared in-transits', function () {
            $requisition = makeConfirmedRequisitionForStats($this->service, $this->origin, $this->destination, $this->variant, 240);
            $this->service->dispatchTransfer($requisition->id);

            // Mark as cleared
            InTransit::where('transfer_requisition_id', $requisition->id)
                ->update(['status' => InTransitStatus::Cleared]);

            $widget = new StatsOverview();
            $stats = $widget->getStats();

            $inTransitStat = collect($stats)->firstWhere(fn ($stat) => $stat->getLabel() === 'Active In-Transit Cargo');
            expect($inTransitStat->getValue())->toBe('0');
        });

        it('excludes in-transits from warehouses user cannot access', function () {
            $otherOrigin = Warehouse::factory()->create();
            $otherDestination = Warehouse::factory()->create();
            $otherVariant = ProductVariant::factory()->withPrice()->create();
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

            $widget = new StatsOverview();
            $stats = $widget->getStats();

            $inTransitStat = collect($stats)->firstWhere(fn ($stat) => $stat->getLabel() === 'Active In-Transit Cargo');
            expect($inTransitStat->getValue())->toBe('0');
        });
    });

    describe('Total Write-Off Value', function () {
        it('sums total_financial_loss from LossLedger', function () {
            $requisition = makeConfirmedRequisitionForStats($this->service, $this->origin, $this->destination, $this->variant, 240);
            $this->service->dispatchTransfer($requisition->id);

            // Simulate loss by receiving less than shipped - first partial receipt (no damage, no loss ledger yet)
            $this->service->scanToReceive($requisition->id, [
                $requisition->items->first()->id => [
                    'good_qty' => 5,
                    'damaged_qty' => 0,
                    'loss_category' => 'shortfall',
                ],
            ]);

            // Second scan: complete the receipt with shortfall (5 of 10 boxes) - triggers LossLedger on final closure
            $this->service->scanToReceive($requisition->id, [
                $requisition->items->first()->id => [
                    'good_qty' => 5,
                    'damaged_qty' => 0,
                    'loss_category' => 'shortfall',
                ],
            ]);

            $widget = new StatsOverview();
            $stats = $widget->getStats();

            $writeOffStat = collect($stats)->firstWhere(fn ($stat) => $stat->getLabel() === 'Total Write-Off Value');
            expect($writeOffStat->getValue())->not->toBe('0.0000');
        });

        it('uses bcmath for precision (4 decimal places)', function () {
            $requisition = makeConfirmedRequisitionForStats($this->service, $this->origin, $this->destination, $this->variant, 240);
            $this->service->dispatchTransfer($requisition->id);

            // First partial receipt (no damage, no loss ledger yet)
            $this->service->scanToReceive($requisition->id, [
                $requisition->items->first()->id => [
                    'good_qty' => 5,
                    'damaged_qty' => 0,
                    'loss_category' => 'shortfall',
                ],
            ]);

            // Second scan: complete the receipt with shortfall - triggers LossLedger on final closure
            $this->service->scanToReceive($requisition->id, [
                $requisition->items->first()->id => [
                    'good_qty' => 5,
                    'damaged_qty' => 0,
                    'loss_category' => 'shortfall',
                ],
            ]);

            $widget = new StatsOverview();
            $stats = $widget->getStats();

            $writeOffStat = collect($stats)->firstWhere(fn ($stat) => $stat->getLabel() === 'Total Write-Off Value');
            // Should have 4 decimal places from bcmath
            expect($writeOffStat->getValue())->toMatch('/^\d+\.\d{4}$/');
        });

        it('excludes losses from warehouses user cannot access', function () {
            $otherOrigin = Warehouse::factory()->create();
            $otherDestination = Warehouse::factory()->create();
            $otherVariant = ProductVariant::factory()->withPrice()->create();
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

            $this->service->scanToReceive($requisition->id, [
                $requisition->items->first()->id => [
                    'good_qty' => 5,
                    'damaged_qty' => 0,
                    'loss_category' => 'shortfall',
                ],
            ]);

            $widget = new StatsOverview();
            $stats = $widget->getStats();

            $writeOffStat = collect($stats)->firstWhere(fn ($stat) => $stat->getLabel() === 'Total Write-Off Value');
            expect($writeOffStat->getValue())->toBe('0.0000');
        });

        it('returns 0.0000 when no losses recorded', function () {
            $widget = new StatsOverview();
            $stats = $widget->getStats();

            $writeOffStat = collect($stats)->firstWhere(fn ($stat) => $stat->getLabel() === 'Total Write-Off Value');
            expect($writeOffStat->getValue())->toBe('0.0000');
        });
    });

    describe('Gate checks', function () {
        it('non-admin/auditor receives empty stats', function () {
            $nonAdmin = User::factory()->create();
            $this->actingAs($nonAdmin);

            $widget = new StatsOverview();
            $stats = $widget->getStats();

            foreach ($stats as $stat) {
                expect($stat->getValue())->toBe('0.0000');
            }
        });

        it('auditor can see stats', function () {
            $auditor = User::factory()->create(['role' => 'auditor']);
            $this->actingAs($auditor);
            $auditor->warehouses()->syncWithoutDetaching([$this->origin->id]);

            $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 500);

            $widget = new StatsOverview();
            $stats = $widget->getStats();

            $onHandStat = collect($stats)->firstWhere(fn ($stat) => $stat->getLabel() === 'Total On-Hand Base Stock');
            expect($onHandStat->getValue())->toBe('500');
        });
    });
});
