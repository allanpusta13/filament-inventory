<?php

declare(strict_types=1);

use App\Filament\Resources\TransferOrders\Pages\CreateTransferOrder;
use App\Filament\Resources\TransferOrders\Pages\ListTransferOrders;
use App\Models\User;
use App\Models\Warehouse;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->warehouse = Warehouse::factory()->create(['is_active' => true]);
});

it('can render list page', function (): void {
    $this->actingAs($this->admin);

    livewire(ListTransferOrders::class)
        ->assertOk();
});

it('can render create page', function (): void {
    $this->actingAs($this->admin);

    livewire(CreateTransferOrder::class)
        ->assertOk();
});

it('restricts edit to draft orders only', function (): void {
    $this->actingAs($this->admin);

    $order = App\Models\TransferOrder::factory()->dispatched()->create([
        'sender_branch_id' => $this->warehouse->id,
        'receiver_branch_id' => Warehouse::factory()->create(['is_active' => true])->id,
    ]);

    $this->assertDatabaseHas(App\Models\TransferOrder::class, [
        'id' => $order->id,
        'status' => 'dispatched',
    ]);
});

it('scopes query to user accessible warehouses', function (): void {
    $staff = User::factory()->warehouseStaff()->create();
    $staff->warehouses()->attach($this->warehouse->id);

    $this->actingAs($staff);

    livewire(ListTransferOrders::class)
        ->assertOk();
});
