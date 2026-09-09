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
**Framework:** Laravel 13 + FilamentPHP v5 + Livewire v4 + Tailwind CSS v4  
**Status:** Production-Ready & Fully Hardened

---

## 1. Design Philosophy

### Core Principles
Our design system is built on these foundational principles:
1. **Operator-First Clarity**: Every UI element serves the person recording movements, checking reorder points, or managing warehouses. The design recedes so data and actions lead.
2. **Clinical Authority**: Filament Blue is institutional—used sparingly for primary actions and focus states only. Semantic colors (Success, Danger, Warning) communicate state exclusively; never decoration.
3. **Role-Scoped Precision**: UI adapts to user role, hiding sensitive data and irrelevant controls from lower-privilege users.
4. **Performance-First Aesthetics**: Visual effects (blur, shadows, transitions) are performant and never block user interactions.
5. **Consistency Across Surfaces**: Filament admin panels, Livewire components, and action modals share a unified design language.
6. **Data Integrity Through Design**: UI prevents accidental data loss and guides users toward correct actions through clear affordances.

### Design Goals
*   **Reduce Cognitive Load**: Clear visual hierarchy and consistent patterns make navigation intuitive for warehouse floor staff and managers alike.
*   **Maintain Trust**: Transparent data scoping and explicit permission indicators build confidence in role-based controls.
*   **Support Scale**: Design system scales from 1–10,000+ inventory items across multiple warehouses without degradation.
*   **Enable Speed**: Optimized caching, lazy loading, and efficient queries ensure sub-second interactions across all workflows.
*   **Serve the Floor**: Compact tables, scannable forms, fast modals — operators work fast.

---

## 2. Design Vision: The Operations Deck

**Creative North Star: "The Operations Deck"**  
This is a command center for warehouse operations — scanability, trust, and speed over expression. The design recedes so data and actions lead. Every pixel serves the operator recording a movement, the manager checking reorder points, the admin configuring the system.

**Key Characteristics:**
*   **Single Accent Color**: Operational Blue (`#3b82f6`) occupies ≤10% of any screen.
*   **Signal Purity**: Semantic colors are signals, never decoration.
*   **Native Filament Layouts**: We utilize standard Filament v5 configurations to bypass the overhead of custom component libraries.
*   **Flat Surfaces**: Elements are flat at rest; elevation is applied only during interaction (hover, focus, modal).
*   **Unified Typography**: Instrument Sans is used throughout to establish a clear visual hierarchy and eliminate font-loading flicker.
*   **Density Serves the Floor**: Compact tables, scannable forms, fast modals — operators work fast.

---

## 3. Colors

Clinical Blue Authority: one institutional blue, three semantic signals, a disciplined neutral scale.

### Primary Palette
| Name | Hex | RGB | Usage |
| :--- | :--- | :--- | :--- |
| **Operational Blue** | `#3b82f6` | `59, 130, 246` | Primary actions (Receive, Ship, Transfer, Save), focus rings, active navigation, links |
| **Primary Deep** | `#2563eb` | `37, 99, 235` | Button hover states, link active states |
| **Primary Muted** | `#dbeafe` | `219, 234, 254` | Focus rings, subtle backgrounds, low-priority hints |

### Semantic Palette
| Name | Hex | RGB | Usage |
| :--- | :--- | :--- | :--- |
| **Verdant Success** | `#16a34a` | `22, 163, 74` | Positive quantity, incoming movements (Receive, Transfer In), completed actions, "in stock" states |
| **Success Muted** | `#dcfce7` | `220, 252, 231` | Success badge background |
| **Signal Danger** | `#dc2626` | `220, 38, 38` | Negative quantity, outgoing movements (Ship, Transfer Out), errors, insufficient stock, low-stock alerts |
| **Danger Muted** | `#fee2e2` | `254, 226, 226` | Danger badge background, low-stock row highlight |
| **Caution Amber** | `#d97706` | `217, 119, 6` | Adjustments, pending states, in-transit items, warnings |
| **Warning Muted** | `#fef3c7` | `254, 243, 199` | Warning badge background |

