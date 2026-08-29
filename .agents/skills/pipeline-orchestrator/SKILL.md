---
name: plan-council-code-test
description: Executes a Graphify and Headroom powered pipeline with Tolaria vault documentation, modular code execution, parallelized backend/frontend testing, custom commit formats, multi-agent audits, and post-commit knowledge graph updates.
---

# Pipeline Orchestrator

Run this task pipeline sequentially. Tolaria manages the `docs/` vault—write documentation as you go, not as an afterthought.

## Context & Output Efficiency Rules (Mandatory for All Subagents)
1. **Graphify Retrieval (Context Selection)**: NEVER scan, list, or read whole directories or entire repository files into context. Always query Graphify (`/graphify query`, `/graphify path`, `/graphify explain`) to retrieve only the exact files and symbols needed.
2. **Headroom Payload Compression (Tool Output Optimization)**: Whenever running terminal commands, test suites, or inspecting large JSON/text outputs, use `headroom_compress` to compress the payload before processing. If exact uncompressed details are needed, use `headroom_retrieve`.

## Tolaria Vault Structure
Always use these exact paths when reading or writing documentation:
- `docs/00-project/architecture-decisions/` — Architectural Decision Records (ADRs) explaining *why*, written when non-obvious decisions are made.
- `docs/00-project/plans/` — Approved task plans, saved after Council approval in Stage 2 (e.g., `02-01-identify-tenant-middleware.md`).
- `docs/00-project/screenshots/` — Playwright screenshots from visual audits.
- `docs/00-project/prompts/` — Task prompt references (read-only during execution).
- `docs/00-project/followups/` — Saved follow-up prompts generated or received during sessions. **Rule**: Do NOT save if the prompt is strictly `"continue"`.
- `docs/01-issues/` — Individual notes per GitHub issue.
- `docs/02-research/` — Package and framework research (e.g., Laravel, Filament, Livewire version specifics).
- `docs/03-daily-logs/` — Daily logs and session summaries for larger tasks.

---

## Stage 0 — Graphify Context & Prior Decisions
1. Search `docs/00-project/architecture-decisions/` (ADRs) and `CLAUDE.md` to check if this design area has prior settled decisions.
2. Query Graphify knowledge graph (`/graphify query`, `/graphify path`, `/graphify explain`) to map file dependencies and call structures before planning.
   - If `graphify-out/` is missing or stale, run `/graphify .` first.

## Stage 1 — Plan
Write a short implementation draft plan including:
- Graphify structural context (affected dependencies/call paths)
- What will change and why
- Exact files to touch
- Explicit non-goals / out of scope

*If a non-obvious design choice is made during planning:* Write an ADR immediately into `docs/00-project/architecture-decisions/{adr-title}.md`.

*Follow-up Prompt Rule:* If a new task prompt or follow-up instruction is received during or after planning, save it to `docs/00-project/followups/{prompt-number}-{task-slug}.md`. **Do NOT save if the input is strictly `"continue"`**.

## Stage 2 — Plan Council Review (Parallel Subagents)
Spawn 3 parallel review subagents, attaching their respective skills:
- **Subagent A**: Load skill `council-architecture` -> Review draft plan against `CLAUDE.md` and existing ADRs.
- **Subagent B**: Load skill `council-security` -> Review draft plan for access control and data security.
- **Subagent C**: Load skill `council-qa` -> Review draft plan for test coverage and edge cases.

*Decision Gate:*
- If **ANY** review returns `DENY`: Revise plan and re-run Stage 2.
- If **ALL** return `APPROVE`: Save final approved plan to `docs/00-project/plans/{prompt-number}-{task-slug}.md` (e.g. `02-01-identify-tenant-middleware.md`).

---

## Stage 3 — Code (Single or Decomposed Execution)

Check the approved plan in `docs/00-project/plans/` to determine execution strategy:

### Mode A: Single Implementer (Default)
1. Read stack conventions from `CLAUDE.md`.
2. Implement code strictly according to the approved plan.

### Mode B: Decomposed / Parallel Implementers (For Larger Tasks)
1. **Define Non-Overlapping File Manifests**: Ensure no two subagents write to the same file.
2. **Define Written Interface Contracts**: Specify precise function signatures, route contracts, or prop shapes in the plan so subagents can code independently without live communication.
3. **Spawn Implementer Subagents**:
   - **Implementer Subagent 1**: Assigned Scope A + Interface Contract.
   - **Implementer Subagent 2**: Assigned Scope B + Interface Contract.
4. **Integration Verification**: Once subagents finish, run an integration pass verifying all signatures, routes, and prop shapes match the written interface contract perfectly.

*Note:* Document any newly discovered framework quirks or package specifics in `docs/02-research/`.

---

## Stage 4 — Test & Multi-Agent Visual Audit

### Parallel Test Execution Strategy
Backend and frontend test suites can run simultaneously in separate subagents or terminal instances, but follow strict process isolation. Pass raw test outputs through `headroom_compress` to prevent log context bloat.

1. **Parallel Runner Subagent 1 — Backend Unit/Feature Suite**:
   - Run `php artisan test --parallel` (or Pest unit/feature tests).
   - Operates against isolated in-memory or worker databases.
2. **Parallel Runner Subagent 2 — Frontend Component Suite**:
   - Run JS component tests (e.g., `vitest` / `jest`) or Livewire testing helpers (`Livewire::test()`).
   - Runs independently of backend HTTP processes.
3. **Serial Execution Gate — Browser Tests (`visit()`)**:
   - **CRITICAL**: Pest v4 browser tests (or Playwright E2E) MUST NOT run in parallel with each other. They hit the live local server and shared database—run browser flows sequentially after parallel unit suites pass.

4. **For UI Tasks — Multi-Agent Visual Audit**:
   - Capture full-page Playwright screenshot directly to `docs/00-project/screenshots/{task-slug}-latest.png`.
   - Spawn **Subagent D**: Load skill `visual-design-audit` -> Review screenshot in `docs/00-project/screenshots/`.
   - Spawn **Subagent E**: Load skill `visual-accessibility-audit` -> Review screenshot in `docs/00-project/screenshots/`.

*Decision Gate:* If any test runner (backend, frontend, browser) or visual audit returns `FAIL` → Fix root cause and re-run Stage 4.

---

## Stage 5 — Commit, Cleanup & Graphify Update
1. Auto-format code (`vendor/bin/pint`).
2. Stage modified files alongside their corresponding `docs/` files (approved plan, ADRs, research notes, screenshots, follow-up prompts).
3. Commit using the strict project format:
   `{branch-type}/{prompt-number}: {work-type}: {message}`

   *Example:*
   ```bash
   git commit -m "feature/02: feat: implement tenant middleware (ref: docs/00-project/plans/02-01-identify-tenant-middleware.md)"

```

4. **Post-Commit Graphify Update**:
* Run `/graphify .` after a successful commit to refresh the knowledge graph (`graphify-out/`) with the newly committed codebase structure.


5. (Optional) Log session output to `docs/03-daily-logs/` for large tasks or audits.