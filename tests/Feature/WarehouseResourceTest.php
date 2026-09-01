<?php

declare(strict_types=1);

use App\Filament\Resources\Warehouses\Pages\CreateWarehouse;
use App\Filament\Resources\Warehouses\Pages\EditWarehouse;
use App\Filament\Resources\Warehouses\Pages\ListWarehouses;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->staff = User::factory()->create(['role' => 'warehouse_staff']);
});

it('admin can render the index page', function (): void {
    $this->actingAs($this->admin);

    livewire(ListWarehouses::class)
        ->assertOk();
});

it('warehouse staff cannot view warehouses', function (): void {
    $this->actingAs($this->staff);

    livewire(ListWarehouses::class)
        ->assertForbidden();
});

it('admin can render the create page', function (): void {
    $this->actingAs($this->admin);

    livewire(CreateWarehouse::class)
        ->assertOk();
});

it('warehouse staff cannot create warehouses', function (): void {
    $this->actingAs($this->staff);

    livewire(CreateWarehouse::class)
        ->assertForbidden();
});

it('admin can render the edit page', function (): void {
    $this->actingAs($this->admin);

    $warehouse = Warehouse::factory()->create();

    livewire(EditWarehouse::class, [
        'record' => $warehouse->id,
    ])
        ->assertOk()
        ->assertSchemaStateSet([
            'name' => $warehouse->name,
        ]);
});

it('has column', function (string $column): void {
    $this->actingAs($this->admin);

    livewire(ListWarehouses::class)
        ->assertTableColumnExists($column);
})->with(['name', 'location', 'created_at', 'updated_at']);

it('admin can create a warehouse', function (): void {
    $this->actingAs($this->admin);

    $warehouse = Warehouse::factory()->make();

    livewire(CreateWarehouse::class)
        ->fillForm([
            'code' => $warehouse->code,
            'name' => $warehouse->name,
            'location' => $warehouse->location,
            'is_active' => $warehouse->is_active,
        ])
        ->call('create')
        ->assertNotified();

    assertDatabaseHas(Warehouse::class, [
        'code' => $warehouse->code,
        'name' => $warehouse->name,
        'location' => $warehouse->location,
    ]);
});

it('admin can update a warehouse', function (): void {
    $this->actingAs($this->admin);

    $warehouse = Warehouse::factory()->create();
    $newData = Warehouse::factory()->make();

    livewire(EditWarehouse::class, [
        'record' => $warehouse->id,
    ])
        ->fillForm([
            'name' => $newData->name,
        ])
        ->call('save')
        ->assertNotified();

    assertDatabaseHas(Warehouse::class, [
        'id' => $warehouse->id,
        'name' => $newData->name,
    ]);
});

it('admin can delete a warehouse', function (): void {
    $this->actingAs($this->admin);

    $warehouse = Warehouse::factory()->create();

    livewire(EditWarehouse::class, [
        'record' => $warehouse->id,
    ])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseMissing($warehouse);
});

it('admin can assign users to warehouse', function (): void {
    $this->actingAs($this->admin);

    $warehouse = Warehouse::factory()->create();
    $users = User::factory()->count(2)->create();

    livewire(EditWarehouse::class, [
        'record' => $warehouse->id,
    ])
        ->fillForm([
            'users' => $users->pluck('id')->toArray(),
        ])
        ->call('save')
        ->assertNotified();

    expect($warehouse->refresh()->users)->toHaveCount(2);
});

it('validates the form data', function (array $data, array $errors): void {
    $this->actingAs($this->admin);

    $warehouse = Warehouse::factory()->create();
    $newData = Warehouse::factory()->make();

    livewire(EditWarehouse::class, [
        'record' => $warehouse->id,
    ])
        ->fillForm([
            'name' => $newData->name,
            ...$data,
        ])
        ->call('save')
        ->assertHasFormErrors($errors)
        ->assertNotNotified();
})->with([
    '`name` is required' => [['name' => null], ['name' => 'required']],
    '`name` is max 255 characters' => [['name' => Str::random(256)], ['name' => 'max']],
]);
