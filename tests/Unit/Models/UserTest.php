<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * User model contract tests.
 *
 * NOTE 1: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * NOTE 2 (structural deviation): Blueprint §3.18 defines three role
 * predicates (isAdmin / isAuditor / isWarehouseStaff). This project
 * adds a fourth — isBranchManager — pending owner decisions on the
 * BranchManager capability matrix. The predicate itself is tested
 * here; policy and badge-scope behaviour are NOT tested because those
 * decisions are still open.
 *
 * NOTE 3 (extension): The `FilamentUser` + MFA contracts and
 * `canAccessPanel()` are extensions — the blueprint does not name
 * them. The `canAccessPanel()` rule is `is_active`-only (role is
 * intentionally not a panel gate).
 *
 * NOTE 4 (schema dependency): The two Filament MFA casts reference
 * `app_authentication_secret` and `app_authentication_recovery_codes`
 * columns that the §2.12 migration does NOT add. Tests that write or
 * read those columns are conditionally skipped until the outstanding
 * MFA migration is authored. See the class docblock.
 *
 * Blueprint anchors exercised:
 *   - §2.12 users schema (role, is_active).
 *   - §2.13 user_warehouse pivot schema.
 *   - §3.18 model shape: fillable, casts, warehouses() relation,
 *     role predicates.
 *   - §5.6 UserFactory default and admin() / auditor() states.
 *   - §8.12 UserPolicy (behavior belongs in the policy test suite).
 *   - §20.1 auditor system-wide read scope (asserted for
 *     hasAccessToWarehouse()).
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Traits, interfaces, class shape
// ---------------------------------------------------------------------------

