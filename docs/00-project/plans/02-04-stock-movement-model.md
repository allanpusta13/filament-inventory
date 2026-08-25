# Plan 02-04: Create StockMovement model with relationships

## Plan

Create StockMovement model with complete structure:

### File
- `app/Models/StockMovement.php`

### Structure
- #[Fillable(['product_id', 'warehouse_id', 'type', 'quantity', 'related_movement_id', 'reference', 'created_by'])] attribute
- Traditional $fillable array alongside attribute (per Product/Warehouse pattern)
- Type casts: type (string), quantity (integer)
- BelongsTo relationship to Product
- BelongsTo relationship to Warehouse
- BelongsTo relationship to self (related_movement_id)
- BelongsTo relationship to User (created_by)
- Model event structure for auto-populating created_by (implementation deferred to stage 3)
- Follow existing model pattern (declare(strict_types=1), final class, proper types)

### Key Implementation Details
- Use #[Fillable] attribute for Laravel 13 with all 7 fillable fields
- Include traditional $fillable array alongside attribute (per Product/Warehouse pattern)
- Add type casts: 'type' => 'string', 'quantity' => 'integer'
- Defer access control scopes/policies to stage 3/9 per blueprint staging
- Defer audit trail auto-population implementation to stage 3 (when role system exists)
- Note: type column uses string cast (can upgrade to PHP enum cast later)

## Expected Behavior
A StockMovement model will exist that can be used in factories, relationships, and throughout the application.

## Council Summary

**Reviewers:** Architecture

**Architecture Reviewer:** APPROVED (after revision) - Explicit fillable fields, type casts, and traditional array pattern now properly specified. Self-referential relationship handled correctly. Model event structure for audit trail appropriately deferred to stage 3. Suggested future upgrade to PHP enum cast for type field (not blocking).

**Security/Access Control Reviewer:** DENIED (initially) - Required role system and warehouse assignments first. Deferred to stages 3 and 9 per blueprint staging strategy.

**Notable Feedback:**
- Architecture reviewer confirmed the plan now follows established Product/Warehouse pattern
- Security reviewer emphasized infrastructure will be added in stages 3 and 9 per blueprint
- Both agreed minimal model now is appropriate given the blueprint's staged approach
