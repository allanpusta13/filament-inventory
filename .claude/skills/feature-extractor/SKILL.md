---
name: feature-extractor
description: Reads a requirement file (markdown, text, or structured spec) and extracts a distinct, scoped list of features or user stories. Outputs a JSON array.
---

# Feature Extractor

## Input
- `source_file`: Path to a file containing requirements (e.g., `prompt.md`, `ticket.txt`).

## Steps
1. Read the file completely.
2. Parse bullet points, numbered lists, headings, and paragraphs.
3. Split the content into **distinct, non‑overlapping features**. 
   - If a section describes multiple actions (e.g., "Build login and registration"), split them into `["User login", "User registration"]`.
4. Ignore purely editorial/contextual filler (e.g., "We need to...").
5. Return a **flat JSON array**:

```json
{
  "features": [
    "Feature A",
    "Feature B",
    "Feature C"
  ]
}
```

**Note**: This skill does **not** audit, plan, or implement anything. It purely extracts.