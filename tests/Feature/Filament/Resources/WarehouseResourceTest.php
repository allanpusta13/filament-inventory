<?php

declare(strict_types=1);

use App\Filament\Resources\Warehouses\Pages\CreateWarehouse;
use App\Filament\Resources\Warehouses\Pages\EditWarehouse;
use App\Filament\Resources\Warehouses\Pages\ListWarehouses;
use App\Filament\Resources\Warehouses\Pages\ViewWarehouse;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Actions\Testing\TestAction;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Warehouse::truncate();

    $this->admin = User::factory()->admin()->create();
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
            'code' => $warehouse->code,
            'name' => $warehouse->name,
            'location' => $warehouse->location,
        ]);
});

it('has column', function (string $column) {
    livewire(ListWarehouses::class)
        ->assertTableColumnExists($column);
})->with(['code', 'name', 'location', 'is_active', 'users_count', 'created_at', 'updated_at']);

it('can sort column', function (string $column) {
    $records = Warehouse::factory(5)->create();

    livewire(ListWarehouses::class)
        ->loadTable()
        ->sortTable($column)
        ->assertCanSeeTableRecords($records->sortBy($column), inOrder: true)
        ->sortTable($column, 'desc')
        ->assertCanSeeTableRecords($records->sortByDesc($column), inOrder: true);
})->with(['code', 'name', 'location', 'is_active']);

it('can search table', function () {
    $warehouse1 = Warehouse::factory()->create(['code' => 'WH-MNL', 'name' => 'Manila Warehouse']);
    $warehouse2 = Warehouse::factory()->create(['code' => 'WH-CEB', 'name' => 'Cebu Warehouse']);
    Warehouse::factory()->create(['code' => 'WH-DVO', 'name' => 'Davao Warehouse']);

    livewire(ListWarehouses::class)
        ->loadTable()
        ->searchTable('WH-MNL')
        ->assertCanSeeTableRecords([$warehouse1])
        ->assertCanNotSeeTableRecords([$warehouse2]);
});

it('can search table by column', function () {
    $warehouse1 = Warehouse::factory()->create(['code' => 'WH-MNL', 'name' => 'Manila Warehouse']);
    $warehouse2 = Warehouse::factory()->create(['code' => 'WH-CEB', 'name' => 'Cebu Warehouse']);

    livewire(ListWarehouses::class)
        ->loadTable()
        ->searchTable('Manila')
        ->assertCanSeeTableRecords([$warehouse1])
        ->assertCanNotSeeTableRecords([$warehouse2]);
});

it('can render table column state', function () {
    $warehouse = Warehouse::factory()->create(['code' => 'WH-TEST', 'name' => 'Test Warehouse']);

    livewire(ListWarehouses::class)
        ->loadTable()
        ->assertTableColumnStateSet('code', 'WH-TEST', record: $warehouse)
        ->assertTableColumnStateSet('name', 'Test Warehouse', record: $warehouse);
});

it('can assert table column visibility', function () {
    livewire(ListWarehouses::class)
        ->loadTable()
        ->assertTableColumnVisible('code')
        ->assertTableColumnVisible('name')
        ->assertTableColumnVisible('location')
        ->assertTableColumnVisible('is_active');
});

it('can assert table column exists', function (string $column) {
    livewire(ListWarehouses::class)
        ->loadTable()
        ->assertTableColumnExists($column);
})->with(['code', 'name', 'location', 'is_active', 'users_count', 'created_at', 'updated_at']);

it('renders empty state correctly', function () {
    Warehouse::truncate();

    livewire(ListWarehouses::class)
        ->loadTable()
        ->assertCountTableRecords(0);
});

it('has edit action on table row', function () {
    $warehouse = Warehouse::factory()->create();

    livewire(ListWarehouses::class)
        ->loadTable()
        ->callAction(TestAction::make('edit')->table($warehouse))
        ->assertHasNoFormErrors();
});

it('can create warehouse', function () {
    $newWarehouseData = Warehouse::factory()->make();

    livewire(CreateWarehouse::class)
        ->fillForm([
            'code' => $newWarehouseData->code,
            'name' => $newWarehouseData->name,
            'location' => $newWarehouseData->location,
            'is_active' => $newWarehouseData->is_active,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(Warehouse::class, [
        'code' => $newWarehouseData->code,
        'name' => $newWarehouseData->name,
        'location' => $newWarehouseData->location,
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
        'location' => $updatedData->location,
    ]);
});

it('can validate unique code', function (string $column) {
    $record = Warehouse::factory()->create();

    livewire(CreateWarehouse::class)
        ->fillForm([$column => $record->$column])
        ->call('create')
        ->assertHasFormErrors([$column => ['unique']]);
})->with(['code']);

it('validates form data on create', function (array $data, array $errors) {
    $newWarehouseData = Warehouse::factory()->make();

    livewire(CreateWarehouse::class)
        ->fillForm([
            'code' => $newWarehouseData->code,
            'name' => $newWarehouseData->name,
            'location' => $newWarehouseData->location,
            'is_active' => $newWarehouseData->is_active,
            ...$data,
        ])
        ->call('create')
        ->assertHasFormErrors($errors)
        ->assertNotNotified();
})->with([
    '`code` required' => [['code' => null], ['code' => 'required']],
    '`name` required' => [['name' => null], ['name' => 'required']],
    '`is_active` required' => [['is_active' => null], ['is_active' => 'required']],
]);

it('validates form data on update', function (array $data, array $errors) {
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

it('renders infolist entries on view page', function () {
    $warehouse = Warehouse::factory()->create();

    livewire(ViewWarehouse::class, ['record' => $warehouse->id])
        ->assertSchemaComponentExists('code')
        ->assertSchemaComponentExists('name')
        ->assertSchemaComponentExists('location')
        ->assertSchemaComponentExists('is_active')
        ->assertSchemaComponentExists('users_count')
        ->assertSchemaComponentExists('users');
});

it('allows access to admin only', function () {
    Warehouse::truncate();
    User::truncate();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    livewire(ListWarehouses::class)
        ->assertOk();
});

it('denies access to non-admin users', function () {
    Warehouse::truncate();
    User::truncate();
    $staff = User::factory()->warehouseStaff()->create();
    $this->actingAs($staff);

    livewire(ListWarehouses::class)
        ->assertForbidden();
});

it('denies access to unauthenticated users', function () {
    Warehouse::truncate();
    User::truncate();

    // Ensure no authenticated user
    $this->app['auth']->logout();

    livewire(ListWarehouses::class)
        ->assertForbidden();
});
