<?php

declare(strict_types=1);

use App\Filament\Resources\Warehouses\Pages\CreateWarehouse;
use App\Filament\Resources\Warehouses\Pages\EditWarehouse;
use App\Filament\Resources\Warehouses\Pages\ListWarehouses;
use App\Filament\Resources\Warehouses\Pages\ViewWarehouse;
use App\Models\Warehouse;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Warehouse::truncate();

    $this->admin = App\Models\User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('can render index page', function () {
    livewire(ListWarehouses::class)
        ->assertOk();
});

it('can render view page', function () {
    $warehouse = Warehouse::factory()->create();

    livewire(ViewWarehouse::class, [
        'record' => $warehouse->id,
    ])
        ->assertOk()
        ->assertSchemaStateSet([
            'name' => $warehouse->name,
            'code' => $warehouse->code,
        ]);
});

it('has column', function (string $column) {
    livewire(ListWarehouses::class)
        ->assertTableColumnExists($column);
})->with(['code', 'name', 'location', 'is_active', 'created_at', 'updated_at']);

it('can sort column', function (string $column) {
    if ($column === 'is_active') {
        // Create 2 inactive and 3 active warehouses
        $inactiveWarehouses = Warehouse::factory()->count(2)->create(['is_active' => false]);
        $activeWarehouses = Warehouse::factory()->count(3)->create(['is_active' => true]);
        // Combine and shuffle to simulate random initial order
        $allWarehouses = $inactiveWarehouses->concat($activeWarehouses)->shuffle();

        livewire(ListWarehouses::class)
            ->loadTable()
            ->sortTable($column)
            ->assertCanSeeTableRecords($inactiveWarehouses->sortBy('id')->concat($activeWarehouses->sortBy('id')), inOrder: true)
            ->sortTable($column, 'desc')
            ->assertCanSeeTableRecords($activeWarehouses->sortBy('id')->concat($inactiveWarehouses->sortBy('id')), inOrder: true);
    } else {
        $records = Warehouse::factory(5)->create();

        livewire(ListWarehouses::class)
            ->loadTable()
            ->sortTable($column)
            ->assertCanSeeTableRecords($records->sortBy($column), inOrder: true)
            ->sortTable($column, 'desc')
            ->assertCanSeeTableRecords($records->sortByDesc($column), inOrder: true);
    }
})->with(['is_active']);

it('can search column', function (string $column) {
    $records = Warehouse::factory(5)->create();
    $value = $records->first()->{$column};

    livewire(ListWarehouses::class)
        ->loadTable()
        ->searchTable($value)
        ->assertCanSeeTableRecords($records->where($column, $value))
        ->assertCanNotSeeTableRecords(
            $records->where($column, '!=', $value)
        );
})->with(['code', 'name', 'location']);

it('can create warehouse', function () {
    $newWarehouse = Warehouse::factory()->make();

    livewire(CreateWarehouse::class)
        ->fillForm([
            'code' => $newWarehouse->code,
            'name' => $newWarehouse->name,
            'location' => $newWarehouse->location,
            'is_active' => $newWarehouse->is_active,
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertDatabaseHas(Warehouse::class, [
        'code' => $newWarehouse->code,
        'name' => $newWarehouse->name,
    ]);
});

it('can update warehouse', function () {
    $warehouse = Warehouse::factory()->create();
    $updatedData = Warehouse::factory()->make();

    livewire(EditWarehouse::class, [
        'record' => $warehouse->id,
    ])
        ->fillForm([
            'code' => $updatedData->code,
            'name' => $updatedData->name,
            'location' => $updatedData->location,
            'is_active' => $updatedData->is_active,
        ])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(Warehouse::class, [
        'id' => $warehouse->id,
        'code' => $updatedData->code,
        'name' => $updatedData->name,
    ]);
});

it('can delete warehouse', function () {
    $warehouse = Warehouse::factory()->create();

    livewire(ViewWarehouse::class, [
        'record' => $warehouse->id,
    ])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseMissing($warehouse);
});

it('can bulk delete warehouses', function () {
    $warehouses = Warehouse::factory()->count(5)->create();

    livewire(ListWarehouses::class)
        ->loadTable()
        ->assertCanSeeTableRecords($warehouses)
        ->selectTableRecords($warehouses)
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
        ->assertNotified()
        ->assertCanNotSeeTableRecords($warehouses);

    $warehouses->each(fn (Warehouse $warehouse) => assertDatabaseMissing($warehouse));
});

it('can validate unique', function (string $column) {
    $record = Warehouse::factory()->create();

    livewire(CreateWarehouse::class)
        ->fillForm([$column => $record->$column])
        ->call('create')
        ->assertHasFormErrors([$column => ['unique']]);
})->with(['code']);

it('validates form data', function (array $data, array $errors) {
    $warehouse = Warehouse::factory()->create();
    $newWarehouseData = Warehouse::factory()->make();

    livewire(EditWarehouse::class, [
        'record' => $warehouse->id,
    ])
        ->fillForm([
            'code' => $newWarehouseData->code,
            'name' => $newWarehouseData->name,
            'location' => $newWarehouseData->location,
            'is_active' => $newWarehouseData->is_active,
            ...$data,
        ])
        ->call('save')
        ->assertHasFormErrors($errors)
        ->assertNotNotified();
})->with([
    '`code` required' => [['code' => null], ['code' => 'required']],
    '`name` required' => [['name' => null], ['name' => 'required']],
    '`is_active` required' => [['is_active' => null], ['is_active' => 'required']],
]);