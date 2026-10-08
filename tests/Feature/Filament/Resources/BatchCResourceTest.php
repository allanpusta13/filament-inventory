<?php

declare(strict_types=1);

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

/**
 * Batch C resource contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 */
uses(RefreshDatabase::class);

// ===========================================================================
// WarehouseResource
// ===========================================================================

describe('WarehouseResource', function () {
    it('binds to the Warehouse model', function () {
        expect(WarehouseResource::getModel())->toBe(Warehouse::class);
    });

    it('declares the SYSTEM ADMIN navigation group with sort 1', function () {
        $reflection = new ReflectionClass(WarehouseResource::class);

        expect($reflection->getStaticPropertyValue('navigationGroup'))->toBe('SYSTEM ADMIN');
        expect($reflection->getStaticPropertyValue('navigationSort'))->toBe(1);
    });

    it('uses distinct resting and active navigation icons', function () {
        $reflection = new ReflectionClass(WarehouseResource::class);

        expect($reflection->getStaticPropertyValue('navigationIcon'))->toBe(Heroicon::OutlinedBuildingOffice);
        expect($reflection->getStaticPropertyValue('activeNavigationIcon'))->toBe(Heroicon::BuildingOffice);
    });

    it('resolves translated labels through resources.warehouses.*', function () {
        expect(WarehouseResource::getModelLabel())->toBe(__('resources.warehouses.model.singular'));
        expect(WarehouseResource::getPluralModelLabel())->toBe(__('resources.warehouses.model.plural'));
        expect(WarehouseResource::getNavigationLabel())->toBe(__('resources.warehouses.navigation.label'));
    });

    it('registers index, create, view, and edit pages', function () {
        $pages = WarehouseResource::getPages();

        expect($pages)->toHaveKeys(['index', 'create', 'view', 'edit']);
    });

    it('resolves the WarehousePolicy through the Gate', function () {
        expect(Gate::getPolicyFor(Warehouse::class))->toBeInstanceOf(App\Policies\WarehousePolicy::class);
    });

    it('eager-loads users and withCounts both users and stockMovements', function () {
        $this->actingAs(User::factory()->admin()->create());

        $query = WarehouseResource::getEloquentQuery();
        expect($query->getEagerLoads())->toHaveKey('users');
    });

    it('renders the list page for an admin', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(WarehouseResource::getUrl('index'))
            ->assertSuccessful();
    });

    it('renders the create page for an admin', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(WarehouseResource::getUrl('create'))
            ->assertSuccessful();
    });

    it('renders the view page for an admin', function () {
        $warehouse = Warehouse::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(WarehouseResource::getUrl('view', ['record' => $warehouse]))
            ->assertSuccessful();
    });

    it('renders the edit page for an admin', function () {
        $warehouse = Warehouse::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(WarehouseResource::getUrl('edit', ['record' => $warehouse]))
            ->assertSuccessful();
    });
});

// ===========================================================================
// SupplierResource
// ===========================================================================

describe('SupplierResource', function () {
    it('binds to the Supplier model', function () {
        expect(SupplierResource::getModel())->toBe(Supplier::class);
    });

    it('declares the PURCHASING navigation group with sort 2', function () {
        $reflection = new ReflectionClass(SupplierResource::class);

        expect($reflection->getStaticPropertyValue('navigationGroup'))->toBe('PURCHASING');
        expect($reflection->getStaticPropertyValue('navigationSort'))->toBe(2);
    });

    it('uses distinct resting and active navigation icons', function () {
        $reflection = new ReflectionClass(SupplierResource::class);

        expect($reflection->getStaticPropertyValue('navigationIcon'))->toBe(Heroicon::OutlinedBuildingStorefront);
        expect($reflection->getStaticPropertyValue('activeNavigationIcon'))->toBe(Heroicon::BuildingStorefront);
    });

    it('resolves translated labels through resources.suppliers.*', function () {
        expect(SupplierResource::getModelLabel())->toBe(__('resources.suppliers.model.singular'));
        expect(SupplierResource::getPluralModelLabel())->toBe(__('resources.suppliers.model.plural'));
        expect(SupplierResource::getNavigationLabel())->toBe(__('resources.suppliers.navigation.label'));
    });

    it('registers index, create, and edit pages (no view)', function () {
        $pages = SupplierResource::getPages();

        expect($pages)->toHaveKeys(['index', 'create', 'edit']);
        expect($pages)->not->toHaveKey('view');
    });

    it('resolves the SupplierPolicy through the Gate', function () {
        expect(Gate::getPolicyFor(Supplier::class))->toBeInstanceOf(App\Policies\SupplierPolicy::class);
    });

    it('renders the list page for an admin', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(SupplierResource::getUrl('index'))
            ->assertSuccessful();
    });

    it('renders the create page for an admin', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(SupplierResource::getUrl('create'))
            ->assertSuccessful();
    });

    it('renders the edit page for an admin', function () {
        $supplier = Supplier::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(SupplierResource::getUrl('edit', ['record' => $supplier]))
            ->assertSuccessful();
    });
});

// ===========================================================================
// CustomerResource
// ===========================================================================

