# Plan Review: Implement Comprehensive Widget Tests (Stage 1)

## Evaluation Summary: DENY

The plan requires revisions before implementation can proceed. Below is a detailed breakdown of each evaluation criterion.

### 1. Does the plan follow Laravel/Filament v5 conventions?

**Partially — but with critical mismatches.**

- ✅ Uses `uses(RefreshDatabase::class)` pattern correctly
- ✅ Uses `$this->actingAs($this->user)` for auth correctly
- ✅ Follows Filament widget test structure
- ✅ Pest test organization is appropriate
- ❌ **Security ADRs assume concepts absent from codebase**: The plan requires `tenant_id` scoping and admin=403 checks, but the actual widgets are neither tenant-scoped nor admin-restricted. They scope by `$user->warehouses` (warehouse IDs via BelongsToMany relationship), not `tenant_id`.
- ❌ **Interface contracts don't match implementations**: The plan's security-required test additions expect `where('tenant_id', $user->tenant_id)` on every query, but the codebase has no `tenant_id` column or relationship. The `User` model has `warehouses()` relationship, not `tenant_id`.

### 2. Are the test additions appropriately scoped (not too broad, not too narrow)?

**NO — Mismatched with actual implementations.**

The 27 new tests are scoped for a "security-aware" plan, but the security categories don't align with the widget architectures:

| Test Category | Plan Expectation | Actual Widget Behavior |
|---|---|---|
| 403 for non-admin | Widget should deny non-admin | Widgets aren't admin-restricted; they filter by user's warehouses |
| SQL injection rejection | Form request validation for SQL payloads | Widgets don't take user SQL input; they display pre-existing data |
| XSS scripts in variant names | Input sanitization test | Widgets display data; they don't accept variant name input from users |
| tenant_id scoping | `where('tenant_id', $user->tenant_id)` | Widgets use `$user->warehouses->pluck('id')` — warehouse_id, not tenant_id |
| Cross-tenant denial | User A cannot access User B's data | Widgets filter by user's assigned warehouses; no tenant concept exists |
| Generic error not details | Error messages must not leak IDs | Widgets have error conditions but not related to tenant leaking |

The tests would either fail (attempting `tenant_id` queries on models that don't have it) or be meaningless (testing for security boundaries that don't exist).

### 3. Are the non-goals clearly stated and reasonable?

**YES — Clearly stated and reasonable.**

The non-goals section (lines 57-62) is well-written and appropriate:
- Don't modify widget PHP implementations unless adding input sanitization middleware required by security ADRs
- Don't add new Filament resource tests
- Don't modify InventoryService implementation
- Don't run playwright E2E tests
- Don't add unit tests for InventoryService
- Don't change cache TTL values (300s/60s are intentional)

These are all appropriate. However, point 1 creates a tension with the security ADRs (see criterion 6).

### 4. Does the interface contract spec match the actual widget implementations?

**NO — Significant mismatch.**

The plan's interface contracts (lines 68-102) expect:
- `where('tenant_id', $user->tenant_id)` on every query — **NOT IN CODEBASE**
- Cross-tenant denial assertions — **NO TENANT CONCEPT IN CODEBASE**
- SQL injection rejection in warehouse context — **Widgets don't accept SQL input**
- XSS scripts in variant names — **Widgets display pre-existing data**
- Cache keys include tenant ID — **Current keys use `userId_warehouseId` format**

The actual widgets scope by warehouse through `$user->warehouses` relationship. There is absolutely no `tenant_id` anywhere in the codebase (confirmed via grep).

### 5. Is the math error fixed (27 not 38)?

**YES — Properly fixed and documented.**

The plan explicitly addresses this with ADR MATH-001 (lines 120-121): "Total new test count is 27 (10 + 8 + 9), not 38." This is verified and documented in the "Exact Files to Touch" table and the Graphify Structural Context section.

### 6. Are the security ADRs (SEC-001, SEC-002, MATH-001) properly integrated?

**PARTIALLY — MATH-001 is correct; SEC-001 and SEC-002 are problematic.**

- **MATH-001**: ✅ Properly integrated. Confirms 27 not 38.
- **SEC-001**: ⚠️ Partially integrated but fundamentally mismatched. The ADR requires "All widget tests must include `tenant_id` scoping via the user's tenant relationship." But the codebase has no `tenant_id` — the widgets use `warehouse_id` via `$user->warehouses`. The ADR's fallback ("If a widget serves multiple tenants without role-based isolation...") doesn't apply because the widgets don't model tenants at all.
- **SEC-002**: ⚠️ Partially integrated but incorrectly applied. The ADR requires widgets to "explicitly define whether they are admin-restricted or multi-tenant." The plan acknowledges at line 112 that "If a widget is NOT admin-restricted, the test must explicitly document that and add authorization-denial tests for non-admin users" — but then proceeds to add "403 when non-admin attempts access" tests for ALL three widgets, which is incorrect since none are admin-restricted.

The security ADRs were written assuming security boundaries that don't exist in the current codebase.

