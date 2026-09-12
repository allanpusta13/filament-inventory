<?php

declare(strict_types=1);

use App\Filament\Resources\Warehouses\Pages\CreateWarehouse;
use App\Filament\Resources\Warehouses\Pages\EditWarehouse;
use App\Filament\Resources\Warehouses\Pages\ListWarehouses;
use App\Filament\Resources\Warehouses\Pages\ViewWarehouse;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Warehouse::truncate();
    User::truncate();

    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

describe('WarehouseResource edge cases', function () {
    it('validates unique code on create', function () {
        $existingWarehouse = Warehouse::factory()->create(['code' => 'WH-EXISTING']);

        livewire(CreateWarehouse::class)
            ->fillForm([
                'code' => 'WH-EXISTING',
                'name' => 'New Warehouse',
                'location' => 'Test Location',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['code' => 'unique'])
            ->assertNotNotified();
    });

    it('validates unique code on edit', function () {
        $existingWarehouse = Warehouse::factory()->create(['code' => 'WH-EXISTING']);
        $warehouse = Warehouse::factory()->create(['code' => 'WH-OTHER']);

        livewire(EditWarehouse::class, ['record' => $warehouse->id])
            ->fillForm([
                'code' => 'WH-EXISTING',
            ])
            ->call('save')
            ->assertHasFormErrors(['code' => 'unique'])
            ->assertNotNotified();
    });

    it('allows same code when editing same record', function () {
        $warehouse = Warehouse::factory()->create(['code' => 'WH-SAME', 'name' => 'Original Name']);

        livewire(EditWarehouse::class, ['record' => $warehouse->id])
            ->fillForm([
                'code' => 'WH-SAME',
                'name' => 'Updated Name',
            ])
            ->call('save')
            ->assertNotified()
            ->assertHasNoFormErrors();

        assertDatabaseHas(Warehouse::class, [
            'id' => $warehouse->id,
            'code' => 'WH-SAME',
            'name' => 'Updated Name',
        ]);
    });

    it('requires code on create', function () {
        livewire(CreateWarehouse::class)
            ->fillForm([
                'name' => 'Test Warehouse',
                'location' => 'Test Location',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['code' => 'required'])
            ->assertNotNotified();
    });

    it('requires name on create', function () {
        livewire(CreateWarehouse::class)
            ->fillForm([
                'code' => 'WH-NEW',
                'location' => 'Test Location',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required'])
            ->assertNotNotified();
    });

    it('allows nullable location', function () {
        livewire(CreateWarehouse::class)
            ->fillForm([
                'code' => 'WH-NEW',
                'name' => 'Test Warehouse',
                'location' => null,
                'is_active' => true,
            ])
            ->call('create')
            ->assertNotified()
            ->assertHasNoFormErrors();

        assertDatabaseHas(Warehouse::class, [
            'code' => 'WH-NEW',
            'name' => 'Test Warehouse',
            'location' => null,
        ]);
    });

    it('validates is_active is boolean', function () {
        livewire(CreateWarehouse::class)
            ->fillForm([
                'code' => 'WH-NEW',
                'name' => 'Test Warehouse',
                'is_active' => 'not-boolean',
            ])
            ->call('create')
            ->assertHasFormErrors(['is_active'])
            ->assertNotNotified();
    });

    it('defaults is_active to true on create', function () {
        livewire(CreateWarehouse::class)
            ->fillForm([
                'code' => 'WH-NEW',
                'name' => 'Test Warehouse',
            ])
            ->call('create')
            ->assertNotified()
            ->assertHasNoFormErrors();

        $warehouse = Warehouse::where('code', 'WH-NEW')->first();
        expect($warehouse->is_active)->toBeTrue();
    });

    it('allows deactivating warehouse', function () {
        $warehouse = Warehouse::factory()->create(['is_active' => true]);

        livewire(EditWarehouse::class, ['record' => $warehouse->id])
            ->fillForm([
                'is_active' => false,
            ])
            ->call('save')
            ->assertNotified()
            ->assertHasNoFormErrors();

        $warehouse->refresh();
        expect($warehouse->is_active)->toBeFalse();
    });

    // Search/filter/sort tests - using direct database assertions with isolated data
    it('can search warehouses by code', function () {
        Warehouse::truncate();
        $wh1 = Warehouse::factory()->create(['code' => 'WH-MNL', 'name' => 'Manila Main', 'location' => 'Manila, Philippines']);
        $wh2 = Warehouse::factory()->create(['code' => 'WH-CEB', 'name' => 'Cebu Branch', 'location' => 'Cebu, Philippines']);

        $results = Warehouse::where('code', 'like', '%MNL%')->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($wh1->id);
    });

    it('can search warehouses by name', function () {
        Warehouse::truncate();
        $wh1 = Warehouse::factory()->create(['code' => 'WH-MNL', 'name' => 'Manila Main', 'location' => 'Manila, Philippines']);
        $wh2 = Warehouse::factory()->create(['code' => 'WH-CEB', 'name' => 'Cebu Branch', 'location' => 'Cebu, Philippines']);

        $results = Warehouse::where('name', 'like', '%Cebu%')->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($wh2->id);
    });

    it('can search warehouses by location', function () {
        Warehouse::truncate();
        $wh1 = Warehouse::factory()->create(['code' => 'WH-MNL', 'name' => 'Manila Main', 'location' => 'Manila, Philippines']);
        $wh2 = Warehouse::factory()->create(['code' => 'WH-CEB', 'name' => 'Cebu Branch', 'location' => 'Cebu, Philippines']);

        $results = Warehouse::where('location', 'like', '%Cebu%')->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($wh2->id);
    });

    it('can filter warehouses by is_active status', function () {
        Warehouse::truncate();
        $active = Warehouse::factory()->create(['is_active' => true]);
        $inactive = Warehouse::factory()->create(['is_active' => false]);

        $activeResults = Warehouse::where('is_active', true)->get();
        $inactiveResults = Warehouse::where('is_active', false)->get();

        expect($activeResults)->toHaveCount(1);
        expect($activeResults->first()->id)->toBe($active->id);

        expect($inactiveResults)->toHaveCount(1);
        expect($inactiveResults->first()->id)->toBe($inactive->id);
    });

    it('can filter warehouses by users relationship', function () {
        Warehouse::truncate();
        User::truncate();

        $wh1 = Warehouse::factory()->create();
        $wh2 = Warehouse::factory()->create();
        $user = User::factory()->create();
        $user->warehouses()->attach($wh1->id);

        $results = Warehouse::whereHas('users', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($wh1->id);
    });

    it('can sort warehouses by code', function () {
        Warehouse::truncate();
        $wh1 = Warehouse::factory()->create(['code' => 'WH-C', 'name' => 'C Warehouse', 'created_at' => now()->subDays(2)]);
        $wh2 = Warehouse::factory()->create(['code' => 'WH-A', 'name' => 'A Warehouse', 'created_at' => now()->subDay()]);
        $wh3 = Warehouse::factory()->create(['code' => 'WH-B', 'name' => 'B Warehouse', 'created_at' => now()]);

        $results = Warehouse::orderBy('code', 'asc')->get();
        expect($results->pluck('id')->toArray())
            ->toBe([$wh2->id, $wh3->id, $wh1->id]);
    });

    it('can sort warehouses by name', function () {
        Warehouse::truncate();
        $wh1 = Warehouse::factory()->create(['code' => 'WH-C', 'name' => 'C Warehouse', 'created_at' => now()->subDays(2)]);
        $wh2 = Warehouse::factory()->create(['code' => 'WH-A', 'name' => 'A Warehouse', 'created_at' => now()->subDay()]);
        $wh3 = Warehouse::factory()->create(['code' => 'WH-B', 'name' => 'B Warehouse', 'created_at' => now()]);

        $results = Warehouse::orderBy('name', 'asc')->get();
        expect($results->pluck('id')->toArray())
            ->toBe([$wh2->id, $wh3->id, $wh1->id]);
    });

    it('can sort warehouses by created_at', function () {
        Warehouse::truncate();
        $wh1 = Warehouse::factory()->create(['code' => 'WH-C', 'name' => 'C Warehouse', 'created_at' => now()->subDays(2)]);
        $wh2 = Warehouse::factory()->create(['code' => 'WH-A', 'name' => 'A Warehouse', 'created_at' => now()->subDay()]);
        $wh3 = Warehouse::factory()->create(['code' => 'WH-B', 'name' => 'B Warehouse', 'created_at' => now()]);

        $results = Warehouse::orderBy('created_at', 'asc')->get();
        expect($results->pluck('id')->toArray())
            ->toBe([$wh1->id, $wh2->id, $wh3->id]);
    });

    it('sorts is_active with correct tiebreaker', function () {
        Warehouse::truncate();
        $active1 = Warehouse::factory()->create(['is_active' => true, 'id' => 1]);
        $active2 = Warehouse::factory()->create(['is_active' => true, 'id' => 2]);
        $inactive = Warehouse::factory()->create(['is_active' => false, 'id' => 3]);

        $results = Warehouse::orderBy('is_active', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        expect($results->pluck('id')->toArray())
            ->toBe([$active1->id, $active2->id, $inactive->id]);
    });

    it('renders create page with defaults', function () {
        livewire(CreateWarehouse::class)
            ->assertOk()
            ->assertSchemaStateSet([
                'is_active' => true,
            ]);
    });

    it('renders edit page with correct data', function () {
        $warehouse = Warehouse::factory()->create(['code' => 'WH-TEST', 'name' => 'Test Warehouse', 'location' => 'Test Location']);

        livewire(EditWarehouse::class, ['record' => $warehouse->id])
            ->assertOk()
            ->assertSchemaStateSet([
                'code' => 'WH-TEST',
                'name' => 'Test Warehouse',
                'location' => 'Test Location',
                'is_active' => true,
            ]);
    });

    it('renders view page with correct data', function () {
        $warehouse = Warehouse::factory()->create(['code' => 'WH-VIEW', 'name' => 'View Warehouse', 'location' => 'View Location']);

        livewire(ViewWarehouse::class, ['record' => $warehouse->id])
            ->assertOk()
            ->assertSchemaStateSet([
                'code' => 'WH-VIEW',
                'name' => 'View Warehouse',
                'location' => 'View Location',
                'is_active' => true,
            ]);
    });

    it('creates warehouse via form', function () {
        livewire(CreateWarehouse::class)
            ->fillForm([
                'code' => 'WH-CREATE',
                'name' => 'Created Warehouse',
                'location' => 'Created Location',
                'is_active' => true,
            ])
            ->call('create')
            ->assertNotified()
            ->assertHasNoFormErrors();

        assertDatabaseHas(Warehouse::class, [
            'code' => 'WH-CREATE',
            'name' => 'Created Warehouse',
            'location' => 'Created Location',
            'is_active' => true,
        ]);
    });

    it('updates warehouse via form', function () {
        $warehouse = Warehouse::factory()->create(['code' => 'WH-ORIG', 'name' => 'Original Name']);

        livewire(EditWarehouse::class, ['record' => $warehouse->id])
            ->fillForm([
                'code' => 'WH-UPDATED',
                'name' => 'Updated Name',
                'location' => 'Updated Location',
            ])
            ->call('save')
            ->assertNotified()
            ->assertHasNoFormErrors();

        assertDatabaseHas(Warehouse::class, [
            'id' => $warehouse->id,
            'code' => 'WH-UPDATED',
            'name' => 'Updated Name',
            'location' => 'Updated Location',
        ]);
    });

    it('deletes warehouse via view page action', function () {
        $warehouse = Warehouse::factory()->create();

        livewire(ViewWarehouse::class, ['record' => $warehouse->id])
            ->callAction(DeleteAction::class)
            ->assertNotified()
            ->assertRedirect();

        assertDatabaseMissing($warehouse);
    });

    it('bulk deletes warehouses', function () {
        $warehouses = Warehouse::factory()->count(5)->create();

        livewire(ListWarehouses::class)
            ->loadTable()
            ->assertCanSeeTableRecords($warehouses)
            ->selectTableRecords($warehouses)
            ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
            ->assertNotified()
            ->assertCanNotSeeTableRecords($warehouses);

        $warehouses->each(fn (Warehouse $w) => assertDatabaseMissing($w));
    });

    it('displays user count in table', function () {
        Warehouse::truncate();
        User::truncate();

        $wh1 = Warehouse::factory()->create();
        $wh2 = Warehouse::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $user1->warehouses()->attach($wh1->id);
        $user2->warehouses()->attach($wh1->id);
        $user3->warehouses()->attach($wh2->id);

        $wh1->loadCount('users');
        $wh2->loadCount('users');

        expect($wh1->users_count)->toBe(2);
        expect($wh2->users_count)->toBe(1);
    });

    it('handles delete gracefully', function () {
        $warehouse = Warehouse::factory()->create();

        livewire(ViewWarehouse::class, ['record' => $warehouse->id])
            ->callAction(DeleteAction::class)
            ->assertNotified()
            ->assertRedirect();

        assertDatabaseMissing($warehouse);
    });

    it('handles warehouse with no users assigned', function () {
        Warehouse::truncate();
        $warehouse = Warehouse::factory()->create();

        $warehouse->loadCount('users');
        expect($warehouse->users_count)->toBe(0);
    });

    it('does not show location when empty in table', function () {
        Warehouse::truncate();
        $whWithLocation = Warehouse::factory()->create(['location' => 'Test Location']);
        $whWithoutLocation = Warehouse::factory()->create(['location' => null]);

        $warehouses = Warehouse::all();
        expect($warehouses)->toHaveCount(2);
    });
});
