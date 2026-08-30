<?php

declare(strict_types=1);

use App\Actions\DispatchTransferAction;
use App\Actions\ReceiveTransferAction;
use App\Enums\MovementType;
use App\Enums\TransferOrderStatus;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\TransferOrder;
use App\Models\TransferOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AuditService;
use App\Services\InventoryService;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function (): void {
    $this->service = new InventoryService();
    $this->auditService = new AuditService();
    $this->dispatchAction = new DispatchTransferAction($this->service, $this->auditService);
    $this->receiveAction = new ReceiveTransferAction($this->service, $this->auditService);
    $this->admin = User::factory()->admin()->create();
    $this->senderWarehouse = Warehouse::factory()->create(['is_active' => true]);
    $this->receiverWarehouse = Warehouse::factory()->create(['is_active' => true]);
    $this->senderStaff = User::factory()->warehouseStaff()->create();
    $this->senderStaff->warehouses()->attach($this->senderWarehouse->id);
    $this->receiverStaff = User::factory()->warehouseStaff()->create();
    $this->receiverStaff->warehouses()->attach($this->receiverWarehouse->id);
    $this->product = Product::factory()->create();
});

test('it receives a dispatched order', function (): void {
    $this->service->recordMovement(
        productId: $this->product->id,
        warehouseId: $this->senderWarehouse->id,
        type: MovementType::Receive,
        quantity: 100,
    );

    $order = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    $item = TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 30,
        'approved_quantity' => 30,
    ]);

    $this->dispatchAction->dispatch($order, $this->admin);

    $this->receiveAction->receive($order, $this->admin, [
        ['transfer_order_item_id' => $item->id, 'quantity_received' => 30],
    ]);

    $order->refresh();
    $item->refresh();

    expect($order->status)->toBe(TransferOrderStatus::Received)
        ->and($order->received_by)->toBe($this->admin->id)
        ->and($order->received_at)->not->toBeNull()
        ->and($item->received_quantity)->toBe(30);

    assertDatabaseHas(StockMovement::class, [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->receiverWarehouse->id,
        'type' => MovementType::TransferIn,
        'quantity' => 30,
    ]);
});

test('it sets received_by and received_at', function (): void {
    $this->service->recordMovement(
        productId: $this->product->id,
        warehouseId: $this->senderWarehouse->id,
        type: MovementType::Receive,
        quantity: 50,
    );

    $order = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    $item = TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 10,
        'approved_quantity' => 10,
    ]);

    $this->dispatchAction->dispatch($order, $this->senderStaff);

    $this->receiveAction->receive($order, $this->receiverStaff, [
        ['transfer_order_item_id' => $item->id, 'quantity_received' => 10],
    ]);

    $order->refresh();

    expect($order->received_by)->toBe($this->receiverStaff->id)
        ->and($order->received_at)->not->toBeNull();
});

test('it handles partial receive with reason', function (): void {
    $this->service->recordMovement(
        productId: $this->product->id,
        warehouseId: $this->senderWarehouse->id,
        type: MovementType::Receive,
        quantity: 100,
    );

    $order = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    $item = TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 30,
        'approved_quantity' => 30,
    ]);

    $this->dispatchAction->dispatch($order, $this->admin);

    $this->receiveAction->receive($order, $this->admin, [
        ['transfer_order_item_id' => $item->id, 'quantity_received' => 20, 'variance_reason' => '2 units damaged in transit'],
    ]);

    $item->refresh();

    expect($item->received_quantity)->toBe(20)
        ->and($item->variance_reason)->toBe('2 units damaged in transit');
});

test('it allows partial receive without reason', function (): void {
    $this->service->recordMovement(
        productId: $this->product->id,
        warehouseId: $this->senderWarehouse->id,
        type: MovementType::Receive,
        quantity: 100,
    );

    $order = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    $item = TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 30,
        'approved_quantity' => 30,
    ]);

    $this->dispatchAction->dispatch($order, $this->admin);

    $this->receiveAction->receive($order, $this->admin, [
        ['transfer_order_item_id' => $item->id, 'quantity_received' => 20],
    ]);

    $item->refresh();

    expect($item->received_quantity)->toBe(20)
        ->and($item->variance_reason)->toBeNull();
});

test('it rejects over-receiving', function (): void {
    $this->service->recordMovement(
        productId: $this->product->id,
        warehouseId: $this->senderWarehouse->id,
        type: MovementType::Receive,
        quantity: 100,
    );

    $order = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    $item = TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 30,
        'approved_quantity' => 30,
    ]);

    $this->dispatchAction->dispatch($order, $this->admin);

    $this->receiveAction->receive($order, $this->admin, [
        ['transfer_order_item_id' => $item->id, 'quantity_received' => 40],
    ]);
})->throws(InvalidArgumentException::class, 'exceeds approved quantity');

