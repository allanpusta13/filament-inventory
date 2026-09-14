<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

beforeEach(function () {
    User::truncate();

    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('can render index page', function () {
    livewire(ListUsers::class)
        ->assertOk();
});

it('can render create page', function () {
    livewire(CreateUser::class)
        ->assertOk();
});

it('can render edit page', function () {
    $user = User::factory()->create();

    livewire(EditUser::class, [
        'record' => $user->id,
    ])
        ->assertOk()
        ->assertSchemaStateSet([
            'name' => $user->name,
            'email' => $user->email,
        ]);
});

it('has column', function (string $column) {
    livewire(ListUsers::class)
        ->assertTableColumnExists($column);
})->with(['name', 'email', 'role', 'warehouses.name', 'created_at']);

it('can sort column', function (string $column) {
    $records = User::factory(5)->create();

    livewire(ListUsers::class)
        ->loadTable()
        ->sortTable($column)
        ->assertCanSeeTableRecords($records->sortBy($column), inOrder: true)
        ->sortTable($column, 'desc')
        ->assertCanSeeTableRecords($records->sortByDesc($column), inOrder: true);
})->with(['name']);

it('can search table', function () {
    $john = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $jane = User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);
    $bob = User::factory()->create(['name' => 'Bob Wilson', 'email' => 'bob@example.com']);

    livewire(ListUsers::class)
        ->loadTable()
        ->searchTable('John')
        ->assertCanSeeTableRecords([$john])
        ->assertCanNotSeeTableRecords([$jane, $bob]);
});

it('can filter table by role', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
    $manager = User::factory()->create(['role' => UserRole::BRANCH_MANAGER->value]);
    $staff = User::factory()->create(['role' => UserRole::WAREHOUSE_STAFF->value]);

    livewire(ListUsers::class)
        ->loadTable()
        ->filterTable('role', UserRole::ADMIN->value)
        ->assertCanSeeTableRecords([$admin])
        ->assertCanNotSeeTableRecords([$manager, $staff]);
});

it('can render table column state', function () {
    $user = User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);

    livewire(ListUsers::class)
        ->loadTable()
        ->assertTableColumnStateSet('name', 'Test User', record: $user)
        ->assertTableColumnStateSet('email', 'test@example.com', record: $user);
});

it('can render table column formatted state', function () {
    $user = User::factory()->create(['created_at' => now()->subDays(5)]);

    livewire(ListUsers::class)
        ->loadTable()
        ->assertTableColumnFormattedStateSet('created_at', $user->created_at->format('M d, Y'), record: $user);
});

it('can assert table column visibility', function () {
    livewire(ListUsers::class)
        ->loadTable()
        ->assertTableColumnVisible('name')
        ->assertTableColumnVisible('email')
        ->assertTableColumnVisible('role')
        ->assertTableColumnVisible('warehouses.name');
});

it('can assert table column exists', function (string $column) {
    livewire(ListUsers::class)
        ->loadTable()
        ->assertTableColumnExists($column);
})->with(['name', 'email', 'role', 'warehouses.name', 'created_at']);

it('renders empty state correctly', function () {
    User::truncate();

    livewire(ListUsers::class)
        ->loadTable()
        ->assertCountTableRecords(0);
});

it('has view action on table row', function () {
    $user = User::factory()->create();

    livewire(ListUsers::class)
        ->loadTable()
        ->callAction(TestAction::make('view')->table($user))
        ->assertHasNoFormErrors();
});

it('has edit action on table row', function () {
    $user = User::factory()->create();

    livewire(ListUsers::class)
        ->loadTable()
        ->callAction(TestAction::make('edit')->table($user))
        ->assertHasNoFormErrors();
});

it('has delete action on table row', function () {
    $user = User::factory()->create();

    livewire(ListUsers::class)
        ->loadTable()
        ->callAction(TestAction::make('delete')->table($user))
        ->assertNotified();

    assertDatabaseMissing($user);
});

it('has header actions', function () {
    livewire(ListUsers::class)
        ->loadTable()
        ->assertCanRenderTableColumn('name');
});

it('can bulk delete users', function () {
    $users = User::factory()->count(5)->create();

    livewire(ListUsers::class)
        ->loadTable()
        ->assertCanSeeTableRecords($users)
        ->selectTableRecords($users)
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
        ->assertNotified()
        ->assertCanNotSeeTableRecords($users);

    $users->each(fn (User $user) => assertDatabaseMissing($user));
});

it('can create user', function () {
    $newUserData = User::factory()->make();

    livewire(CreateUser::class)
        ->fillForm([
            'name' => $newUserData->name,
            'email' => $newUserData->email,
            'role' => UserRole::WAREHOUSE_STAFF->value,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(User::class, [
        'name' => $newUserData->name,
        'email' => $newUserData->email,
        'role' => UserRole::WAREHOUSE_STAFF->value,
    ]);
});

it('can update user', function () {
    $user = User::factory()->create();
    $newUserData = User::factory()->make();

    livewire(EditUser::class, [
        'record' => $user->id,
    ])
        ->fillForm([
            'name' => $newUserData->name,
            'email' => $newUserData->email,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($user->refresh()->name)->toBe($newUserData->name)
        ->and($user->refresh()->email)->toBe($newUserData->email);
});

it('can delete user', function () {
    $user = User::factory()->create();

    livewire(EditUser::class, [
        'record' => $user->id,
    ])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseMissing($user);
});

it('can validate unique email', function (string $column) {
    $record = User::factory()->create();

    livewire(CreateUser::class)
        ->fillForm(['email' => $record->email])
        ->call('create')
        ->assertHasFormErrors([$column => ['unique']]);
})->with(['email']);

it('validates form data', function (array $data, array $errors) {
    $user = User::factory()->create();
    $newUserData = User::factory()->make();

    livewire(EditUser::class, [
        'record' => $user->id,
    ])
        ->fillForm([
            'name' => $newUserData->name,
            'email' => $newUserData->email,
            ...$data,
        ])
        ->call('save')
        ->assertHasFormErrors($errors)
        ->assertNotNotified();
})->with([
    '`name` required' => [['name' => null], ['name' => 'required']],
    '`name` max 255 characters' => [['name' => Str::random(256)], ['name' => 'max']],
    '`email` valid email address' => [['email' => Str::random()], ['email' => 'email']],
    '`email` required' => [['email' => null], ['email' => 'required']],
    '`email` max 255 characters' => [['email' => Str::random(256)], ['email' => 'max']],
]);

it('allows access to all authenticated users via canAccessPanel', function () {
    User::truncate();
    $staff = User::factory()->create(['role' => UserRole::WAREHOUSE_STAFF->value]);
    $this->actingAs($staff);

    livewire(ListUsers::class)
        ->assertOk();
});
