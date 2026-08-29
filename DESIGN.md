---
name: "Filament Inventory"
description: "Multi-warehouse inventory admin panel — Filament v5 defaults with Clinical Blue Authority"
colors:
  primary: "#3b82f6"
  primary-deep: "#2563eb"
  primary-muted: "#dbeafe"
  success: "#16a34a"
  success-muted: "#dcfce7"
  danger: "#dc2626"
  danger-muted: "#fee2e2"
  warning: "#d97706"
  warning-muted: "#fef3c7"
  gray: "#71717a"
  gray-muted: "#f4f4f5"
  neutral-bg: "#ffffff"
  neutral-surface: "#fafafa"
  neutral-border: "#e4e4e7"
  neutral-border-dark: "#18181b"
  neutral-text: "#18181b"
  neutral-text-muted: "#71717a"
typography:
  display:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(1.875rem, 4vw, 2.25rem)"
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: "-0.02em"
  headline:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: 1.3
    letterSpacing: "-0.01em"
  title:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: 1.4
  body:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 500
    lineHeight: 1.5
    letterSpacing: "0.02em"
    textTransform: "uppercase"
rounded:
  sm: "4px"
  md: "6px"
  lg: "8px"
  xl: "12px"
spacing:
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "32px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.neutral-bg}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
    height: "40px"
    typography: "{typography.label}"
  button-primary-hover:
    backgroundColor: "{colors.primary-deep}"
    textColor: "{colors.neutral-bg}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
    height: "40px"
  button-secondary:
    backgroundColor: "{colors.neutral-surface}"
    textColor: "{colors.neutral-text}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
    height: "40px"
    border: "1px solid {colors.neutral-border}"
  button-secondary-hover:
    backgroundColor: "{colors.neutral-border}"
    textColor: "{colors.neutral-text}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
    height: "40px"
  button-danger:
    backgroundColor: "{colors.danger}"
    textColor: "{colors.neutral-bg}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
    height: "40px"
  input-text:
    backgroundColor: "{colors.neutral-bg}"
    textColor: "{colors.neutral-text}"
    rounded: "{rounded.md}"
    padding: "8px 12px"
    height: "40px"
    border: "1px solid {colors.neutral-border}"
    typography: "{typography.body}"
  input-text-focus:
    border: "1px solid {colors.primary}"
    boxShadow: "0 0 0 3px {colors.primary-muted}"
  select:
    backgroundColor: "{colors.neutral-bg}"
    textColor: "{colors.neutral-text}"
    rounded: "{rounded.md}"
    padding: "8px 12px"
    height: "40px"
    border: "1px solid {colors.neutral-border}"
    typography: "{typography.body}"
  badge-success:
    backgroundColor: "{colors.success-muted}"
    textColor: "{colors.success}"
    rounded: "{rounded.sm}"
    padding: "2px 8px"
    typography: "{typography.label}"
  badge-danger:
    backgroundColor: "{colors.danger-muted}"
    textColor: "{colors.danger}"
    rounded: "{rounded.sm}"
    padding: "2px 8px"
    typography: "{typography.label}"
  badge-warning:
    backgroundColor: "{colors.warning-muted}"
    textColor: "{colors.warning}"
    rounded: "{rounded.sm}"
    padding: "2px 8px"
    typography: "{typography.label}"
  table-row:
    backgroundColor: "{colors.neutral-bg}"
    borderBottom: "1px solid {colors.neutral-border}"
  table-row-hover:
    backgroundColor: "{colors.neutral-surface}"
  table-header:
    backgroundColor: "{colors.neutral-surface}"
    textColor: "{colors.neutral-text-muted}"
    typography: "{typography.label}"
  card:
    backgroundColor: "{colors.neutral-bg}"
    rounded: "{rounded.lg}"
    border: "1px solid {colors.neutral-border}"
    padding: "{spacing.md}"
  modal:
    backgroundColor: "{colors.neutral-bg}"
    rounded: "{rounded.xl}"
    boxShadow: "0 25px 50px -12px rgba(0,0,0,0.15)"
    padding: "{spacing.lg}"
  sidebar:
    backgroundColor: "{colors.neutral-bg}"
    borderRight: "1px solid {colors.neutral-border}"
  sidebar-dark:
    backgroundColor: "#0f0f10"
    borderRight: "1px solid {colors.neutral-border-dark}"
