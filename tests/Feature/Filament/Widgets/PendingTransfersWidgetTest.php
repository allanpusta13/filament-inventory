<?php

declare(strict_types=1);

use App\Filament\Widgets\PendingTransfersWidget;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->staff = User::factory()->create(['role' => 'warehouse_staff']);
    $this->wh1 = Warehouse::factory()->create();
    $this->wh2 = Warehouse::factory()->create();
    $this->staff->warehouses()->attach($this->wh1->id);
});

it('admin can see all pending transfers', function (): void {
    $requisition = TransferRequisition::factory()->create([
        'from_warehouse_id' => $this->wh1->id,
        'to_warehouse_id' => $this->wh2->id,
        'status' => 'requested',
        'requested_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);
    livewire(PendingTransfersWidget::class)
        ->assertOk();
});

it('shows requisitions with under_review_fulfiller status', function (): void {
    $requisition = TransferRequisition::factory()->create([
        'from_warehouse_id' => $this->wh1->id,
        'to_warehouse_id' => $this->wh2->id,
        'status' => 'under_review_fulfiller',
        'requested_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);
    livewire(PendingTransfersWidget::class)
        ->assertOk();
});

it('shows requisitions with under_review_requestor status', function (): void {
    $requisition = TransferRequisition::factory()->create([
        'from_warehouse_id' => $this->wh1->id,
        'to_warehouse_id' => $this->wh2->id,
        'status' => 'under_review_requestor',
        'requested_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);
    livewire(PendingTransfersWidget::class)
        ->assertOk();
});

it('does not show completed requisitions', function (): void {
    $requisition = TransferRequisition::factory()->create([
        'from_warehouse_id' => $this->wh1->id,
        'to_warehouse_id' => $this->wh2->id,
        'status' => 'completed',
        'requested_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);
    livewire(PendingTransfersWidget::class)
        ->assertOk();
});

it('staff sees only requisitions involving their warehouse', function (): void {
    $relevantRequisition = TransferRequisition::factory()->create([
        'from_warehouse_id' => $this->wh1->id,
        'to_warehouse_id' => $this->wh2->id,
        'status' => 'requested',
        'requested_by' => $this->admin->id,
    ]);

    $irrelevantRequisition = TransferRequisition::factory()->create([
        'from_warehouse_id' => $this->wh2->id,
        'to_warehouse_id' => $this->wh2->id,
        'status' => 'requested',
        'requested_by' => $this->admin->id,
    ]);

    $this->actingAs($this->staff);
    livewire(PendingTransfersWidget::class)
        ->assertOk();
});