describe('CustomerResource', function () {
    it('binds to the Customer model', function () {
        expect(CustomerResource::getModel())->toBe(Customer::class);
    });

    it('declares the SALES navigation group with sort 2', function () {
        $reflection = new ReflectionClass(CustomerResource::class);

        expect($reflection->getStaticPropertyValue('navigationGroup'))->toBe('SALES');
        expect($reflection->getStaticPropertyValue('navigationSort'))->toBe(2);
    });

    it('uses distinct resting and active navigation icons', function () {
        $reflection = new ReflectionClass(CustomerResource::class);

        expect($reflection->getStaticPropertyValue('navigationIcon'))->toBe(Heroicon::OutlinedUserGroup);
        expect($reflection->getStaticPropertyValue('activeNavigationIcon'))->toBe(Heroicon::UserGroup);
    });

    it('resolves translated labels through resources.customers.*', function () {
        expect(CustomerResource::getModelLabel())->toBe(__('resources.customers.model.singular'));
        expect(CustomerResource::getPluralModelLabel())->toBe(__('resources.customers.model.plural'));
        expect(CustomerResource::getNavigationLabel())->toBe(__('resources.customers.navigation.label'));
    });

    it('registers index, create, and edit pages (no view)', function () {
        $pages = CustomerResource::getPages();

        expect($pages)->toHaveKeys(['index', 'create', 'edit']);
        expect($pages)->not->toHaveKey('view');
    });

    it('resolves the CustomerPolicy through the Gate', function () {
        expect(Gate::getPolicyFor(Customer::class))->toBeInstanceOf(App\Policies\CustomerPolicy::class);
    });

    it('renders the list page for an admin', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(CustomerResource::getUrl('index'))
            ->assertSuccessful();
    });

    it('renders the create page for an admin', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(CustomerResource::getUrl('create'))
            ->assertSuccessful();
    });

    it('renders the edit page for an admin', function () {
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(CustomerResource::getUrl('edit', ['record' => $customer]))
            ->assertSuccessful();
    });
});

// ===========================================================================
// Translation coverage (§0A.15)
// ===========================================================================

it('resolves every warehouses translation key it renders', function () {
    $keys = [
        'resources.warehouses.model.singular',
        'resources.warehouses.model.plural',
        'resources.warehouses.navigation.label',
        'resources.warehouses.fields.code',
        'resources.warehouses.fields.name',
        'resources.warehouses.fields.location',
        'resources.warehouses.fields.is_active',
        'resources.warehouses.fields.users',
        'resources.warehouses.fields.assigned_staff',
        'resources.warehouses.help.code',
        'resources.warehouses.help.users_readonly',
        'resources.warehouses.placeholders.code',
        'resources.warehouses.filters.is_active',
        'resources.warehouses.form.profile',
        'resources.warehouses.form.access_status',
        'resources.warehouses.table.code',
        'resources.warehouses.table.name',
        'resources.warehouses.table.location',
        'resources.warehouses.table.status',
        'resources.warehouses.table.staff',
        'resources.warehouses.table.ledger_entries',
        'resources.warehouses.infolist.profile',
        'resources.warehouses.infolist.status',
        'resources.warehouses.infolist.assigned_staff',
        'resources.warehouses.empty_staff',
        'resources.warehouses.delete_confirm_description',
    ];

    foreach ($keys as $key) {
        $label = __($key);
        expect($label)->toBeString()->not->toBe('');
        expect($label)->not->toBe($key);
    }
});

it('resolves every suppliers translation key it renders', function () {
    $keys = [
        'resources.suppliers.model.singular',
        'resources.suppliers.model.plural',
        'resources.suppliers.navigation.label',
        'resources.suppliers.fields.name',
        'resources.suppliers.fields.contact_person',
        'resources.suppliers.fields.phone',
        'resources.suppliers.fields.email',
        'resources.suppliers.fields.address',
        'resources.suppliers.fields.is_active',
        'resources.suppliers.filters.is_active',
        'resources.suppliers.table.name',
        'resources.suppliers.table.contact',
        'resources.suppliers.table.email',
        'resources.suppliers.table.phone',
        'resources.suppliers.table.status',
        'resources.suppliers.table.purchase_orders',
    ];

    foreach ($keys as $key) {
        $label = __($key);
        expect($label)->toBeString()->not->toBe('');
        expect($label)->not->toBe($key);
    }
});

it('resolves every customers translation key it renders', function () {
    $keys = [
        'resources.customers.model.singular',
        'resources.customers.model.plural',
        'resources.customers.navigation.label',
        'resources.customers.fields.name',
        'resources.customers.fields.contact_person',
        'resources.customers.fields.phone',
        'resources.customers.fields.email',
        'resources.customers.fields.address',
        'resources.customers.fields.is_active',
        'resources.customers.filters.is_active',
        'resources.customers.table.name',
        'resources.customers.table.contact',
        'resources.customers.table.email',
        'resources.customers.table.phone',
        'resources.customers.table.status',
        'resources.customers.table.sales_orders',
    ];

    foreach ($keys as $key) {
        $label = __($key);
        expect($label)->toBeString()->not->toBe('');
        expect($label)->not->toBe($key);
    }
});