### Neutral Palette
| Name | Hex | RGB | Usage |
| :--- | :--- | :--- | :--- |
| **Paper White** | `#ffffff` | `255, 255, 255` | Page background, card surfaces, modal backgrounds, table rows |
| **Whisper Gray** | `#fafafa` | `250, 250, 250` | Header bars, filter forms, hovered table rows, disabled inputs |
| **Border Zinc** | `#e4e4e7` | `228, 228, 231` | Table dividers, input borders, card edges, sidebar divider |
| **Border Zinc Dark** | `#18181b` | `24, 24, 27` | Sidebar divider in dark mode |
| **Charcoal** | `#18181b` | `24, 24, 27` | Primary text, headings, labels |
| **Muted Zinc** | `#71717a` | `113, 113, 122` | Secondary text, placeholder, disabled text, table header labels |

### Color Application Rules
*   **The One Voice Rule**: Operational Blue appears on ≤10% of any screen. Its rarity is the point—it marks the next action. Every blue element draws attention; use sparingly.
*   **The Signal Purity Rule**: Success, Danger, Warning are never used for decoration. They appear only on quantity badges (green = stock increased, red = stock decreased), movement type badges, row highlights, toasts, and validation states.
*   **The Neutral Discipline Rule**: No custom grays. The neutral scale is Filament's default (Zinc 50–950). If a new neutral is needed, it comes from the Zinc scale—no hex inventions.

---

## 4. Typography

*   **Display Font**: Instrument Sans (with ui-sans-serif, system-ui fallback)
*   **Body Font**: Instrument Sans (with ui-sans-serif, system-ui fallback)
*   **Label/Mono Font**: Instrument Sans (same family, weight/transform differentiate)

Instrument Sans is geometric, open, and highly legible at small sizes—chosen specifically for warehouse floor scanning. One family eliminates font-loading flicker and keeps the bundle lean. Hierarchy is carried by weight, size, and case, not font switching.

### Typography Scale
| Level | Size | Weight | Line Height | Letter Spacing | Usage |
| :--- | :--- | :---: | :---: | :---: | :--- |
| **Display** | `clamp(1.875rem, 4vw, 2.25rem)` | 600 | 1.2 | `-0.02em` | Page titles only (List Products, List Stock Movements) |
| **Headline** | `1.5rem` | 600 | 1.3 | `-0.01em` | Section headings within pages |
| **Title** | `1.125rem` | 600 | 1.4 | 0 | Resource headers, modal titles, widget headings |
| **Body** | `0.875rem` | 400 | 1.5 | 0 | Table cells, form inputs, help text, notifications, reading text |
| **Label** | `0.75rem` | 500 | 1.5 | `0.02em` | Table headers, badge text, filter labels, button text, nav items (UPPERCASE) |

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
*   **The Single Family Rule**: No second font. If monospace is needed for SKUs or codes, use the `font-mono` utility (system mono)—never load an external family.
*   **The Uppercase Label Rule**: All column headers, filter labels, and button text are Label style (uppercase, tracked). This is the visual rhythm of the system.

---

## 5. Layout & Responsive Patterns

### Container & Grid System
Filament's SPA shell provides:
*   Fixed sidebar (280px expanded, 72px collapsed)
*   Top header (64px)
*   Content area with max-width container (1280px)

**Spacing Rhythm**: 8px base (Tailwind CSS standard spacing)
```
xs: 2px,   sm: 4px,   base: 8px,   md: 12px,
lg: 16px,  xl: 24px,  2xl: 32px,   3xl: 48px,
4xl: 64px, 5xl: 96px
```
All padding, margins, and gaps use multiples of 4px.

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
*   **Table Layout**: Tables expand to fill container. Dense structure: 8px vertical row padding, 12px horizontal. No zebra striping; hover states only.
*   **Form Layout**: Forms center at 640px max. Schema grid is 2-column default on desktop, 1-column on mobile. Use `columnSpanFull` for wide fields.
*   **Dashboard Layout (Asymmetrical Bento)**: Mobile uses 1 column; tablet (sm) uses 2 columns; desktop (lg) uses 4 columns. Cards span different footprints (1, 2, or 4 columns) to establish visual hierarchy.
*   **Sidebar Navigation**: Desktop (lg+) has a fixed left sidebar (~280px width). Tablet (md-lg) has a collapsible sidebar. Mobile (sm) uses a full-screen slide-out drawer (hamburger menu).

---

## 6. Shapes & Elevation