test('it creates transfer_in movements for each item', function (): void {
    $product2 = Product::factory()->create();

    $this->service->recordMovement(
        productId: $this->product->id,
        warehouseId: $this->senderWarehouse->id,
        type: MovementType::Receive,
        quantity: 100,
    );
    $this->service->recordMovement(
        productId: $product2->id,
        warehouseId: $this->senderWarehouse->id,
        type: MovementType::Receive,
        quantity: 100,
    );

    $order = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    $item1 = TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 20,
        'approved_quantity' => 20,
    ]);
    $item2 = TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $product2->id,
        'requested_quantity' => 15,
        'approved_quantity' => 15,
    ]);

    $this->dispatchAction->dispatch($order, $this->admin);

    $this->receiveAction->receive($order, $this->admin, [
        ['transfer_order_item_id' => $item1->id, 'quantity_received' => 20],
        ['transfer_order_item_id' => $item2->id, 'quantity_received' => 15],
    ]);

    assertDatabaseHas(StockMovement::class, [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->receiverWarehouse->id,
        'type' => MovementType::TransferIn,
        'quantity' => 20,
    ]);

    assertDatabaseHas(StockMovement::class, [
        'product_id' => $product2->id,
        'warehouse_id' => $this->receiverWarehouse->id,
        'type' => MovementType::TransferIn,
        'quantity' => 15,
    ]);
});

test('it rejects draft order', function (): void {
    $order = TransferOrder::factory()->draft()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    $item = TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 10,
        'approved_quantity' => 10,
    ]);

    $this->receiveAction->receive($order, $this->admin, [
        ['transfer_order_item_id' => $item->id, 'quantity_received' => 10],
    ]);
})->throws(RuntimeException::class, 'Only dispatched transfer orders can be received.');

test('it rejects already received order', function (): void {
    $order = TransferOrder::factory()->received()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    $item = TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 10,
        'approved_quantity' => 10,
        'received_quantity' => 10,
    ]);

    $this->receiveAction->receive($order, $this->admin, [
        ['transfer_order_item_id' => $item->id, 'quantity_received' => 10],
    ]);
})->throws(RuntimeException::class, 'Only dispatched transfer orders can be received.');

test('it rejects receive from unauthorized user', function (): void {
    $this->service->recordMovement(
        productId: $this->product->id,
        warehouseId: $this->senderWarehouse->id,
        type: MovementType::Receive,
        quantity: 100,
    );

    $order = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    $item = TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 10,
        'approved_quantity' => 10,
    ]);

    $this->dispatchAction->dispatch($order, $this->admin);

    $unauthorizedStaff = User::factory()->warehouseStaff()->create();

    $this->receiveAction->receive($order, $unauthorizedStaff, [
        ['transfer_order_item_id' => $item->id, 'quantity_received' => 10],
    ]);
})->throws(RuntimeException::class, 'You are not authorized to receive this transfer order.');

test('it allows admin to receive any order', function (): void {
    $this->service->recordMovement(
        productId: $this->product->id,
        warehouseId: $this->senderWarehouse->id,
        type: MovementType::Receive,
        quantity: 100,
    );

    $order = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    $item = TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 10,
        'approved_quantity' => 10,
    ]);

    $this->dispatchAction->dispatch($order, $this->admin);

    $this->receiveAction->receive($order, $this->admin, [
        ['transfer_order_item_id' => $item->id, 'quantity_received' => 10],
    ]);

    $order->refresh();
    expect($order->status)->toBe(TransferOrderStatus::Received);
});

test('it updates item reason on partial receive', function (): void {
    $this->service->recordMovement(
        productId: $this->product->id,
        warehouseId: $this->senderWarehouse->id,
        type: MovementType::Receive,
        quantity: 100,
    );

    $order = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    $item = TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 30,
        'approved_quantity' => 30,
    ]);

    $this->dispatchAction->dispatch($order, $this->admin);

    $this->receiveAction->receive($order, $this->admin, [
        ['transfer_order_item_id' => $item->id, 'quantity_received' => 25, 'variance_reason' => '5 units damaged'],
    ]);

    $item->refresh();

    expect($item->variance_reason)->toBe('5 units damaged')
        ->and($item->received_quantity)->toBe(25);
});
