---
name: audit-gap-analyzer
description: Audits a given list of features against existing code, plans, and tests — including browser/end-to-end tests. Returns a structured gap report (Complete, Missing, Broken, Unplanned).
---

# Audit & Gap Analyzer

## Input Parameters
- `feature_list`: array of features.
- `plans_path`: directory for plans.
- `decisions_path`: directory for ADRs.
- `blueprint_path`: optional project vision file.
- `code_root`: source code root.
- `test_command`: command to run the full test suite (must include browser tests if UI).
- `browser_test_command`: **NEW** – specific command to run browser/end-to-end tests (e.g., `php artisan test --testsuite=Browser`, `npx playwright test`, `npm run test:e2e`).
- `ui_check`: boolean – whether this feature is likely UI-touching (default: `true` if frontend files exist).

---

## Steps

1. Accept `feature_list`.
2. Search plans, ADRs, and codebase for each feature.
3. **Determine if the feature is UI-touching**:
   - Check if it involves routes that render views, frontend components, or user-facing pages.
   - If uncertain, default to `ui_check = true` (safer to over-audit).
4. Run `test_command` to verify core backend/frontend unit/integration tests.
5. **If UI-touching, run `browser_test_command`** to verify that the feature actually renders and behaves in a real browser environment.

---

## Decision Matrix (Extended)

For every feature, apply this matrix. **Browser tests are required for UI features** – absence = BROKEN.

| Plan exists? | Impl exists? | Unit/Integration Tests pass? | Browser Tests exist & pass? (if UI) | Verdict | Action |
| :--- | :--- | :--- | :--- | :--- | :--- |
| ✅ Yes | ✅ Yes | ✅ Yes | ✅ Yes | **COMPLETE** | No action. |
| ✅ Yes | ✅ Yes | ✅ Yes | ❌ No (missing/failing) | **BROKEN** | Missing browser test coverage – write/fix browser tests before marking complete. |
| ✅ Yes | ✅ Yes | ❌ No | Any | **BROKEN** | Fix unit/backend tests first. |
| ✅ Yes | ❌ No | N/A | N/A | **MISSING** | Implement from plan (including browser tests). |
| ✅ Yes | ✅ Yes (mismatch) | ✅ Yes | ✅ Yes | **MISALIGNED** | Plan or code needs updating. |
| ❌ No | ✅ Yes | ✅ Yes | ✅ Yes | **UNPLANNED** | Write retro plan. |
| ❌ No | ✅ Yes | ✅ Yes | ❌ No | **BROKEN** | Code works but lacks browser test coverage. Write tests, then retro plan. |
| ❌ No | ✅ Yes | ❌ No | Any | **BROKEN** | Code is unsound. Delete/refactor. |
| ❌ No | ❌ No | N/A | N/A | **MISSING** | Create new plan (including browser tests). |

---

## Output

```json
{
  "summary": "2 complete, 1 broken (missing browser test), 1 missing",
  "needs_clarification": false,
  "clarification_notes": null,
  "items": {
    "<feature>": {
      "status": "COMPLETE|BROKEN|MISSING|UNPLANNED|MISALIGNED",
      "plan_path": "path/to/plan.md or null",
      "code_path": "path/to/code.php or null",
      "browser_test_path": "path/to/browser/test or null",
      "action_summary": "Write browser tests for UI feature"
    }
  }
}