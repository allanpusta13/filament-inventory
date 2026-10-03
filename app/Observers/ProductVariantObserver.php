<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ProductVariant;
use App\Models\ProductVariantUnitConversion;

/**
 * ProductVariantObserver — base-unit self-conversion row materializer.
 *
 * Blueprint §3.19. Registered in `AppServiceProvider::boot()` (§17.3)
 * via `ProductVariant::observe(ProductVariantObserver::class)`.
 *
 * Contract (F19 — "Base-Unit 'Self-Conversion' Row Required"): every
 * variant must carry exactly one row in `product_variant_unit_conversions`
 * where `unit_name = product_variant.base_unit_name` and
 * `base_unit_ratio = 1`. This observer materializes that row on
 * variant creation.
 *
 * `firstOrCreate` semantics: if the row already exists (e.g. a factory
 * pre-seeded it via `ProductVariantUnitConversionFactory::baseUnit()`,
 * §5.4), the call is a no-op. The observer therefore never duplicates
 * the row and never overwrites an existing one.
 *
 * The observer MUST be registered before any seeder or factory creates
 * variants (§17.3): every unit-selection `Select` in the four wizard
 * forms (§7B.1, §7C.1, §7G.1, §7H.1) sources its options from these
 * rows. A variant with no base-unit row has no selectable units at all.
 *
 * The observer does NOT enforce deletion guard on the base-unit row —
 * that guard lives in `ManageUnitConversionsAction` (§7A.4.4) at the UI
 * layer, backed by the `isBaseUnitRow()` check (§3.4).
 */
class ProductVariantObserver
{
    public function created(ProductVariant $variant): void
    {
        ProductVariantUnitConversion::firstOrCreate(
            [
                'product_variant_id' => $variant->id,
                'unit_name' => $variant->base_unit_name,
            ],
            [
                'base_unit_ratio' => 1,
                'is_default_purchase' => false,
                'is_default_transfer' => false,
            ],
        );
    }
}
