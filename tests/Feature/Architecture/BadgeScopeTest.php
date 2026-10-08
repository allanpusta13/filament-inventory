<?php

declare(strict_types=1);

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
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * §23.6 — Badge Scope.
 *
 * Verifies the four-tier scope resolver (§1B.1a) and the four badge-
 * bearing resources. Covers Admin, Auditor, WarehouseStaff (N=1, N≥2,
 * N=0), and BranchManager (assigned-warehouse tier).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    TransferRequisitionResource::flushBadgeScope();
    PurchaseOrderResource::flushBadgeScope();
    SalesOrderResource::flushBadgeScope();
    InTransitResource::flushBadgeScope();
});

// ---------------------------------------------------------------------------
// Trait usage
// ---------------------------------------------------------------------------

it('uses the ScopesNavigationBadges trait on every badge-bearing resource', function (string $class) {
    expect(class_uses_recursive($class))->toHaveKey(ScopesNavigationBadges::class);
})->with([
    TransferRequisitionResource::class,
    PurchaseOrderResource::class,
    SalesOrderResource::class,
    InTransitResource::class,
]);

it('declares flushBadgeScope() as a public static method on every badge-bearing resource', function (string $class) {
    $method = new ReflectionMethod($class, 'flushBadgeScope');

    expect($method->isPublic())->toBeTrue();
    expect($method->isStatic())->toBeTrue();
})->with([
    TransferRequisitionResource::class,
    PurchaseOrderResource::class,
    SalesOrderResource::class,
    InTransitResource::class,
]);

// ---------------------------------------------------------------------------
// Admin and Auditor — full system scope
// ---------------------------------------------------------------------------

it('grants the full system badge count to an admin', function () {
    Warehouse::factory()->count(3)->create();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    TransferRequisition::factory()->count(5)->create([
        'status' => App\Enums\TransferRequisitionStatus::Requested,
    ]);

    expect(TransferRequisitionResource::getNavigationBadge())->toBe('5');
});

it('grants the full system badge count to an auditor', function () {
    Warehouse::factory()->count(3)->create();
    $auditor = User::factory()->auditor()->create();
    $this->actingAs($auditor);

    TransferRequisition::factory()->count(5)->create([
        'status' => App\Enums\TransferRequisitionStatus::Requested,
    ]);

    expect(TransferRequisitionResource::getNavigationBadge())->toBe('5');
});

it('grants the full system badge count to an admin with an empty warehouse pivot', function () {
    // §1B.1a: admin badge authority is global, independent of pivot.
    // UserFactory::admin() attaches a warehouse in afterCreating; detach
    // here so the pivot is genuinely empty — this is the premise under test.
    $admin = User::factory()->admin()->create();
    $admin->warehouses()->detach();
    $this->actingAs($admin);

    expect($admin->warehouses()->count())->toBe(0);

    PurchaseOrder::factory()->count(3)->create([
        'status' => App\Enums\PurchaseOrderStatus::Ordered,
    ]);

    expect(PurchaseOrderResource::getNavigationBadge())->toBe('3');
});

// ---------------------------------------------------------------------------
// WarehouseStaff — cardinality-driven tiers
// ---------------------------------------------------------------------------

it('scopes the badge count to exactly the single assigned warehouse (N=1)', function () {
    // §1B.1a / A12: N==1 is exact — no widening, no counterpart union.
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();

    $staff = User::factory()->create();
    $staff->warehouses()->attach($warehouseA->id);
    $this->actingAs($staff);

    PurchaseOrder::factory()->count(2)->create([
        'warehouse_id' => $warehouseA->id,
        'status' => App\Enums\PurchaseOrderStatus::Ordered,
    ]);
    PurchaseOrder::factory()->count(5)->create([
        'warehouse_id' => $warehouseB->id,
        'status' => App\Enums\PurchaseOrderStatus::Ordered,
    ]);

    expect(PurchaseOrderResource::getNavigationBadge())->toBe('2');
});

it('scopes the badge count to the union of assigned warehouses (N≥2)', function () {
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();
    $warehouseC = Warehouse::factory()->create();

    $staff = User::factory()->create();
    $staff->warehouses()->attach([$warehouseA->id, $warehouseB->id]);
    $this->actingAs($staff);

    SalesOrder::factory()->count(2)->create([
        'warehouse_id' => $warehouseA->id,
        'status' => App\Enums\SalesOrderStatus::Confirmed,
    ]);
    SalesOrder::factory()->count(3)->create([
        'warehouse_id' => $warehouseB->id,
        'status' => App\Enums\SalesOrderStatus::Confirmed,
    ]);
    SalesOrder::factory()->count(10)->create([
        'warehouse_id' => $warehouseC->id,
        'status' => App\Enums\SalesOrderStatus::Confirmed,
    ]);

    expect(SalesOrderResource::getNavigationBadge())->toBe('5');
});

it('returns null for warehouse staff with no assignments (N=0)', function () {
    PurchaseOrder::factory()->count(5)->create([
        'status' => App\Enums\PurchaseOrderStatus::Ordered,
    ]);

    $staff = User::factory()->create();
    $this->actingAs($staff);

    expect(PurchaseOrderResource::getNavigationBadge())->toBeNull();
});

// ---------------------------------------------------------------------------
// The counterpart warehouse does not widen scope — §1B.1b / A12
// ---------------------------------------------------------------------------

