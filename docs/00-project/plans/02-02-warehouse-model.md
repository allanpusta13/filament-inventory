# Plan 02-02: Create Warehouse model with relationships

## Plan

Create Warehouse model with minimal structure:

### File
- `app/Models/Warehouse.php`

### Structure
- #[Fillable] attribute for Laravel 13 (fields: name, location, is_active)
- HasMany relationship to StockMovement
- BelongsToMany relationship to User (via user_warehouse pivot)
- Follow existing model pattern (declare(strict_types=1), final class, proper types)

### Key Implementation Details
- Use #[Fillable(['name', 'location', 'is_active'])] attribute
- Defer access control scopes to stage 3 (after role enum and isAdmin() helper are created)
- Defer soft deletes and additional features to later stages if needed
- Follow existing User model pattern for structure

## Expected Behavior
A Warehouse model will exist that can be used in factories, relationships, and throughout the application.

## Council Summary

**Reviewers:** Architecture, Security/Access Control

**Architecture Reviewer:** APPROVED - Relationships are appropriate, #[Fillable] attribute is correct for Laravel 13. Recommended deferring access control scopes to stage 3 when role enum and helper methods are available per blueprint line 62.

**Security/Access Control Reviewer:** DENIED (initially) - Required model-level access control scopes. After discussion, agreed to defer scopes to stage 3 since they depend on isAdmin() which doesn't exist yet.

**Notable Feedback:**
- Architecture reviewer confirmed the plan aligns with blueprint staging
- Security reviewer emphasized that access control scopes should be added in stage 3 when the role system is built
- Both agreed minimal model now is appropriate given the blueprint's staged approach
