name: audit-gap-analyzer
description: Audits a given list of features against existing code, plans, and decisions. Returns a structured gap report (Complete, Missing, Broken, Unplanned).
---

# Audit & Gap Analyzer

## Input Parameters
- `feature_list`: array of features.
- `plans_path`: directory for plans.
- `decisions_path`: directory for ADRs.
- `blueprint_path`: optional project vision file.
- `code_root`: source code root.
- `test_command`: optional test runner.

## Decision Matrix
| Plan exists? | Impl exists? | Tests pass? | Verdict | Action |
| :--- | :--- | :--- | :--- | :--- |
| ✅ Yes | ✅ Yes | ✅ Yes | **COMPLETE** | None |
| ✅ Yes | ✅ Yes | ❌ No | **BROKEN** | Fix code |
| ✅ Yes | ❌ No | N/A | **MISSING** | Implement from plan |
| ✅ Yes | ✅ Yes (mismatch) | ✅ Yes | **MISALIGNED** | Update plan or refactor code |
| ❌ No | ✅ Yes | ✅ Yes | **UNPLANNED** | Write retro plan |
| ❌ No | ✅ Yes | ❌ No | **BROKEN** | Delete/refactor, new plan |
| ❌ No | ❌ No | N/A | **MISSING** | New plan, implement |

## Output
```json
{
  "summary": "2 complete, 1 broken, 1 missing",
  "needs_clarification": false,
  "clarification_notes": null,
  "items": {
    "<feature>": {
      "status": "COMPLETE|BROKEN|MISSING|UNPLANNED|MISALIGNED",
      "plan_path": "path/to/plan.md or null",
      "code_path": "path/to/code.php or null",
      "action_summary": "Fix failing tests"
    }
  }
}
```