### 7. Are the tenant_id scoping requirements reasonable and implementable?

**NO — Not implementable without modifying widget implementations.**

- The codebase has **no `tenant_id` column** on any model, confirmed via grep across entire project
- The `User` model has `warehouses()` (BelongsToMany), not `tenant_id`
- Widget cache keys use format `active_in_transit_{userId}_{warehouseId}`, etc. — **not** tenant-inclusive
- The widgets scope queries by `$user->warehouses->pluck('id')` — warehouse IDs, not tenant IDs
- Implementing the plan's `where('tenant_id', $user->tenant_id)` requirement would require:
  1. Adding `tenant_id` column to users (or a tenants table)
  2. Adding tenant relationships to InTransit, ProductVariant, StockMovement models
  3. Re-architecting all three widget queries
  4. This contradicts the plan's explicit non-goal: "Do NOT modify widget PHP implementations"

The plan states at line 57: "Do NOT modify widget PHP implementations... unless adding input sanitization middleware required by security ADRs" — but the security ADRs require tenant_id scoping that **cannot** be implemented without modifying the widgets. This is a direct contradiction.

## Final Determination

**DENY** — The plan cannot be implemented as written without violating its own non-goals and attempting to implement security requirements that don't match the codebase.

## Exact Revisions Needed

### Revise Security ADRs (SEC-001, SEC-002) to Match Codebase Realities

**Replace SEC-001** with a tenant/warehouse-aware requirement that matches the actual codebase:

> **Revised SEC-001**: All widget tests must scope queries by the user's assigned warehouses via `$user->warehouses->pluck('id')`. Cross-warehouse denial assertions should verify that users can only access data from warehouses they are assigned to. If future changes introduce tenant-level isolation, a new ADR should document the multi-tenant access pattern.

**Replace SEC-002** with admin-status verification that matches the actual widget architectures:

> **Revised SEC-002**: Widgets are warehouse-scoped, not admin-restricted. Tests must verify that users can access widget data from their assigned warehouses, and that unauthorized warehouse access is denied. The `actingAs(User::factory()->admin()->create())` pattern in existing tests confirms admin users have full access — tests should document this boundary rather than asserting 403 for non-admin access on widgets that aren't admin-restricted.

### Revise Interface Contracts to Match Actual Widget Implementations

- Remove all requirements for `where('tenant_id', $user->tenant_id)` — the codebase uses warehouse_id scoping
- Change cross-tenant denial tests to cross-warehouse denial tests
- Change SQL injection/XSS test rationales to match what the widgets actually do (display pre-existing data, filter by user's warehouses)
- Update cache key expectations to match the existing `userId_warehouseId` format
- Remove the mandatory `tenant_id` scoping requirement from the "Design Notes" section (line 108-109)

### Revise Test Additions to Match Widget Behaviors

- Replace "403 when non-admin attempts access" tests with tests that verify admin users (via `->admin()`) can access all widget data — which the existing tests already confirm
- Replace SQL injection rejection tests with tests that verify form request validation patterns (or remove if no user input flows exist)
- Replace XSS script rejection tests with tests that verify data sanitization if the widgets accept any user-generated content (they don't appear to)
- Replace `tenant_id` scoping tests with `warehouse_id` scoping tests using `$user->warehouses->pluck('id')`
- Replace cross-tenant denial with cross-warehouse denial: verify users cannot access widgets from warehouses they aren't assigned to

### Update Non-Goals

- Add clarification: "Do NOT modify widget PHP implementations to add tenant_id scoping — that requires a separate architecture change. Security ADRs should reference existing warehouse-scoping patterns."

### Alternative Approach

If the intent is to add genuine security testing, consider these options:

1. **Test warehouse isolation** (which actually exists): Verify users can only access widgets from warehouses they're assigned to — this is a real security boundary in the codebase.

2. **Test admin boundaries** (which actually exist): The existing tests already use `User::factory()->admin()->create()` and confirm admins have full access. Add tests that verify non-admin users (regular users) get appropriate access levels.

3. **Test input validation** on any widget endpoints that actually accept user input (if any exist beyond what's been reviewed).

4. **Test cache behavior** (which actually exists): The widgets already have cache TTL tests (300s/60s). Add tests for cache bypass, cache invalidation on data changes, etc.

5. **Test error handling** (which actually exists): The widgets have edge case tests (empty states, null values). Add more comprehensive error condition tests.

These genuine security tests would be implementable without modifying widget implementations and would test actual security boundaries in the codebase.

## Conclusion

The plan has good structure and addresses a real need for enhanced test coverage, but the security requirements (tenant_id scoping, admin=403 boundaries) are fundamentally mismatched with the actual widget implementations. The plan cannot be implemented as written without either violating its own non-goals or making code changes it explicitly excludes. Security ADRs must be revised to reference the existing warehouse-scoping patterns rather than introducing tenant_id concepts that don't exist in the codebase.

The math error is correctly fixed (27 not 38), and the non-goals are well-stated. But the security ADRs and interface contracts need substantial revision before this plan can pass review.