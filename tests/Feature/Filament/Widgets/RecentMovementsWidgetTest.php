<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Filament\Widgets\RecentMovementsWidget;
use App\Models\StockMovement;
use App\Models\ProductVariant;
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
    $this->variant2 = ProductVariant::factory()->create();

    $this->user->warehouses()->syncWithoutDetaching([$this->origin->id, $this->destination->id]);

    $this->cacheKey = 'recent_movements_'.$this->user->id.'_'.$this->origin->id;
    Cache::forget($this->cacheKey);
});

describe('RecentMovementsWidget', function () {
    it('returns recent movements for user warehouses', function () {
        $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 100);

        $widget = new RecentMovementsWidget();
        $movements = $widget->getRecentMovements();

        expect($movements->count())->toBe(1);
        $firstSku = $movements->first()['variant_sku'];
        expect($firstSku)->toBe($this->variant->sku);
    });

    it('limits to 20 most recent movements', function () {
        for ($i = 0; $i < 25; $i++) {
            $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, $i + 1);
        }

        $widget = new RecentMovementsWidget();
        $movements = $widget->getRecentMovements();

        expect($movements->count())->toBe(20);
    });

    it('caches results for 60 seconds', function () {
        $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 100);

        $widget = new RecentMovementsWidget();
        $first = $widget->getRecentMovements();
        $second = $widget->getRecentMovements();

        expect($second)->toBe($first);
    });

    it('excludes movements from warehouses user cannot access', function () {
        $otherWarehouse = Warehouse::factory()->create();
        $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 100);
        $this->service->recordMovement($this->variant->id, $otherWarehouse->id, StockMovementType::Receive, 50);

        $widget = new RecentMovementsWidget();
        $movements = $widget->getRecentMovements();

        expect($movements->count())->toBe(1);
        expect($movements->first()['warehouse_name'])->toBe($this->origin->name);
    });

    it('includes all movement types with correct color mapping', function () {
        Cache::flush();
        Cache::forget($this->cacheKey);

        $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 100);
        $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Ship, 50);
        $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Adjustment, 10);
        $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Loss, 5);

        $widget = new RecentMovementsWidget();
        $movements = $widget->getRecentMovements();

        expect($movements->count())->toBe(4);

        $types = $movements->pluck('type')->map(fn ($e) => $e->value)->toArray();
        expect($types)->toContain(StockMovementType::Receive->value)
            ->and($types)->toContain(StockMovementType::Ship->value)
            ->and($types)->toContain(StockMovementType::Adjustment->value)
            ->and($types)->toContain(StockMovementType::Loss->value);
    });

    it('handles empty movements gracefully', function () {
        $widget = new RecentMovementsWidget();
        $movements = $widget->getRecentMovements();

        expect($movements->count())->toBe(0);
    });

    it('includes transfer movements with reference codes', function () {
        $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 100);
        $this->service->directTransfer($this->variant->id, $this->origin->id, $this->destination->id, 30, referenceCode: 'DTR-001');

        $widget = new RecentMovementsWidget();
        $movements = $widget->getRecentMovements();

        expect($movements->count())->toBe(3); // receive + transfer_out + transfer_in
        $refs = $movements->pluck('reference_code')->filter()->toArray();
        expect($refs)->toContain('DTR-001');
    });

    it('orders by created_at descending', function () {
        $freshVariant1 = ProductVariant::factory()->create();
        $freshVariant2 = ProductVariant::factory()->create();
        $this->service->recordMovement($freshVariant1->id, $this->origin->id, StockMovementType::Receive, 100);
        sleep(1);
        $this->service->recordMovement($freshVariant2->id, $this->origin->id, StockMovementType::Receive, 200);

        $widget = new RecentMovementsWidget();
        $movements = $widget->getRecentMovements();

        expect($movements->first()['variant_sku'])->toBe($freshVariant2->sku);
    });
});