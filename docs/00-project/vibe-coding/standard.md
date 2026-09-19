# Vibe Coding Standard

## Purpose

This document defines WHAT must happen and WHEN during AI-assisted development. `blueprint.md` defines the system; `guideline.md` explains WHY/HOW TO THINK; `CLAUDE.md` defines HOW THE AI BEHAVES.

## Standard Stack

Unless the project explicitly specifies otherwise: PHP `^8.4`, Laravel `^13.0`, Filament `^5.6`, Livewire `^4.0`, Alpine `^3.x`, Tailwind `^4.x`, Pest `^4.0`, Playwright latest. Verify installed versions before version-sensitive work.

## AI Session Initialization — Mandatory

At the start of every AI work session before substantive project analysis:

1. **Update Graphify.**
2. Verify Graphify is available/current.
3. **Use Graphify as the primary codebase knowledge layer** for structure, relationships, dependencies, callers/callees, and impact analysis.
4. Verify important graph findings against source, tests, or runtime evidence.
5. Use **Context7** for version-sensitive framework/package/library/API research.
6. Use **EnvKit** for supported local PHP environment inspection and operations.

If Graphify cannot be updated or queried, state that fact and do not claim it was used.

## Graphify Standard

Graphify is the primary codebase knowledge layer, not project authority. Refresh it after material code changes before relying on graph results again. Treat inferred/ambiguous relationships as hypotheses until verified.

## Context7 Standard

Context7 is required for version-sensitive external technical research. It supplements, and never replaces, repository inspection, Graphify, tests, security review, approved requirements, or user approval.

## EnvKit Standard

EnvKit is the standard local PHP development environment/tooling layer. Safe/read-only operations may be autonomous. Destructive/consequential operations require explicit user approval unless already covered by an approved plan. Production/staging remains outside EnvKit authority.

## AI Behavioral Standard

The AI must:
- distinguish **Stated, Observed, Inferred, Proposed, Approved** information;
- report outcomes truthfully and never claim unobserved fixes/tests/verification;
- inspect available context before asking the user to repeat information;
- preserve requested scope and avoid silent scope expansion/narrowing;
- isolate uncertainty rather than invent decisions;
- protect privacy/secrets;
- preserve user authority for material product, architecture, security, data, dependency, and consequential decisions.

## Non-Trivial Work Gate

For work that can materially affect product behavior, architecture, security, data, dependencies, multiple application areas, external side effects, or deployment/operations:

**Discovery → Plan → Council audit → save `pending/` → user approval → save `approved/` → Build → Council review → Test → Evidence.**

Do not implement before explicit approval. `continue` does not bypass approval. Routine changes clearly implied by approved requirements do not require a new plan.

## Council

Members: Product/PM, Security, Architecture, QA, Skeptic. Council reviews Phase 0–3 work and identifies ambiguity, risk, scope issues, security concerns, architectural concerns, and test gaps. Council does not replace the user's authority for material decisions.

## Phase 0 — Discovery

Identify requirements, scope, affected components, current architecture, Graphify relationships, installed versions, existing tests, security implications, dependencies, and unknowns.

## Phase 1 — Plan

Define scope in/out, tasks, evidence, stop conditions, and decisions requiring approval. Council-audit and save non-trivial plans under `docs/00-project/ai/plans/pending/`, then obtain explicit user approval.

## Phase 2 — Build

Execute only the approved scope. Use Graphify, Context7, and EnvKit according to their standards. Stop for material deviations. Refresh Graphify after material changes.

## Phase 3 — Test / Refine

Use **Pest Unit + Feature** and **standalone Playwright for E2E**. Verify behavior, validation, authorization, record access, database changes, integrations, UI behavior, errors, and security controls as applicable.

## Security Pass

Review applicable changes for: authentication; authorization; record-level access; mass assignment; input validation; file upload/storage security; dependency security; rate limiting/abuse protection.

## Package Decision Gate

Before adding/removing/replacing/materially changing dependencies: identify need, check existing capabilities, research version/compatibility, assess maintenance/security and architecture/test/deployment impact, and obtain approval unless already covered by an approved plan.

## Destructive Change Gate

Require approval for destructive/consequential operations unless explicitly covered by an approved plan: destructive DB operations, irreversible migrations, data deletion, force resets/pushes, deleting project files, production/staging changes, and external side effects.

## Browser Testing

Standalone Playwright is the browser E2E standard. Inspect current state, avoid unauthorized destructive actions, verify resulting state, preserve useful evidence, and stop after repeated failures instead of blindly retrying.

## Evidence Before Done

Do not call work fixed, tested, verified, saved, deployed, working, or complete without evidence appropriate to that claim.

## Deviation Protocol

For material deviation: stop → explain difference/reason/impact → request approval → update plan → continue only after approval.

## Phase 4 — Deploy / Monitor

Use the project's deployment authority and approval rules. EnvKit is not a production authority. Monitor errors, performance, queues, integrations, security signals, and user-impacting behavior.

## Phase 5 — Document / Grow

Record durable knowledge such as ADRs, approved plans, research, selected screenshots, meaningful daily logs, and changelog entries. Never store secrets or sensitive production data.