---

# Design System: Filament Inventory

## Overview

**Creative North Star: "The Operations Deck"**

This is a command center for warehouse operations — scanability, trust, and speed over expression. The design recedes so data and actions lead. Every pixel serves the operator recording a movement, the manager checking reorder points, the admin configuring the system.

Clinical Blue Authority anchors the brand: Filament Blue is the single institutional color, used sparingly for primary actions and focus states. Semantic colors (Success green, Danger red, Warning amber) are pure signal — they never decorate, only communicate state. The rest is neutral: white surfaces, gray borders, charcoal text. No gradients, no illustrations, no decorative accents.

**Key Characteristics:**
- Single accent color (Filament Blue) — ≤10% of any screen
- Semantic colors are signal, never decoration
- Filament defaults everywhere — no custom component library to maintain
- Flat surfaces at rest; elevation only on interaction (hover, focus, modal)
- Instrument Sans throughout — one font, clear hierarchy
- Density serves the floor: compact tables, scannable forms, fast modals

## Colors

Clinical Blue Authority: one institutional blue, three semantic signals, a disciplined neutral scale.

### Primary
- **Operational Blue** (#3b82f6): Primary actions (Receive, Ship, Transfer, Save), focus rings, active navigation, links. The only non-semantic color in the system.

### Semantic Signals
- **Verdant Success** (#16a34a): Positive quantity, incoming movements (Receive, Transfer In), completed actions, "in stock" states.
- **Signal Danger** (#dc2626): Negative quantity, outgoing movements (Ship, Transfer Out), errors, insufficient stock, low-stock alerts.
- **Caution Amber** (#d97706): Adjustments, pending states, warnings, filters with unsaved changes.

### Neutral
- **Paper White** (#ffffff): Page background, card surfaces, modal backgrounds, table rows.
- **Whisper Gray** (#fafafa): Header bars, filter forms, hovered table rows, disabled inputs.
- **Border Zinc** (#e4e4e7): Table dividers, input borders, card edges, sidebar divider.
- **Border Zinc Dark** (#18181b): Sidebar divider in dark mode.
- **Charcoal** (#18181b): Primary text, headings, labels.
- **Muted Zinc** (#71717a): Secondary text, placeholder, disabled text, table header labels, badge text (uppercase).

### Named Rules

**The One Voice Rule.** Operational Blue appears on ≤10% of any screen. Its rarity is the point — it marks the next action.

**The Signal Purity Rule.** Success, Danger, Warning are never used for decoration. They appear only on: quantity badges, movement type badges, row highlights for low stock, notification toasts, validation states. A green badge means "stock increased"; it never means "this button looks nice."

**The Neutral Discipline Rule.** No custom grays. The neutral scale is Filament's default (Zinc). If a new neutral is needed, it comes from Zinc-50 through Zinc-950 — no hex inventions.

## Typography

**Display Font:** Instrument Sans (with ui-sans-serif, system-ui fallback)  
**Body Font:** Instrument Sans (with ui-sans-serif, system-ui fallback)  
**Label/Mono Font:** Instrument Sans (same family, weight/transform differentiate)

**Character:** Instrument Sans is geometric, open, highly legible at small sizes — chosen for warehouse floor scanning. One family eliminates font-loading flicker and keeps the bundle lean. Hierarchy is carried by weight, size, and case, not font switching.

### Hierarchy

- **Display** (600, clamp(1.875rem, 4vw, 2.25rem), 1.2, -0.02em): Page titles only (List Products, List Stock Movements). Never in tables or forms.
- **Headline** (600, 1.5rem, 1.3, -0.01em): Section headings within pages (rare — Filament uses Title for most).
- **Title** (600, 1.125rem, 1.4): Resource headers, modal titles, widget headings.
- **Body** (400, 0.875rem, 1.5): Table cells, form inputs, help text, notifications, all reading text. Max line length ~75ch in wide containers.
- **Label** (500, 0.75rem, 1.5, 0.02em, uppercase): Table headers, badge text, filter labels, button text, navigation items. Uppercase + tracking creates scanability at density.

### Named Rules

**The Single Family Rule.** No second font. If monospace is needed for SKUs or codes, use `font-mono` utility (system mono) — not a new family.

**The Uppercase Label Rule.** All column headers, filter labels, and button text are Label style (uppercase, tracked). This is the visual rhythm of the system.

## Layout

Filament's SPA shell: fixed sidebar (280px), top header (64px), content area with max-width container (1280px). Tables expand to fill; forms center at 640px max. Spacing rhythm is 8px base (Tailwind 2) — sm=8, md=16, lg=24, xl=32.

**Grid:** No custom grid. Tables are single-column full-width. Forms use Filament's Schema grid (2-column default, `columnSpanFull` for wide fields). Dashboard uses Filament's widget grid (1/2/3 columns responsive).

**Breakpoints:** Tailwind defaults (sm 640, md 768, lg 1024, xl 1280, 2xl 1536). Sidebar collapses to icon-only at lg (Filament's `sidebarCollapsibleOnDesktop`). Tables stack columns on mobile via Filament's responsive behavior.

**Density:** Compact by default. Table row padding 8px vertical, 12px horizontal. Form field gap 16px. Modal padding 24px. No "comfortable" density option — operators work fast.

## Elevation & Depth

Flat by Default, Lift on Interaction. Surfaces (cards, tables, sidebar, header) are flat at rest — no box-shadow. Elevation appears only as response to state:

- **Hover lift:** Table rows → `neutral-surface` background (no shadow)
- **Focus ring:** Inputs, buttons, selects → 3px `primary-muted` ring (no shadow)
- **Modal elevation:** `0 25px 50px -12px rgba(0,0,0,0.15)` — the only true shadow in the system
- **Dropdown elevation:** `0 10px 15px -3px rgba(0,0,0,0.1)` — select menus, action menus, date pickers
- **Sidebar:** Border-only divider (1px `neutral-border`); no shadow

### Shadow Vocabulary

- **Modal Lift** (`0 25px 50px -12px rgba(0,0,0,0.15)`): Action modals (Receive, Ship, Transfer, Adjustment), confirmation dialogs, profile menu.
- **Dropdown Lift** (`0 10px 15px -3px rgba(0,0,0,0.1)`): Select options, filter menus, date picker popover, notification dropdown.
- **Tooltip Lift** (`0 4px 6px -1px rgba(0,0,0,0.1)`): Icon tooltips, truncated cell tooltips.

### Named Rules

**The Flat-By-Default Rule.** Cards, table containers, sidebar, header — no shadow at rest. Depth is conveyed by border (1px) and background contrast (white vs. `neutral-surface`).

**The Interaction-Only Elevation Rule.** Shadows appear only on: modals, dropdowns, tooltips. Never on static cards, never on table rows, never on buttons at rest.

## Shapes

Filament's default radius scale (Tailwind v4): sm=4px, md=6px, lg=8px, xl=12px. Applied consistently:

- **Inputs/Selects/Buttons:** md (6px) — tactile but not pill-shaped
- **Badges:** sm (4px) — tight to text
- **Cards/Modals:** lg (8px) — visible but not rounded-rectangle
- **Tables:** no radius on rows; container has lg (8px) via card wrapper
- **Sidebar/Top bar:** no radius

Borders: 1px solid `neutral-border` on cards, inputs, selects, table dividers, sidebar. No double borders, no gradient borders.

### Named Rules

**The Consistent Radius Rule.** Four radii, four jobs. No ad-hoc `rounded-full`, no `rounded-none` on interactive elements. Buttons are never pills; badges are never square.

## Components

All components are Filament v5 defaults. No custom component library exists. This section documents the Filament primitives as they appear in this application.

### Buttons
- **Shape:** md radius (6px), 40px height, 16px horizontal padding
- **Primary:** Operational Blue background, white text. Hover → Primary Deep. Focus → 3px Primary Muted ring.
- **Secondary:** Whisper Gray background, Charcoal text, Border Zinc border. Hover → Border Zinc background.
- **Danger:** Signal Danger background, white text. Hover → darker Danger.
- **Ghost (text-only):** No background, Charcoal text, hover → Whisper Gray background.
- **Icon buttons:** 40px square, md radius, same color logic.

### Chips / Badges
- **Style:** sm radius (4px), 8px horizontal / 2px vertical padding, Label typography (uppercase, tracked)
- **Success:** Verdant Success text on Success Muted background — used for Receive, Transfer In, positive quantity
- **Danger:** Signal Danger text on Danger Muted background — used for Ship, Transfer Out, negative quantity, low stock
- **Warning:** Caution Amber text on Warning Muted background — used for Adjustment
- **Gray:** Muted Zinc text on Gray Muted background — used for zero quantity, neutral states

### Cards / Containers
- **Corner Style:** lg radius (8px)
- **Background:** Paper White
- **Border:** 1px Border Zinc
- **Shadow:** None at rest (Flat-By-Default Rule)
- **Internal Padding:** md (16px) for content; lg (24px) for modals
- **Header:** Border Zinc bottom divider, Title typography

### Inputs / Fields
- **Style:** md radius (6px), 40px height, 12px horizontal / 8px vertical padding, Border Zinc border, Paper White background
- **Focus:** Operational Blue border + 3px Primary Muted ring (no shadow)
- **Error:** Signal Danger border + 3px Danger Muted ring, helper text in Signal Danger
- **Disabled:** Whisper Gray background, Muted Zinc text, no focus ring
- **Select:** Same as input, with chevron icon (Filament default)

### Tables
- **Header:** Whisper Gray background, Label typography (uppercase, tracked), Border Zinc bottom border
- **Row:** Paper White, 1px Border Zinc bottom divider, Body typography
- **Hover:** Whisper Gray background (no shadow)
- **Sorted column:** Subtle Operational Blue indicator on header
- **Selected row:** Primary Muted background tint (Filament default)
- **Low-stock row highlight:** Danger Muted background (`bg-danger-50` via `recordClasses`)

### Navigation
- **Sidebar:** Paper White, 280px (expanded) / 72px (collapsed), 1px Border Zinc right divider
- **Items:** Body typography, 12px vertical / 12px horizontal padding, Charcoal text, hover → Whisper Gray background
- **Active item:** Operational Blue text + 3px left accent border (Filament default)
- **Group labels:** Label typography (uppercase, tracked), Muted Zinc text
- **Dark mode:** Sidebar Dark background (#0f0f10), Border Zinc Dark divider

### Action Modals (Signature Pattern)
Four modal forms (Receive, Ship, Transfer, Adjustment) — identical structure:
- **Size:** 640px max-width, vertically centered
- **Header:** Title (modal title), Icon (Heroicon), Close (X)
- **Body:** 2-column Schema grid (Product + Warehouse selects full-width on mobile), Quantity + Reference
- **Footer:** Cancel (Ghost) + Submit (Primary)
- **Validation:** Inline on blur, submit disabled until valid
- **Success:** Toast notification (Success/Warning/Danger per action)

## Do's and Don'ts

### Do:
- **Do** use Operational Blue only for primary actions and focus states — One Voice Rule
- **Do** use semantic colors (Success/Danger/Warning) only for quantity, movement type, validation, and alerts — Signal Purity Rule
- **Do** use Filament defaults for every component — no custom button, card, input, or table classes
- **Do** keep tables dense: 8px vertical row padding, no zebra striping, hover only
- **Do** use Label style (uppercase, tracked) for all column headers, filter labels, button text
- **Do** let Filament handle responsive behavior — no custom breakpoints
- **Do** use `recordClasses` for row-level state (low stock → `bg-danger-50`)
- **Do** use `->color()` closures on TextColumn for cell-level semantic color

### Don't:
- **Don't** introduce a second accent color — the palette is locked
- **Don't** use Success/Danger/Warning for decorative purposes (button variants, card headers, backgrounds)
- **Don't** add shadows to cards, table containers, or static surfaces — Flat-By-Default Rule
- **Don't** create custom radius values — Four radii, four jobs
- **Don't** add a second font family — Single Family Rule
- **Don't** use sentence-case for labels — Uppercase Label Rule
- **Don't** override Filament's default spacing scale — 8px base rhythm
- **Don't** add decorative illustrations, patterns, or gradients — Invisible Utility philosophy