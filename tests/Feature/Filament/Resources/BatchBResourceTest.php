<?php

declare(strict_types=1);

use App\Filament\Resources\DirectTransfers\DirectTransferResource;
use App\Filament\Resources\LossLedgers\LossLedgerResource;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Models\DirectTransfer;
use App\Models\LossLedger;
use App\Models\StockMovement;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

/**
 * Batch B resource contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Consolidation rationale: the three resources in this batch share a
 * uniform shape — model binding, navigation metadata, resource URL
 * rendering, policy resolution, and translation coverage. A single
 * file with `describe()` blocks per resource keeps the contract
 * readable; per-resource files would be near-identical.
 *
 * Blueprint anchors exercised:
 *   - §7C / §7E / §7F resource class shapes.
 *   - §1A.4 navigation metadata.
 *   - §8.5 / §8.6 / §8.13 policy bindings.
 *   - §1B.2 no badge on any of the three.
 *   - §0A.15 translation coverage.
 */
uses(RefreshDatabase::class);

// ===========================================================================
// DirectTransferResource
// ===========================================================================

describe('DirectTransferResource', function () {
    it('binds to the DirectTransfer model', function () {
        expect(DirectTransferResource::getModel())->toBe(DirectTransfer::class);
    });

    it('declares the OPERATIONS navigation group with sort 2', function () {
        $reflection = new ReflectionClass(DirectTransferResource::class);

        expect($reflection->getStaticPropertyValue('navigationGroup'))->toBe('OPERATIONS');
        expect($reflection->getStaticPropertyValue('navigationSort'))->toBe(2);
    });

    it('uses distinct resting and active navigation icons', function () {
        $reflection = new ReflectionClass(DirectTransferResource::class);

        expect($reflection->getStaticPropertyValue('navigationIcon'))->toBe(Heroicon::OutlinedArrowPath);
        expect($reflection->getStaticPropertyValue('activeNavigationIcon'))->toBe(Heroicon::ArrowPath);
    });

    it('resolves translated labels through resources.direct_transfers.*', function () {
        expect(DirectTransferResource::getModelLabel())->toBe(__('resources.direct_transfers.model.singular'));
        expect(DirectTransferResource::getPluralModelLabel())->toBe(__('resources.direct_transfers.model.plural'));
        expect(DirectTransferResource::getNavigationLabel())->toBe(__('resources.direct_transfers.navigation.label'));
    });

    it('registers index, create, and view pages but no edit page', function () {
        // A11: fire-and-forget — no edit route.
        $pages = DirectTransferResource::getPages();

        expect($pages)->toHaveKeys(['index', 'create', 'view']);
        expect($pages)->not->toHaveKey('edit');
    });

    it('declares no navigation badge', function () {
        expect(DirectTransferResource::getNavigationBadge())->toBeNull();
    });

    it('resolves the DirectTransferPolicy through the Gate', function () {
        expect(Gate::getPolicyFor(DirectTransfer::class))
            ->toBeInstanceOf(App\Policies\DirectTransferPolicy::class);
    });

    it('eager-loads fromWarehouse, toWarehouse, transferredBy, and items.productVariant', function () {
        $this->actingAs(User::factory()->admin()->create());

        $eagerLoads = DirectTransferResource::getEloquentQuery()->getEagerLoads();

        expect($eagerLoads)->toHaveKey('fromWarehouse');
        expect($eagerLoads)->toHaveKey('toWarehouse');
        expect($eagerLoads)->toHaveKey('transferredBy');
        expect($eagerLoads)->toHaveKey('items.productVariant');
    });

    it('renders the list page for an admin', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(DirectTransferResource::getUrl('index'))
            ->assertSuccessful();
    });

    it('renders the view page for an admin', function () {
        $transfer = DirectTransfer::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(DirectTransferResource::getUrl('view', ['record' => $transfer]))
            ->assertSuccessful();
    });
});

// ===========================================================================
// StockMovementResource
// ===========================================================================

