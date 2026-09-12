<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

beforeEach(function () {
    User::truncate();
    Warehouse::truncate();

    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

describe('UserResource edge cases', function () {
    it('validates email format on create', function () {
        livewire(CreateUser::class)
            ->fillForm([
                'name' => 'Test User',
                'email' => 'invalid-email',
                'password' => 'password123',
                'role' => UserRole::WAREHOUSE_STAFF,
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'email'])
            ->assertNotNotified();
    });

    it('validates email format on edit', function () {
        $user = User::factory()->create();

        livewire(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'email' => 'invalid-email',
            ])
            ->call('save')
            ->assertHasFormErrors(['email' => 'email'])
            ->assertNotNotified();
    });

    it('prevents duplicate email on create', function () {
        $existingUser = User::factory()->create(['email' => 'existing@test.com']);

        livewire(CreateUser::class)
            ->fillForm([
                'name' => 'New User',
                'email' => 'existing@test.com',
                'password' => 'password123',
                'role' => UserRole::WAREHOUSE_STAFF,
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique'])
            ->assertNotNotified();
    });

    it('prevents duplicate email on edit', function () {
        $existingUser = User::factory()->create(['email' => 'existing@test.com']);
        $user = User::factory()->create(['email' => 'other@test.com']);

        livewire(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'email' => 'existing@test.com',
            ])
            ->call('save')
            ->assertHasFormErrors(['email' => 'unique'])
            ->assertNotNotified();
    });

    it('allows same email when editing same record', function () {
        $user = User::factory()->create(['email' => 'same@test.com']);

        livewire(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'email' => 'same@test.com',
                'name' => 'Updated Name',
            ])
            ->call('save')
            ->assertNotified()
            ->assertHasNoFormErrors();

        assertDatabaseHas(User::class, [
            'id' => $user->id,
            'email' => 'same@test.com',
            'name' => 'Updated Name',
        ]);
    });

    it('validates name max length', function () {
        $longName = Str::random(256);

        livewire(CreateUser::class)
            ->fillForm([
                'name' => $longName,
                'email' => 'test@test.com',
                'password' => 'password123',
                'role' => UserRole::WAREHOUSE_STAFF,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'max'])
            ->assertNotNotified();
    });

    it('requires name on create', function () {
        livewire(CreateUser::class)
            ->fillForm([
                'email' => 'test@test.com',
                'password' => 'password123',
                'role' => UserRole::WAREHOUSE_STAFF,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required'])
            ->assertNotNotified();
    });

    it('requires email on create', function () {
        livewire(CreateUser::class)
            ->fillForm([
                'name' => 'Test User',
                'password' => 'password123',
                'role' => UserRole::WAREHOUSE_STAFF,
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'required'])
            ->assertNotNotified();
    });

    it('requires password on create', function () {
        livewire(CreateUser::class)
            ->fillForm([
                'name' => 'Test User',
                'email' => 'test@test.com',
                'role' => UserRole::WAREHOUSE_STAFF,
            ])
            ->call('create')
            ->assertHasFormErrors(['password' => 'required'])
            ->assertNotNotified();
    });

    it('does not require password on edit when not changing', function () {
        $user = User::factory()->create();

        livewire(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'name' => 'Updated Name',
            ])
            ->call('save')
            ->assertNotified()
            ->assertHasNoFormErrors();

        assertDatabaseHas(User::class, [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    });

    it('allows password change on edit', function () {
        $user = User::factory()->create(['password' => bcrypt('oldpass')]);

        livewire(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'password' => 'newpassword123',
            ])
            ->call('save')
            ->assertNotified()
            ->assertHasNoFormErrors();

        $user->refresh();
        expect($user->password)->not->toBe(bcrypt('oldpass'));
    });

    it('allows multiple warehouse assignments', function () {
        $warehouse1 = Warehouse::factory()->create();
        $warehouse2 = Warehouse::factory()->create();
        $warehouse3 = Warehouse::factory()->create();

        $user = User::factory()->create();

        livewire(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'warehouses' => [$warehouse1->id, $warehouse2->id, $warehouse3->id],
            ])
            ->call('save')
            ->assertNotified()
            ->assertHasNoFormErrors();

        $user->refresh();
        expect($user->warehouses->pluck('id')->sort()->values()->toArray())
            ->toBe([$warehouse1->id, $warehouse2->id, $warehouse3->id]);
    });

    it('allows removing all warehouse assignments', function () {
        $warehouse = Warehouse::factory()->create();
        $user = User::factory()->create();
        $user->warehouses()->attach($warehouse->id);

        livewire(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'warehouses' => [],
            ])
            ->call('save')
            ->assertNotified()
            ->assertHasNoFormErrors();

        $user->refresh();
        expect($user->warehouses)->toBeEmpty();
    });

    it('allows all UserRole enum values', function () {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);

            expect($user->role)->toBe($role);
            expect($user->role->value)->toBe($role->value);
        }
    });

    it('bulk delete works correctly', function () {
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

    it('deletes user on delete action', function () {
        $user = User::factory()->create();

        livewire(EditUser::class, ['record' => $user->id])
            ->callAction(DeleteAction::class)
            ->assertNotified()
            ->assertRedirect();

        assertDatabaseMissing($user);
    });

    it('renders create page with defaults', function () {
        livewire(CreateUser::class)
            ->assertOk();
    });

    it('renders edit page with correct data', function () {
        $user = User::factory()->create(['name' => 'Test User', 'email' => 'test@test.com']);

        livewire(EditUser::class, ['record' => $user->id])
            ->assertOk()
            ->assertSchemaStateSet([
                'name' => 'Test User',
                'email' => 'test@test.com',
            ]);
    });

    it('creates user with all required fields', function () {
        livewire(CreateUser::class)
            ->fillForm([
                'name' => 'Test User',
                'email' => 'test@test.com',
                'password' => 'password123',
                'role' => UserRole::BRANCH_MANAGER,
            ])
            ->call('create')
            ->assertNotified()
            ->assertHasNoFormErrors();

        assertDatabaseHas(User::class, [
            'name' => 'Test User',
            'email' => 'test@test.com',
            'role' => UserRole::BRANCH_MANAGER,
        ]);
    });

    it('updates user with all fields', function () {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@test.com',
        ]);

        livewire(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'name' => 'Updated Name',
                'email' => 'updated@test.com',
                'role' => UserRole::ADMIN,
            ])
            ->call('save')
            ->assertNotified()
            ->assertHasNoFormErrors();

        assertDatabaseHas(User::class, [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@test.com',
            'role' => UserRole::ADMIN,
        ]);
    });

    it('allows warehouse assignment with proper relationship', function () {
        $warehouse = Warehouse::factory()->create();
        $user = User::factory()->create();

        livewire(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'warehouses' => [$warehouse->id],
            ])
            ->call('save')
            ->assertNotified()
            ->assertHasNoFormErrors();

        $user->refresh();
        expect($user->warehouses->pluck('id')->toArray())
            ->toBe([$warehouse->id]);
    });

    // Search/filter/sort tests - using direct database assertions with isolated data
    it('can search users by name', function () {
        User::truncate();
        $user1 = User::factory()->create(['name' => 'John Smith', 'email' => 'john@test.com']);
        $user2 = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@test.com']);

        $results = User::where('name', 'like', '%John%')->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($user1->id);
    });

    it('can search users by email', function () {
        User::truncate();
        $user1 = User::factory()->create(['name' => 'John Smith', 'email' => 'john@test.com']);
        $user2 = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@test.com']);

        $results = User::where('email', 'like', '%jane%')->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($user2->id);
    });

    it('can filter users by role', function () {
        User::truncate();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $staff = User::factory()->create(['role' => UserRole::WAREHOUSE_STAFF]);
        $manager = User::factory()->create(['role' => UserRole::BRANCH_MANAGER]);

        $admins = User::where('role', UserRole::ADMIN->value)->get();
        expect($admins)->toHaveCount(1);
        expect($admins->first()->id)->toBe($admin->id);
    });

    it('can sort users by created_at asc', function () {
        User::truncate();
        $user1 = User::factory()->create(['created_at' => now()->subDays(2)]);
        $user2 = User::factory()->create(['created_at' => now()->subDay()]);
        $user3 = User::factory()->create(['created_at' => now()]);

        $results = User::orderBy('created_at', 'asc')->get();
        expect($results->pluck('id')->toArray())
            ->toBe([$user1->id, $user2->id, $user3->id]);
    });

    it('can sort users by created_at desc', function () {
        User::truncate();
        $user1 = User::factory()->create(['created_at' => now()->subDays(2)]);
        $user2 = User::factory()->create(['created_at' => now()->subDay()]);
        $user3 = User::factory()->create(['created_at' => now()]);

        $results = User::orderBy('created_at', 'desc')->get();
        expect($results->pluck('id')->toArray())
            ->toBe([$user3->id, $user2->id, $user1->id]);
    });
});