### Border Radius Scale
| Size | Value | Usage |
| :--- | :--- | :--- |
| **None** | `0px` | Squared elements, sidebar outer shells, top menu edges |
| **XS (sm)** | `4px` | Icon buttons, minimal rounding, badges |
| **SM (md)** | `6px` | Form inputs, buttons, small components, standard interactions |
| **MD (lg)** | `8px` | Cards, modals, table containers, widgets |
| **LG (xl)** | `12px` | Feature cards, large modals, primary containers |

Applied consistently across:
*   **Inputs/Selects/Buttons**: md (6px) — tactile but not pill-shaped.
*   **Badges**: sm (4px) — tight to text.
*   **Cards/Modals**: lg (8px) / xl (12px) — visible but not rounded-rectangle.
*   **Tables**: lg (8px) via card wrapper.
*   **Sidebar/Top bar**: no radius.

### Elevation & Depth Strategy
**Flat by Default, Lift on Interaction.** Surfaces (cards, tables, sidebar, header) are flat at rest—no box-shadow. Elevation appears only as a response to state:
| Level | Shadow | Usage |
| :--- | :--- | :--- |
| **Flat** | None | Cards, tables, sidebar, header at rest |
| **Hover** | None (bg change) | Table rows $
ightarrow$ `neutral-surface` (`#fafafa`) background highlight |
| **Focus** | 3px primary-muted ring | Inputs, buttons, selects |
| **Modal Lift** | `0 25px 50px -12px rgba(0,0,0,0.15)` | Action modals, confirmation dialogs |
| **Dropdown Lift**| `0 10px 15px -3px rgba(0,0,0,0.1)` | Select options, filter menus, date pickers |
| **Tooltip Lift** | `0 4px 6px -1px rgba(0,0,0,0.1)` | Icon tooltips, truncated cell tooltips |

### Named Rules
*   **The Flat-By-Default Rule**: Cards, table containers, sidebar, header have no shadow at rest. Depth is conveyed by border (1px) and background contrast (white vs. neutral-surface).
*   **The Interaction-Only Elevation Rule**: Shadows appear only on modals, dropdowns, and tooltips. Never on static cards, table rows, or buttons at rest.
*   **The Consistent Radius Rule**: Four radii, four jobs. No ad-hoc `rounded-full` or `rounded-none` on interactive elements. Buttons are never pills; badges are never square.

---

## 7. Component Specifications

All components are Filament v5 defaults. No custom component library exists.

### Buttons
*   **Primary Button**: Backed by Operational Blue (`#3b82f6`), white text, 6px (md) rounding, 40px height, 8px vertical / 16px horizontal padding. Hover shifts to Primary Deep (`#2563eb`). Focus applies a 3px Primary Muted ring.
*   **Secondary Button**: Whisper Gray background, Charcoal text, 1px Border Zinc edge, 6px rounding, 40px height. Hover uses Border Zinc background. Focus applies a 3px Primary Muted ring.
*   **Danger Button**: Signal Danger background, white text, 6px rounding, 40px height. Hover is a darker danger shade. Focus uses a 3px Danger Muted ring. Requires confirmation modal.
*   **Ghost / Text Button**: Transparent background, Charcoal text. Hover uses Whisper Gray background. Focus applies a 3px Primary Muted ring. No padding.
*   **Icon Buttons**: 40px square, 6px rounding, same color logic as text buttons. Accessible label required: `aria-label="Close dialog"`.

### Chips & Badges
| Type | Background | Text | Radius | Padding | Usage |
| :--- | :--- | :--- | :---: | :--- | :--- |
| **Success** | Success Muted (`#dcfce7`) | Verdant Success (`#16a34a`) | `4px` | 8px H / 2px V | Receive, Transfer In, positive quantity, completed status |
| **Danger** | Danger Muted (`#fee2e2`) | Signal Danger (`#dc2626`) | `4px` | 8px H / 2px V | Ship, Transfer Out, negative quantity, low stock |
| **Warning** | Warning Muted (`#fef3c7`) | Caution Amber (`#d97706`) | `4px` | 8px H / 2px V | Adjustment, pending states, virtual transit |
| **Gray** | Gray Muted (`#f4f4f5`) | Muted Zinc (`#71717a`) | `4px` | 8px H / 2px V | Zero quantity, neutral states, draft, cancelled |

