---
name: council-qa
description: Evaluates implementation plans for test coverage, edge cases, and test harness safety.
---

# Council Skill: QA & Test Review

Evaluate the implementation plan proposed in Stage 1/2 for testability, edge case coverage, and suite safety.

## Context & Output Rules
- **Context Retrieval**: DO NOT read the entire test suite. Query Graphify (`/graphify query`, `/graphify path`, `/graphify explain`) to identify affected test targets and dependency relationships.
- **Payload Compression**: Wrap test execution outputs and stack traces with `headroom_compress` to summarize raw test logs.

## Evaluation Checklist
1. **Test Coverage Strategy**: Does the plan detail unit, feature, and browser test requirements for all new functionality?
2. **Edge Cases**: Are empty states, invalid inputs, authorization failure paths, and concurrency limits addressed in the test plan?
3. **Execution Safety**: Are browser tests isolated from parallel test execution to prevent live database race conditions?
4. **Mocking & Fixtures**: Are external service calls and third-party APIs properly mocked?

## Output Requirement
Return a clear review output ending strictly with your verdict:
- **`APPROVE`** — Test strategy is complete, safe, and covers necessary edge cases.
- **`DENY`** — Missing test paths or unsafe test strategies identified (specify exact gaps).