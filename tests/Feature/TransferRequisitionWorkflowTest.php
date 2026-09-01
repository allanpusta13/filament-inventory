<?php

declare(strict_types=1);

use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = new InventoryService();
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->whMnl = Warehouse::factory()->create(['code' => 'WH-MNL']);
    $this->whCeb = Warehouse::factory()->create(['code' => 'WH-CEB']);
    $this->variant = ProductVariant::factory()->create(['base_unit_name' => 'gram']);

    WarehouseStock::create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->whMnl->id,
        'on_hand_quantity' => 10000,
        'reserved_quantity' => 0,
    ]);

    WarehouseStock::create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->whCeb->id,
        'on_hand_quantity' => 0,
        'reserved_quantity' => 0,
    ]);
});

it('completes full requisition lifecycle: draft to completed', function (): void {
    $this->actingAs($this->admin);

    $requisition = TransferRequisition::create([
        'reference_code' => 'TRQ-TEST-0001',
        'from_warehouse_id' => $this->whMnl->id,
        'to_warehouse_id' => $this->whCeb->id,
        'status' => 'draft',
        'requested_by' => $this->admin->id,
        'requested_at' => now(),
    ]);

    TransferRequisitionItem::create([
        'requisition_id' => $requisition->id,
        'variant_id' => $this->variant->id,
        'requested_unit_name' => 'gram',
        'requested_unit_ratio' => 1,
        'requested_qty' => 5000,
        'requested_base_qty' => 5000,
        'approved_unit_name' => 'gram',
        'approved_unit_ratio' => 1,
        'approved_qty' => 5000,
        'approved_base_qty' => 5000,
    ]);

    // Step 1: Submit
    $requisition->update(['status' => 'requested']);
    expect($requisition->fresh()->status->value)->toBe('requested');

    // Step 2: Confirm (locks stock)
    $this->service->lockStockForRequisition($requisition->id);
    expect($requisition->fresh()->status->value)->toBe('confirmed');

    $stock = WarehouseStock::where('variant_id', $this->variant->id)
        ->where('warehouse_id', $this->whMnl->id)
        ->first();
    expect($stock->reserved_quantity)->toBe(5000);

    // Step 3: Dispatch
    $this->service->dispatchTransfer($requisition->id);
    expect($requisition->fresh()->status->value)->toBe('dispatched');

    $stock->refresh();
    expect($stock->on_hand_quantity)->toBe(5000);
    expect($stock->reserved_quantity)->toBe(0);

    // Step 4: Receive
    $this->service->scanToReceive($requisition->id, [
        $requisition->items->first()->id => [
            'good_qty' => 5000,
            'damaged_qty' => 0,
        ],
    ]);

    expect($requisition->fresh()->status->value)->toBe('completed');

    $destStock = WarehouseStock::where('variant_id', $this->variant->id)
        ->where('warehouse_id', $this->whCeb->id)
        ->first();
    expect($destStock->on_hand_quantity)->toBe(5000);
});

it('handles receiving with loss and writes to loss_ledger', function (): void {
    $this->actingAs($this->admin);

    $requisition = TransferRequisition::create([
        'reference_code' => 'TRQ-TEST-0002',
        'from_warehouse_id' => $this->whMnl->id,
        'to_warehouse_id' => $this->whCeb->id,
        'status' => 'requested',
        'requested_by' => $this->admin->id,
        'requested_at' => now(),
    ]);

    TransferRequisitionItem::create([
        'requisition_id' => $requisition->id,
        'variant_id' => $this->variant->id,
        'requested_unit_name' => 'gram',
        'requested_unit_ratio' => 1,
        'requested_qty' => 1000,
        'requested_base_qty' => 1000,
        'approved_unit_name' => 'gram',
        'approved_unit_ratio' => 1,
        'approved_qty' => 1000,
        'approved_base_qty' => 1000,
    ]);

    $this->service->lockStockForRequisition($requisition->id);
    $this->service->dispatchTransfer($requisition->id);

    $this->service->scanToReceive($requisition->id, [
        $requisition->items->first()->id => [
            'good_qty' => 900,
            'damaged_qty' => 100,
            'loss_category' => 'Damaged in Transit',
        ],
    ]);

    expect($requisition->fresh()->status->value)->toBe('closed_with_loss');

    $this->assertDatabaseHas('loss_ledgers', [
        'variant_id' => $this->variant->id,
        'damaged_base_qty' => 100,
        'loss_category' => 'Damaged in Transit',
    ]);

    $destStock = WarehouseStock::where('variant_id', $this->variant->id)
        ->where('warehouse_id', $this->whCeb->id)
        ->first();
    expect($destStock->on_hand_quantity)->toBe(900);
});
