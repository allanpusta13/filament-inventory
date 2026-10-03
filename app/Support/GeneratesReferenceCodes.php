<?php

declare(strict_types=1);

namespace App\Support;

/**
 * GeneratesReferenceCodes — pure reference-code generator.
 *
 * Blueprint §2 (reference-code generation contract). A reference code
 * is a stable, human-readable identifier prefixed by document type:
 *
 *   - `TR-` — Transfer Requisition (§2.7)
 *   - `DT-` — Direct Transfer      (§2.21)
 *   - `PO-` — Purchase Order       (§2.16)
 *   - `SO-` — Sales Order          (§2.18)
 *
 * Format: `PREFIX-YmdHis-random(100-999)`, hyphenated.
 * Example: `TR-20260115120000-427`
 *
 * This class is a **pure function**:
 *   - It performs no database access.
 *   - It performs no uniqueness check.
 *   - It performs no retry on collision.
 *
 * The caller owns collision retry (regenerate, up to 5 attempts on
 * unique-constraint violation) at the correct transaction boundary —
 * never inside `DB::transaction()` (§2). Canonical call sites:
 *   - `CreateTransferRequisition::mutateFormDataBeforeCreate()` (§7B.2)
 *   - `CreateDirectTransfer::handleRecordCreation()` (§7C.2)
 *   - `CreatePurchaseOrder::mutateFormDataBeforeCreate()` (§7G.2)
 *   - `CreateSalesOrder::mutateFormDataBeforeCreate()` (§7H.2)
 *
 * Collision window: ≈ 1/900 per same-second, same-type pair (§2).
 *
 * Factories call this same helper with a deterministic random part
 * (`$this->faker->unique()->numberBetween(100, 999)`) so seeded data
 * fails loudly on collision instead of silently duplicating. See §5.9
 * (TransferRequisitionFactory), §5.17 (DirectTransferFactory), §5.13
 * (PurchaseOrderFactory), §5.15 (SalesOrderFactory).
 */
final class GeneratesReferenceCodes
{
    /**
     * Generate a reference code with the given prefix.
     *
     * @param  string  $prefix  Document-type prefix, uppercase, no trailing hyphen.
     * @param  int|null  $random  Deterministic random part for factory/seeder use;
     *                            `null` falls back to `random_int(100, 999)`.
     * @return string The generated reference code.
     */
    public static function generateReferenceCode(string $prefix, ?int $random = null): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.($random ?? random_int(100, 999));
    }
}
