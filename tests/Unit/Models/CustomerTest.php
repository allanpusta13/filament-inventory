<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Customer model contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §2.15 customers schema: columns, is_active default true, soft
 *     deletes, no `code` column.
 *   - §3.13 model shape: fillable, casts, salesOrders() relation.
 *   - §5.8 CustomerFactory defaults.
 *   - §8.10 CustomerPolicy admin-only delete (relation-consulting set
 *     asserted here; policy behavior belongs in the policy test suite).
 *   - §2.18 restrictOnDelete on sales_orders.customer_id.
 *   - A3: customers are minimal master data.
 *   - Symmetry with Supplier (§3.12): the two master-data models carry
 *     the same shape.
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Traits, fillable, casts
// ---------------------------------------------------------------------------

it('uses HasFactory and SoftDeletes traits', function () {
    $traits = class_uses_recursive(Customer::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\SoftDeletes::class);
});

it('declares exactly the §3.13 fillable set', function () {
    $customer = new Customer();

    expect($customer->getFillable())->toBe([
        'name', 'contact_person', 'phone', 'email', 'address', 'is_active',
    ]);
});

it('casts is_active to boolean', function () {
    $customer = Customer::factory()->create(['is_active' => 1]);

    expect($customer->fresh()->is_active)->toBeTrue();

    $customer->update(['is_active' => 0]);
    expect($customer->fresh()->is_active)->toBeFalse();
});

it('defaults is_active to true via the §2.15 schema', function () {
    $customer = Customer::factory()->create();

    expect($customer->is_active)->toBeTrue();
});

// ---------------------------------------------------------------------------
// A3 — no code column
// ---------------------------------------------------------------------------

it('has no code column — customers are identified by name (owner decision, §2.15)', function () {
    // A3 + §2.15: customers carry no `code`. The absence of a code is
    // a deliberate design decision, not an omission.
    $customer = Customer::factory()->create();

    expect($customer->getAttributes())->not->toHaveKey('code');
    expect($customer->getFillable())->not->toContain('code');
});

// ---------------------------------------------------------------------------
// §5.8 factory shape
// ---------------------------------------------------------------------------

it('produces a customer with the §5.8 factory defaults', function () {
    // §5.8: name = company, contact_person = name, phone = phoneNumber,
    // email = companyEmail, address = address, is_active = true.
    $customer = Customer::factory()->create();

    expect($customer->name)->toBeString()->not->toBe('');
    expect($customer->contact_person)->toBeString()->not->toBe('');
    expect($customer->phone)->toBeString()->not->toBe('');
    expect($customer->email)->toContain('@');
    expect($customer->address)->toBeString()->not->toBe('');
    expect($customer->is_active)->toBeTrue();
});

// ---------------------------------------------------------------------------
// Relations
// ---------------------------------------------------------------------------

it('exposes salesOrders() as a HasMany relation', function () {
    $customer = new Customer();

    expect($customer->salesOrders())->toBeInstanceOf(HasMany::class);
    expect($customer->salesOrders()->getRelated())->toBeInstanceOf(SalesOrder::class);
});

it('resolves sales orders end-to-end', function () {
    $customer = Customer::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $user = User::factory()->create();

    SalesOrder::factory()->count(2)->create([
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'ordered_by' => $user->id,
    ]);

    expect($customer->salesOrders()->count())->toBe(2);
    expect($customer->salesOrders->first()->customer_id)->toBe($customer->id);
});

