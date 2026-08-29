---
name: council-architecture
description: Evaluates implementation plans for architectural integrity, ADR alignment, and stack compliance.
---

# Council Skill: Architecture Review

Evaluate the implementation plan proposed in Stage 1/2 against project standards and ADRs.

## Context & Output Rules
- **Context Retrieval**: DO NOT read large files or entire directory trees into context. Query Graphify (`/graphify query`, `/graphify path`, `/graphify explain`) to extract only the relevant class nodes, routes, or interfaces.
- **Payload Compression**: If analyzing large configuration files or terminal logs, compress them via `headroom_compress`.

## Evaluation Checklist
1. **ADR & CLAUDE.md Compliance**: Does the plan respect all existing Architectural Decision Records (`docs/00-project/architecture-decisions/`) and stack guidelines?
2. **Dependency Boundaries**: Are package and class dependencies properly isolated without breaking domain modularity?
3. **Data & Schema Design**: Do database migrations, models, or state changes avoid structural coupling or schema anti-patterns?
4. **Interface Contracts**: Are function signatures, route structures, or component props explicitly defined and consistent?

## Output Requirement
Return a clear review output ending strictly with your verdict:
- **`APPROVE`** — Architecture aligns with project conventions and ADRs.
- **`DENY`** — Architectural flaws detected (specify exact issues and required changes).