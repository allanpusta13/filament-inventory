# Plan 02-03: Create Product model with relationships

## Plan

Create Product model with minimal structure:

### File
- `app/Models/Product.php`

### Structure
- #[Fillable] attribute for Laravel 13 (fields: sku, name, category, unit, reorder_point)
- HasMany relationship to StockMovement
- Type cast for reorder_point (integer)
- Follow existing model pattern (declare(strict_types=1), final class, proper types)

### Key Implementation Details
- Use #[Fillable(['sku', 'name', 'category', 'unit', 'reorder_point'])] attribute
- Include traditional $fillable array alongside attribute (for IDE/tooling compatibility per Warehouse model pattern)
- Add type cast: 'reorder_point' => 'integer'
- Defer access control scopes to stage 3/9 (when role system and query scoping are implemented)
- Follow existing Warehouse model pattern for structure

## Expected Behavior
A Product model will exist that can be used in factories, relationships, and throughout the application.

## Council Summary

**Reviewers:** Architecture, Security/Access Control

**Architecture Reviewer:** APPROVED - Relationships are appropriate, #[Fillable] attribute is correct for Laravel 13. Recommended adding type cast for reorder_point and following Warehouse model pattern with both attribute and traditional array.

**Security/Access Control Reviewer:** APPROVED - Minimal model is appropriate for stage 2 with access control properly deferred to stages 3 (role system) and 9 (query scoping). The HasMany relationship to StockMovement will need scoping in stage 9 to prevent cross-warehouse data exposure.

**Notable Feedback:**
- Architecture reviewer confirmed the plan aligns with blueprint staging
- Security reviewer emphasized that access control will be implemented in stages 3 and 9 per blueprint
- Both agreed minimal model now is appropriate given the blueprint's staged approach
