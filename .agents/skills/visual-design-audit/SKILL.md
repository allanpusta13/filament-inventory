---
name: visual-design-audit
description: Audits UI screenshots for design consistency, visual hierarchy, layout alignment, and component usage.
---

# Visual Audit Skill: Design & Layout Review

Audit full-page Playwright screenshots in `docs/00-project/screenshots/` to ensure visual design quality.

## Context & Output Rules
- **Context Retrieval**: If relevant Blade templates or CSS definitions are needed, DO NOT read full UI directories. Query Graphify (`/graphify query`, `/graphify explain`) to target only the specific view or component.
- **Payload Compression**: Use `headroom_compress` if inspecting large HTML or CSS dump payloads.

## Audit Checklist
1. **Layout Integrity**: Check for broken flex/grid containers, unintended horizontal scrolling, overflow bugs, or misaligned elements.
2. **Design System & Styling**: Verify spacing scale, typography hierarchy, colors, and button states match project UI standards (Tailwind/Filament).
3. **Dynamic Content States**: Confirm empty states, loading indicators, long text truncation, and badges render cleanly.

## Output Requirement
Return an audit report detailing findings and conclude with:
- **`PASS`** — Visual layout and styling adhere to design requirements.
- **`FAIL`** — Visual bugs or layout regressions present (specify necessary CSS/blade fixes).