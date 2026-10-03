<?php

declare(strict_types=1);

use App\Filament\Resources\DirectTransfers\DirectTransferResource;
use App\Filament\Resources\InTransits\InTransitResource;
use App\Filament\Resources\LossLedgers\LossLedgerResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use App\Models\DirectTransfer;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * §23.5 — Warehouse Query Scope.
 *
 * Verifies query-level warehouse filtering — not just visibility. A
 * resource's getEloquentQuery() must apply scope before pagination so
 * a user cannot page through records from unrelated warehouses.
 */
uses(RefreshDatabase::class);

it('scopes TransferRequisitionResource to records touching the assigned warehouse', function () {
    [$a, $b, $c] = Warehouse::factory()->count(3)->create();

    $staff = User::factory()->create();
    $staff->warehouses()->attach($a->id);
    $this->actingAs($staff);

    // Touches A (source) — visible
    TransferRequisition::factory()->create([
        'from_warehouse_id' => $a->id,
        'to_warehouse_id' => $b->id,
    ]);
    // Touches A (destination) — visible
    TransferRequisition::factory()->create([
        'from_warehouse_id' => $b->id,
        'to_warehouse_id' => $a->id,
    ]);
    // Does not touch A — hidden
    TransferRequisition::factory()->create([
        'from_warehouse_id' => $b->id,
        'to_warehouse_id' => $c->id,
    ]);

    expect(TransferRequisitionResource::getEloquentQuery()->count())->toBe(2);
});

it('scopes PurchaseOrderResource to records in the assigned warehouse', function () {
    [$a, $b] = Warehouse::factory()->count(2)->create();

    $staff = User::factory()->create();
    $staff->warehouses()->attach($a->id);
    $this->actingAs($staff);

    PurchaseOrder::factory()->count(2)->create(['warehouse_id' => $a->id]);
    PurchaseOrder::factory()->create(['warehouse_id' => $b->id]);

    expect(PurchaseOrderResource::getEloquentQuery()->count())->toBe(2);
});

it('scopes SalesOrderResource to records in the assigned warehouse', function () {
    [$a, $b] = Warehouse::factory()->count(2)->create();

    $staff = User::factory()->create();
    $staff->warehouses()->attach($a->id);
    $this->actingAs($staff);

    SalesOrder::factory()->count(3)->create(['warehouse_id' => $a->id]);
    SalesOrder::factory()->create(['warehouse_id' => $b->id]);

    expect(SalesOrderResource::getEloquentQuery()->count())->toBe(3);
});

it('scopes StockMovementResource to records in the assigned warehouse', function () {
    [$a, $b] = Warehouse::factory()->count(2)->create();
    $variant = ProductVariant::factory()->create();

    $staff = User::factory()->create();
    $staff->warehouses()->attach($a->id);
    $this->actingAs($staff);

    StockMovement::factory()->count(4)->create([
        'warehouse_id' => $a->id,
        'product_variant_id' => $variant->id,
    ]);
    StockMovement::factory()->count(2)->create([
        'warehouse_id' => $b->id,
        'product_variant_id' => $variant->id,
    ]);

    expect(StockMovementResource::getEloquentQuery()->count())->toBe(4);
});

it('scopes LossLedgerResource to records in the assigned warehouse', function () {
    [$a, $b] = Warehouse::factory()->count(2)->create();
    $variant = ProductVariant::factory()->create();

    $staff = User::factory()->create();
    $staff->warehouses()->attach($a->id);
    $this->actingAs($staff);

    LossLedger::factory()->count(2)->create([
        'warehouse_id' => $a->id,
        'product_variant_id' => $variant->id,
    ]);
    LossLedger::factory()->create([
        'warehouse_id' => $b->id,
        'product_variant_id' => $variant->id,
    ]);

    expect(LossLedgerResource::getEloquentQuery()->count())->toBe(2);
});

it('scopes InTransitResource through the parent requisition endpoints', function () {
    [$a, $b, $c] = Warehouse::factory()->count(3)->create();
    $variant = ProductVariant::factory()->create();

    $staff = User::factory()->create();
    $staff->warehouses()->attach($a->id);
    $this->actingAs($staff);

    $touchesA = TransferRequisition::factory()->create([
        'from_warehouse_id' => $a->id,
        'to_warehouse_id' => $b->id,
    ]);
    $notTouchingA = TransferRequisition::factory()->create([
        'from_warehouse_id' => $b->id,
        'to_warehouse_id' => $c->id,
    ]);

    App\Models\InTransit::factory()->create([
        'transfer_requisition_id' => $touchesA->id,
        'product_variant_id' => $variant->id,
    ]);
    App\Models\InTransit::factory()->create([
        'transfer_requisition_id' => $notTouchingA->id,
        'product_variant_id' => $variant->id,
    ]);

    expect(InTransitResource::getEloquentQuery()->count())->toBe(1);
});

it('scopes DirectTransferResource by BOTH endpoints (AND-scope)', function () {
    // §20.1: a direct transfer moves stock between two warehouses, so a
    // non-privileged user must be assigned to BOTH endpoints to see it.
    [$a, $b, $c] = Warehouse::factory()->count(3)->create();

    $staff = User::factory()->create();
    $staff->warehouses()->attach([$a->id, $b->id]);
    $this->actingAs($staff);

    // A → B: both endpoints assigned — visible
    DirectTransfer::factory()->create([
        'from_warehouse_id' => $a->id,
        'to_warehouse_id' => $b->id,
    ]);
    // A → C: only A assigned — hidden
    DirectTransfer::factory()->create([
        'from_warehouse_id' => $a->id,
        'to_warehouse_id' => $c->id,
    ]);
    // B → C: only B assigned — hidden
    DirectTransfer::factory()->create([
        'from_warehouse_id' => $b->id,
        'to_warehouse_id' => $c->id,
    ]);

    expect(DirectTransferResource::getEloquentQuery()->count())->toBe(1);
});

it('does not scope queries for an admin', function () {
    [$a, $b] = Warehouse::factory()->count(2)->create();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    PurchaseOrder::factory()->count(2)->create(['warehouse_id' => $a->id]);
    PurchaseOrder::factory()->count(3)->create(['warehouse_id' => $b->id]);

    // Admin sees everything regardless of warehouse assignment.
    expect(PurchaseOrderResource::getEloquentQuery()->count())->toBe(5);
});

it('does not scope queries for an auditor', function () {
    [$a, $b] = Warehouse::factory()->count(2)->create();

    $auditor = User::factory()->auditor()->create();
    $this->actingAs($auditor);

    PurchaseOrder::factory()->count(2)->create(['warehouse_id' => $a->id]);
    PurchaseOrder::factory()->count(3)->create(['warehouse_id' => $b->id]);

    expect(PurchaseOrderResource::getEloquentQuery()->count())->toBe(5);
});
