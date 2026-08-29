---
name: council-security
description: Evaluates implementation plans for authorization, data isolation, and security vulnerabilities.
---

# Council Skill: Security Review

Evaluate the implementation plan proposed in Stage 1/2 for security risks and access boundary violations.

## Context & Output Rules
- **Context Retrieval**: DO NOT scan the whole repository. Query Graphify (`/graphify query`, `/graphify path`, `/graphify explain`) to trace middleware chains, user authorization models, and policy files directly.
- **Payload Compression**: If inspecting policy files or middleware traces, use `headroom_compress` on large responses.

## Evaluation Checklist
1. **Tenant Isolation**: Does the plan guarantee multi-tenant boundary checks (e.g., scoping queries by tenant ID/middleware)?
2. **Authorization & RBAC**: Are policy checks, Livewire/controller middleware, or Filament gates explicitly required for sensitive actions?
3. **Input & Sanitization**: Are user inputs, file uploads, and parameters properly validated and sanitized?
4. **Data Exposure**: Are sensitive fields, tokens, or PII prevented from being exposed in API responses, logs, or state variables?

## Output Requirement
Return a clear review output ending strictly with your verdict:
- **`APPROVE`** — No authorization or data exposure vulnerabilities detected.
- **`DENY`** — Security risks found (specify exact vulnerabilities and required fixes).