describe('StockMovementResource', function () {
    it('binds to the StockMovement model', function () {
        expect(StockMovementResource::getModel())->toBe(StockMovement::class);
    });

    it('declares the AUDIT LEDGERS navigation group with sort 1', function () {
        $reflection = new ReflectionClass(StockMovementResource::class);

        expect($reflection->getStaticPropertyValue('navigationGroup'))->toBe('AUDIT LEDGERS');
        expect($reflection->getStaticPropertyValue('navigationSort'))->toBe(1);
    });

    it('resolves translated labels through resources.stock_movements.*', function () {
        expect(StockMovementResource::getModelLabel())->toBe(__('resources.stock_movements.model.singular'));
        expect(StockMovementResource::getPluralModelLabel())->toBe(__('resources.stock_movements.model.plural'));
        expect(StockMovementResource::getNavigationLabel())->toBe(__('resources.stock_movements.navigation.label'));
    });

    it('registers index and view pages only (read-only ledger)', function () {
        $pages = StockMovementResource::getPages();

        expect($pages)->toHaveKeys(['index', 'view']);
        expect($pages)->not->toHaveKey('create');
        expect($pages)->not->toHaveKey('edit');
    });

    it('resolves the StockMovementPolicy through the Gate', function () {
        expect(Gate::getPolicyFor(StockMovement::class))
            ->toBeInstanceOf(App\Policies\StockMovementPolicy::class);
    });

    it('eager-loads productVariant, warehouse, and createdBy', function () {
        $this->actingAs(User::factory()->admin()->create());

        $eagerLoads = StockMovementResource::getEloquentQuery()->getEagerLoads();

        expect($eagerLoads)->toHaveKey('productVariant');
        expect($eagerLoads)->toHaveKey('warehouse');
        expect($eagerLoads)->toHaveKey('createdBy');
    });

    it('renders the list page for an admin', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(StockMovementResource::getUrl('index'))
            ->assertSuccessful();
    });

    it('renders the view page for an admin', function () {
        $movement = StockMovement::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(StockMovementResource::getUrl('view', ['record' => $movement]))
            ->assertSuccessful();
    });
});

// ===========================================================================
// LossLedgerResource
// ===========================================================================

describe('LossLedgerResource', function () {
    it('binds to the LossLedger model', function () {
        expect(LossLedgerResource::getModel())->toBe(LossLedger::class);
    });

    it('declares the AUDIT LEDGERS navigation group with sort 2', function () {
        $reflection = new ReflectionClass(LossLedgerResource::class);

        expect($reflection->getStaticPropertyValue('navigationGroup'))->toBe('AUDIT LEDGERS');
        expect($reflection->getStaticPropertyValue('navigationSort'))->toBe(2);
    });

    it('resolves translated labels through resources.loss_ledgers.*', function () {
        expect(LossLedgerResource::getModelLabel())->toBe(__('resources.loss_ledgers.model.singular'));
        expect(LossLedgerResource::getPluralModelLabel())->toBe(__('resources.loss_ledgers.model.plural'));
        expect(LossLedgerResource::getNavigationLabel())->toBe(__('resources.loss_ledgers.navigation.label'));
    });

    it('registers index and view pages only (read-only ledger)', function () {
        $pages = LossLedgerResource::getPages();

        expect($pages)->toHaveKeys(['index', 'view']);
        expect($pages)->not->toHaveKey('create');
        expect($pages)->not->toHaveKey('edit');
    });

    it('resolves the LossLedgerPolicy through the Gate', function () {
        expect(Gate::getPolicyFor(LossLedger::class))
            ->toBeInstanceOf(App\Policies\LossLedgerPolicy::class);
    });

    it('renders the list page for an admin', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(LossLedgerResource::getUrl('index'))
            ->assertSuccessful();
    });

    it('renders the view page for an admin', function () {
        $loss = LossLedger::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(LossLedgerResource::getUrl('view', ['record' => $loss]))
            ->assertSuccessful();
    });
});

// ===========================================================================
// Cross-cutting — translation coverage (§0A.15)
// ===========================================================================

