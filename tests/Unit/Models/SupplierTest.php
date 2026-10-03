<?php

declare(strict_types=1);

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Supplier model contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §2.14 suppliers schema: columns, is_active default true, soft
 *     deletes, no `code` column.
 *   - §3.12 model shape: fillable, casts, purchaseOrders() relation.
 *   - §5.7 SupplierFactory defaults.
 *   - §8.9 SupplierPolicy admin-only delete (relation-consulting set
 *     asserted here; policy behavior belongs in the policy test suite).
 *   - §2.16 restrictOnDelete on purchase_orders.supplier_id.
 *   - A3: suppliers are minimal master data.
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Traits, fillable, casts
// ---------------------------------------------------------------------------

it('uses HasFactory and SoftDeletes traits', function () {
    $traits = class_uses_recursive(Supplier::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\SoftDeletes::class);
});

it('declares exactly the §3.12 fillable set', function () {
    $supplier = new Supplier();

    expect($supplier->getFillable())->toBe([
        'name', 'contact_person', 'phone', 'email', 'address', 'is_active',
    ]);
});

it('casts is_active to boolean', function () {
    $supplier = Supplier::factory()->create(['is_active' => 1]);

    expect($supplier->fresh()->is_active)->toBeTrue();

    $supplier->update(['is_active' => 0]);
    expect($supplier->fresh()->is_active)->toBeFalse();
});

it('defaults is_active to true via the §2.14 schema', function () {
    $supplier = Supplier::factory()->create();

    expect($supplier->is_active)->toBeTrue();
});

// ---------------------------------------------------------------------------
// A3 — no code column
// ---------------------------------------------------------------------------

it('has no code column — suppliers are identified by name (owner decision, §2.14)', function () {
    // A3 + §2.14: suppliers carry no `code`. The absence of a code is a
    // deliberate design decision, not an omission.
    $supplier = Supplier::factory()->create();

    expect($supplier->getAttributes())->not->toHaveKey('code');
    expect($supplier->getFillable())->not->toContain('code');
});

// ---------------------------------------------------------------------------
// §5.7 factory shape
// ---------------------------------------------------------------------------

it('produces a supplier with the §5.7 factory defaults', function () {
    // §5.7: name = company, contact_person = name, phone = phoneNumber,
    // email = companyEmail, address = address, is_active = true.
    $supplier = Supplier::factory()->create();

    expect($supplier->name)->toBeString()->not->toBe('');
    expect($supplier->contact_person)->toBeString()->not->toBe('');
    expect($supplier->phone)->toBeString()->not->toBe('');
    expect($supplier->email)->toContain('@');
    expect($supplier->address)->toBeString()->not->toBe('');
    expect($supplier->is_active)->toBeTrue();
});

// ---------------------------------------------------------------------------
// Relations
// ---------------------------------------------------------------------------

it('exposes purchaseOrders() as a HasMany relation', function () {
    $supplier = new Supplier();

    expect($supplier->purchaseOrders())->toBeInstanceOf(HasMany::class);
    expect($supplier->purchaseOrders()->getRelated())->toBeInstanceOf(PurchaseOrder::class);
});

it('resolves purchase orders end-to-end', function () {
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $user = User::factory()->create();

    PurchaseOrder::factory()->count(2)->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'ordered_by' => $user->id,
    ]);

    expect($supplier->purchaseOrders()->count())->toBe(2);
    expect($supplier->purchaseOrders->first()->supplier_id)->toBe($supplier->id);
});

it('returns an empty collection when no purchase orders exist', function () {
    $supplier = Supplier::factory()->create();

    expect($supplier->purchaseOrders)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// §2.14 nullable columns
// ---------------------------------------------------------------------------

it('allows contact_person, phone, email, and address to be null', function () {
    // §2.14: all four optional fields are nullable.
    $supplier = Supplier::factory()->create([
        'contact_person' => null,
        'phone' => null,
        'email' => null,
        'address' => null,
    ]);

    $fresh = $supplier->fresh();

    expect($fresh->contact_person)->toBeNull();
    expect($fresh->phone)->toBeNull();
    expect($fresh->email)->toBeNull();
    expect($fresh->address)->toBeNull();
});

// ---------------------------------------------------------------------------
// §2.14 soft-delete semantics
// ---------------------------------------------------------------------------

it('soft-deletes a supplier', function () {
    $supplier = Supplier::factory()->create();

    $supplier->delete();

    expect(Supplier::find($supplier->id))->toBeNull();
    expect(Supplier::withTrashed()->find($supplier->id))->not->toBeNull();
    expect($supplier->fresh()->deleted_at)->not->toBeNull();
});

it('restores a soft-deleted supplier', function () {
    $supplier = Supplier::factory()->create();
    $supplier->delete();

    $supplier->restore();

    expect(Supplier::find($supplier->id))->not->toBeNull();
    expect($supplier->fresh()->deleted_at)->toBeNull();
});

it('supports onlyTrashed() lookup', function () {
    $active = Supplier::factory()->create();
    $trashed = Supplier::factory()->create();
    $trashed->delete();

    expect(Supplier::onlyTrashed()->pluck('id')->all())->toBe([$trashed->id]);
});

// ---------------------------------------------------------------------------
// §2.16 FK — restrictOnDelete on purchase_orders.supplier_id
// ---------------------------------------------------------------------------

it('blocks hard deletion of a supplier referenced by a purchase order (restrictOnDelete, §2.16)', function () {
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $user = User::factory()->create();

    PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'ordered_by' => $user->id,
    ]);

    expect(fn () => $supplier->forceDelete())->toThrow(QueryException::class);
});

it('allows hard deletion of a supplier with no purchase orders', function () {
    $supplier = Supplier::factory()->create();

    $supplier->forceDelete();

    expect(Supplier::withTrashed()->find($supplier->id))->toBeNull();
});

it('allows soft deletion of a supplier that has purchase orders', function () {
    // Soft delete only sets `deleted_at` — it never touches the FK, so
    // a supplier with historical POs can be retired without breaking
    // the audit trail.
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $user = User::factory()->create();

    PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'ordered_by' => $user->id,
    ]);

    $supplier->delete();

    expect($supplier->fresh()->deleted_at)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// §8.9 SupplierPolicy::delete() — relation-consulting set
// ---------------------------------------------------------------------------

it('exposes the purchaseOrders() relation SupplierPolicy::delete() consults', function () {
    // §8.9 does not consult relations — it is admin-only (`return
    // $user->isAdmin()`). The policy does not gate on purchase-order
    // existence, so there is nothing additional to assert here beyond
    // the relation itself. This test exists to document the absence:
    // the model does not need to expose any deletion-guard relations
    // for the policy. Kept as an explicit negative assertion.
    $supplier = new Supplier();

    // No relations beyond purchaseOrders() are needed by the policy.
    $relationMethods = collect((new ReflectionClass(Supplier::class))->getMethods())
        ->filter(fn ($m) => $m->class === Supplier::class
            && $m->getNumberOfParameters() === 0
            && ! $m->isStatic()
            && $m->getReturnType()?->getName() === HasMany::class)
        ->pluck('name')
        ->all();

    expect($relationMethods)->toBe(['purchaseOrders']);
});
