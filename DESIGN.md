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

# Filament Inventory — Complete Design System

**Version:** 2.0 (September 2026)  
**Framework:** Laravel 13 + FilamentPHP v5 + Livewire v4 + Tailwind CSS v3  
**Status:** Production-Ready

---

## Table of Contents

1. [Design Philosophy](#design-philosophy)
2. [Design Vision: The Operations Deck](#design-vision-the-operations-deck)
3. [Colors](#colors)
4. [Typography](#typography)
5. [Layout & Responsive Patterns](#layout--responsive-patterns)
6. [Shapes & Elevation](#shapes--elevation)
7. [Component Specifications](#component-specifications)
8. [Role-Based UI Patterns](#role-based-ui-patterns)
9. [Dashboard Design (Phase 13 Glassmorphism)](#dashboard-design-phase-13-glassmorphism)
10. [Filament Resources & Customization](#filament-resources--customization)
11. [Livewire Component Patterns](#livewire-component-patterns)
12. [Accessibility & Inclusivity](#accessibility--inclusivity)
13. [Animation & Interaction](#animation--interaction)
14. [Dark Mode Support](#dark-mode-support)
15. [Performance Considerations](#performance-considerations)
16. [Do's and Don'ts](#dos-and-donts)

---

## Design Philosophy

### Core Principles

Our design system is built on these foundational principles:

1. **Operator-First Clarity**: Every UI element serves the person recording movements, checking reorder points, or managing warehouses. The design recedes so data and actions lead.
2. **Clinical Authority**: Filament Blue is institutional—used sparingly for primary actions and focus states only. Semantic colors (Success, Danger, Warning) communicate state exclusively; never decoration.
3. **Role-Scoped Precision**: UI adapts to user role, hiding sensitive data and irrelevant controls from lower-privilege users.
4. **Performance-First Aesthetics**: Visual effects (blur, shadows, transitions) are performant and never block user interactions.
5. **Consistency Across Surfaces**: Filament admin panels, Livewire components, and action modals share a unified design language.
6. **Data Integrity Through Design**: UI prevents accidental data loss and guides users toward correct actions through clear affordances.

### Design Goals

- **Reduce Cognitive Load**: Clear visual hierarchy and consistent patterns make navigation intuitive for warehouse floor staff and managers alike.
- **Maintain Trust**: Transparent data scoping and explicit permission indicators build confidence in role-based controls.
- **Support Scale**: Design system scales from 1–10,000+ inventory items across multiple warehouses without degradation.
- **Enable Speed**: Optimized caching, lazy loading, and efficient queries ensure sub-second interactions across all workflows.
- **Serve the Floor**: Compact tables, scannable forms, fast modals — operators work fast.

---

## Design Vision: The Operations Deck

**Creative North Star: "The Operations Deck"**

This is a command center for warehouse operations — scanability, trust, and speed over expression. The design recedes so data and actions lead. Every pixel serves the operator recording a movement, the manager checking reorder points, the admin configuring the system.

**Key Characteristics:**
- Single accent color (Filament Blue) — ≤10% of any screen
- Semantic colors are signal, never decoration
- Filament defaults everywhere — no custom component library to maintain
- Flat surfaces at rest; elevation only on interaction (hover, focus, modal)
- Instrument Sans throughout — one font, clear hierarchy
- Density serves the floor: compact tables, scannable forms, fast modals

---

## Colors

Clinical Blue Authority: one institutional blue, three semantic signals, a disciplined neutral scale.

### Primary Palette

| Name | Hex | RGB | Usage |
|------|-----|-----|-------|
| **Operational Blue** | `#3b82f6` | 59, 130, 246 | Primary actions (Receive, Ship, Transfer, Save), focus rings, active navigation, links |
| **Primary Deep** | `#2563eb` | 37, 99, 235 | Button hover states, link active states |
| **Primary Muted** | `#dbeafe` | 219, 234, 254 | Focus rings, subtle backgrounds, low-priority hints |

### Semantic Palette

| Name | Hex | RGB | Usage |
|------|-----|-----|-------|
| **Verdant Success** | `#16a34a` | 22, 163, 74 | Positive quantity, incoming movements (Receive, Transfer In), completed actions, "in stock" states |
| **Success Muted** | `#dcfce7` | 220, 252, 231 | Success badge background |
| **Signal Danger** | `#dc2626` | 220, 38, 38 | Negative quantity, outgoing movements (Ship, Transfer Out), errors, insufficient stock, low-stock alerts |
| **Danger Muted** | `#fee2e2` | 254, 226, 226 | Danger badge background, low-stock row highlight |
| **Caution Amber** | `#d97706` | 217, 119, 6 | Adjustments, pending states, in-transit items, warnings |
| **Warning Muted** | `#fef3c7` | 254, 243, 199 | Warning badge background |

### Neutral Palette

| Name | Hex | RGB | Usage |
|------|-----|-----|-------|
| **Paper White** | `#ffffff` | 255, 255, 255 | Page background, card surfaces, modal backgrounds, table rows |
| **Whisper Gray** | `#fafafa` | 250, 250, 250 | Header bars, filter forms, hovered table rows, disabled inputs |
| **Border Zinc** | `#e4e4e7` | 228, 228, 231 | Table dividers, input borders, card edges, sidebar divider |
| **Border Zinc Dark** | `#18181b` | 24, 24, 27 | Sidebar divider in dark mode |
| **Charcoal** | `#18181b` | 24, 24, 27 | Primary text, headings, labels |
| **Muted Zinc** | `#71717a` | 113, 113, 122 | Secondary text, placeholder, disabled text, table header labels |

### Color Application Rules

**The One Voice Rule**  
Operational Blue appears on ≤10% of any screen. Its rarity is the point—it marks the next action. Every blue element draws attention; use sparingly.

**The Signal Purity Rule**  
Success, Danger, Warning are never used for decoration. They appear only on:
- Quantity badges (green = stock increased, red = stock decreased)
- Movement type badges (green = Receive, red = Ship)
- Row highlights for low stock (danger muted background)
- Notification toasts
- Validation states (success, error, warning)

A green badge means "stock increased"; it never means "this button looks nice."

**The Neutral Discipline Rule**  
No custom grays. The neutral scale is Filament's default (Zinc 50–950). If a new neutral is needed, it comes from the Zinc scale—no hex inventions.

---

## Typography

**Display Font:** Instrument Sans (with ui-sans-serif, system-ui fallback)  
**Body Font:** Instrument Sans (with ui-sans-serif, system-ui fallback)  
**Label/Mono Font:** Instrument Sans (same family, weight/transform differentiate)

**Character:** Instrument Sans is geometric, open, highly legible at small sizes—chosen for warehouse floor scanning. One family eliminates font-loading flicker and keeps the bundle lean. Hierarchy is carried by weight, size, and case, not font switching.

### Typography Scale

| Level | Size | Weight | Line Height | Letter Spacing | Usage |
|-------|------|--------|-------------|-----------------|-------|
| **Display** | clamp(1.875rem, 4vw, 2.25rem) | 600 | 1.2 | -0.02em | Page titles only (List Products, List Stock Movements) |
| **Headline** | 1.5rem | 600 | 1.3 | -0.01em | Section headings within pages |
| **Title** | 1.125rem | 600 | 1.4 | 0 | Resource headers, modal titles, widget headings |
| **Body** | 0.875rem | 400 | 1.5 | 0 | Table cells, form inputs, help text, notifications, reading text |
| **Label** | 0.75rem | 500 | 1.5 | 0.02em (uppercase) | Table headers, badge text, filter labels, button text, nav items |

### Hierarchy in Practice

```html
<!-- Display: Page heading -->
<h1 class="text-3xl font-bold tracking-tight">Stock Movements</h1>

<!-- Title: Modal or widget heading -->
<h2 class="text-lg font-semibold">Receive Stock</h2>

<!-- Label: All caps for UI chrome -->
<label class="text-xs font-medium uppercase tracking-wider">WAREHOUSE</label>

<!-- Body: Reading text -->
<p class="text-sm text-gray-600">Choose the warehouse to receive stock into.</p>
```

### Named Rules

**The Single Family Rule**  
No second font. If monospace is needed for SKUs or codes, use `font-mono` utility (system mono)—not a new family.

**The Uppercase Label Rule**  
All column headers, filter labels, and button text are Label style (uppercase, tracked). This is the visual rhythm of the system.

---

## Layout & Responsive Patterns

### Container & Grid System

Filament's SPA shell provides:
- Fixed sidebar (280px expanded, 72px collapsed)
- Top header (64px)
- Content area with max-width container (1280px)

**Spacing Rhythm:** 8px base (Tailwind v3)
```
xs: 2px,   sm: 4px,   base: 8px,   md: 12px,
lg: 16px,  xl: 24px,  2xl: 32px,   3xl: 48px,
4xl: 64px, 5xl: 96px
```

All padding, margins, and gaps use multiples of 4px (Tailwind defaults).

### Breakpoint Strategy

```
Default (mobile-first):  < 640px   (1 column, full-width)
SM (small tablet):       640px+    (2 columns)
MD (tablet):             768px+    (2-3 columns)
LG (desktop):            1024px+   (4 columns, sidebar)
XL (wide desktop):       1280px+   (4 columns, wider sidebar)
2XL (ultrawide):         1536px+   (5 columns, fixed sidebar)
```

### Component Layout Patterns

**Table Layout (Single Column, Full-Width)**
- Tables expand to fill container
- Dense: 8px vertical row padding, 12px horizontal
- No zebra striping; hover states only

**Form Layout (2-Column, Centered)**
- Forms center at 640px max
- Schema grid: 2-column default on desktop, 1-column on mobile
- `columnSpanFull` for wide fields (full width)

**Dashboard Layout (Asymmetrical Bento)**
- Mobile: 1 column
- Tablet (sm): 2 columns
- Desktop (lg): 4 columns
- Cards span different footprints (1, 2, or 4 columns)

**Sidebar Navigation (Responsive)**
- Desktop (lg+): Fixed left sidebar, ~280px width
- Tablet (md-lg): Collapsible sidebar, slides in/out
- Mobile (sm): Full-screen slide-out drawer (hamburger menu)

---

## Shapes & Elevation

### Border Radius Scale

| Size | Value | Usage |
|------|-------|-------|
| **None** | 0px | Squared elements |
| **XS (sm)** | 4px | Icon buttons, minimal rounding, badges |
| **SM (md)** | 6px | Form inputs, buttons, small components, standard interactions |
| **MD (lg)** | 8px | Cards, modals, table containers, widgets |
| **LG (xl)** | 12px | Feature cards, large modals, primary containers |

Applied consistently across:
- **Inputs/Selects/Buttons:** md (6px) — tactile but not pill-shaped
- **Badges:** sm (4px) — tight to text
- **Cards/Modals:** lg (8px) — visible but not rounded-rectangle
- **Tables:** lg (8px) via card wrapper
- **Sidebar/Top bar:** no radius

Borders: 1px solid `neutral-border` on cards, inputs, selects, table dividers, sidebar. No double borders, no gradient borders.

### Elevation & Depth Strategy

**Flat by Default, Lift on Interaction.** Surfaces (cards, tables, sidebar, header) are flat at rest—no box-shadow. Elevation appears only as response to state:

| Level | Shadow | Usage |
|-------|--------|-------|
| **Flat** | None | Cards, tables, sidebar, header at rest |
| **Hover** | None (background change only) | Table rows → `neutral-surface` background |
| **Focus** | 3px `primary-muted` ring | Inputs, buttons, selects |
| **Modal Lift** | `0 25px 50px -12px rgba(0,0,0,0.15)` | Action modals, confirmation dialogs |
| **Dropdown Lift** | `0 10px 15px -3px rgba(0,0,0,0.1)` | Select options, filter menus, date pickers |
| **Tooltip Lift** | `0 4px 6px -1px rgba(0,0,0,0.1)` | Icon tooltips, truncated cell tooltips |

### Named Rules

**The Flat-By-Default Rule**  
Cards, table containers, sidebar, header—no shadow at rest. Depth is conveyed by border (1px) and background contrast (white vs. `neutral-surface`).

**The Interaction-Only Elevation Rule**  
Shadows appear only on: modals, dropdowns, tooltips. Never on static cards, never on table rows, never on buttons at rest.

**The Consistent Radius Rule**  
Four radii, four jobs. No ad-hoc `rounded-full`, no `rounded-none` on interactive elements. Buttons are never pills; badges are never square.

---

## Component Specifications

All components are Filament v5 defaults. No custom component library exists. This section documents Filament primitives as they appear in this application.

### Buttons

**Primary Button**
```
Background: Operational Blue (#3b82f6)
Text Color: Paper White
Border Radius: 6px (md)
Height: 40px
Padding: 8px horizontal, 16px vertical
Typography: Label (uppercase, tracked)
Hover: Primary Deep (#2563eb)
Focus: 3px Primary Muted ring + Primary Deep background
```

**Secondary Button**
```
Background: Whisper Gray (#fafafa)
Text Color: Charcoal (#18181b)
Border: 1px Border Zinc (#e4e4e7)
Border Radius: 6px (md)
Height: 40px
Hover: Border Zinc background
Focus: 3px Primary Muted ring
```

**Danger Button**
```
Background: Signal Danger (#dc2626)
Text Color: Paper White
Border Radius: 6px (md)
Height: 40px
Hover: Darker Danger shade
Focus: 3px Danger Muted ring
Requires Confirmation: Always (modal confirmation)
```

**Ghost / Text Button**
```
Background: None
Text Color: Charcoal (#18181b)
Hover: Whisper Gray background
Focus: 3px Primary Muted ring
No padding required
```

**Icon Buttons**
```
Size: 40px square
Border Radius: 6px (md)
Same color logic as text buttons
Accessible label required: aria-label="Close dialog"
```

### Chips & Badges

| Type | Background | Text | Radius | Padding | Usage |
|------|------------|------|--------|---------|-------|
| **Success** | Success Muted (`#dcfce7`) | Verdant Success (`#16a34a`) | 4px (sm) | 8px H / 2px V | Receive, Transfer In, positive quantity |
| **Danger** | Danger Muted (`#fee2e2`) | Signal Danger (`#dc2626`) | 4px (sm) | 8px H / 2px V | Ship, Transfer Out, negative quantity, low stock |
| **Warning** | Warning Muted (`#fef3c7`) | Caution Amber (`#d97706`) | 4px (sm) | 8px H / 2px V | Adjustment, pending states, in-transit |
| **Gray** | Gray Muted (`#f4f4f5`) | Muted Zinc (`#71717a`) | 4px (sm) | 8px H / 2px V | Zero quantity, neutral states |

Typography: Label (uppercase, tracked, 0.75rem)

### Cards & Containers

```
Border Radius: 8px (lg)
Background: Paper White (#ffffff)
Border: 1px Border Zinc (#e4e4e7)
Shadow: None at rest (Flat-By-Default Rule)
Internal Padding: 16px (md) for content; 24px (lg) for modals
Header: Border Zinc bottom divider, Title typography
Hover: Subtle background or border change (no shadow)
```

### Input Fields & Selects

**Base Style**
```
Background: Paper White (#ffffff)
Text Color: Charcoal (#18181b)
Border Radius: 6px (md)
Height: 40px
Padding: 8px vertical, 12px horizontal
Border: 1px Border Zinc (#e4e4e7)
Typography: Body (0.875rem, 400)
```

**Focus State**
```
Border: 1px Operational Blue (#3b82f6)
Ring: 3px Primary Muted (#dbeafe) box-shadow
Shadow: None (ring only)
```

**Error State**
```
Border: 1px Signal Danger (#dc2626)
Ring: 3px Danger Muted (#fee2e2)
Helper Text: Signal Danger color, Body typography
```

**Disabled State**
```
Background: Whisper Gray (#fafafa)
Text Color: Muted Zinc (#71717a)
Border: 1px Neutral Border (grayed out)
No focus ring
Cursor: not-allowed
```

**Select & Dropdowns**
```
Same as input
Chevron icon: Muted Zinc color, right-aligned
Option hover: Whisper Gray background
Active option: Operational Blue text + background tint
```

### Tables

| Element | Styling |
|---------|---------|
| **Header** | Whisper Gray background (#fafafa), Label typography (uppercase, tracked), Border Zinc bottom border (1px) |
| **Row** | Paper White, 1px Border Zinc bottom divider, Body typography |
| **Row Padding** | 8px vertical, 12px horizontal (compact density) |
| **Hover** | Whisper Gray background (no shadow) |
| **Sorted Column** | Subtle Operational Blue indicator on header |
| **Selected Row** | Primary Muted background tint |
| **Low-Stock Row** | Danger Muted background highlight (`bg-danger-50` via `recordClasses`) |

### Navigation

**Sidebar**
```
Background: Paper White (#ffffff)
Width: 280px (expanded) / 72px (collapsed)
Border Right: 1px Border Zinc (#e4e4e7)
Padding: 12px
```

**Sidebar Items**
```
Typography: Body (0.875rem, 400)
Padding: 12px vertical, 12px horizontal
Text Color: Charcoal (#18181b)
Hover: Whisper Gray background
Active: Operational Blue text + 3px left accent border
```

**Sidebar Group Labels**
```
Typography: Label (uppercase, tracked)
Text Color: Muted Zinc (#71717a)
Hover: No change (non-interactive)
```

**Sidebar Dark Mode**
```
Background: #0f0f10
Border Right: 1px Border Zinc Dark (#18181b)
Text: Lighter gray (auto-inverted by Filament)
```

### Action Modals (Signature Pattern)

Four modal forms (Receive, Ship, Transfer, Adjustment) follow identical structure:

**Layout**
```
Size: 640px max-width, vertically centered
Header: Title + Icon + Close (X)
Body: 2-column Schema grid
Footer: Cancel (Ghost) + Submit (Primary)
```

**Header**
```
Icon: Heroicon matching action (Package for Receive, Truck for Transfer)
Title: Action name (e.g., "Receive Stock")
Close: X icon button (top-right, Ghost style)
Border Bottom: 1px Border Zinc divider
```

**Body**
```
Product Select: Full-width (sm: columnSpan 2, mobile: columnSpan 1)
Warehouse Select: Full-width on mobile, beside Product on desktop
Quantity: 1 column on desktop, 2 columns on mobile
Reference: 1 column on desktop, 2 columns on mobile
Validation: Inline on blur, errors in Signal Danger
```

**Footer**
```
Cancel: Ghost button (text-only, exits without save)
Submit: Primary button (disabled until valid)
Spacing: 16px gap between buttons, right-aligned
```

**Success Feedback**
```
Toast notification: Success/Warning/Danger per action
Duration: 5 seconds auto-dismiss
Action: Link to related resource if applicable
```

---

## Role-Based UI Patterns

### Role Hierarchy

```
Admin (Full access, data masking disabled)
  ├── Can view all warehouses and cross-warehouse analytics
  ├── Can view sensitive financial metrics (losses, valuations)
  └── Can manage users, roles, and policies

Auditor (Read-only access to all data)
  ├── Can view all warehouses and cross-warehouse analytics
  ├── Can view sensitive financial metrics
  └── Cannot edit or delete data

Branch Manager (Warehouse-scoped access)
  ├── Can view only assigned warehouse(s)
  ├── Cannot view sensitive financial metrics
  └── Can manage requisitions within assigned warehouse(s)

Staff (Basic, transaction-scoped access)
  ├── Can view only assigned warehouse(s)
  ├── Cannot view sensitive financial metrics
  └── Can only view and fulfill their assigned tasks
```

### Data Masking Implementation

#### Pattern 1: Hide Element Based on Role
```php
@if(auth()->user()->hasRole(['admin', 'auditor']))
    <div class="warehouse-filter">
        <!-- Filter by warehouse dropdown -->
    </div>
@endif
```

#### Pattern 2: Scope Query to User Warehouses
```php
protected function getTableQuery(): Builder
{
    $user = auth()->user();
    $query = Inventory::query();
    
    if (!$user->hasRole(['admin', 'auditor'])) {
        $warehouseIds = $user->warehouses()->pluck('id');
        $query->whereIn('warehouse_id', $warehouseIds);
    }
    
    return $query;
}
```

#### Pattern 3: Return Empty/Null for Sensitive Cards
```php
// In stat widget, lower roles see nothing (not "N/A")
if (!auth()->user()->hasRole(['admin', 'auditor'])) {
    return null; // Card is completely hidden
}

// Render 30-day loss metric only for admin/auditor
```

### UI Affordances for Restricted Content

When a lower-role user encounters restricted content:

1. **Silent Hide** (preferred): Content is invisible, no placeholder
2. **Placeholder with Tooltip** (if needed): Gray box with lock icon + "Not available for your role"
3. **Disabled Control**: Button/field is grayed out with `disabled` attribute and hover tooltip

Never show "Access Denied" or "Unauthorized" messages (reveals sensitive data existence).

---

## Dashboard Design (Phase 13 Glassmorphism)

### Overview

Phase 13 introduces a stunning, modern **Glassmorphic Bento Grid Dashboard** that maintains Clinical Blue Authority while adding depth and visual interest through frosted glass effects.

### Layout Specification

**Page Structure:**
```
┌─────────────────────────────────────────────┐
│ Filament Header (Logo, User Menu)           │
├─────────────────────────────────────────────┤
│ [Warehouse Filter] (Admin/Auditor only)     │
├─────────────────────────────────────────────┤
│  ┌─────┐  ┌──────────┐  ┌─────┐  ┌─────┐  │
│  │  1  │  │    2     │  │  3  │  │  4  │  │
│  └─────┘  └──────────┘  └─────┘  └─────┘  │
│                                            │
│  ┌─────────────────────────────────────┐  │
│  │   Pending Requisitions (4 cols)     │  │
│  └─────────────────────────────────────┘  │
└─────────────────────────────────────────────┘
```

### Bento Grid Layout

| Card | Columns | All Roles | Role Restriction | Content |
|------|---------|-----------|------------------|---------|
| **On-Hand Stock** | 1 | ✅ | None | Total units, sparkline, blue accent |
| **In-Transit Stock** | 2 (desktop), 1 (mobile) | ✅ | None | Total units, sparkline, amber accent |
| **30-Day Material Loss** | 1 | ❌ Admin/Auditor only | Hidden from Branch Manager/Staff | Loss value ($X,XXX.XXXX), sparkline, red accent |
| **Inventory Health** | 1 | ✅ | None | Accuracy %, sparkline, green accent |
| **Pending Requisitions** | 4 (full width) | ✅ | Warehouse-scoped for non-admin | Wide table with status, actions |

### Glassmorphic Styling

Each stat card uses frosted glass effect with:

**CSS Foundation (Light Mode)**
```css
.glass-panel {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(24px) saturate(120%);
    -webkit-backdrop-filter: blur(24px) saturate(120%);
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.05);
}
```

**CSS Foundation (Dark Mode)**
```css
.dark .glass-panel {
    background: rgba(10, 10, 10, 0.15);
    backdrop-filter: blur(24px) saturate(140%);
    -webkit-backdrop-filter: blur(24px) saturate(140%);
    border: 1px solid rgba(255, 255, 255, 0.04);
    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.15);
}
```

**Hover & Interaction**
```css
.glass-panel {
    transition: all 300ms ease-out;
}

.glass-panel:hover {
    transform: scale(1.01) translateY(-2px);
    box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.08);
}

@media (prefers-reduced-motion: reduce) {
    .glass-panel {
        transition: none;
    }
    .glass-panel:hover {
        transform: none;
    }
}
```

**Accent Variant (Error/Loss Cards)**
```css
.glass-accent-red {
    background: rgba(239, 68, 68, 0.04);
    border: 1px solid rgba(239, 68, 68, 0.15);
    box-shadow: 0 8px 32px 0 rgba(239, 68, 68, 0.05);
}
```

### Stat Card Structure

```html
<div class="glass-panel p-6 rounded-xl transition-all duration-300 
            hover:scale-[1.01] hover:translate-y-[-2px] hover:shadow-2xl">
  <div class="flex items-start justify-between">
    <div>
      <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
        On-Hand Stock
      </p>
      <h3 class="text-3xl font-bold text-gray-900 dark:text-gray-50 mt-2">
        12,547
      </h3>
    </div>
    <div class="text-blue-500 dark:text-blue-400">
      <IconPackage class="w-8 h-8" />
    </div>
  </div>
  
  <!-- Sparkline footer -->
  <div class="mt-4 pt-4 border-t border-white/10 dark:border-white/5">
    <div class="h-12">
      <!-- Recharts sparkline component -->
    </div>
  </div>
</div>
```

### Dashboard Data Masking & Caching

**Role-Based Warehouse Filter (Admin/Auditor Only)**
```html
<form class="mb-6 p-4 bg-slate-50 dark:bg-slate-900/50 rounded-lg">
  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
    Filter by Warehouse
  </label>
  
  <select class="w-full px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 
                 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-50">
    <option value="">All Warehouses</option>
    <option value="1">Portland, OR</option>
    <option value="2">Seattle, WA</option>
    <option value="3">San Francisco, CA</option>
  </select>
  
  <button class="mt-3 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md 
                 transition-colors duration-200">
    Apply Filter
  </button>
</form>
```

**5-Minute Cache Implementation**
```php
$cacheKey = 'stats_overview_' . ($warehouseIds ? implode('_', sort($warehouseIds)) : 'all');
$onHandStock = Cache::remember($cacheKey . '_on_hand', 300, function () {
    return Inventory::whereIn('warehouse_id', $warehouseIds)->sum('quantity');
});
```

**4-Decimal Financial Precision**
```php
// All financial metrics rendered with 4-decimal places
$lossValue = $writeOffTotal;
echo formatNumber($lossValue, 4); // Output: $1,250.0150
```

---

## Filament Resources & Customization

### Resource Structure

Each resource (Inventory, Warehouse, Requisition, etc.) follows this pattern:

```php
class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';
    protected static ?string $navigationLabel = 'Inventory';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Stock Information')
                ->schema([
                    Forms\Components\Select::make('warehouse_id')
                        ->label('Warehouse')
                        ->relationship('warehouse', 'name')
                        ->required(),
                    // ... additional fields
                ]),
            Section::make('Metadata')
                ->schema([
                    // Read-only fields, timestamps, etc.
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->sortable()
                    ->color(fn ($state) => $state > 100 ? 'success' : 'danger'),
                // ... additional columns
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse')
                    ->relationship('warehouse', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
```

### Relation Managers

Relation managers (viewing inventory items within a warehouse) inherit from `RelationManager`:

```php
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Item form schema
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->columns([
                Tables\Columns\TextColumn::make('sku'),
                Tables\Columns\TextColumn::make('quantity'),
                // ... additional columns
            ]);
    }
}
```

### Custom Actions

Action classes extend `Action`:

```php
class ApproveRequisitionAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'approve';
    }

    protected function setUp(): void
    {
        $this
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->action(function (Requisition $record) {
                $record->update(['status' => 'approved']);
                Notification::make()
                    ->success()
                    ->title('Requisition Approved')
                    ->send();
            });
    }
}
```

---

## Livewire Component Patterns

### Component Lifecycle

All Livewire components follow this structure:

```php
<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;

class InventorySearch extends Component
{
    public $search = '';
    public $results = [];

    #[On('warehouse-changed')]
    public function onWarehouseChanged($warehouseId)
    {
        $this->search = '';
        $this->results = [];
    }

    #[Computed]
    public function filteredResults()
    {
        return Inventory::query()
            ->where('warehouse_id', auth()->user()->warehouse_id)
            ->where('sku', 'like', "%{$this->search}%")
            ->limit(10)
            ->get();
    }

    public function selectResult($inventoryId)
    {
        $this->dispatch('inventory-selected', id: $inventoryId);
        $this->search = '';
    }

    public function render()
    {
        return view('livewire.inventory-search', [
            'results' => $this->filteredResults(),
        ]);
    }
}
```

### State Management

- **Public Properties**: Used for user-facing reactive state (searches, filters, selections)
- **Computed Properties**: Derived state that recalculates when dependencies change (use `#[Computed]`)
- **Cached Properties**: Expensive calculations cached for component lifetime (use `#[Cached]`)
- **Session Data**: Persisted across requests (warehouse selection, user preferences)

### Event Dispatch Patterns

Components communicate via events:

```php
// Dispatch from child component
$this->dispatch('requisition-approved', id: $requisitionId);

// Listen in parent component
#[On('requisition-approved')]
public function onRequisitionApproved($id)
{
    $this->refresh();
}
```

---

## Accessibility & Inclusivity

### WCAG 2.1 Level AA Compliance

All components must meet these standards:

#### Color Contrast
- **Text on background**: Minimum 4.5:1 ratio (normal text)
- **Large text**: Minimum 3:1 ratio (18pt+, 14pt+ bold)
- **UI components**: Minimum 3:1 ratio (focused/active states)

#### Keyboard Navigation
- All interactive elements accessible via Tab key
- Logical Tab order (left-to-right, top-to-bottom)
- Visible focus indicator (`:focus` state with outline or highlight)
- No keyboard traps (users can always escape using Escape key)

#### Screen Reader Support
- Semantic HTML (`<button>`, `<nav>`, `<main>`, `<article>`)
- ARIA labels for icon-only buttons: `aria-label="Close modal"`
- Form labels explicitly associated: `<label for="warehouse-select">`
- Live regions for dynamic content: `aria-live="polite"` or `aria-live="assertive"`

#### Motion & Animation
- Provide alternative static versions for animated content
- Respect `prefers-reduced-motion` media query
- Animations do not auto-play or loop indefinitely

### Implementation Examples

**Icon Button with Accessible Label:**
```html
<button 
  class="p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-800"
  aria-label="Close dialog"
  @click="open = false"
>
  <IconX class="w-5 h-5" />
</button>
```

**Form Field with Explicit Label:**
```html
<div class="form-group">
  <label for="warehouse-select" class="block text-sm font-medium mb-2">
    Select Warehouse
  </label>
  <select 
    id="warehouse-select"
    class="px-4 py-2 rounded border border-gray-300"
  >
    <option value="">Choose a warehouse...</option>
  </select>
</div>
```

**Respecting Reduced Motion:**
```css
@media (prefers-reduced-motion: reduce) {
  .glass-panel {
    transition: none;
  }
  
  .glass-panel:hover {
    transform: none;
  }
}
```

---

## Animation & Interaction

### Transition Utilities

All animations use Tailwind's timing functions:

```
duration-200: 200ms (snappy interactions)
duration-300: 300ms (smooth hover states)
duration-500: 500ms (longer modal/drawer animations)
```

### Micro-Interactions

#### Hover States
```html
<!-- Stat cards lift slightly on hover -->
<div class="transition-all duration-300 hover:scale-[1.01] hover:translate-y-[-2px] 
            hover:shadow-xl">
  ...
</div>

<!-- Buttons darken on hover -->
<button class="bg-blue-600 hover:bg-blue-700 transition-colors duration-200">
  Click me
</button>

<!-- Table rows highlight on hover -->
<tr class="hover:bg-gray-50 dark:hover:bg-gray-900/20 transition-colors">
  ...
</tr>
```

#### Focus States
```css
/* Outlined focus ring (accessible) */
:focus {
  outline: 2px solid #0ea5e9;
  outline-offset: 2px;
}

/* Or shadow-based focus (Filament default) */
:focus {
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
```

#### Loading States
```html
<!-- Skeleton loaders for async content -->
<div class="animate-pulse">
  <div class="h-12 bg-gray-300 dark:bg-gray-700 rounded"></div>
</div>

<!-- Spinner for inline operations -->
<button>
  <IconLoading class="animate-spin" />
  Processing...
</button>
```

### Page Transitions

- **Navigation between pages**: Fade out → load new page → fade in (100ms total)
- **Modal open/close**: Scale up/down + fade (300ms)
- **Dropdown menu**: Slide down + fade (150ms)
- **Drawer open/close**: Slide in/out from edge (300ms)

---

## Dark Mode Support

### Implementation Strategy

Tailwind's dark mode uses the `dark:` prefix. All components must have explicit dark mode variants:

```html
<!-- Light mode uses white bg, dark mode uses gray-900 -->
<div class="bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-50">
  Content here
</div>
```

### Color Mapping (Light → Dark)

| Light | Dark | Purpose |
|-------|------|---------|
| `#ffffff` | `#0f172a` | Page background |
| `#fafafa` | `#1e293b` | Card background |
| `#e4e4e7` | `#334155` | Border, divider |
| `#18181b` | `#f8fafc` | Primary text |
| `#71717a` | `#cbd5e1` | Secondary text |

### Dark Mode Toggle

Filament provides a built-in theme switcher. Users can toggle dark mode via:
1. User profile menu (theme preference)
2. System preference (respects `prefers-color-scheme: dark`)
3. Persistent setting (stored in database/session)

### Component-Specific Dark Mode Notes

- **Glassmorphic panels**: Darker background + brighter border in dark mode
- **Shadows**: Softer, lighter in dark mode (reduce black shadow impact)
- **Icons**: Automatically invert for contrast (use Heroicons, which support this)
- **Charts & Graphs**: Color palette adjusts (lighter shades for dark background)

---

## Performance Considerations

### Load Time Optimization

1. **CSS**: Critical path CSS inlined, non-critical deferred
2. **JavaScript**: Code-split Livewire/Filament bundles
3. **Images**: Lazy-loaded, responsive `srcset`, WebP format
4. **Fonts**: System font stack preferred (fallback to Instrument Sans via CDN, load via `font-display: swap`)

### Caching Strategy

- **Dashboard aggregates**: 300-second (5-minute) cache (warehouse-scoped key)
- **Resource data**: Soft caching via Livewire's `#[Cached]` decorator
- **Static assets**: Browser caching headers (Cache-Control: max-age=31536000)
- **API responses**: Cache-Control: private, max-age=300 (for JSON)

### Database Query Optimization

- **Eager loading**: All relationships pre-loaded via `.with()`
- **Indexing**: Warehouse ID, user ID, created_at all indexed
- **Pagination**: Default 50 rows per page (configurable)
- **N+1 prevention**: Livewire uses `#[Computed]` for derived data

### Component Rendering

- **Lazy components**: Heavy tables/modals load on-demand
- **Virtual scrolling**: Tables > 500 rows use virtual scroll library
- **Debounced searches**: 300ms debounce on search input
- **Optimistic updates**: UI responds immediately, server catches up

---

## Browser Support

### Minimum Supported Versions

- Chrome/Edge: 90+
- Firefox: 88+
- Safari: 14+
- iOS Safari: 14+
- Android Chrome: 90+

### Feature Polyfills

- CSS Grid: Native support (no polyfill needed)
- Backdrop Filter: Supported in all modern browsers; graceful fallback to solid color in older browsers
- CSS Variables: Native support

---

## Do's and Don'ts

### Do:

- **Do** use Operational Blue only for primary actions and focus states — **One Voice Rule**
- **Do** use semantic colors (Success/Danger/Warning) only for quantity, movement type, validation, and alerts — **Signal Purity Rule**
- **Do** use Filament defaults for every component — no custom button, card, input, or table classes
- **Do** keep tables dense: 8px vertical row padding, no zebra striping, hover only
- **Do** use Label style (uppercase, tracked) for all column headers, filter labels, button text
- **Do** let Filament handle responsive behavior — no custom breakpoints
- **Do** use `recordClasses` for row-level state (low stock → `bg-danger-50`)
- **Do** use `->color()` closures on TextColumn for cell-level semantic color
- **Do** respect `prefers-reduced-motion` in animations
- **Do** provide explicit `aria-label` on icon-only buttons
- **Do** use semantic HTML for all interactive elements

### Don't:

- **Don't** introduce a second accent color — the palette is locked
- **Don't** use Success/Danger/Warning for decorative purposes (button variants, card headers, backgrounds)
- **Don't** add shadows to cards, table containers, or static surfaces — **Flat-By-Default Rule**
- **Don't** create custom radius values — Four radii, four jobs
- **Don't** add a second font family — **Single Family Rule**
- **Don't** use sentence-case for labels — **Uppercase Label Rule**
- **Don't** override Filament's default spacing scale — 8px base rhythm
- **Don't** add decorative illustrations, patterns, or gradients — Invisible Utility philosophy
- **Don't** animate content by default — respect `prefers-reduced-motion`
- **Don't** hide focus indicators for accessibility
- **Don't** exceed 10% Operational Blue on any single screen

---

## Changelog

### v2.0 (September 2026)
- Integrated Phase 13 glassmorphic dashboard design
- Added comprehensive accessibility guidelines (WCAG 2.1 AA)
- Expanded role-based UI patterns with warehouse scoping
- Added Livewire component patterns and state management
- Documented 5-minute caching strategy
- Added dark mode support specifications

### v1.0 (Original Release)
- Clinical Blue Authority color system
- Filament v5 component specifications
- Typography and spacing scale
- Elevation and interaction patterns

---

## Questions or Contributions?

This design system is a living document. For updates, questions, or suggestions:

1. Open an issue in the project repository
2. Create a pull request with changes to this file
3. Discuss with the design team in Slack (#design-system)

---

**Maintained by:** Design & Engineering Team  
**Last Review:** September 2026  
**Next Review:** December 2026