it('uses HasFactory and Notifiable traits', function () {
    $traits = class_uses_recursive(User::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
    expect($traits)->toHaveKey(Illuminate\Notifications\Notifiable::class);
});

it('implements the FilamentUser contract', function () {
    expect(new User())->toBeInstanceOf(FilamentUser::class);
});

it('implements both Filament MFA contracts', function () {
    // Extension — Filament v5 two-factor authentication.
    $user = new User();

    expect($user)->toBeInstanceOf(HasAppAuthentication::class);
    expect($user)->toBeInstanceOf(HasAppAuthenticationRecovery::class);
});

it('is declared final', function () {
    // Extension — not a blueprint constraint, but the model is `final`
    // in the source. This test locks the intent so a future edit
    // removing `final` is a deliberate change.
    expect((new ReflectionClass(User::class))->isFinal())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Fillable
// ---------------------------------------------------------------------------

it('declares exactly the §3.18 fillable set', function () {
    $user = new User();

    expect($user->getFillable())->toBe(['name', 'email', 'password', 'role', 'is_active']);
});

// ---------------------------------------------------------------------------
// Hidden
// ---------------------------------------------------------------------------

it('hides password, remember_token, and both MFA attributes from serialization', function () {
    $user = new User();

    expect($user->getHidden())->toEqualCanonicalizing([
        'password',
        'remember_token',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ]);
});

it('never leaks the password when serialized to array', function () {
    $user = User::factory()->create(['password' => 'plaintext-password']);

    expect($user->toArray())->not->toHaveKey('password');
    expect($user->toArray())->not->toHaveKey('remember_token');
    expect($user->toArray())->not->toHaveKey('app_authentication_secret');
    expect($user->toArray())->not->toHaveKey('app_authentication_recovery_codes');
});

// ---------------------------------------------------------------------------
// Casts — read through getCasts() so the property + method merge is honoured
// ---------------------------------------------------------------------------

it('declares the §3.18 blueprint-mandated casts', function () {
    // §3.18 mandates role, is_active, password.
    $casts = (new User())->getCasts();

    expect($casts['role'])->toBe(UserRole::class);
    expect($casts['is_active'])->toBe('boolean');
    expect($casts['password'])->toBe('hashed');
});

it('declares the Filament MFA extension casts', function () {
    // Extension — Filament MFA columns. `encrypted` and
    // `encrypted:array` encrypt the column at rest.
    $casts = (new User())->getCasts();

    expect($casts['app_authentication_secret'])->toBe('encrypted');
    expect($casts['app_authentication_recovery_codes'])->toBe('encrypted:array');
});

it('declares the Laravel default email_verified_at cast', function () {
    // Extension — Laravel default, harmless when email verification is
    // unused.
    $casts = (new User())->getCasts();

    expect($casts['email_verified_at'])->toBe('datetime');
});

it('casts role to the UserRole enum on read', function () {
    $user = User::factory()->create(['role' => UserRole::WarehouseStaff->value]);

    expect($user->fresh()->role)->toBe(UserRole::WarehouseStaff);
    expect($user->fresh()->role)->toBeInstanceOf(UserRole::class);
});

it('casts is_active to boolean on read', function () {
    $user = User::factory()->create(['is_active' => 1]);

    expect($user->fresh()->is_active)->toBeTrue();

    $user->update(['is_active' => 0]);
    expect($user->fresh()->is_active)->toBeFalse();
});

it('hashes passwords via the hashed cast', function () {
    $user = User::factory()->create(['password' => 'plaintext-password']);

    $stored = $user->fresh()->getAttributes()['password'];

    expect($stored)->not->toBe('plaintext-password');
    expect(Hash::check('plaintext-password', $stored))->toBeTrue();
});

// ---------------------------------------------------------------------------
// §2.12 schema defaults
// ---------------------------------------------------------------------------

it('defaults role to warehouse_staff via the §2.12 schema', function () {
    DB::table('users')->insert([
        'name' => 'Schema Default Test',
        'email' => 'schema-default@example.test',
        'password' => Hash::make('x'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = User::where('email', 'schema-default@example.test')->first();

    expect($user->role)->toBe(UserRole::WarehouseStaff);
    expect($user->is_active)->toBeTrue();
});

// ---------------------------------------------------------------------------
// §5.6 factory defaults and states
// ---------------------------------------------------------------------------

it('produces a user with the §5.6 factory defaults', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::WarehouseStaff);
    expect($user->is_active)->toBeTrue();
});

it('supports the §5.6 admin() state', function () {
    expect(User::factory()->admin()->create()->role)->toBe(UserRole::Admin);
});

it('supports the §5.6 auditor() state', function () {
    expect(User::factory()->auditor()->create()->role)->toBe(UserRole::Auditor);
});

it('supports the branchManager() state added at owner direction', function () {
    // Extension — not in the blueprint's §5.6 factory contract.
    expect(User::factory()->branchManager()->create()->role)->toBe(UserRole::BranchManager);
});

// ---------------------------------------------------------------------------
// §2.13 / §3.18 warehouses() pivot
// ---------------------------------------------------------------------------

it('exposes warehouses() as a BelongsToMany on the user_warehouse pivot', function () {
    $user = new User();

    expect($user->warehouses())->toBeInstanceOf(BelongsToMany::class);
    expect($user->warehouses()->getRelated())->toBeInstanceOf(Warehouse::class);
    expect($user->warehouses()->getTable())->toBe('user_warehouse');
});

it('resolves assigned warehouses end-to-end', function () {
    $user = User::factory()->create();
    $warehouses = Warehouse::factory()->count(3)->create();

    $user->warehouses()->attach($warehouses->pluck('id'));

    expect($user->fresh()->warehouses)->toHaveCount(3);
});

it('supports detaching warehouses', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $user->warehouses()->attach($warehouse->id);
    $user->warehouses()->detach($warehouse->id);

    expect($user->fresh()->warehouses)->toHaveCount(0);
});

// ---------------------------------------------------------------------------
// §3.18 role predicates — one per UserRole case
// ---------------------------------------------------------------------------

it('isAdmin() is true only for the Admin role', function () {
    expect(User::factory()->admin()->create()->isAdmin())->toBeTrue();

    foreach ([UserRole::Auditor, UserRole::WarehouseStaff, UserRole::BranchManager] as $role) {
        expect(User::factory()->create(['role' => $role])->isAdmin())->toBeFalse();
    }
});

it('isAuditor() is true only for the Auditor role', function () {
    expect(User::factory()->auditor()->create()->isAuditor())->toBeTrue();

    foreach ([UserRole::Admin, UserRole::WarehouseStaff, UserRole::BranchManager] as $role) {
        expect(User::factory()->create(['role' => $role])->isAuditor())->toBeFalse();
    }
});

it('isWarehouseStaff() is true only for the WarehouseStaff role', function () {
    expect(User::factory()->create()->isWarehouseStaff())->toBeTrue();

    foreach ([UserRole::Admin, UserRole::Auditor, UserRole::BranchManager] as $role) {
        expect(User::factory()->create(['role' => $role])->isWarehouseStaff())->toBeFalse();
    }
});

it('isBranchManager() is true only for the BranchManager role', function () {
    expect(User::factory()->branchManager()->create()->isBranchManager())->toBeTrue();

    foreach ([UserRole::Admin, UserRole::Auditor, UserRole::WarehouseStaff] as $role) {
        expect(User::factory()->create(['role' => $role])->isBranchManager())->toBeFalse();
    }
});

it('defines exactly one predicate per UserRole case', function () {
    // A regression that adds a role without a predicate, or duplicates
    // coverage, surfaces here.
    foreach (UserRole::cases() as $role) {
        $user = User::factory()->create(['role' => $role]);

        $matched = collect([
            'isAdmin' => $user->isAdmin(),
            'isAuditor' => $user->isAuditor(),
            'isWarehouseStaff' => $user->isWarehouseStaff(),
            'isBranchManager' => $user->isBranchManager(),
        ])->filter()->keys()->all();

        expect($matched)->toHaveCount(1);

        $expectedMethod = match ($role) {
            UserRole::Admin => 'isAdmin',
            UserRole::Auditor => 'isAuditor',
            UserRole::WarehouseStaff => 'isWarehouseStaff',
            UserRole::BranchManager => 'isBranchManager',
        };

        expect($matched[0])->toBe($expectedMethod);
    }
});

it('does not treat BranchManager as any other role (inert predicate)', function () {
    // ⚠ Interim assertion — capability matrix undecided.
    $branchManager = User::factory()->branchManager()->create();

    expect($branchManager->isBranchManager())->toBeTrue();
    expect($branchManager->isAdmin())->toBeFalse();
    expect($branchManager->isAuditor())->toBeFalse();
    expect($branchManager->isWarehouseStaff())->toBeFalse();
});

// ---------------------------------------------------------------------------
// hasAccessToWarehouse() — §20.1 auditor system-wide read scope
// ---------------------------------------------------------------------------

it('grants an admin access to every warehouse', function () {
    $admin = User::factory()->admin()->create();
    $warehouse = Warehouse::factory()->create();

    expect($admin->hasAccessToWarehouse($warehouse->id))->toBeTrue();
});

it('grants an auditor access to every warehouse', function () {
    // §20.1: auditors hold full-system read scope regardless of pivot.
    $auditor = User::factory()->auditor()->create();
    $warehouse = Warehouse::factory()->create();

    expect($auditor->hasAccessToWarehouse($warehouse->id))->toBeTrue();
});

it('grants a warehouse_staff access only to assigned warehouses', function () {
    $staff = User::factory()->create(['role' => UserRole::WarehouseStaff]);
    [$assigned, $unassigned] = Warehouse::factory()->count(2)->create();
    $staff->warehouses()->attach($assigned->id);

    expect($staff->hasAccessToWarehouse($assigned->id))->toBeTrue();
    expect($staff->hasAccessToWarehouse($unassigned->id))->toBeFalse();
});

it('denies a warehouse_staff with no assignments access to any warehouse', function () {
    $staff = User::factory()->create(['role' => UserRole::WarehouseStaff]);
    $warehouse = Warehouse::factory()->create();

    expect($staff->hasAccessToWarehouse($warehouse->id))->toBeFalse();
});

it('grants a branch_manager access only to assigned warehouses (inert default)', function () {
    // ⚠ Interim behaviour — the current method does not special-case
    // BranchManager, so it falls through to the assigned-warehouse
    // branch. That is a conservative default, not an approved design.
    $bm = User::factory()->branchManager()->create();
    [$assigned, $unassigned] = Warehouse::factory()->count(2)->create();
    $bm->warehouses()->attach($assigned->id);

    expect($bm->hasAccessToWarehouse($assigned->id))->toBeTrue();
    expect($bm->hasAccessToWarehouse($unassigned->id))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Filament panel access — canAccessPanel()
// ---------------------------------------------------------------------------

it('allows an active user to access the panel', function () {
    $user = User::factory()->create(['is_active' => true]);

    expect($user->canAccessPanel(filamentPanel()))->toBeTrue();
});

it('denies an inactive user access to the panel', function () {
    $user = User::factory()->create(['is_active' => false]);

    expect($user->canAccessPanel(filamentPanel()))->toBeFalse();
});

it('does not gate panel access by role — every active role is allowed', function (UserRole $role) {
    // Panel access is is_active-only. Role gates live in the §8
    // policies, not here (A8).
    $user = User::factory()->create(['role' => $role, 'is_active' => true]);

    expect($user->canAccessPanel(filamentPanel()))->toBeTrue();
})->with(UserRole::cases());

it('denies an inactive user regardless of role', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role, 'is_active' => false]);

    expect($user->canAccessPanel(filamentPanel()))->toBeFalse();
})->with(UserRole::cases());

it('treats the panel argument as advisory — result invariant across panel instances', function () {
    // Only one panel exists (admin, §17.5). The method does not branch
    // on $panel, so a future second panel is a schema/policy decision,
    // not a silent widening.
    $user = User::factory()->create(['is_active' => true]);

    expect($user->canAccessPanel(filamentPanel('admin')))->toBeTrue();
    expect($user->canAccessPanel(filamentPanel('some-other-panel')))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Filament MFA methods — require the outstanding MFA migration
// ---------------------------------------------------------------------------

it('returns the app authentication holder name as the user email', function () {
    $user = User::factory()->create(['email' => 'mfa@example.test']);

    expect($user->getAppAuthenticationHolderName())->toBe('mfa@example.test');
});

it('persists the app authentication secret', function () {
    if (! Schema::hasColumn('users', 'app_authentication_secret')) {
        $this->markTestSkipped(
            'Requires app_authentication_secret column — see outstanding MFA migration.'
        );
    }

    $user = User::factory()->create();

    $user->saveAppAuthenticationSecret('ABCDEF123456');

    expect($user->fresh()->getAppAuthenticationSecret())->toBe('ABCDEF123456');
});

it('round-trips the app authentication secret as encrypted ciphertext at rest', function () {
    if (! Schema::hasColumn('users', 'app_authentication_secret')) {
        $this->markTestSkipped(
            'Requires app_authentication_secret column — see outstanding MFA migration.'
        );
    }

    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret('SECRET-VALUE');

    // Raw column must NOT equal the plaintext — the `encrypted` cast
    // is the guard.
    $raw = DB::table('users')->where('id', $user->id)->value('app_authentication_secret');

    expect($raw)->not->toBe('SECRET-VALUE');
    expect($user->fresh()->getAppAuthenticationSecret())->toBe('SECRET-VALUE');
});

it('allows the app authentication secret to be cleared', function () {
    if (! Schema::hasColumn('users', 'app_authentication_secret')) {
        $this->markTestSkipped(
            'Requires app_authentication_secret column — see outstanding MFA migration.'
        );
    }

    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret('SECRET-VALUE');
    $user->saveAppAuthenticationSecret(null);

    expect($user->fresh()->getAppAuthenticationSecret())->toBeNull();
});

it('persists app authentication recovery codes', function () {
    if (! Schema::hasColumn('users', 'app_authentication_recovery_codes')) {
        $this->markTestSkipped(
            'Requires app_authentication_recovery_codes column — see outstanding MFA migration.'
        );
    }

    $user = User::factory()->create();
    $codes = ['code-1', 'code-2', 'code-3'];

    $user->saveAppAuthenticationRecoveryCodes($codes);

    expect($user->fresh()->getAppAuthenticationRecoveryCodes())->toBe($codes);
});

it('round-trips recovery codes as encrypted array at rest', function () {
    if (! Schema::hasColumn('users', 'app_authentication_recovery_codes')) {
        $this->markTestSkipped(
            'Requires app_authentication_recovery_codes column — see outstanding MFA migration.'
        );
    }

    $user = User::factory()->create();
    $codes = ['code-1', 'code-2'];
    $user->saveAppAuthenticationRecoveryCodes($codes);

    $raw = DB::table('users')->where('id', $user->id)->value('app_authentication_recovery_codes');

    expect($raw)->not->toContain('code-1');
    expect($user->fresh()->getAppAuthenticationRecoveryCodes())->toBe($codes);
});

// ---------------------------------------------------------------------------
// §2.13 pivot cascade
// ---------------------------------------------------------------------------

it('cascades pivot rows on user deletion (cascadeOnDelete, §2.13)', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $user->warehouses()->attach($warehouse->id);

    $user->forceDelete();

    expect(DB::table('user_warehouse')->where('user_id', $user->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Helper — build a Filament Panel for canAccessPanel() assertions
// ---------------------------------------------------------------------------

function filamentPanel(string $id = 'admin'): Panel
{
    return Panel::make()->id($id);
}
