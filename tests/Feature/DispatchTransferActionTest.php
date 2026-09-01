<?php

declare(strict_types=1);

use App\Actions\DispatchTransferAction;
use App\Enums\MovementType;
use App\Enums\TransferOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\TransferOrder;
use App\Models\TransferOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AuditService;
use App\Services\InventoryService;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

beforeEach(function (): void {
    $this->service = new InventoryService();
    $this->auditService = new AuditService();
    $this->action = new DispatchTransferAction($this->service, $this->auditService);
    $this->admin = User::factory()->admin()->create();
    $this->senderWarehouse = Warehouse::factory()->create(['is_active' => true]);
    $this->receiverWarehouse = Warehouse::factory()->create(['is_active' => true]);
    $this->staff = User::factory()->warehouseStaff()->create();
    $this->staff->warehouses()->attach($this->senderWarehouse->id);
    $this->product = Product::factory()->create();
    $this->variant = ProductVariant::factory()->create(['product_id' => $this->product->id]);
});

test('it dispatches a confirmed order', function (): void {
    $this->service->recordMovement(
        variantId: $this->variant->id,
        warehouseId: $this->senderWarehouse->id,
        type: MovementType::Receive,
        baseQuantity: 100,
    );

    $order = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 30,
        'approved_quantity' => 30,
        'item_status' => 'approved',
    ]);

    $this->action->dispatch($order, $this->admin);

    $order->refresh();

    expect($order->status)->toBe(TransferOrderStatus::Dispatched)
        ->and($order->dispatched_by)->toBe($this->admin->id)
        ->and($order->dispatched_at)->not->toBeNull();

    assertDatabaseHas(StockMovement::class, [
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->senderWarehouse->id,
        'type' => MovementType::TransferOut,
        'quantity' => -30,
    ]);

    expect($this->service->currentQuantity($this->product->id, $this->senderWarehouse->id))->toBe(70);
});

test('it sets dispatched_by and dispatched_at', function (): void {
    $this->service->recordMovement(
        variantId: $this->variant->id,
        warehouseId: $this->senderWarehouse->id,
        type: MovementType::Receive,
        baseQuantity: 50,
    );

    $order = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 10,
        'approved_quantity' => 10,
        'item_status' => 'approved',
    ]);

    $this->action->dispatch($order, $this->staff);

    $order->refresh();

    expect($order->dispatched_by)->toBe($this->staff->id)
        ->and($order->dispatched_at)->not->toBeNull();
});

test('it creates transfer_out movements for each item', function (): void {
    $product2 = Product::factory()->create();
    $variant2 = ProductVariant::factory()->create(['product_id' => $product2->id]);

    $this->service->recordMovement(
        variantId: $this->variant->id,
        warehouseId: $this->senderWarehouse->id,
        type: MovementType::Receive,
        baseQuantity: 100,
    );
    $this->service->recordMovement(
        variantId: $variant2->id,
        warehouseId: $this->senderWarehouse->id,
        type: MovementType::Receive,
        baseQuantity: 100,
    );

    $order = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 20,
        'approved_quantity' => 20,
        'item_status' => 'approved',
    ]);
    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $product2->id,
        'requested_quantity' => 15,
        'approved_quantity' => 15,
        'item_status' => 'approved',
    ]);

    $this->action->dispatch($order, $this->admin);

    assertDatabaseHas(StockMovement::class, [
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->senderWarehouse->id,
        'type' => MovementType::TransferOut,
        'quantity' => -20,
    ]);

    assertDatabaseHas(StockMovement::class, [
        'variant_id' => $variant2->id,
        'warehouse_id' => $this->senderWarehouse->id,
        'type' => MovementType::TransferOut,
        'quantity' => -15,
    ]);
});

test('it throws on insufficient stock', function (): void {
    $this->service->recordMovement(
        variantId: $this->variant->id,
        warehouseId: $this->senderWarehouse->id,
        type: MovementType::Receive,
        baseQuantity: 10,
    );

    $order = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 50,
        'approved_quantity' => 50,
        'item_status' => 'approved',
    ]);

    $this->action->dispatch($order, $this->admin);
})->throws(InsufficientStockException::class);

test('it rolls back all movements on partial failure', function (): void {
    $product2 = Product::factory()->create();
    $variant2 = ProductVariant::factory()->create(['product_id' => $product2->id]);

    $this->service->recordMovement(
        variantId: $this->variant->id,
        warehouseId: $this->senderWarehouse->id,
        type: MovementType::Receive,
        baseQuantity: 100,
    );
    $this->service->recordMovement(
        variantId: $variant2->id,
        warehouseId: $this->senderWarehouse->id,
        type: MovementType::Receive,
        baseQuantity: 5,
    );

    $order = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 20,
        'approved_quantity' => 20,
        'item_status' => 'approved',
    ]);
    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $product2->id,
        'requested_quantity' => 20,
        'approved_quantity' => 20,
        'item_status' => 'approved',
    ]);

    try {
        $this->action->dispatch($order, $this->admin);
    } catch (InsufficientStockException) {
        // Expected
    }

    assertDatabaseMissing(StockMovement::class, [
        'variant_id' => $this->variant->id,
        'type' => MovementType::TransferOut,
    ]);

    assertDatabaseMissing(StockMovement::class, [
        'variant_id' => $variant2->id,
        'type' => MovementType::TransferOut,
    ]);

    $order->refresh();
    expect($order->status)->toBe(TransferOrderStatus::Confirmed);
});

test('it rejects already dispatched order', function (): void {
    $order = TransferOrder::factory()->dispatched()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    $this->action->dispatch($order, $this->admin);
})->throws(RuntimeException::class, 'Only confirmed transfer orders can be dispatched.');

test('it rejects cancelled order', function (): void {
    $order = TransferOrder::factory()->cancelled()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    $this->action->dispatch($order, $this->admin);
})->throws(RuntimeException::class, 'Only confirmed transfer orders can be dispatched.');

test('it rejects draft order from unauthorized user', function (): void {
    $order = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    $unauthorizedStaff = User::factory()->warehouseStaff()->create();

    $this->action->dispatch($order, $unauthorizedStaff);
})->throws(RuntimeException::class, 'You are not authorized to dispatch this transfer order.');

test('it allows admin to dispatch any order', function (): void {
    $this->service->recordMovement(
        variantId: $this->variant->id,
        warehouseId: $this->senderWarehouse->id,
        type: MovementType::Receive,
        baseQuantity: 100,
    );

    $order = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 10,
        'approved_quantity' => 10,
        'item_status' => 'approved',
    ]);

    $this->action->dispatch($order, $this->admin);

    $order->refresh();
    expect($order->status)->toBe(TransferOrderStatus::Dispatched);
});

test('it rejects order with zero items', function (): void {
    $order = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    $this->action->dispatch($order, $this->admin);
})->throws(RuntimeException::class, 'Cannot dispatch a transfer order with no items.');

test('it validates stock after lock acquisition', function (): void {
    $this->service->recordMovement(
        variantId: $this->variant->id,
        warehouseId: $this->senderWarehouse->id,
        type: MovementType::Receive,
        baseQuantity: 10,
    );

    $order = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 20,
        'approved_quantity' => 20,
        'item_status' => 'approved',
    ]);

    $this->action->dispatch($order, $this->admin);
})->throws(InsufficientStockException::class);
