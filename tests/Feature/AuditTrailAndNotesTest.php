<?php

declare(strict_types=1);

use App\Enums\MovementType;
use App\Enums\TransferRequisitionStatus;
use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AuditService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->warehouse = Warehouse::factory()->create();
    $this->user = User::factory()->warehouseStaff()->create();
    $this->inventoryService = app(InventoryService::class);
    $this->auditService = app(AuditService::class);
});

describe('Stock Movement Notes', function () {
    test('recordMovement persists notes on stock_movements', function () {
        $variant = ProductVariant::factory()->create();

        $movement = $this->inventoryService->recordMovement(
            variantId: $variant->id,
            warehouseId: $this->warehouse->id,
            type: MovementType::Receive,
            baseQuantity: 100,
            unitName: 'pcs',
            unitRatio: 1,
            referenceType: null,
            referenceId: null,
            referenceCode: null,
            relatedMovementId: null,
            reference: null,
            notes: 'Received 100 units from supplier ABC'
        );

        $this->assertDatabaseHas('stock_movements', [
            'id' => $movement->id,
            'notes' => 'Received 100 units from supplier ABC',
        ]);
    });

    test('recordMovement handles null notes', function () {
        $variant = ProductVariant::factory()->create();

        $movement = $this->inventoryService->recordMovement(
            variantId: $variant->id,
            warehouseId: $this->warehouse->id,
            type: MovementType::Receive,
            baseQuantity: 100,
            unitName: 'pcs',
            unitRatio: 1,
            referenceType: null,
            referenceId: null,
            referenceCode: null,
            relatedMovementId: null,
            reference: null,
            notes: null
        );

        $this->assertDatabaseHas('stock_movements', [
            'id' => $movement->id,
            'notes' => null,
        ]);
    });
});

describe('Requisition Audit Trail', function () {
    test('lockStockForRequisition creates confirmed audit entry', function () {
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id' => Warehouse::factory()->create(),
            'requested_by' => $this->user->id,
            'status' => TransferRequisitionStatus::Draft,
        ]);

        $this->inventoryService->lockStockForRequisition(
            requisitionId: $requisition->id,
            user: $this->user
        );

        $this->assertDatabaseHas('transfer_requisition_audits', [
            'transfer_requisition_id' => $requisition->id,
            'action' => 'confirmed',
            'user_id' => $this->user->id,
        ]);

        $requisition->refresh();
        expect($requisition->status)->toBe(TransferRequisitionStatus::Confirmed);
    });

    test('dispatchTransfer creates dispatched audit entry', function () {
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id' => Warehouse::factory()->create(),
            'requested_by' => $this->user->id,
            'status' => TransferRequisitionStatus::Confirmed,
        ]);

        $this->inventoryService->dispatchTransfer(
            requisitionId: $requisition->id,
            user: $this->user
        );

        $this->assertDatabaseHas('transfer_requisition_audits', [
            'transfer_requisition_id' => $requisition->id,
            'action' => 'dispatched',
            'user_id' => $this->user->id,
        ]);

        $requisition->refresh();
        expect($requisition->status)->toBe(TransferRequisitionStatus::Dispatched);
    });

    test('scanToReceive creates received audit entry with correct status', function () {
        $variant = ProductVariant::factory()->create([
            'cost_price' => 10.00,
        ]);

        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id' => Warehouse::factory()->create(),
            'requested_by' => $this->user->id,
            'status' => TransferRequisitionStatus::Dispatched,
            'reference_code' => 'TRQ-TEST-001',
        ]);

        $item = $requisition->items()->create([
            'variant_id' => $variant->id,
            'requested_unit_name' => 'pcs',
            'requested_unit_ratio' => 1,
            'requested_qty' => 100,
            'requested_base_qty' => 100,
            'shipped_base_qty' => 100,
        ]);

        $receivedItemsData = [
            $item->id => [
                'good_qty' => 95,
                'damaged_qty' => 3,
            ],
        ];

        $this->inventoryService->scanToReceive(
            requisitionId: $requisition->id,
            receivedItemsData: $receivedItemsData,
            userId: $this->user->id,
            user: $this->user
        );

        $this->assertDatabaseHas('transfer_requisition_audits', [
            'transfer_requisition_id' => $requisition->id,
            'action' => 'received',
            'user_id' => $this->user->id,
        ]);

        $requisition->refresh();
        expect($requisition->status)->toBe(TransferRequisitionStatus::ClosedWithLoss);
    });
});

describe('Requisition Submission and Counter-Offer Audit', function () {
    test('submit creates submitted audit entry', function () {
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id' => Warehouse::factory()->create(),
            'requested_by' => $this->user->id,
            'status' => TransferRequisitionStatus::Draft,
        ]);

        $this->auditService->recordRequisition(
            requisition: $requisition,
            user: $this->user,
            action: 'submitted',
            changes: ['status' => 'requested']
        );

        $this->assertDatabaseHas('transfer_requisition_audits', [
            'transfer_requisition_id' => $requisition->id,
            'action' => 'submitted',
            'user_id' => $this->user->id,
        ]);
    });

    test('counter_offer creates counter_offered audit entry', function () {
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id' => Warehouse::factory()->create(),
            'requested_by' => $this->user->id,
            'status' => TransferRequisitionStatus::Requested,
        ]);

        $this->auditService->recordRequisition(
            requisition: $requisition,
            user: $this->user,
            action: 'counter_offered',
            changes: ['status' => 'under_review_requestor', 'notes' => 'Need more details']
        );

        $this->assertDatabaseHas('transfer_requisition_audits', [
            'transfer_requisition_id' => $requisition->id,
            'action' => 'counter_offered',
            'user_id' => $this->user->id,
        ]);
    });
});
