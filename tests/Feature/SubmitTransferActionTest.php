<?php

declare(strict_types=1);

use App\Actions\SubmitTransferAction;
use App\Enums\TransferOrderStatus;
use App\Models\TransferOrder;
use App\Models\TransferOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->wh1 = Warehouse::factory()->create();
    $this->wh2 = Warehouse::factory()->create();

    $this->staff = User::factory()->create();
    $this->staff->warehouses()->attach($this->wh1->id);

    $this->admin = User::factory()->create(['role' => 'admin']);

    $this->action = app(SubmitTransferAction::class);
});

it('submits a draft order with items to requested status', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->wh1->id,
        'receiver_branch_id' => $this->wh2->id,
        'status' => 'draft',
    ]);

    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
    ]);

    $this->action->submit($order, $this->staff);

    $order->refresh();
    expect($order->status)->toBe(TransferOrderStatus::Requested);
    expect($order->audits()->where('action', 'submitted')->count())->toBe(1);
});

it('throws when submitting a non-draft order', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->wh1->id,
        'receiver_branch_id' => $this->wh2->id,
        'status' => 'requested',
    ]);

    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
    ]);

    $this->action->submit($order, $this->staff);
})->throws(RuntimeException::class, 'Only draft transfer orders can be submitted.');

it('throws when submitting an order with no items', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->wh1->id,
        'receiver_branch_id' => $this->wh2->id,
        'status' => 'draft',
    ]);

    $this->action->submit($order, $this->staff);
})->throws(RuntimeException::class, 'Cannot submit a transfer order with no items.');

it('throws when user has no access to sender warehouse', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->wh2->id,
        'receiver_branch_id' => $this->wh1->id,
        'status' => 'draft',
    ]);

    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
    ]);

    $this->action->submit($order, $this->staff);
})->throws(RuntimeException::class, 'You are not authorized to submit this transfer order.');

it('creates an audit record with correct payload', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->wh1->id,
        'receiver_branch_id' => $this->wh2->id,
        'status' => 'draft',
    ]);

    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
    ]);

    $this->action->submit($order, $this->staff);

    $audit = $order->audits()->where('action', 'submitted')->first();
    expect($audit)->not->toBeNull();
    expect($audit->user_id)->toBe($this->staff->id);
    expect($audit->changes_payload)->toBe([
        'status' => ['old' => 'draft', 'new' => 'requested'],
    ]);
});
