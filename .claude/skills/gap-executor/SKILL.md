---
name: gap-executor
description: Executes the full Plan→Council→Code→Test→Commit loop for a single feature with a known status (MISSING, BROKEN, UNPLANNED, or MISALIGNED). Returns a success/failure report.
---

# Gap Executor

## Input
- `feature`: string name.
- `status`: `MISSING | BROKEN | UNPLANNED | MISALIGNED`.
- `existing_plan`: path to plan (null if none).
- `existing_code`: path to code (null if none).
- `blueprint_path`: path to project blueprint.
- `plans_path`: directory to save new/retro plans.
- `test_command`: command to run full suite.

## Execution Rules

### If `MISSING`:
- Write a new plan from scratch using the `feature` + `blueprint_path`.
- Take through council review (Step 2).
- Implement per the approved plan.
- Run full tests + Playwright/Impeccable (if UI).
- Commit.

### If `BROKEN`:
- Determine if there is an existing plan.
  - If **yes**: Refactor code to strictly match the plan, fix failing tests.
  - If **no**: Delete/refactor the broken code. Create a *new* plan, council review, implement fresh.
- Run full tests. Commit.

### If `UNPLANNED` (code exists, tests pass, no plan):
- Write a **retroactive plan** describing the current implementation.
- Submit to council review.
  - **Approved**: Save plan. Mark complete.
  - **Denied**: Refactor code to meet the council's objections (effectively treating it as BROKEN), then commit.

### If `MISALIGNED` (plan exists, code exists, tests pass, but they disagree):
- Check `blueprint_path` to decide which one aligns better.
- Option A: Update the plan to match the code (submit to council for approval).
- Option B: Refactor the code to match the plan (submit change to council).
- Execute the chosen path, run tests, commit.

## Output
```json
{
  "feature": "Feature A",
  "success": true,
  "summary": "Fixed broken tests and refactored controller.",
  "commits": ["abc1234", "def5678"],
  "errors": null
}
```