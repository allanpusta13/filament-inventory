# Plan 04-01: InventoryService

## Plan

Create `app/Services/InventoryService.php` with 5 methods for stock management.

### Supporting Files
- `app/Enums/MovementType.php` — backed enum for stock movement types
- `app/Exceptions/InsufficientStockException.php` — thrown on insufficient stock
- `database/factories/ProductFactory.php` — for testing
- `database/factories/StockMovementFactory.php` — for testing
- `app/Models/StockMovement.php` — uncomment boot event for created_by auto-population

### Methods
1. `recordMovement(int $productId, int $warehouseId, MovementType $type, int $quantity, ?string $reference, ?int $relatedMovementId): StockMovement`
2. `ship(int $productId, int $warehouseId, int $quantity, ?string $reference): StockMovement` — DB::transaction, checks quantity inside transaction
3. `transfer(int $productId, int $fromWarehouseId, int $toWarehouseId, int $quantity, ?string $reference): array{StockMovement, StockMovement}` — DB::transaction wrapping both movements
4. `currentQuantity(int $productId, int $warehouseId): int` — SUM(quantity)
5. `totalQuantity(int $productId): int` — SUM(quantity) across all warehouses

### Security Fixes (from council)
- ship() uses DB::transaction with quantity check inside transaction (atomic)
- transfer() wraps both movements in DB::transaction (atomic)
- MovementType enum validates type at boundary
- Quantity validation: must be positive in ship/transfer

## Council Summary

**Reviewers:** Architecture, Security, Testing

**Architecture:** APPROVED — Recommended MovementType enum, quantity validation, created_by resolution via model event.

**Security:** DENIED (initially) — Race condition on ship(), atomicity of transfer(), type validation. All addressed in revised plan.

**Testing:** APPROVED — Need ProductFactory, StockMovementFactory, edge case tests.