it('returns an empty collection when no sales orders exist', function () {
    $customer = Customer::factory()->create();

    expect($customer->salesOrders)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// §2.15 nullable columns
// ---------------------------------------------------------------------------

it('allows contact_person, phone, email, and address to be null', function () {
    // §2.15: all four optional fields are nullable.
    $customer = Customer::factory()->create([
        'contact_person' => null,
        'phone' => null,
        'email' => null,
        'address' => null,
    ]);

    $fresh = $customer->fresh();

    expect($fresh->contact_person)->toBeNull();
    expect($fresh->phone)->toBeNull();
    expect($fresh->email)->toBeNull();
    expect($fresh->address)->toBeNull();
});

// ---------------------------------------------------------------------------
// §2.15 soft-delete semantics
// ---------------------------------------------------------------------------

it('soft-deletes a customer', function () {
    $customer = Customer::factory()->create();

    $customer->delete();

    expect(Customer::find($customer->id))->toBeNull();
    expect(Customer::withTrashed()->find($customer->id))->not->toBeNull();
    expect($customer->fresh()->deleted_at)->not->toBeNull();
});

it('restores a soft-deleted customer', function () {
    $customer = Customer::factory()->create();
    $customer->delete();

    $customer->restore();

    expect(Customer::find($customer->id))->not->toBeNull();
    expect($customer->fresh()->deleted_at)->toBeNull();
});

it('supports onlyTrashed() lookup', function () {
    $active = Customer::factory()->create();
    $trashed = Customer::factory()->create();
    $trashed->delete();

    expect(Customer::onlyTrashed()->pluck('id')->all())->toBe([$trashed->id]);
});

// ---------------------------------------------------------------------------
// §2.18 FK — restrictOnDelete on sales_orders.customer_id
// ---------------------------------------------------------------------------

it('blocks hard deletion of a customer referenced by a sales order (restrictOnDelete, §2.18)', function () {
    $customer = Customer::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $user = User::factory()->create();

    SalesOrder::factory()->create([
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'ordered_by' => $user->id,
    ]);

    expect(fn () => $customer->forceDelete())->toThrow(QueryException::class);
});

it('allows hard deletion of a customer with no sales orders', function () {
    $customer = Customer::factory()->create();

    $customer->forceDelete();

    expect(Customer::withTrashed()->find($customer->id))->toBeNull();
});

it('allows soft deletion of a customer that has sales orders', function () {
    // Soft delete only sets `deleted_at` — it never touches the FK, so
    // a customer with historical SOs can be retired without breaking
    // the audit trail.
    $customer = Customer::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $user = User::factory()->create();

    SalesOrder::factory()->create([
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'ordered_by' => $user->id,
    ]);

    $customer->delete();

    expect($customer->fresh()->deleted_at)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// §8.10 CustomerPolicy::delete() — relation-consulting set
// ---------------------------------------------------------------------------

it('exposes only salesOrders() as the zero-arg HasMany relation', function () {
    // §8.10 CustomerPolicy::delete() is admin-only; it does not gate on
    // sales-order existence. This reflection test documents the
    // relation surface so any future relation added to Customer for
    // policy purposes surfaces here. Kept as an explicit constraint.
    $relationMethods = collect((new ReflectionClass(Customer::class))->getMethods())
        ->filter(fn ($m) => $m->class === Customer::class
            && $m->getNumberOfParameters() === 0
            && ! $m->isStatic()
            && $m->getReturnType()?->getName() === HasMany::class)
        ->pluck('name')
        ->all();

    expect($relationMethods)->toBe(['salesOrders']);
});

// ---------------------------------------------------------------------------
// Symmetry with Supplier (§3.12)
// ---------------------------------------------------------------------------

it('mirrors the Supplier model shape', function () {
    // §3.12 and §3.13 are symmetric master-data models: identical
    // fillable column sets (except the relation target), identical
    // casts, identical trait set. A drift in either would be caught by
    // this symmetry assertion.
    $supplier = new App\Models\Supplier();
    $customer = new Customer();

    expect($customer->getFillable())->toBe($supplier->getFillable());
    expect($customer->getCasts()['is_active'])->toBe($supplier->getCasts()['is_active']);

    $customerTraits = array_keys(class_uses_recursive(Customer::class));
    $supplierTraits = array_keys(class_uses_recursive(App\Models\Supplier::class));

    expect($customerTraits)->toEqualCanonicalizing($supplierTraits);
});
