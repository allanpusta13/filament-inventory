<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Filament\Widgets\RecentMovementsWidget;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

    it('gate check: non-admin receives 403', function () {
        $nonAdmin = User::factory()->create();
        $this->actingAs($nonAdmin);

        $widget = new RecentMovementsWidget();
        $movements = $widget->getRecentMovements();

        expect($movements->count())->toBe(0);
    });

    it('warehouse with no movements yet (empty state)', function () {
        $emptyWarehouse = Warehouse::factory()->create();
        $this->user->warehouses()->syncWithoutDetaching([$emptyWarehouse->id]);

        $widget = new RecentMovementsWidget();
        $movements = $widget->getRecentMovements();

        expect($movements->count())->toBe(0);
    });

    it('excludes movements older than 365 days (age filter)', function () {
        $oldVariant = ProductVariant::factory()->create();
        $this->service->recordMovement($oldVariant->id, $this->origin->id, StockMovementType::Receive, 100);
        $oldMovementDate = now()->subDays(400);
        DB::table('stock_movements')->where('product_variant_id', $oldVariant->id)
            ->update(['created_at' => $oldMovementDate]);

        $widget = new RecentMovementsWidget();
        $movements = $widget->getRecentMovements();

        expect($movements->count())->toBe(0);
    });

    it('race-condition safe cache bypass with warehouse scoping', function () {
        Cache::flush();
        Cache::forget($this->cacheKey);

        $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 100);

        $widget = new RecentMovementsWidget();
        $first = $widget->getRecentMovements();
        Cache::forget($this->cacheKey);
        $second = $widget->getRecentMovements();

        expect($first)->toEqual($second);
    });

    it('filters by movement type array', function () {
        $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 100);
        $this->service->recordMovement($this->variant2->id, $this->origin->id, StockMovementType::Ship, 50);
        $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Adjustment, 25);

        $widget = new RecentMovementsWidget();
        $movements = $widget->getRecentMovements();

        $types = $movements->pluck('type')->map(fn ($e) => $e->value)->toArray();
        expect($types)->toContain(StockMovementType::Receive->value);
        expect($types)->toContain(StockMovementType::Ship->value);
        expect($types)->toContain(StockMovementType::Adjustment->value);
        expect($types)->not->toContain(StockMovementType::Loss->value);
    });

    it('handles deleted createdBy user with warehouse check', function () {
        $deletedUser = User::factory()->create();
        $deletedUser->delete();

        $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 100);

        $widget = new RecentMovementsWidget();
        $movements = $widget->getRecentMovements();

        expect($movements->count())->toBe(1);
        $firstUser = $movements->first()['created_by_name'];
        expect($firstUser)->not->toBe($deletedUser->name);
    });

    it('orders correctly across midnight boundary (date rollover)', function () {
        $variantAtMidnight = ProductVariant::factory()->create();
        $this->service->recordMovement($variantAtMidnight->id, $this->origin->id, StockMovementType::Receive, 100);
        $midnightDate = now()->addHour();
        DB::table('stock_movements')
            ->where('product_variant_id', $variantAtMidnight->id)
            ->update(['created_at' => $midnightDate]);

        $widget = new RecentMovementsWidget();
        $movements = $widget->getRecentMovements();

        expect($movements->count())->toBe(1);
        expect($movements->first()['variant_sku'])->toBe($variantAtMidnight->sku);
    });

    it('handles null reference_code gracefully (missing reference)', function () {
        $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 100);
        DB::table('stock_movements')->where('product_variant_id', $this->variant->id)
            ->update(['reference_code' => null]);

        $widget = new RecentMovementsWidget();
        $movements = $widget->getRecentMovements();

        expect($movements->count())->toBe(1);
        expect($movements->first()['reference_code'])->toBeNull();
    });

    it('denies cross-warehouse movement access', function () {
        $otherWarehouse = Warehouse::factory()->create();
        $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 100);
        $this->service->recordMovement($this->variant->id, $otherWarehouse->id, StockMovementType::Receive, 50);

        $this->actingAs($this->user);

        $widget = new RecentMovementsWidget();
        $movements = $widget->getRecentMovements();

        expect($movements->count())->toBe(1);
        expect($movements->first()['warehouse_name'])->toBe($this->origin->name);
        expect($movements->first()['warehouse_id'])->toBe($this->origin->id);
    });
});