it('does not widen the transfer requisition badge for a single-warehouse user', function () {
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();

    $staff = User::factory()->create();
    $staff->warehouses()->attach($warehouseA->id);
    $this->actingAs($staff);

    // A → B: touches A on source — counted
    TransferRequisition::factory()->create([
        'from_warehouse_id' => $warehouseA->id,
        'to_warehouse_id' => $warehouseB->id,
        'status' => App\Enums\TransferRequisitionStatus::Requested,
    ]);
    // B → A: touches A on destination — counted
    TransferRequisition::factory()->create([
        'from_warehouse_id' => $warehouseB->id,
        'to_warehouse_id' => $warehouseA->id,
        'status' => App\Enums\TransferRequisitionStatus::Requested,
    ]);

    // 2 total, not 4 — a badge of 2 proves the counterpart B does not
    // contribute. A naive OR-scope on the whole requisition would show 2,
    // but the exclusion proves the scope is per-endpoint.
    expect(TransferRequisitionResource::getNavigationBadge())->toBe('2');
});

// ---------------------------------------------------------------------------
// BranchManager — assigned-warehouse tier
// ---------------------------------------------------------------------------

it('scopes the branch manager badge to assigned warehouses', function () {
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();

    $bm = User::factory()->branchManager()->create();
    $bm->warehouses()->attach($warehouseA->id);
    $this->actingAs($bm);

    PurchaseOrder::factory()->count(4)->create([
        'warehouse_id' => $warehouseA->id,
        'status' => App\Enums\PurchaseOrderStatus::Ordered,
    ]);
    PurchaseOrder::factory()->count(10)->create([
        'warehouse_id' => $warehouseB->id,
        'status' => App\Enums\PurchaseOrderStatus::Ordered,
    ]);

    expect(PurchaseOrderResource::getNavigationBadge())->toBe('4');
});

it('returns null for a branch manager with no warehouse assignments', function () {
    PurchaseOrder::factory()->count(3)->create([
        'status' => App\Enums\PurchaseOrderStatus::Ordered,
    ]);

    $bm = User::factory()->branchManager()->create();
    $this->actingAs($bm);

    expect(PurchaseOrderResource::getNavigationBadge())->toBeNull();
});

// ---------------------------------------------------------------------------
// Badge count status filter — only the "awaiting" status contributes
// ---------------------------------------------------------------------------

it('counts only Requested requisitions in the TransferRequisition badge', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    TransferRequisition::factory()->count(2)->create([
        'status' => App\Enums\TransferRequisitionStatus::Requested,
    ]);
    TransferRequisition::factory()->count(5)->create([
        'status' => App\Enums\TransferRequisitionStatus::Confirmed,
    ]);

    expect(TransferRequisitionResource::getNavigationBadge())->toBe('2');
});

it('counts only Ordered purchase orders in the PurchaseOrder badge', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    PurchaseOrder::factory()->count(3)->create([
        'status' => App\Enums\PurchaseOrderStatus::Ordered,
    ]);
    PurchaseOrder::factory()->count(5)->create([
        'status' => App\Enums\PurchaseOrderStatus::Received,
    ]);

    expect(PurchaseOrderResource::getNavigationBadge())->toBe('3');
});

it('counts only Confirmed sales orders in the SalesOrder badge', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    SalesOrder::factory()->count(4)->create([
        'status' => App\Enums\SalesOrderStatus::Confirmed,
    ]);
    SalesOrder::factory()->count(5)->create([
        'status' => App\Enums\SalesOrderStatus::Dispatched,
    ]);

    expect(SalesOrderResource::getNavigationBadge())->toBe('4');
});

it('counts only InTransit cargo in the InTransit badge', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    InTransit::factory()->count(3)->create([
        'status' => App\Enums\InTransitStatus::InTransit,
    ]);
    InTransit::factory()->count(5)->create([
        'status' => App\Enums\InTransitStatus::Cleared,
        'cleared_at' => now(),
    ]);

    expect(InTransitResource::getNavigationBadge())->toBe('3');
});

// ---------------------------------------------------------------------------
// Badge color threshold
// ---------------------------------------------------------------------------

it('uses primary color for counts up to 10', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    PurchaseOrder::factory()->count(5)->create([
        'status' => App\Enums\PurchaseOrderStatus::Ordered,
    ]);

    expect(PurchaseOrderResource::getNavigationBadgeColor())->toBe('primary');
});

it('uses warning color above 10', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    PurchaseOrder::factory()->count(11)->create([
        'status' => App\Enums\PurchaseOrderStatus::Ordered,
    ]);

    expect(PurchaseOrderResource::getNavigationBadgeColor())->toBe('warning');
});

// ---------------------------------------------------------------------------
// Per-request caching + flush
// ---------------------------------------------------------------------------

it('caches the badge count within the request', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    PurchaseOrder::factory()->count(2)->create([
        'status' => App\Enums\PurchaseOrderStatus::Ordered,
    ]);

    // Warm the cache.
    PurchaseOrderResource::getNavigationBadge();

    // Insert one more matching row after the cache is warm.
    PurchaseOrder::factory()->create([
        'status' => App\Enums\PurchaseOrderStatus::Ordered,
    ]);

    // Second call reads the cached count (2).
    expect(PurchaseOrderResource::getNavigationBadge())->toBe('2');

    // After flush, the count picks up the new row (3).
    PurchaseOrderResource::flushBadgeScope();
    expect(PurchaseOrderResource::getNavigationBadge())->toBe('3');
});

it('returns null when no records match', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    expect(PurchaseOrderResource::getNavigationBadge())->toBeNull();
});
