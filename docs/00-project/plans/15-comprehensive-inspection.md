# Plan: Complete Inspection, Gap Analysis, Refactoring, and Implementation

## Goal
Perform a complete top-to-bottom inspection of the application, ensuring that every component specified in the blueprint prompts (00.md through 14.md) exists and is correct, that all architectural guardrails are enforced, that there is no duplicate or dead code, that all tests pass, and that the application runs without errors.

## Steps

### Phase 0: Preparation
- [ ] Update Graphify knowledge graph (already done with --code-only)
- [ ] Review existing ADRs and CLAUDE.md for prior decisions

### Phase 1: Blueprint Verification (Prompts 00-14)
For each prompt file in `docs/00-project/prompts/` (00.md to 14.md):
  - [ ] Extract all specified Filament Resources, Pages, RelationManagers, Widgets, Actions, Enums, Models, Policies, etc.
  - [ ] For each specified component:
        - Check if it exists in the codebase.
        - If not, create it with full implementation as per the prompt and guardrails.
        - If it exists, audit it for:
            * Compliance with the prompt specification
            * Adherence to Filament v5 conventions
            * Correct use of namespaces and components
            * Proper authorization (especially for auditor role restrictions on order creation)
            * Enum icon and color bindings (if applicable)
            * Redundancy and dead code
            * Test coverage (backend and frontend)
  - [ ] After fixing any missing or incorrect components, rescan and retest the affected areas.

### Phase 2: Redundancy and Dead Code Audit
- [ ] Scan the entire `app/` directory for duplicate Filament resources, redundant service classes, dead helper methods, duplicate Eloquent scopes, repeated UI schema logic.
- [ ] Refactor duplicate logic into shared traits, service classes, or base Filament components.
- [ ] Remove obsolete or redundant files, ensuring no broken dependencies.
- [ ] Rescan to verify cleanup.

### Phase 3: Order Creation Authorization Sweep
- [ ] Audit all Order resources (TransferRequisitionResource, etc.) and related pages, action modals, and policies.
- [ ] Verify that only `warehouse_manager`, `warehouse_staff`, and `admin` roles can create, submit, edit, or alter orders.
- [ ] Ensure `auditor` role sees no order creation/edit actions and receives 403 on backend attempts.
- [ ] Fix any leaks immediately and rescan.

### Phase 4: Test Coverage and Skipped Tests Resolution
- [ ] Audit all test files (`tests/Unit/`, `tests/Feature/`, `tests/Browser/`) for skipped tests (`->skip()`, `markTestSkipped`, `test.skip()`, commented-out blocks).
- [ ] Refactor code or test setups to un-skip each test immediately.
- [ ] Run each fixed test to confirm it passes.
- [ ] Ensure dual-layer test coverage (backend Pest/PHPUnit and frontend Playwright) for every model, resource, page, RelationManager, action modal, API endpoint, policy boundary, and UI workflow.

### Phase 5: Backend and Database Integrity Audit
- [ ] Run `php artisan migrate:fresh --seed` and verify database constraint integrity.
- [ ] Inspect all Eloquent Models, Enums, and Migrations for:
        * Correct foreign keys
        * Proper $casts and $fillable
        * Null accessors
- [ ] Fix any issues immediately and rescan model relations.
- [ ] Write/pass Pest tests covering model relationships, factories, enums, and order policies.

### Phase 6: Filament V5 UI, RelationManagers, and Frontend Action Sweep
- [ ] Inspect all Filament Resources, Pages, Widgets, and RelationManagers for:
        * Form schemas, table columns, badges, RelationManager inline actions
        * STN PDF generation, Scan-to-Receive modals, Loss Ledger triggers
        * Zero runtime reflection or null property exceptions
- [ ] Fix any issues immediately and rescan UI components.
- [ ] Write/pass Playwright specs testing form inputs, RelationManager tabs/tables, action buttons, modal triggers, and QR scan parameters.

### Phase 7: Authorization and Role-Based Access Control (RBAC) Audit
- [ ] Inspect all Policies (`app/Policies/*.php`) for the roles: admin, branch_manager, warehouse_manager, warehouse_staff, auditor.
- [ ] Fix any unauthorized access paths or visible buttons immediately and rescan permission policies.
- [ ] Dual test requirement:
        * Pest: Assert policy gates return true/false for each role.
        * Playwright: Authenticate as each role and assert sidebar, table actions, and direct URL restrictions.

### Phase 8: Route, Event, and Document Generation Check
- [ ] Audit custom endpoints (STN PDF output, QR code rendering, Loss Ledger CSV exports).
- [ ] Fix event listeners and response headers as needed.
- [ ] Rescan to ensure correctness.

### Phase 9: Final Verification Pass
- [ ] Run `vendor/bin/pest` to confirm all backend unit and feature tests pass with zero skips.
- [ ] Run `npx playwright test` to confirm all frontend E2E browser tests pass across all roles with zero skips.
- [ ] Clear all application caches (`php artisan config:clear`, `view:clear`, `route:clear`, `filament:optimize-clear`).

## Non-Goals / Out of Scope
- Changing the application's dependencies (unless required to fix a bug or missing feature as per the prompts).
- Creating new base folders outside the existing directory structure.
- Writing verification scripts or tinker when tests already cover the functionality.

## Expected Outcomes
- Every component specified in the prompts exists and is correct.
- All architectural guardrails are enforced.
- No duplicate logic or dead code remains.
- All tests pass with zero skips.
- The application runs without errors.