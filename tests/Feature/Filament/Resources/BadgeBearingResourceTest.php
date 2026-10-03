<?php

declare(strict_types=1);

use App\Enums\InTransitStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\TransferRequisitionStatus;
use App\Filament\Resources\InTransits\InTransitResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use App\Filament\Support\Concerns\ScopesNavigationBadges;
use App\Models\InTransit;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

/**
 * Badge-bearing resource contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Consolidation rationale: the four badge-bearing resources share a
 * uniform shape — model binding, nav metadata, translated labels,
 * `ScopesNavigationBadges` usage, badge count/color/tooltip, warehouse
 * query scope, policy resolution, page rendering, and translation
 * coverage. One file with `describe()` blocks per resource keeps the
 * contract readable.
 *
 * Blueprint anchors exercised:
 *   - §1B.2 / §1B.3a badge-bearing resource class shapes.
 *   - §1B.1a scope tiers and BranchManager interim treatment.
 *   - §1A.4 navigation metadata.
 *   - §8.3 / §8.7 / §8.8 / §8.4 policy bindings.
 *   - §20.1 query-level warehouse scope.
 *   - §0A.15 translation coverage.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    TransferRequisitionResource::flushBadgeScope();
    PurchaseOrderResource::flushBadgeScope();
    SalesOrderResource::flushBadgeScope();
    InTransitResource::flushBadgeScope();
});

// ===========================================================================
// Shared contract — every badge-bearing resource uses the trait
// ===========================================================================

it('uses the ScopesNavigationBadges trait', function (string $resourceClass) {
    // §1B.3 / §1B.5 invariant: the scope resolver trait is the only
    // sanctioned way to compute badge warehouse IDs.
    $traits = class_uses_recursive($resourceClass);

    expect($traits)->toHaveKey(ScopesNavigationBadges::class);
})->with([
    TransferRequisitionResource::class,
    PurchaseOrderResource::class,
    SalesOrderResource::class,
    InTransitResource::class,
]);

it('declares a public static flushBadgeScope method', function (string $resourceClass) {
    // §1B.3: AppServiceProvider::registerBadgeScopeFlush() calls this
    // method on each resource — it must be public static.
    $method = new ReflectionMethod($resourceClass, 'flushBadgeScope');

    expect($method->isPublic())->toBeTrue();
    expect($method->isStatic())->toBeTrue();
})->with([
    TransferRequisitionResource::class,
    PurchaseOrderResource::class,
    SalesOrderResource::class,
    InTransitResource::class,
]);

it('declares a badge tooltip that resolves through the translation catalogue', function (string $resourceClass, string $key) {
    $tooltip = $resourceClass::getNavigationBadgeTooltip();

    expect($tooltip)->toBeString()->not->toBe('');
    expect($tooltip)->not->toBe($key);
    expect($tooltip)->toBe(__($key));
})->with([
    [TransferRequisitionResource::class, 'resources.transfer_requisitions.badge_tooltip'],
    [PurchaseOrderResource::class, 'resources.purchase_orders.badge_tooltip'],
    [SalesOrderResource::class, 'resources.sales_orders.badge_tooltip'],
    [InTransitResource::class, 'resources.in_transits.badge_tooltip'],
]);

// ===========================================================================
// TransferRequisitionResource — badge, scope, rendering
// ===========================================================================

describe('TransferRequisitionResource', function () {
    it('binds to the TransferRequisition model', function () {
        expect(TransferRequisitionResource::getModel())->toBe(TransferRequisition::class);
    });

    it('declares the OPERATIONS navigation group with sort 1', function () {
        $reflection = new ReflectionClass(TransferRequisitionResource::class);

        expect($reflection->getStaticPropertyValue('navigationGroup'))->toBe('OPERATIONS');
        expect($reflection->getStaticPropertyValue('navigationSort'))->toBe(1);
    });

    it('uses distinct resting and active navigation icons', function () {
        $reflection = new ReflectionClass(TransferRequisitionResource::class);

        expect($reflection->getStaticPropertyValue('navigationIcon'))->toBe(Heroicon::OutlinedArrowsRightLeft);
        expect($reflection->getStaticPropertyValue('activeNavigationIcon'))->toBe(Heroicon::ArrowsRightLeft);
    });

    it('resolves translated labels through resources.transfer_requisitions.*', function () {
        expect(TransferRequisitionResource::getModelLabel())->toBe(__('resources.transfer_requisitions.model.singular'));
        expect(TransferRequisitionResource::getPluralModelLabel())->toBe(__('resources.transfer_requisitions.model.plural'));
        expect(TransferRequisitionResource::getNavigationLabel())->toBe(__('resources.transfer_requisitions.navigation.label'));
    });

    it('registers index, create, view, and edit pages', function () {
        $pages = TransferRequisitionResource::getPages();

        expect($pages)->toHaveKeys(['index', 'create', 'view', 'edit']);
    });

    it('resolves the TransferRequisitionPolicy through the Gate', function () {
        expect(Gate::getPolicyFor(TransferRequisition::class))
            ->toBe(App\Policies\TransferRequisitionPolicy::class);
    });

    it('counts only Requested status toward the badge', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $warehouses = Warehouse::factory()->count(2)->create();

        // Requested — counted
        TransferRequisition::factory()->count(3)->create([
            'from_warehouse_id' => $warehouses[0]->id,
            'to_warehouse_id' => $warehouses[1]->id,
            'status' => TransferRequisitionStatus::Requested,
        ]);

        // Draft / Confirmed / Dispatched — not counted
        TransferRequisition::factory()->create([
            'from_warehouse_id' => $warehouses[0]->id,
            'to_warehouse_id' => $warehouses[1]->id,
            'status' => TransferRequisitionStatus::Draft,
        ]);
        TransferRequisition::factory()->create([
            'from_warehouse_id' => $warehouses[0]->id,
            'to_warehouse_id' => $warehouses[1]->id,
            'status' => TransferRequisitionStatus::Confirmed,
        ]);
        TransferRequisition::factory()->create([
            'from_warehouse_id' => $warehouses[0]->id,
            'to_warehouse_id' => $warehouses[1]->id,
            'status' => TransferRequisitionStatus::Dispatched,
        ]);

        expect(TransferRequisitionResource::getNavigationBadge())->toBe('3');
    });

    it('returns null when no matching requisitions exist', function () {
        $this->actingAs(User::factory()->admin()->create());

        expect(TransferRequisitionResource::getNavigationBadge())->toBeNull();
    });

    it('uses primary color for counts up to 10', function () {
        $this->actingAs(User::factory()->admin()->create());

        expect(TransferRequisitionResource::getNavigationBadgeColor())->toBe('primary');
    });

    it('uses warning color above 10', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $warehouses = Warehouse::factory()->count(2)->create();

        TransferRequisition::factory()->count(11)->create([
            'from_warehouse_id' => $warehouses[0]->id,
            'to_warehouse_id' => $warehouses[1]->id,
            'status' => TransferRequisitionStatus::Requested,
        ]);

        expect(TransferRequisitionResource::getNavigationBadgeColor())->toBe('warning');
    });

    it('returns null when the acting user has no badge scope', function () {
        // Warehouse staff with no assignments → empty set → null badge.
        $this->actingAs(User::factory()->create());
        TransferRequisition::factory()->count(3)->create([
            'status' => TransferRequisitionStatus::Requested,
        ]);

        expect(TransferRequisitionResource::getNavigationBadge())->toBeNull();
    });

    it('scopes the badge count to the acting user\'s assigned warehouse', function () {
        // Single-warehouse user assigned to A — badge counts only
        // requisitions touching A. Counterpart warehouses do not widen
        // the scope (§1B.1b / A12).
        $warehouseA = Warehouse::factory()->create();
        $warehouseB = Warehouse::factory()->create();
        $warehouseC = Warehouse::factory()->create();

        $staff = User::factory()->create();
        $staff->warehouses()->attach($warehouseA->id);
        $this->actingAs($staff);

        // A → B: touches A on the source side — counted.
        TransferRequisition::factory()->create([
            'from_warehouse_id' => $warehouseA->id,
            'to_warehouse_id' => $warehouseB->id,
            'status' => TransferRequisitionStatus::Requested,
        ]);

        // C → B: does not touch A — not counted.
        TransferRequisition::factory()->create([
            'from_warehouse_id' => $warehouseC->id,
            'to_warehouse_id' => $warehouseB->id,
            'status' => TransferRequisitionStatus::Requested,
        ]);

        // B → A: touches A on the destination side — counted.
        TransferRequisition::factory()->create([
            'from_warehouse_id' => $warehouseB->id,
            'to_warehouse_id' => $warehouseA->id,
            'status' => TransferRequisitionStatus::Requested,
        ]);

        expect(TransferRequisitionResource::getNavigationBadge())->toBe('2');
    });

    it('renders the list page for an admin', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(TransferRequisitionResource::getUrl('index'))
            ->assertSuccessful();
    });

    it('renders the view page for an admin', function () {
        $requisition = TransferRequisition::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(TransferRequisitionResource::getUrl('view', ['record' => $requisition]))
            ->assertSuccessful();
    });
});

// ===========================================================================
// PurchaseOrderResource — badge, scope, rendering
// ===========================================================================

describe('PurchaseOrderResource', function () {
    it('binds to the PurchaseOrder model', function () {
        expect(PurchaseOrderResource::getModel())->toBe(PurchaseOrder::class);
    });

    it('declares the PURCHASING navigation group with sort 1', function () {
        $reflection = new ReflectionClass(PurchaseOrderResource::class);

        expect($reflection->getStaticPropertyValue('navigationGroup'))->toBe('PURCHASING');
        expect($reflection->getStaticPropertyValue('navigationSort'))->toBe(1);
    });

    it('uses distinct resting and active navigation icons', function () {
        $reflection = new ReflectionClass(PurchaseOrderResource::class);

        expect($reflection->getStaticPropertyValue('navigationIcon'))->toBe(Heroicon::OutlinedShoppingCart);
        expect($reflection->getStaticPropertyValue('activeNavigationIcon'))->toBe(Heroicon::ShoppingCart);
    });

    it('resolves translated labels through resources.purchase_orders.*', function () {
        expect(PurchaseOrderResource::getModelLabel())->toBe(__('resources.purchase_orders.model.singular'));
        expect(PurchaseOrderResource::getPluralModelLabel())->toBe(__('resources.purchase_orders.model.plural'));
        expect(PurchaseOrderResource::getNavigationLabel())->toBe(__('resources.purchase_orders.navigation.label'));
    });

    it('registers index, create, view, and edit pages', function () {
        expect(PurchaseOrderResource::getPages())->toHaveKeys(['index', 'create', 'view', 'edit']);
    });

    it('resolves the PurchaseOrderPolicy through the Gate', function () {
        expect(Gate::getPolicyFor(PurchaseOrder::class))->toBe(App\Policies\PurchaseOrderPolicy::class);
    });

    it('counts only Ordered status toward the badge', function () {
        $this->actingAs(User::factory()->admin()->create());
        $warehouse = Warehouse::factory()->create();

        PurchaseOrder::factory()->count(2)->create([
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrderStatus::Ordered,
        ]);
        PurchaseOrder::factory()->create([
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrderStatus::Draft,
        ]);
        PurchaseOrder::factory()->create([
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrderStatus::Received,
        ]);

        expect(PurchaseOrderResource::getNavigationBadge())->toBe('2');
    });

    it('returns null when no matching orders exist', function () {
        $this->actingAs(User::factory()->admin()->create());

        expect(PurchaseOrderResource::getNavigationBadge())->toBeNull();
    });

    it('uses warning color above 10', function () {
        $this->actingAs(User::factory()->admin()->create());
        $warehouse = Warehouse::factory()->create();

        PurchaseOrder::factory()->count(11)->create([
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrderStatus::Ordered,
        ]);

        expect(PurchaseOrderResource::getNavigationBadgeColor())->toBe('warning');
    });

    it('scopes the badge count to the acting user\'s assigned warehouse', function () {
        $warehouseA = Warehouse::factory()->create();
        $warehouseB = Warehouse::factory()->create();

        $staff = User::factory()->create();
        $staff->warehouses()->attach($warehouseA->id);
        $this->actingAs($staff);

        PurchaseOrder::factory()->count(2)->create([
            'warehouse_id' => $warehouseA->id,
            'status' => PurchaseOrderStatus::Ordered,
        ]);
        PurchaseOrder::factory()->create([
            'warehouse_id' => $warehouseB->id,
            'status' => PurchaseOrderStatus::Ordered,
        ]);

        expect(PurchaseOrderResource::getNavigationBadge())->toBe('2');
    });

    it('returns null when warehouse staff have no assignments', function () {
        $this->actingAs(User::factory()->create());
        PurchaseOrder::factory()->count(3)->create([
            'status' => PurchaseOrderStatus::Ordered,
        ]);

        expect(PurchaseOrderResource::getNavigationBadge())->toBeNull();
    });

    it('renders the list page for an admin', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(PurchaseOrderResource::getUrl('index'))
            ->assertSuccessful();
    });

    it('renders the view page for an admin', function () {
        $order = PurchaseOrder::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(PurchaseOrderResource::getUrl('view', ['record' => $order]))
            ->assertSuccessful();
    });
});

// ===========================================================================
// SalesOrderResource — badge, scope, rendering
// ===========================================================================

describe('SalesOrderResource', function () {
    it('binds to the SalesOrder model', function () {
        expect(SalesOrderResource::getModel())->toBe(SalesOrder::class);
    });

    it('declares the SALES navigation group with sort 1', function () {
        $reflection = new ReflectionClass(SalesOrderResource::class);

        expect($reflection->getStaticPropertyValue('navigationGroup'))->toBe('SALES');
        expect($reflection->getStaticPropertyValue('navigationSort'))->toBe(1);
    });

    it('uses distinct resting and active navigation icons', function () {
        $reflection = new ReflectionClass(SalesOrderResource::class);

        expect($reflection->getStaticPropertyValue('navigationIcon'))->toBe(Heroicon::OutlinedBanknotes);
        expect($reflection->getStaticPropertyValue('activeNavigationIcon'))->toBe(Heroicon::Banknotes);
    });

    it('resolves translated labels through resources.sales_orders.*', function () {
        expect(SalesOrderResource::getModelLabel())->toBe(__('resources.sales_orders.model.singular'));
        expect(SalesOrderResource::getPluralModelLabel())->toBe(__('resources.sales_orders.model.plural'));
        expect(SalesOrderResource::getNavigationLabel())->toBe(__('resources.sales_orders.navigation.label'));
    });

    it('registers index, create, view, and edit pages', function () {
        expect(SalesOrderResource::getPages())->toHaveKeys(['index', 'create', 'view', 'edit']);
    });

    it('resolves the SalesOrderPolicy through the Gate', function () {
        expect(Gate::getPolicyFor(SalesOrder::class))->toBe(App\Policies\SalesOrderPolicy::class);
    });

    it('counts only Confirmed status toward the badge', function () {
        $this->actingAs(User::factory()->admin()->create());
        $warehouse = Warehouse::factory()->create();

        SalesOrder::factory()->count(2)->create([
            'warehouse_id' => $warehouse->id,
            'status' => SalesOrderStatus::Confirmed,
        ]);
        SalesOrder::factory()->create([
            'warehouse_id' => $warehouse->id,
            'status' => SalesOrderStatus::Draft,
        ]);
        SalesOrder::factory()->create([
            'warehouse_id' => $warehouse->id,
            'status' => SalesOrderStatus::Dispatched,
        ]);

        expect(SalesOrderResource::getNavigationBadge())->toBe('2');
    });

    it('returns null when no matching orders exist', function () {
        $this->actingAs(User::factory()->admin()->create());

        expect(SalesOrderResource::getNavigationBadge())->toBeNull();
    });

    it('uses warning color above 10', function () {
        $this->actingAs(User::factory()->admin()->create());
        $warehouse = Warehouse::factory()->create();

        SalesOrder::factory()->count(11)->create([
            'warehouse_id' => $warehouse->id,
            'status' => SalesOrderStatus::Confirmed,
        ]);

        expect(SalesOrderResource::getNavigationBadgeColor())->toBe('warning');
    });

    it('scopes the badge count to the acting user\'s assigned warehouse', function () {
        $warehouseA = Warehouse::factory()->create();
        $warehouseB = Warehouse::factory()->create();

        $staff = User::factory()->create();
        $staff->warehouses()->attach($warehouseA->id);
        $this->actingAs($staff);

        SalesOrder::factory()->count(3)->create([
            'warehouse_id' => $warehouseA->id,
            'status' => SalesOrderStatus::Confirmed,
        ]);
        SalesOrder::factory()->create([
            'warehouse_id' => $warehouseB->id,
            'status' => SalesOrderStatus::Confirmed,
        ]);

        expect(SalesOrderResource::getNavigationBadge())->toBe('3');
    });

    it('returns null when warehouse staff have no assignments', function () {
        $this->actingAs(User::factory()->create());
        SalesOrder::factory()->count(3)->create([
            'status' => SalesOrderStatus::Confirmed,
        ]);

        expect(SalesOrderResource::getNavigationBadge())->toBeNull();
    });

    it('renders the list page for an admin', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(SalesOrderResource::getUrl('index'))
            ->assertSuccessful();
    });

    it('renders the view page for an admin', function () {
        $order = SalesOrder::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(SalesOrderResource::getUrl('view', ['record' => $order]))
            ->assertSuccessful();
    });
});

// ===========================================================================
// InTransitResource — badge, scope, rendering
// ===========================================================================

describe('InTransitResource', function () {
    it('binds to the InTransit model', function () {
        expect(InTransitResource::getModel())->toBe(InTransit::class);
    });

    it('declares the OPERATIONS navigation group with sort 3', function () {
        $reflection = new ReflectionClass(InTransitResource::class);

        expect($reflection->getStaticPropertyValue('navigationGroup'))->toBe('OPERATIONS');
        expect($reflection->getStaticPropertyValue('navigationSort'))->toBe(3);
    });

    it('uses distinct resting and active navigation icons', function () {
        $reflection = new ReflectionClass(InTransitResource::class);

        expect($reflection->getStaticPropertyValue('navigationIcon'))->toBe(Heroicon::OutlinedTruck);
        expect($reflection->getStaticPropertyValue('activeNavigationIcon'))->toBe(Heroicon::Truck);
    });

    it('resolves translated labels through resources.in_transits.*', function () {
        expect(InTransitResource::getModelLabel())->toBe(__('resources.in_transits.model.singular'));
        expect(InTransitResource::getPluralModelLabel())->toBe(__('resources.in_transits.model.plural'));
        expect(InTransitResource::getNavigationLabel())->toBe(__('resources.in_transits.navigation.label'));
    });

    it('registers index and view pages only (read-only monitor)', function () {
        $pages = InTransitResource::getPages();

        expect($pages)->toHaveKeys(['index', 'view']);
        expect($pages)->not->toHaveKey('create');
        expect($pages)->not->toHaveKey('edit');
    });

    it('counts only InTransit status toward the badge', function () {
        $this->actingAs(User::factory()->admin()->create());

        InTransit::factory()->count(2)->create([
            'status' => InTransitStatus::InTransit,
        ]);
        InTransit::factory()->create([
            'status' => InTransitStatus::Cleared,
            'cleared_at' => now(),
        ]);
        InTransit::factory()->create([
            'status' => InTransitStatus::Lost,
            'cleared_at' => now(),
        ]);

        expect(InTransitResource::getNavigationBadge())->toBe('2');
    });

    it('returns null when no active cargo exists', function () {
        $this->actingAs(User::factory()->admin()->create());

        expect(InTransitResource::getNavigationBadge())->toBeNull();
    });

    it('uses warning color above 10', function () {
        $this->actingAs(User::factory()->admin()->create());

        InTransit::factory()->count(11)->create([
            'status' => InTransitStatus::InTransit,
        ]);

        expect(InTransitResource::getNavigationBadgeColor())->toBe('warning');
    });

    it('returns null when warehouse staff have no assignments', function () {
        $this->actingAs(User::factory()->create());
        InTransit::factory()->count(3)->create([
            'status' => InTransitStatus::InTransit,
        ]);

        expect(InTransitResource::getNavigationBadge())->toBeNull();
    });

    it('renders the list page for an admin', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(InTransitResource::getUrl('index'))
            ->assertSuccessful();
    });

    it('renders the view page for an admin', function () {
        $inTransit = InTransit::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(InTransitResource::getUrl('view', ['record' => $inTransit]))
            ->assertSuccessful();
    });
});

// ===========================================================================
// BranchManager interim treatment — same warehouse tier as WarehouseStaff
// ===========================================================================

it('treats a branch manager with one assigned warehouse like warehouse staff for the badge scope', function () {
    // ⚠ Owner-direction tier: BranchManager uses the warehouse-assignment
    // path, same as WarehouseStaff. Single-warehouse case is exact.
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();

    $bm = User::factory()->branchManager()->create();
    $bm->warehouses()->attach($warehouseA->id);
    $this->actingAs($bm);

    PurchaseOrder::factory()->count(2)->create([
        'warehouse_id' => $warehouseA->id,
        'status' => PurchaseOrderStatus::Ordered,
    ]);
    PurchaseOrder::factory()->create([
        'warehouse_id' => $warehouseB->id,
        'status' => PurchaseOrderStatus::Ordered,
    ]);

    expect(PurchaseOrderResource::getNavigationBadge())->toBe('2');
});

it('returns null for a branch manager with no warehouse assignments', function () {
    $this->actingAs(User::factory()->branchManager()->create());

    PurchaseOrder::factory()->count(3)->create(['status' => PurchaseOrderStatus::Ordered]);

    expect(PurchaseOrderResource::getNavigationBadge())->toBeNull();
});

// ===========================================================================
// Admin / auditor hold global badge scope regardless of pivot
// ===========================================================================

it('grants an admin with an empty pivot the full system badge count', function () {
    // §1B.1a: Admin badge scope is global — the empty user_warehouse
    // pivot must not suppress the count.
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    expect($admin->warehouses()->count())->toBe(0);

    PurchaseOrder::factory()->count(5)->create(['status' => PurchaseOrderStatus::Ordered]);

    expect(PurchaseOrderResource::getNavigationBadge())->toBe('5');
});

it('grants an auditor with an empty pivot the full system badge count', function () {
    $auditor = User::factory()->auditor()->create();
    $this->actingAs($auditor);

    PurchaseOrder::factory()->count(5)->create(['status' => PurchaseOrderStatus::Ordered]);

    expect(PurchaseOrderResource::getNavigationBadge())->toBe('5');
});

// ===========================================================================
// Per-request badge caching — §1B.4
// ===========================================================================

it('caches the badge count within the request', function () {
    $this->actingAs(User::factory()->admin()->create());
    PurchaseOrder::factory()->count(3)->create(['status' => PurchaseOrderStatus::Ordered]);

    // Warm the cache.
    PurchaseOrderResource::getNavigationBadge();

    // Insert a new matching row after the cache is warm.
    PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Ordered]);

    // Second call reads the cached count.
    expect(PurchaseOrderResource::getNavigationBadge())->toBe('3');

    // After flush, the count picks up the new row.
    PurchaseOrderResource::flushBadgeScope();
    expect(PurchaseOrderResource::getNavigationBadge())->toBe('4');
});
