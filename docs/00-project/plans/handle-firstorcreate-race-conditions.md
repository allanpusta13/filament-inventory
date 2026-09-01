# Handle firstOrCreate race conditions with try-catch and fallback retrieval

## Problem
In high-concurrency environments, multiple workers may attempt to execute `firstOrCreate` on the same `(variant_id, warehouse_id)` simultaneously. This can lead to duplicate key insert collisions when the unique constraint on the `warehouse_stock` table is violated, resulting in unhandled `QueryException` errors.

## Solution
Wrap `firstOrCreate` calls in a try-catch block that catches `Illuminate\Database\QueryException`. On exception, fall back to retrieving the row directly using `where(...)->firstOrFail()` while maintaining the pessimistic lock.

## Changes Required
1. **InventoryService::lockStockForProduct** (lines 26-34)
2. **InventoryService::recordMovement** (lines 86-89)

## Implementation Details
- Use `try { ... } catch (QueryException $e) { ... }` around each `firstOrCreate`
- In the catch block, execute: `WarehouseStock::where('variant_id', $variantId)->where('warehouse_id', $warehouseId)->lockForUpdate()->firstOrFail()`
- Maintain existing lockForUpdate() usage to preserve pessimistic locking semantics
- Preserve all existing functionality and error handling

## Testing
- Run existing test suite to ensure no regressions
- Create concurrency test to verify race condition handling (if test infrastructure supports it)
- Verify that the fallback retrieval returns the correct row with lock held

## Related Files
- app/Services/InventoryService.php
- docs/00-project/blueprint.md (Guardrail 3)