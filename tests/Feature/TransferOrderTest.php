<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\TransferOrder;
use App\Models\TransferOrderItem;
use App\Models\User;
use App\Models\Warehouse;

use function Pest\Laravel\assertDatabaseMissing;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->senderWarehouse = Warehouse::factory()->create(['is_active' => true]);
    $this->receiverWarehouse = Warehouse::factory()->create(['is_active' => true]);
    $this->staff = User::factory()->warehouseStaff()->create();
    $this->staff->warehouses()->attach($this->senderWarehouse->id);
    $this->product = Product::factory()->create();
});

test('it computes inTransitQuantity from items', function (): void {
    $order = TransferOrder::factory()->dispatched()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 50,
        'approved_quantity' => 50,
        'received_quantity' => 20,
    ]);

    expect($order->inTransitQuantity($this->product->id))->toBe(50);
});

test('it computes inTransitQuantity as zero when fully received', function (): void {
    $order = TransferOrder::factory()->received()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 50,
        'approved_quantity' => 50,
        'received_quantity' => 50,
    ]);

    expect($order->inTransitQuantity($this->product->id))->toBe(0);
});

test('it reports isEditable for draft status', function (): void {
    $order = TransferOrder::factory()->draft()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    expect($order->isEditable())->toBeTrue();
});

test('it rejects edit for dispatched status', function (): void {
    $order = TransferOrder::factory()->dispatched()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    expect($order->isEditable())->toBeFalse();
});

test('it rejects edit for received status', function (): void {
    $order = TransferOrder::factory()->received()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    expect($order->isEditable())->toBeFalse();
});

test('it generates unique reference numbers', function (): void {
    $ref1 = TransferOrder::generateReferenceNumber();

    TransferOrder::factory()->create([
        'reference_number' => $ref1,
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    $ref2 = TransferOrder::generateReferenceNumber();

    expect($ref1)->not->toBe($ref2);
    expect($ref1)->toStartWith('TRF-');
    expect($ref2)->toStartWith('TRF-');
});

test('it validates sender != receiver', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->senderWarehouse->id,
    ]);

    $validator = Illuminate\Support\Facades\Validator::make(
        $order->toArray(),
        ['sender_branch_id' => 'different:receiver_branch_id'],
    );

    expect($validator->fails())->toBeTrue();
});

test('it allows canBeDispatchedBy for sender-branch member', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    expect($order->canBeDispatchedBy($this->staff))->toBeTrue();
});

test('it denies canBeDispatchedBy for non-sender member', function (): void {
    $otherStaff = User::factory()->warehouseStaff()->create();

    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    expect($order->canBeDispatchedBy($otherStaff))->toBeFalse();
});

test('it allows canBeDispatchedBy for admin', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    expect($order->canBeDispatchedBy($this->admin))->toBeTrue();
});

test('it allows canBeReceivedBy for receiver-branch member', function (): void {
    $receiverStaff = User::factory()->warehouseStaff()->create();
    $receiverStaff->warehouses()->attach($this->receiverWarehouse->id);

    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    expect($order->canBeReceivedBy($receiverStaff))->toBeTrue();
});

test('it denies canBeReceivedBy for non-receiver member', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    expect($order->canBeReceivedBy($this->staff))->toBeFalse();
});

test('it allows canBeReceivedBy for admin', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    expect($order->canBeReceivedBy($this->admin))->toBeTrue();
});

test('it cascades delete to items', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->senderWarehouse->id,
        'receiver_branch_id' => $this->receiverWarehouse->id,
    ]);

    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
    ]);

    $orderId = $order->id;
    $order->delete();

    assertDatabaseMissing(TransferOrderItem::class, ['transfer_order_id' => $orderId]);
});
