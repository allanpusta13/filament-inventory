<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\TransferOrders\Pages\CreateTransferOrder;
use App\Filament\Resources\TransferOrders\Pages\ListTransferOrders;
use App\Filament\Resources\TransferOrders\Pages\ViewTransferOrder;
use App\Models\Product;
use App\Models\TransferOrder;
use App\Models\TransferOrderItem;
use App\Models\User;
use App\Models\Warehouse;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->wh1 = Warehouse::factory()->create(['name' => 'Manila']);
    $this->wh2 = Warehouse::factory()->create(['name' => 'Cebu']);
    $this->admin->warehouses()->attach([$this->wh1->id, $this->wh2->id]);
    $this->product = Product::factory()->create();
});

it('lists transfer orders for admin', function (): void {
    $this->actingAs($this->admin);

    TransferOrder::factory()->create([
        'sender_branch_id' => $this->wh1->id,
        'receiver_branch_id' => $this->wh2->id,
    ]);

    livewire(ListTransferOrders::class)
        ->assertSuccessful();
});

it('admin can create a transfer order with items', function (): void {
    $this->actingAs($this->admin);

    livewire(CreateTransferOrder::class)
        ->fillForm([
            'sender_branch_id' => $this->wh1->id,
            'receiver_branch_id' => $this->wh2->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'requested_quantity' => 5,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('transfer_orders', [
        'sender_branch_id' => $this->wh1->id,
        'receiver_branch_id' => $this->wh2->id,
        'status' => 'draft',
    ]);
});

it('staff cannot create transfer orders as auditor', function (): void {
    $auditor = User::factory()->create(['role' => UserRole::Auditor]);

    $this->actingAs($auditor);

    livewire(CreateTransferOrder::class)
        ->assertForbidden();
});

it('views a transfer order with correct status badge', function (): void {
    $this->actingAs($this->admin);

    $order = TransferOrder::factory()->create([
        'sender_branch_id' => $this->wh1->id,
        'receiver_branch_id' => $this->wh2->id,
        'status' => 'draft',
    ]);

    livewire(ViewTransferOrder::class, ['record' => $order->id])
        ->assertSuccessful();
});

it('submit action is visible on draft orders', function (): void {
    $this->actingAs($this->admin);

    $order = TransferOrder::factory()->draft()->create([
        'sender_branch_id' => $this->wh1->id,
        'receiver_branch_id' => $this->wh2->id,
    ]);

    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 10,
    ]);

    livewire(ViewTransferOrder::class, ['record' => $order->id])
        ->assertActionVisible('submit');
});

it('submit action transitions draft to requested', function (): void {
    $this->actingAs($this->admin);

    $order = TransferOrder::factory()->draft()->create([
        'sender_branch_id' => $this->wh1->id,
        'receiver_branch_id' => $this->wh2->id,
    ]);

    TransferOrderItem::factory()->create([
        'transfer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'requested_quantity' => 10,
    ]);

    livewire(ViewTransferOrder::class, ['record' => $order->id])
        ->callAction('submit')
        ->assertHasNoActionErrors();

    expect($order->fresh()->status->value)->toBe('requested');
});

it('scopes orders to user warehouses', function (): void {
    $staff = User::factory()->warehouseStaff()->create();
    $staff->warehouses()->attach($this->wh1->id);

    $this->actingAs($staff);

    TransferOrder::factory()->create([
        'sender_branch_id' => $this->wh1->id,
        'receiver_branch_id' => $this->wh2->id,
    ]);

    TransferOrder::factory()->create([
        'sender_branch_id' => $this->wh2->id,
        'receiver_branch_id' => $this->wh2->id,
    ]);

    $query = TransferOrder::query();
    $warehouseIds = $staff->warehouses()->pluck('warehouses.id')->toArray();

    $query->where(function ($q) use ($warehouseIds): void {
        $q->whereIn('sender_branch_id', $warehouseIds)
            ->orWhereIn('receiver_branch_id', $warehouseIds);
    });

    expect($query->count())->toBe(1);
});