### Cards & Containers
*   Border Radius: 8px (lg)
*   Background: Paper White (`#ffffff`)
*   Border: 1px Border Zinc (`#e4e4e7`)
*   Shadow: None at rest (Flat-By-Default Rule)
*   Internal Padding: 16px (md) for content; 24px (lg) for modals
*   Header: Border Zinc bottom divider, Title typography

### Input Fields & Selects
*   **Base Style**: Paper White background, Charcoal text, 6px rounding, 40px height, 8px vertical / 12px horizontal padding, 1px Border Zinc.
*   **Focus State**: 1px Operational Blue border, 3px Primary Muted box-shadow ring.
*   **Error State**: 1px Signal Danger border, 3px Danger Muted box-shadow ring.
*   **Disabled State**: Whisper Gray background, Muted Zinc text, disabled cursor.

### Tables
*   **Header**: Whisper Gray background, Label typography (uppercase, tracked), 1px Border Zinc bottom border.
*   **Row**: Paper White background, 1px Border Zinc bottom divider, Body typography. Compact row padding: 8px vertical, 12px horizontal.
*   **Hover**: Whisper Gray background, transition is 150ms ease-in-out.
*   **Row Highlight**: Tables use `recordClasses` to apply warning colors (`hover:bg-amber-50/40`) to active, unapproved requisitions.

### Navigation
*   **Sidebar**: Paper White background, 280px width, 1px Border Zinc right-border.
*   **Sidebar Items**: Body typography, 12px vertical / 12px horizontal padding. Charcoal text. Hover uses Whisper Gray background. Active state triggers Operational Blue text and a 3px left accent border.
*   **Sidebar Dark Mode**: `#0f0f10` background, 1px Border Zinc Dark (`#18181b`) right-border.

### Action Modals (Signature Pattern)
*   **Layout**: 640px max-width, vertically centered. Cancel (Ghost) + Submit (Primary).
*   **Header**: Close button (X), Title, and icon aligned with action intent (e.g. Package for Receive, Truck for Transfer).
*   **Body**: 2-column Schema grid.
*   **Footer**: Right-aligned, cancel on left, primary submit on right.
*   **DLP Safe**: Every modal must register `->closeModalByClickingAway(false)` to lock progress.

---

## 8. Role-Based UI Patterns

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

#### Pattern 1: Hide Element Based on Role (Blade Check)
```php
@if(auth()->user()->isAdmin() || auth()->user()->isAuditor())
    <div class="warehouse-filter">
        <!-- Filter by warehouse dropdown -->
    </div>
@endif
```

#### Pattern 2: Scope Query to User Warehouses (Eloquent Policy)
```php
public static function getEloquentQuery(): Builder
{
    $user = auth()->user();
    $query = parent::getEloquentQuery();
    
    if (!$user->isAdmin() && !$user->isAuditor()) {
        $warehouseIds = $user->warehouses()->pluck('warehouses.id');
        $query->whereIn('warehouse_id', $warehouseIds);
    }
    
    return $query;
}
```

#### Pattern 3: Return Empty/Null for Sensitive Cards
```php
// In BentoStatsWidget, lower roles see nothing (not "N/A" or "0")
if (!auth()->user()->isAdmin() && !auth()->user()->isAuditor()) {
    return null; // Stat card is completely skipped
}
```

---

## 9. Dashboard Design (Phase 13 Glassmorphism)

### Overview
The dashboard features an asymmetrical **Glassmorphic Bento Grid** composed of frosted glass cards suspended over a dynamic neutral back-plane.

### Layout Specification
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
| :--- | :---: | :---: | :--- | :--- |
| **On-Hand Stock** | 1 | ✅ | None | Total units, sparkline, blue accent |
| **In-Transit Stock** | 2 | ✅ | None | Total units, sparkline, amber accent |
| **30-Day Material Loss** | 1 | ❌ | Admin/Auditor only | Loss value ($X,XXX.XXXX), sparkline, red accent |
| **Inventory Health** | 1 | ✅ | None | Accuracy %, sparkline, green accent |
| **Pending Requisitions** | 4 | ✅ | Warehouse-scoped for non-admins | Wide table with status, actions |

