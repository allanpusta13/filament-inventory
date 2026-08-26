
---

### 2. Updated: `gap-executor.md`
*(Now bakes browser tests into the mandatory Test step – a feature isn't "done" until the browser test passes and Playwright visual check is clean)*

```markdown
---
name: gap-executor
description: Executes the full Plan→Council→Code→Test→Commit loop for a single feature. Enforces browser tests for any UI-touching feature as a non-negotiable exit criterion.
---

# Gap Executor

## Input
- `feature`: string name.
- `status`: `MISSING | BROKEN | UNPLANNED | MISALIGNED`.
- `existing_plan`: path to plan (null if none).
- `existing_code`: path to code (null if none).
- `blueprint_path`: path to project blueprint.
- `plans_path`: directory to save new/retro plans.
- `test_command`: full test suite command.
- `browser_test_command`: **NEW** – e.g., `php artisan test --testsuite=Browser` or `npx playwright test`.
- `is_ui_feature`: boolean (defaults to true if frontend files are involved).

---

## Execution Rules (per status)

### If `MISSING`:
- Write a new plan. **Ensure the plan explicitly includes browser tests** if `is_ui_feature` is true.
- Council review (Step 2).
- Implement code **and** corresponding browser test.
- **Test phase** must run: `test_command` AND (if UI) `browser_test_command` **and** a Playwright visual check (if using the skill).
- Run Impeccable design audit for UI.
- Only commit if all pass.

### If `BROKEN`:
- Identify root cause.
- If missing browser tests → write them immediately.
- If code is broken → refactor to match plan OR delete and reimplement.
- Re-run `test_command` and `browser_test_command` (if UI). Playwright visual check is mandatory for UI fixes.
- Commit.

### If `UNPLANNED` (code exists, tests pass, no plan):
- Write retro plan. **Must include browser test specification** for UI features.
- Council review.
  - **Approved**: Save plan. Ensure existing browser tests pass. If none exist, write them now.
  - **Denied**: Refactor code and write new browser tests as needed.
- Commit.

### If `MISALIGNED` (plan vs code disagree):
- Decide alignment based on `blueprint_path`.
- Execute chosen path.
- **Regardless of path**, run the full test suite + browser suite to confirm the reconciled version works end‑to‑end.
- Commit.

---

## Mandatory "Done" Checklist (applies to all paths)

Before returning `success: true`, this skill **must** confirm:

- [ ] `test_command` passes (backend + frontend unit/integration).
- [ ] If `is_ui_feature`:
  - [ ] `browser_test_command` passes (e.g., Pest Browser, Playwright, Cypress).
  - [ ] **Playwright visual screenshot** has been taken and matches intended design.
  - [ ] **Impeccable audit** (or critique) passes for the affected UI.
- [ ] All changes are committed with a clear message.
- [ ] `vendor/bin/pint` or equivalent linter has been run.

---

## Output
```json
{
  "feature": "Feature A",
  "success": true,
  "summary": "Fixed broken implementation, wrote missing browser tests, all visual checks pass.",
  "commits": ["abc1234", "def5678"],
  "browser_tests_written": ["tests/Browser/feature_a_test.php"],
  "errors": null
}