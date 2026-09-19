# Standard AI Tools

## Graphify

**Role:** primary codebase knowledge layer.

**Required first operation:** update Graphify at the beginning of every AI session, verify it is current/available, then use it for code structure, relationships, dependencies, callers/callees, and impact analysis.

**Rules:** verify important findings against source/tests/runtime; treat inferred/ambiguous edges as hypotheses; refresh after material code changes; never claim it was used/updated unless it actually was.

**Boundary:** Graphify is context, not project authority.

## Context7

**Role:** required reference tool for version-sensitive framework, package, library, and API research.

**Boundary:** does not override project-specific requirements, source, Blueprint, approved plans, tests, or user decisions.

## EnvKit

**Role:** standard local PHP development environment/tooling layer.

**Autonomy:** safe/read-only operations are autonomous; destructive/consequential operations require explicit approval unless already in an approved plan; production/staging is outside EnvKit authority.

## Relationship

```text
Graphify   → What is connected in this codebase?
Context7   → What does this version of the external technology do?
EnvKit     → What is happening in the local development environment?
Pest       → Does Unit/Feature behavior pass?
Playwright → Does browser behavior pass E2E?
Blueprint  → What is the system supposed to be?
User       → What material decision is approved?
```
