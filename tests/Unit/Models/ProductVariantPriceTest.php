<?php

declare(strict_types=1);

use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * ProductVariantPrice model contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §2.3 product_variant_prices schema: columns, defaults,
 *     decimal(15,4) precision, `effective_from` default, `is_current`
 *     default, `set_by` nullOnDelete.
 *   - §2.3 partial unique index on `(product_variant_id) WHERE
 *     is_current = true` on PG/SQLite.
 *   - §3.3 model shape: fillable, casts, productVariant() and
 *     setBy() relations.
 *   - §6.4 PurchaseService::updateCurrentCostPrice() canonical
 *     replacement path.
 *   - §7A.4.1 SetCurrentPriceAction canonical replacement path.
 *   - §5.3 ProductVariantPriceFactory defaults.
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Traits, fillable, casts
// ---------------------------------------------------------------------------

it('uses HasFactory', function () {
    $traits = class_uses_recursive(ProductVariantPrice::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
});

it('declares exactly the §3.3 fillable set', function () {
    $price = new ProductVariantPrice();

    expect($price->getFillable())->toBe([
        'product_variant_id', 'cost_price', 'sale_price',
        'effective_from', 'is_current', 'set_by', 'notes',
    ]);
});

it('casts cost_price and sale_price to decimal:4', function () {
    $variant = ProductVariant::factory()->create();
    $price = ProductVariantPrice::factory()->create([
        'product_variant_id' => $variant->id,
        'cost_price' => '12.3456',
        'sale_price' => '17.2838',
    ]);

    $fresh = $price->fresh();

    // decimal:4 cast returns a string with exactly four fractional
    // digits — the canonical (15,4) shape used by LossLedger and by
    // every bccomp() comparison in the services.
    expect($fresh->cost_price)->toBe('12.3456');
    expect($fresh->sale_price)->toBe('17.2838');
    expect($fresh->cost_price)->toBeString();
    expect($fresh->sale_price)->toBeString();
});

it('casts effective_from to datetime', function () {
    $variant = ProductVariant::factory()->create();
    $price = ProductVariantPrice::factory()->create([
        'product_variant_id' => $variant->id,
        'effective_from' => '2026-01-15 10:30:00',
    ]);

    expect($price->fresh()->effective_from)->toBeInstanceOf(Illuminate\Support\Carbon::class);
});

it('casts is_current to boolean', function () {
    $variant = ProductVariant::factory()->create();

    $current = ProductVariantPrice::factory()->create([
        'product_variant_id' => $variant->id,
        'is_current' => 1,
    ]);
    $historical = ProductVariantPrice::factory()->create([
        'product_variant_id' => $variant->id,
        'is_current' => 0,
    ]);

    expect($current->fresh()->is_current)->toBeTrue();
    expect($historical->fresh()->is_current)->toBeFalse();
});

it('defaults cost_price and sale_price to 0.0000 via the §2.3 schema', function () {
    // §2.3: cost_price / sale_price both default to 0.0000. Insert a
    // row explicitly without these columns to confirm the DB default.
    $variant = ProductVariant::factory()->create();

    DB::table('product_variant_prices')->insert([
        'product_variant_id' => $variant->id,
        'effective_from' => now(),
        'is_current' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $price = ProductVariantPrice::where('product_variant_id', $variant->id)->first();

    expect($price->cost_price)->toBe('0.0000');
    expect($price->sale_price)->toBe('0.0000');
});

it('defaults is_current to true via the §2.3 schema', function () {
    // §2.3: is_current defaults to true.
    $variant = ProductVariant::factory()->create();

    DB::table('product_variant_prices')->insert([
        'product_variant_id' => $variant->id,
        'effective_from' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $price = ProductVariantPrice::where('product_variant_id', $variant->id)->first();

    expect($price->is_current)->toBeTrue();
});

it('defaults effective_from to the current time via the §2.3 schema', function () {
    // §2.3: effective_from default = current time (useCurrent()).
    $variant = ProductVariant::factory()->create();

    $before = now()->subSecond();

    DB::table('product_variant_prices')->insert([
        'product_variant_id' => $variant->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $price = ProductVariantPrice::where('product_variant_id', $variant->id)->first();

    expect($price->effective_from)->not->toBeNull();
    expect($price->effective_from->greaterThanOrEqualTo($before))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Factory defaults (§5.3)
// ---------------------------------------------------------------------------

it('creates a price row with §5.3 factory defaults', function () {
    // §5.3: cost = randomFloat(4, 1, 500); sale = cost × 1.4; is_current = true.
    $price = ProductVariantPrice::factory()->create();

    expect($price->product_variant_id)->not->toBeNull();
    expect((float) $price->cost_price)->toBeGreaterThanOrEqual(1.0);
    expect((float) $price->cost_price)->toBeLessThanOrEqual(500.0);
    expect((float) $price->sale_price)->toBeGreaterThan((float) $price->cost_price);
    expect($price->is_current)->toBeTrue();
    expect($price->effective_from)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Relations
// ---------------------------------------------------------------------------

it('exposes productVariant() as a BelongsTo relation', function () {
    $price = new ProductVariantPrice();

    expect($price->productVariant())->toBeInstanceOf(BelongsTo::class);
    expect($price->productVariant()->getRelated())->toBeInstanceOf(ProductVariant::class);
});

it('exposes setBy() as a BelongsTo relation on the set_by FK', function () {
    $price = new ProductVariantPrice();

    expect($price->setBy())->toBeInstanceOf(BelongsTo::class);
    expect($price->setBy()->getRelated())->toBeInstanceOf(User::class);
    expect($price->setBy()->getForeignKeyName())->toBe('set_by');
});

it('resolves the owning variant', function () {
    $variant = ProductVariant::factory()->create();
    $price = ProductVariantPrice::factory()->create(['product_variant_id' => $variant->id]);

    expect($price->productVariant->id)->toBe($variant->id);
});

it('resolves the acting user via setBy()', function () {
    $user = User::factory()->create();
    $price = ProductVariantPrice::factory()->create(['set_by' => $user->id]);

    expect($price->setBy->id)->toBe($user->id);
});

it('allows set_by to be null', function () {
    // §2.3: set_by is nullable (nullOnDelete).
    $price = ProductVariantPrice::factory()->create(['set_by' => null]);

    expect($price->set_by)->toBeNull();
    expect($price->setBy)->toBeNull();
});

it('nulls set_by when the user is deleted (nullOnDelete, §2.3)', function () {
    $user = User::factory()->create();
    $price = ProductVariantPrice::factory()->create(['set_by' => $user->id]);

    $user->delete();

    expect($price->fresh()->set_by)->toBeNull();
});

// ---------------------------------------------------------------------------
// Cascade delete with the owning variant (§2.3)
// ---------------------------------------------------------------------------

it('cascades on variant force delete (cascadeOnDelete, §2.3)', function () {
    $variant = ProductVariant::factory()->create();
    $price = ProductVariantPrice::factory()->create(['product_variant_id' => $variant->id]);

    $variant->forceDelete();

    expect(ProductVariantPrice::find($price->id))->toBeNull();
});

// ---------------------------------------------------------------------------
// §2.3 invariant: at most one is_current = true row per variant
// ---------------------------------------------------------------------------

it('enforces the partial unique current-price invariant on pg/sqlite drivers', function () {
    // §2.3: partial unique index on (product_variant_id) WHERE
    // is_current = true on pgsql/sqlite. MySQL has no partial-index
    // support and relies on the service layer alone — this test
    // therefore skips on drivers without the index.
    $driver = DB::getDriverName();

    if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
        $this->markTestSkipped("Driver {$driver} has no partial-unique-index support.");
    }

    $variant = ProductVariant::factory()->create();

    ProductVariantPrice::factory()->create([
        'product_variant_id' => $variant->id,
        'is_current' => true,
    ]);

    expect(fn () => ProductVariantPrice::factory()->create([
        'product_variant_id' => $variant->id,
        'is_current' => true,
    ]))->toThrow(QueryException::class);
});

it('allows multiple non-current rows per variant', function () {
    $variant = ProductVariant::factory()->create();

    ProductVariantPrice::factory()->count(3)->create([
        'product_variant_id' => $variant->id,
        'is_current' => false,
    ]);

    expect(ProductVariantPrice::where('product_variant_id', $variant->id)->count())->toBe(3);
});

it('allows one current row per variant across multiple variants', function () {
    $v1 = ProductVariant::factory()->create();
    $v2 = ProductVariant::factory()->create();

    ProductVariantPrice::factory()->create([
        'product_variant_id' => $v1->id,
        'is_current' => true,
    ]);
    ProductVariantPrice::factory()->create([
        'product_variant_id' => $v2->id,
        'is_current' => true,
    ]);

    expect(ProductVariantPrice::where('is_current', true)->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// §6.4 canonical replacement path — updateCurrentCostPrice()
// ---------------------------------------------------------------------------

it('supports the canonical price-writer transaction from §6.4', function () {
    // §6.4 PurchaseService::updateCurrentCostPrice() flow:
    //   1. lockForUpdate the variant
    //   2. clear the previous current row (is_current = false)
    //   3. insert the new row carrying the preserved sale_price
    // This test exercises the same shape at the model layer to assert
    // the §2.3 invariant is preserved by the canonical replacement.
    $variant = ProductVariant::factory()->create();

    $original = ProductVariantPrice::factory()->create([
        'product_variant_id' => $variant->id,
        'cost_price' => '10.0000',
        'sale_price' => '14.0000',
        'is_current' => true,
    ]);

    DB::transaction(function () use ($variant, $original) {
        $current = $variant->currentPrice;
        expect($current->id)->toBe($original->id);

        $current->update(['is_current' => false]);

        ProductVariantPrice::create([
            'product_variant_id' => $variant->id,
            'cost_price' => '12.0000',
            'sale_price' => $current->sale_price,
            'effective_from' => now(),
            'is_current' => true,
            'set_by' => null,
        ]);
    });

    $fresh = $variant->fresh();

    // Exactly one current row.
    expect(ProductVariantPrice::where('product_variant_id', $variant->id)
        ->where('is_current', true)
        ->count())->toBe(1);

    // Current row carries the new cost and the preserved sale price.
    expect($fresh->currentPrice->cost_price)->toBe('12.0000');
    expect($fresh->currentPrice->sale_price)->toBe('14.0000');

    // The original is now historical.
    expect($original->fresh()->is_current)->toBeFalse();

    // Two rows exist — history is retained.
    expect(ProductVariantPrice::where('product_variant_id', $variant->id)->count())->toBe(2);
});

it('supports the §7A.4.1 no-op guard on identical prices', function () {
    // §7A.4.1: SetCurrentPriceAction returns without writing a new row
    // when both cost_price and sale_price match the current row exactly
    // (bccomp at 4dp). Exercise the comparison shape against the model.
    $variant = ProductVariant::factory()->create();
    $current = ProductVariantPrice::factory()->create([
        'product_variant_id' => $variant->id,
        'cost_price' => '10.0000',
        'sale_price' => '14.0000',
        'is_current' => true,
    ]);

    $isNoOp = bccomp((string) $current->cost_price, '10.0000', 4) === 0
        && bccomp((string) $current->sale_price, '14.0000', 4) === 0;

    expect($isNoOp)->toBeTrue();

    $isNotNoOp = bccomp((string) $current->cost_price, '11.0000', 4) === 0;

    expect($isNotNoOp)->toBeFalse();
});

// ---------------------------------------------------------------------------
// Soft history — no SoftDeletes on this model
// ---------------------------------------------------------------------------

it('does not use SoftDeletes — price history is retained as rows, not soft-deletes', function () {
    // §2.3 has no `deleted_at` column on product_variant_prices.
    // Versioning is via `is_current` + `effective_from`, not deletion.
    $traits = class_uses_recursive(ProductVariantPrice::class);

    expect($traits)->not->toHaveKey(Illuminate\Database\Eloquent\SoftDeletes::class);
    expect((new ProductVariantPrice())->getDates())->not->toContain('deleted_at');
});
