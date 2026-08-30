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
use function Pest\Laravel\assertDatabaseMissing;

beforeEach(function (): void {
    $this->service = new InventoryService();
    $this->auditService = new AuditService();
    $this->dispatchAction = new DispatchTransferAction($this->service, $this->auditService);
    $this->receiveAction = new ReceiveTransferAction($this->service, $this->auditService);
    $this->admin = User::factory()->admin()->create();
    $this->senderWarehouse = Warehouse::factory()->create(['is_active' => true]);
    $this->receiverWarehouse = Warehouse::factory()->create(['is_active' => true]);
    $this->product = Product::factory()->create();
});

test('partial receiving with damage log works end to end', function (): void {
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
        'requested_quantity' => 50,
        'approved_quantity' => 50,
    ]);

    $this->dispatchAction->dispatch($order, $this->admin);

    $this->receiveAction->receive($order, $this->admin, [
        [
            'transfer_order_item_id' => $item->id,
            'quantity_received' => 45,
            'variance_reason' => '5 units damaged during transit',
        ],
    ]);

    $item->refresh();
    $order->refresh();

    expect($order->status)->toBe(TransferOrderStatus::Received)
        ->and($item->received_quantity)->toBe(45)
        ->and($item->variance_reason)->toBe('5 units damaged during transit');

    assertDatabaseHas(StockMovement::class, [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->receiverWarehouse->id,
        'type' => MovementType::TransferIn,
        'quantity' => 45,
    ]);

    expect($this->service->currentQuantity($this->product->id, $this->senderWarehouse->id))->toBe(50)
        ->and($this->service->currentQuantity($this->product->id, $this->receiverWarehouse->id))->toBe(45);
});

test('concurrent dispatch attempts serialize correctly', function (): void {
    $this->service->recordMovement(
        productId: $this->product->id,
        warehouseId: $this->senderWarehouse->id,
        type: MovementType::Receive,
        quantity: 30,
    );

    $order1 = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);
    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order1->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 20,
        'approved_quantity' => 20,
    ]);

    $order2 = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => Warehouse::factory()->create(['is_active' => true])->id,
    ]);
    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order2->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 20,
        'approved_quantity' => 20,
    ]);

    $this->dispatchAction->dispatch($order1, $this->admin);

    try {
        $this->dispatchAction->dispatch($order2, $this->admin);
    } catch (App\Exceptions\InsufficientStockException $e) {
        expect($e->available)->toBe(10)
            ->and($e->requested)->toBe(20);
    }

    assertDatabaseHas(StockMovement::class, [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->senderWarehouse->id,
        'type' => MovementType::TransferOut,
        'quantity' => -20,
    ]);

    assertDatabaseMissing(StockMovement::class, [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->senderWarehouse->id,
        'type' => MovementType::TransferOut,
        'quantity' => -40,
    ]);
});

test('editing dispatched transfer items is blocked', function (): void {
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

    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 30,
        'approved_quantity' => 30,
    ]);

    $this->dispatchAction->dispatch($order, $this->admin);

    expect($order->fresh()->isEditable())->toBeFalse();
});

test('full audit trail across stock movements', function (): void {
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
        quantity: 50,
    );

    $order = TransferOrder::factory()->confirmed()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
        'notes' => 'Monthly stock rebalance',
    ]);

    $item1 = TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 25,
        'approved_quantity' => 25,
    ]);
    $item2 = TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $product2->id,
        'requested_quantity' => 15,
        'approved_quantity' => 15,
    ]);

    $this->dispatchAction->dispatch($order, $this->admin);

    $p1Movement = StockMovement::where('product_id', $this->product->id)
        ->where('type', MovementType::TransferOut)
        ->where('warehouse_id', $this->senderWarehouse->id)
        ->first();

    expect($p1Movement)->not->toBeNull()
        ->and($p1Movement->quantity)->toBe(-25)
        ->and($p1Movement->reference)->toBe($order->reference_number);

    $p2Movement = StockMovement::where('product_id', $product2->id)
        ->where('type', MovementType::TransferOut)
        ->where('warehouse_id', $this->senderWarehouse->id)
        ->first();

    expect($p2Movement)->not->toBeNull()
        ->and($p2Movement->quantity)->toBe(-15)
        ->and($p2Movement->reference)->toBe($order->reference_number);

    $this->receiveAction->receive($order, $this->admin, [
        ['transfer_order_item_id' => $item1->id, 'quantity_received' => 25],
        ['transfer_order_item_id' => $item2->id, 'quantity_received' => 10, 'variance_reason' => '5 damaged'],
    ]);

    $p1Receive = StockMovement::where('product_id', $this->product->id)
        ->where('type', MovementType::TransferIn)
        ->where('warehouse_id', $this->receiverWarehouse->id)
        ->first();

    expect($p1Receive)->not->toBeNull()
        ->and($p1Receive->quantity)->toBe(25)
        ->and($p1Receive->reference)->toBe($order->reference_number);

    $p2Receive = StockMovement::where('product_id', $product2->id)
        ->where('type', MovementType::TransferIn)
        ->where('warehouse_id', $this->receiverWarehouse->id)
        ->first();

    expect($p2Receive)->not->toBeNull()
        ->and($p2Receive->quantity)->toBe(10)
        ->and($p2Receive->reference)->toBe($order->reference_number);

    $order->refresh();
    expect($order->dispatched_by)->toBe($this->admin->id)
        ->and($order->received_by)->toBe($this->admin->id)
        ->and($order->dispatched_at)->not->toBeNull()
        ->and($order->received_at)->not->toBeNull();
});

test('cannot receive already received order', function (): void {
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

    $this->receiveAction->receive($order, $this->admin, [
        ['transfer_order_item_id' => $item->id, 'quantity_received' => 30],
    ]);
})->throws(RuntimeException::class, 'Only dispatched transfer orders can be received.');

test('cannot dispatch already dispatched order', function (): void {
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

    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 30,
        'approved_quantity' => 30,
    ]);

    $this->dispatchAction->dispatch($order, $this->admin);
    $this->dispatchAction->dispatch($order, $this->admin);
})->throws(RuntimeException::class, 'Only confirmed transfer orders can be dispatched.');

test('inTransitQuantity decreases after partial receive', function (): void {
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
        'requested_quantity' => 50,
        'approved_quantity' => 50,
    ]);

    $this->dispatchAction->dispatch($order, $this->admin);

    expect($order->fresh()->inTransitQuantity($this->product->id))->toBe(50);

    $this->receiveAction->receive($order, $this->admin, [
        ['transfer_order_item_id' => $item->id, 'quantity_received' => 30, 'variance_reason' => 'partial'],
    ]);

    expect($order->fresh()->inTransitQuantity($this->product->id))->toBe(0);
});
