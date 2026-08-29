---
name: visual-accessibility-audit
description: Audits UI screenshots for color contrast, visual legibility, focus indicators, and accessible layout design.
---

# Visual Audit Skill: Accessibility (a11y) Review

Audit full-page Playwright screenshots in `docs/00-project/screenshots/` for visual accessibility compliance.

## Context & Output Rules
- **Context Retrieval**: If relevant Blade components or form schemas are needed, DO NOT inspect entire frontend file trees. Query Graphify (`/graphify query`, `/graphify explain`) to pinpoint only the affected component node.
- **Payload Compression**: Compress raw DOM trees or accessibility trees using `headroom_compress`.

## Audit Checklist
1. **Color Contrast**: Verify text against background colors meets WCAG AA contrast standards (minimum 4.5:1 for normal text).
2. **Visual Hierarchy & Scalability**: Ensure text sizes, form labels, and iconography are legible without relying solely on color to convey state.
3. **Control Legibility**: Confirm input borders, focus outlines, button targets, and interactive controls are clearly visible.

## Output Requirement
Return an audit report detailing findings and conclude with:
- **`PASS`** — Visual accessibility standards met.
- **`FAIL`** — Accessibility issues detected (specify exact UI elements requiring adjustment).