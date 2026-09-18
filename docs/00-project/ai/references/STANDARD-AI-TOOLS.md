# Standard AI Tools

## Context7

Context7 is a standard AI reference tool for version-sensitive framework, package, SDK, API, setup, configuration, and integration work. Use it before implementation when the external dependency's version or current API materially matters.

Rules:
- Prefer version-specific documentation matching the project's installed or approved version.
- Do not treat Context7 output as authorization or as a replacement for tests and local code inspection.
- Record material source/version decisions in the plan or research record.
- Never commit Context7 API keys or credentials.

## EnvKit

EnvKit is the standard local PHP development environment for supported platforms. When used, document the project site, PHP version, database, and required services.

EnvKit MCP autonomy is bounded:
- safe/read-only inspection and diagnostics: autonomous
- destructive/consequential environment changes: explicit user approval unless already covered by an approved plan
- production/staging management: outside EnvKit's authority

This document is reference guidance. `CLAUDE.md`, the Vibe Coding Standard, approved specifications, and current user instructions remain authoritative.
