<?php

declare(strict_types=1);

use App\Models\ProductVariant;
use App\Models\ProductVariantUnitConversion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * ProductVariantUnitConversion model contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §2.4 product_variant_unit_conversions schema: columns, casts,
 *     unique constraint on (product_variant_id, unit_name), no
 *     deleted_at.
 *   - §3.4 model shape: fillable, casts, productVariant() relation,
 *     isBaseUnitRow() contract.
 *   - §3.19 ProductVariantObserver base-unit self-conversion row on
 *     variant create (F19).
 *   - §5.4 factory defaults and ->baseUnit() state.
 *   - §7A.4.4 ManageUnitConversionsAction base-unit deletion guard.
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Traits, fillable, casts
// ---------------------------------------------------------------------------

it('uses HasFactory', function () {
    $traits = class_uses_recursive(ProductVariantUnitConversion::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
});

it('does not use SoftDeletes — the §2.4 schema declares no deleted_at', function () {
    // §2.4: no `deleted_at` column. Unit conversions are managed in
    // place, not soft-deleted.
    $traits = class_uses_recursive(ProductVariantUnitConversion::class);

    expect($traits)->not->toHaveKey(Illuminate\Database\Eloquent\SoftDeletes::class);
    expect((new ProductVariantUnitConversion())->getDates())->not->toContain('deleted_at');
});

it('declares exactly the §3.4 fillable set', function () {
    $conversion = new ProductVariantUnitConversion();

    expect($conversion->getFillable())->toBe([
        'product_variant_id', 'unit_name', 'base_unit_ratio',
        'is_default_purchase', 'is_default_transfer',
    ]);
});

it('casts base_unit_ratio to integer and both default flags to boolean', function () {
    $variant = ProductVariant::factory()->create();

    $conversion = ProductVariantUnitConversion::factory()->create([
        'product_variant_id' => $variant->id,
        'unit_name' => 'case',
        'base_unit_ratio' => 24,
        'is_default_purchase' => 1,
        'is_default_transfer' => 0,
    ]);

    $fresh = $conversion->fresh();

    expect($fresh->base_unit_ratio)->toBe(24);
    expect($fresh->base_unit_ratio)->toBeInt();
    expect($fresh->is_default_purchase)->toBeTrue();
    expect($fresh->is_default_transfer)->toBeFalse();
});

// ---------------------------------------------------------------------------
// Relation
// ---------------------------------------------------------------------------

it('exposes productVariant() as a BelongsTo relation', function () {
    $conversion = new ProductVariantUnitConversion();

    expect($conversion->productVariant())->toBeInstanceOf(BelongsTo::class);
    expect($conversion->productVariant()->getRelated())->toBeInstanceOf(ProductVariant::class);
});

it('resolves the owning variant', function () {
    $variant = ProductVariant::factory()->create();
    $conversion = ProductVariantUnitConversion::factory()->create([
        'product_variant_id' => $variant->id,
    ]);

    expect($conversion->productVariant->id)->toBe($variant->id);
});

// ---------------------------------------------------------------------------
// §2.4 unique (product_variant_id, unit_name)
// ---------------------------------------------------------------------------

it('rejects a duplicate unit_name for the same variant', function () {
    $variant = ProductVariant::factory()->create();

    // ProductVariantObserver created the base-unit row on variant create.
    // Attempt to add another row with the same unit_name.
    expect(fn () => ProductVariantUnitConversion::factory()->create([
        'product_variant_id' => $variant->id,
        'unit_name' => $variant->base_unit_name,
    ]))->toThrow(QueryException::class);
});

it('allows the same unit_name across different variants', function () {
    $v1 = ProductVariant::factory()->create();
    $v2 = ProductVariant::factory()->create();

    ProductVariantUnitConversion::factory()->create([
        'product_variant_id' => $v1->id,
        'unit_name' => 'case',
    ]);
    ProductVariantUnitConversion::factory()->create([
        'product_variant_id' => $v2->id,
        'unit_name' => 'case',
    ]);

    expect(ProductVariantUnitConversion::where('unit_name', 'case')->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// §3.19 / F19 — base-unit self-conversion row is auto-created on variant create
// ---------------------------------------------------------------------------

it('auto-creates the base-unit self-conversion row via the observer', function () {
    // F19 / §3.19: ProductVariantObserver::created() materializes the
    // base-unit row with base_unit_ratio = 1.
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    $base = ProductVariantUnitConversion::where('product_variant_id', $variant->id)
        ->where('unit_name', 'pc')
        ->first();

    expect($base)->not->toBeNull();
    expect($base->base_unit_ratio)->toBe(1);
    expect($base->is_default_purchase)->toBeFalse();
    expect($base->is_default_transfer)->toBeFalse();
});

it('does not duplicate the base-unit row if it already exists', function () {
    // §3.19: the observer uses firstOrCreate, so an existing base row
    // is preserved. This case is exercised by the factory ->baseUnit()
    // state, which may seed the row before the observer runs.
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    // Manually re-run the observer's logic (as the observer would on a
    // second created() dispatch — Eloquent does not normally fire it twice,
    // but firstOrCreate is the contract).
    ProductVariantUnitConversion::firstOrCreate(
        [
            'product_variant_id' => $variant->id,
            'unit_name' => 'pc',
        ],
        [
            'base_unit_ratio' => 1,
            'is_default_purchase' => false,
            'is_default_transfer' => false,
        ],
    );

    expect(ProductVariantUnitConversion::where('product_variant_id', $variant->id)
        ->where('unit_name', 'pc')
        ->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// §3.4 isBaseUnitRow()
// ---------------------------------------------------------------------------

it('identifies the base-unit self-conversion row as isBaseUnitRow = true', function () {
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    $base = ProductVariantUnitConversion::where('product_variant_id', $variant->id)
        ->where('unit_name', 'pc')
        ->first();

    expect($base->isBaseUnitRow())->toBeTrue();
});

it('identifies non-base rows as isBaseUnitRow = false', function () {
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    $case = ProductVariantUnitConversion::factory()->create([
        'product_variant_id' => $variant->id,
        'unit_name' => 'case',
        'base_unit_ratio' => 24,
    ]);

    expect($case->isBaseUnitRow())->toBeFalse();
});

it('returns false when unit_name matches but ratio is not 1', function () {
    // Both conditions — same unit_name AND ratio = 1 — are required.
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    // Bypass the observer by deleting the auto-created base row first.
    ProductVariantUnitConversion::where('product_variant_id', $variant->id)->delete();

    $mismatched = ProductVariantUnitConversion::factory()->create([
        'product_variant_id' => $variant->id,
        'unit_name' => 'pc',
        'base_unit_ratio' => 2,
    ]);

    expect($mismatched->isBaseUnitRow())->toBeFalse();
});

it('returns false when ratio matches but unit_name differs', function () {
    // Both conditions — same unit_name AND ratio = 1 — are required.
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    // Bypass the observer by deleting the auto-created base row first.
    ProductVariantUnitConversion::where('product_variant_id', $variant->id)->delete();

    $mismatched = ProductVariantUnitConversion::factory()->create([
        'product_variant_id' => $variant->id,
        'unit_name' => 'case',
        'base_unit_ratio' => 1,
    ]);

    expect($mismatched->isBaseUnitRow())->toBeFalse();
});

it('resolves the parent relation lazily when not eager-loaded', function () {
    // §3.4 contract: when the relation is not loaded, the method
    // resolves it with one explicit query rather than an implicit lazy
    // load — and never fatals on a null property read.
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);
    $base = ProductVariantUnitConversion::where('product_variant_id', $variant->id)
        ->where('unit_name', 'pc')
        ->first();

    // Force the relation to be unloaded.
    $fresh = ProductVariantUnitConversion::find($base->id);

    expect($fresh->relationLoaded('productVariant'))->toBeFalse();
    expect($fresh->isBaseUnitRow())->toBeTrue();
});

it('returns false when the parent variant row is missing', function () {
    // §3.4: "a missing parent resolves to `false` instead of fataling
    // on a null property read."
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);
    $base = ProductVariantUnitConversion::where('product_variant_id', $variant->id)
        ->where('unit_name', 'pc')
        ->first();

    // Simulate a dangling FK. In the real schema this cannot happen
    // (cascadeOnDelete), so we simulate by detaching the parent id
    // without violating the FK — we assert against a fresh model
    // whose product_variant_id resolves to nothing.
    $orphan = new ProductVariantUnitConversion([
        'product_variant_id' => 999999,   // no such variant
        'unit_name' => 'pc',
        'base_unit_ratio' => 1,
    ]);

    expect($orphan->isBaseUnitRow())->toBeFalse();
});

// ---------------------------------------------------------------------------
// §5.4 factory
// ---------------------------------------------------------------------------

it('produces a non-base conversion row via the §5.4 default state', function () {
    // §5.4: default unit_name is one of box/case/pallet, ratio from
    // {6, 12, 24, 48}, both defaults false.
    $conversion = ProductVariantUnitConversion::factory()->create();

    expect($conversion->unit_name)->toBeIn(['box', 'case', 'pallet']);
    expect($conversion->base_unit_ratio)->toBeIn([6, 12, 24, 48]);
    expect($conversion->is_default_purchase)->toBeFalse();
    expect($conversion->is_default_transfer)->toBeFalse();
});

it('produces a base-unit self-conversion row via the §5.4 ->baseUnit() state', function () {
    // §5.4: baseUnit() state produces unit_name = 'pc', ratio = 1.
    // Variant created quietly so ProductVariantObserver (§3.19) does not
    // materialize its own pc row first and collide on the
    // (product_variant_id, unit_name) unique index (§2.4).
    $variant = ProductVariant::factory()->createQuietly();
    $conversion = ProductVariantUnitConversion::factory()->baseUnit()->create([
        'product_variant_id' => $variant->id,
    ]);

    expect($conversion->unit_name)->toBe('pc');
    expect($conversion->base_unit_ratio)->toBe(1);
});

// ---------------------------------------------------------------------------
// §7A.4.4 ManageUnitConversionsAction deletion guard — model-layer shape
// ---------------------------------------------------------------------------

it('gives ManageUnitConversionsAction a reliable way to skip the base-unit row', function () {
    // §7A.4.4 uses `isBaseUnitRow()` to reject deletion of the base-unit
    // self-conversion row when the operator edits conversions. Assert
    // the split over a realistic fixture: 1 base + 2 non-base rows.
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);
    ProductVariantUnitConversion::factory()->create([
        'product_variant_id' => $variant->id,
        'unit_name' => 'box',
    ]);
    ProductVariantUnitConversion::factory()->create([
        'product_variant_id' => $variant->id,
        'unit_name' => 'case',
    ]);

    $rows = ProductVariantUnitConversion::where('product_variant_id', $variant->id)
        ->with('productVariant')
        ->get();

    $base = $rows->filter(fn ($r) => $r->isBaseUnitRow());
    $others = $rows->reject(fn ($r) => $r->isBaseUnitRow());

    expect($rows)->toHaveCount(3);
    expect($base)->toHaveCount(1);
    expect($others)->toHaveCount(2);
    expect($base->first()->unit_name)->toBe('pc');
});

// ---------------------------------------------------------------------------
// §2.4 FK cascade
// ---------------------------------------------------------------------------

it('cascades on variant force delete (cascadeOnDelete, §2.4)', function () {
    $variant = ProductVariant::factory()->create();

    // Observer created the base row. Add another.
    ProductVariantUnitConversion::factory()->create([
        'product_variant_id' => $variant->id,
        'unit_name' => 'case',
    ]);

    $before = ProductVariantUnitConversion::where('product_variant_id', $variant->id)->count();
    expect($before)->toBe(2);

    $variant->forceDelete();

    expect(ProductVariantUnitConversion::where('product_variant_id', $variant->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// direct DB default sanity
// ---------------------------------------------------------------------------

it('defaults both default flags to false via the §2.4 schema', function () {
    // §2.4: is_default_purchase / is_default_transfer both default to false.
    $variant = ProductVariant::factory()->create();

    DB::table('product_variant_unit_conversions')->insert([
        'product_variant_id' => $variant->id,
        'unit_name' => 'pallet',
        'base_unit_ratio' => 48,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = ProductVariantUnitConversion::where('product_variant_id', $variant->id)
        ->where('unit_name', 'pallet')
        ->first();

    expect($row->is_default_purchase)->toBeFalse();
    expect($row->is_default_transfer)->toBeFalse();
});
