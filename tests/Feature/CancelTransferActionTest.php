<?php

declare(strict_types=1);

use App\Actions\CancelTransferAction;
use App\Enums\TransferOrderStatus;
use App\Models\TransferOrder;
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

    $this->action = app(CancelTransferAction::class);
});

it('cancels a draft order', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->wh1->id,
        'receiver_branch_id' => $this->wh2->id,
        'status' => 'draft',
    ]);

    $this->action->cancel($order, $this->staff);

    $order->refresh();
    expect($order->status)->toBe(TransferOrderStatus::Cancelled);
});

it('cancels a requested order', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->wh1->id,
        'receiver_branch_id' => $this->wh2->id,
        'status' => 'requested',
    ]);

    $this->action->cancel($order, $this->staff);

    $order->refresh();
    expect($order->status)->toBe(TransferOrderStatus::Cancelled);
});

it('cancels an under_review_fulfiller order', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->wh1->id,
        'receiver_branch_id' => $this->wh2->id,
        'status' => 'under_review_fulfiller',
    ]);

    $this->action->cancel($order, $this->staff);

    $order->refresh();
    expect($order->status)->toBe(TransferOrderStatus::Cancelled);
});

it('cancels an under_review_requestor order', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->wh1->id,
        'receiver_branch_id' => $this->wh2->id,
        'status' => 'under_review_requestor',
    ]);

    $this->action->cancel($order, $this->staff);

    $order->refresh();
    expect($order->status)->toBe(TransferOrderStatus::Cancelled);
});

it('throws when cancelling a confirmed order', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->wh1->id,
        'receiver_branch_id' => $this->wh2->id,
        'status' => 'confirmed',
    ]);

    $this->action->cancel($order, $this->staff);
})->throws(RuntimeException::class, 'This transfer order cannot be cancelled in its current status.');

it('throws when cancelling a dispatched order', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->wh1->id,
        'receiver_branch_id' => $this->wh2->id,
        'status' => 'dispatched',
    ]);

    $this->action->cancel($order, $this->staff);
})->throws(RuntimeException::class, 'This transfer order cannot be cancelled in its current status.');

it('throws when user has no access to sender or receiver warehouse', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->wh2->id,
        'receiver_branch_id' => $this->wh2->id,
        'status' => 'draft',
    ]);

    $this->action->cancel($order, $this->staff);
})->throws(RuntimeException::class, 'You are not authorized to cancel this transfer order.');

it('appends cancellation reason to notes', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->wh1->id,
        'receiver_branch_id' => $this->wh2->id,
        'status' => 'draft',
        'notes' => 'Original notes',
    ]);

    $this->action->cancel($order, $this->staff, 'No longer needed');

    $order->refresh();
    expect($order->notes)->toContain('Original notes');
    expect($order->notes)->toContain('Cancellation reason: No longer needed');
});

it('creates notes with cancellation reason when no prior notes exist', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->wh1->id,
        'receiver_branch_id' => $this->wh2->id,
        'status' => 'draft',
        'notes' => null,
    ]);

    $this->action->cancel($order, $this->staff, 'Budget cut');

    $order->refresh();
    expect($order->notes)->toBe('Cancellation reason: Budget cut');
});

it('creates an audit record with correct payload', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->wh1->id,
        'receiver_branch_id' => $this->wh2->id,
        'status' => 'draft',
    ]);

    $this->action->cancel($order, $this->staff, 'Changed mind');

    $audit = $order->audits()->where('action', 'cancelled')->first();
    expect($audit)->not->toBeNull();
    expect($audit->user_id)->toBe($this->staff->id);
    expect($audit->changes_payload)->toBe([
        'status' => ['old' => 'draft', 'new' => 'cancelled'],
        'cancellation_reason' => ['old' => null, 'new' => 'Changed mind'],
    ]);
});

it('creates an audit record without cancellation_reason when none provided', function (): void {
    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->wh1->id,
        'receiver_branch_id' => $this->wh2->id,
        'status' => 'draft',
    ]);

    $this->action->cancel($order, $this->staff);

    $audit = $order->audits()->where('action', 'cancelled')->first();
    expect($audit)->not->toBeNull();
    expect($audit->changes_payload)->toBe([
        'status' => ['old' => 'draft', 'new' => 'cancelled'],
    ]);
});