it('resolves every direct_transfers translation key it renders', function () {
    $keys = [
        'resources.direct_transfers.model.singular',
        'resources.direct_transfers.navigation.label',
        'resources.direct_transfers.sections.routing',
        'resources.direct_transfers.sections.stock_allocation',
        'resources.direct_transfers.steps.location_mapping',
        'resources.direct_transfers.steps.location_mapping_description',
        'resources.direct_transfers.steps.stock_allocation',
        'resources.direct_transfers.steps.stock_allocation_description',
        'resources.direct_transfers.steps.review_verify',
        'resources.direct_transfers.steps.review_verify_description',
        'resources.direct_transfers.fields.from_warehouse',
        'resources.direct_transfers.fields.to_warehouse',
        'resources.direct_transfers.fields.variant_sku',
        'resources.direct_transfers.fields.unit',
        'resources.direct_transfers.fields.ratio_base',
        'resources.direct_transfers.fields.qty',
        'resources.direct_transfers.fields.notes',
        'resources.direct_transfers.filters.from_warehouse',
        'resources.direct_transfers.filters.to_warehouse',
        'resources.direct_transfers.table.reference',
        'resources.direct_transfers.table.from',
        'resources.direct_transfers.table.to',
        'resources.direct_transfers.table.items',
        'resources.direct_transfers.table.by',
        'resources.direct_transfers.table.transferred_at',
        'resources.direct_transfers.infolist.profile',
        'resources.direct_transfers.infolist.manifest',
        'resources.direct_transfers.infolist.authorization',
        'resources.direct_transfers.hints.ratio_auto',
    ];

    foreach ($keys as $key) {
        $label = __($key);
        expect($label)->toBeString()->not->toBe('');
        expect($label)->not->toBe($key);
    }
});

it('resolves every stock_movements translation key it renders', function () {
    $keys = [
        'resources.stock_movements.model.singular',
        'resources.stock_movements.model.plural',
        'resources.stock_movements.navigation.label',
        'resources.stock_movements.fields.sku',
        'resources.stock_movements.fields.warehouse',
        'resources.stock_movements.fields.type',
        'resources.stock_movements.fields.quantity',
        'resources.stock_movements.fields.by',
        'resources.stock_movements.fields.timestamp',
        'resources.stock_movements.fields.reference_code',
        'resources.stock_movements.fields.unit',
        'resources.stock_movements.fields.notes',
        'resources.stock_movements.filters.warehouse',
        'resources.stock_movements.filters.variant',
        'resources.stock_movements.filters.type',
        'resources.stock_movements.table.sku',
        'resources.stock_movements.table.warehouse',
        'resources.stock_movements.table.type',
        'resources.stock_movements.table.qty',
        'resources.stock_movements.table.unit',
        'resources.stock_movements.table.by',
        'resources.stock_movements.table.timestamp',
        'resources.stock_movements.table.reference_code',
        'resources.stock_movements.infolist.movement',
    ];

    foreach ($keys as $key) {
        $label = __($key);
        expect($label)->toBeString()->not->toBe('');
        expect($label)->not->toBe($key);
    }
});

it('resolves every loss_ledgers translation key it renders', function () {
    $keys = [
        'resources.loss_ledgers.model.singular',
        'resources.loss_ledgers.model.plural',
        'resources.loss_ledgers.navigation.label',
        'resources.loss_ledgers.fields.requisition',
        'resources.loss_ledgers.fields.sku',
        'resources.loss_ledgers.fields.warehouse',
        'resources.loss_ledgers.fields.lost_base',
        'resources.loss_ledgers.fields.damaged_base',
        'resources.loss_ledgers.fields.notes',
        'resources.loss_ledgers.fields.transfer_requisition_item',
        'resources.loss_ledgers.fields.recorded_at',
        'resources.loss_ledgers.fields.loss_category',
        'resources.loss_ledgers.fields.unit_cost_price',
        'resources.loss_ledgers.fields.total_financial_loss',
        'resources.loss_ledgers.filters.warehouse',
        'resources.loss_ledgers.filters.loss_category',
        'resources.loss_ledgers.table.requisition',
        'resources.loss_ledgers.table.sku',
        'resources.loss_ledgers.table.warehouse',
        'resources.loss_ledgers.table.lost',
        'resources.loss_ledgers.table.damaged',
        'resources.loss_ledgers.table.category',
        'resources.loss_ledgers.table.unit_cost',
        'resources.loss_ledgers.table.total_loss',
        'resources.loss_ledgers.table.by',
        'resources.loss_ledgers.table.recorded',
        'resources.loss_ledgers.infolist.record',
        'resources.loss_ledgers.infolist.financial_impact',
        'resources.loss_ledgers.notes.cost_missing',
    ];

    foreach ($keys as $key) {
        $label = __($key);
        expect($label)->toBeString()->not->toBe('');
        expect($label)->not->toBe($key);
    }
});
