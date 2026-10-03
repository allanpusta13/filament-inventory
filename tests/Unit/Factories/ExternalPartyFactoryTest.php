<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * External-party + user factory tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §5.6 UserFactory (+ admin/auditor/branchManager states).
 *   - §5.7 SupplierFactory; §5.8 CustomerFactory.
 *   - A3 — suppliers and customers are minimal master data (no code
 *     column).
 */
uses(RefreshDatabase::class);

// ===========================================================================
// UserFactory (§5.6 + branchManager extension)
// ===========================================================================

describe('UserFactory', function () {
    it('produces the §5.6 default state', function () {
        $user = User::factory()->create();

        expect($user->role)->toBe(UserRole::WarehouseStaff);
        expect($user->is_active)->toBeTrue();
        expect($user->name)->toBeString()->not->toBe('');
        expect($user->email)->toContain('@');
    });

    it('hashes the password', function () {
        $user = User::factory()->create();

        // Raw column must not equal the plaintext; Hash::check must pass.
        $raw = $user->fresh()->getAttributes()['password'];
        expect($raw)->not->toBe('password');
        expect(Hash::check('password', $raw))->toBeTrue();
    });

    it('produces unique emails across a batch', function () {
        $users = User::factory()->count(20)->create();
        expect($users->pluck('email')->unique())->toHaveCount(20);
    });

    it('produces the admin() state', function () {
        expect(User::factory()->admin()->create()->role)->toBe(UserRole::Admin);
    });

    it('produces the auditor() state', function () {
        expect(User::factory()->auditor()->create()->role)->toBe(UserRole::Auditor);
    });

    it('produces the branchManager() state', function () {
        // ⚠ Owner-direction extension — no capability matrix yet.
        expect(User::factory()->branchManager()->create()->role)->toBe(UserRole::BranchManager);
    });

    it('does not attach any warehouse assignments', function () {
        // Assignments are an explicit opt-in via `->warehouses()->attach()`.
        expect(User::factory()->create()->warehouses()->count())->toBe(0);
    });
});

// ===========================================================================
// SupplierFactory (§5.7)
// ===========================================================================

describe('SupplierFactory', function () {
    it('produces a fully-populated supplier', function () {
        $supplier = Supplier::factory()->create();

        expect($supplier->name)->toBeString()->not->toBe('');
        expect($supplier->contact_person)->toBeString()->not->toBe('');
        expect($supplier->phone)->toBeString()->not->toBe('');
        expect($supplier->email)->toContain('@');
        expect($supplier->address)->toBeString()->not->toBe('');
        expect($supplier->is_active)->toBeTrue();
    });

    it('does not set a code column (A3)', function () {
        // A3 + §2.14: suppliers are identified by name, never by code.
        $supplier = Supplier::factory()->create();

        expect($supplier->getAttributes())->not->toHaveKey('code');
    });
});

// ===========================================================================
// CustomerFactory (§5.8)
// ===========================================================================

describe('CustomerFactory', function () {
    it('produces a fully-populated customer', function () {
        $customer = Customer::factory()->create();

        expect($customer->name)->toBeString()->not->toBe('');
        expect($customer->contact_person)->toBeString()->not->toBe('');
        expect($customer->phone)->toBeString()->not->toBe('');
        expect($customer->email)->toContain('@');
        expect($customer->address)->toBeString()->not->toBe('');
        expect($customer->is_active)->toBeTrue();
    });

    it('does not set a code column (A3)', function () {
        $customer = Customer::factory()->create();
        expect($customer->getAttributes())->not->toHaveKey('code');
    });

    it('mirrors the SupplierFactory shape', function () {
        // §3.12 / §3.13: the two master-data models are symmetric. A
        // drift in either factory surfaces here.
        $customer = Customer::factory()->create();
        $supplier = Supplier::factory()->create();

        expect(array_keys($customer->getAttributes()))
            ->toEqualCanonicalizing(array_keys($supplier->getAttributes()));
    });
});