### Glassmorphic CSS Styling
```css
/* Custom Glassmorphism styles applied in theme.css */
.bento-glass-panel {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(24px) saturate(120%);
    -webkit-backdrop-filter: blur(24px) saturate(120%);
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.05);
}

.dark .bento-glass-panel {
    background: rgba(10, 10, 10, 0.15);
    backdrop-filter: blur(24px) saturate(140%);
    border: 1px solid rgba(255, 255, 255, 0.04);
    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.15);
}

.bento-glass-panel-accent-red {
    background: rgba(239, 68, 68, 0.04);
    border: 1px solid rgba(239, 68, 68, 0.15);
}
```

---

## 10. Filament Resources & Customization

All Filament resources decouple visual layout components into modular files under `Schemas/`, `Tables/`, and `RelationManagers/`.

### Resource Structure
```php
namespace App\Filament\Resources;

use App\Models\Product;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\Products\Tables\ProductsTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::make($table);
    }
}
```

---

## 11. Livewire Component Patterns

### Component Lifecycle & State Management
*   **Public Properties**: Reserved strictly for user-facing reactive inputs (e.g. search fields, active filter toggles).
*   **Computed Properties**: Used for derived data lookups (via `#[Computed]`), caching queries within the request lifecycle.
*   **Caching (`Cache::remember()`)**: Wraps database-intensive sum and average operations on the ledger in a 300-second cache tag.

---

## 12. Accessibility & Inclusivity (WCAG 2.1 AA)

*   **Color Contrast**: Standard text cell elements satisfy a minimum contrast ratio of **4.5:1** against backgrounds. UI signals, headings, and bold badges adhere to a minimum **3:1** ratio.
*   **Keyboard Navigation**: Tab progression follows logical left-to-right, top-to-bottom layout boundaries, with active focus outlines on form fields.
*   **Screen Reader Support**: All icon-only triggers specify explicit `aria-label` attributes to assist floor operators using handheld scanners or tablet devices.
*   **Motion**: Respects the `prefers-reduced-motion` media query, disabling CSS scale and translateY shifts automatically for matched browsers.

---

## 13. Animation & Interaction

### Transition Utilities
All transitions are mapped to snappy, hardware-accelerated Tailwind classes:
*   `duration-200`: Responsive click feedback, input focus outlines.
*   `duration-300`: Bento grid card hover shifts, modal overlays.
*   `duration-500`: Action slide-over panel drawers.

---

## 14. Dark Mode Support

Filament Inventory enforces a clean, zero-compromise dark mode experience using Tailwind's `dark:` utility class:

### Color Mapping
| Light Token | Dark Token | Purpose |
| :--- | :--- | :--- |
| `#ffffff` (Paper White) | `#0f0f10` (Ink Black) | Page backgrounds, cards |
| `#fafafa` (Whisper Gray) | `#18181b` (Dark Charcoal) | Section headers, filters |
| `#e4e4e7` (Border Zinc) | `#27272a` (Border Zinc Dark) | Input borders, table dividers |
| `#18181b` (Charcoal) | `#f8fafc` (Inverted Slate) | Main text headers |

---

## 15. Performance Considerations

*   **Flat Query Footprint**: Filament resource tables override `getEloquentQuery()` and declare explicit eager-loaded properties (`with([...])`) to prevent relational N+1 loops.
*   **Indexed Searches**: Tables enforce searchable columns mapped to indexed database columns (e.g. `variant_id`, `warehouse_id`, `created_at`, `reference_code`).
*   **Optimistic UI Toggles**: Modals resolve validation states instantly before committing transactions.

---

## 16. Do's and Don'ts

### Do:
*   **DO** wrap every user-facing string in Laravel's translation wrapper `__('filename.key')`.
*   **DO** use strongly-typed **`Filament\Support\Icons\Heroicon`** static properties for all iconography definitions.
*   **DO** restrict Operational Blue to **≤10%** of any single dashboard or resource view.
*   **DO** compute physical stock balances on-the-fly at query-time from the append-only movement ledger.
*   **DO** use slide-overs or table modals for any form containing fewer than 8 input parameters.

### Don't:
*   **DON'T** use semantic colors (Verdant Success, Signal Danger) for decorative UI highlights.
*   **DON'T** add drop shadows to cards, table containers, or static panel menus at rest.
*   **DON'T** hardcode raw string paths (like `'heroicon-o-check-circle'`) inside Filament components.
*   **DON'T** enable alternating row zebra-striping on index tables.
*   **DON'T** introduce a second display font family.
