# Phase 0 — Surface & Component Inventory & DESIGN.md Coverage Map

## 1. DESIGN.md Coverage Map

| `DESIGN.md` Section | Topic / Description | Target Surfaces / Components |
|---|---|---|
| **1. Executive Summary & Design System Identity** | Philosophy, Clinical Blue Authority theme | All Panel Shells, Global UI Elements |
| **2. Design Tokens** | Color palette, typography scale, spacing rhythm, radius, shadows, motion | All UI components, buttons, forms, tables, badges |
| **3. Layout & Structure** | Container widths, grid systems, density modes, break points | Dashboard, Resource List/Form/View pages, Modals |
| **4. Component Specifications** | Buttons, Inputs, Tables, Badges, Modals, Drawers | Forms, Tables, Infolists, Action Modals |
| **5. Navigation & Panel Shell** | Sidebar width (280px), Topbar, User Menu, Global Search, Dark Mode | S01 Panel Shell & Navigation |
| **6. Form & Input Patterns** | 40px height, borders, ring states, error states, DLP Safe modal policy | All Create / Edit Form surfaces |
| **7. Data Display Patterns** | Compact table padding (8px v / 12px h), hover states, row highlights | All Resource Tables, Relation Managers, Infolists |
| **8. Role-Based UI Patterns** | Role hierarchy (Admin, Auditor, Branch Manager, Staff), Data Masking | Dashboard Bento Cards, Resource Filters & Fields |
| **9. Dashboard Design (Bento Grid)** | Glassmorphic Bento Cards, Stats Overview, Pending Requisitions | S02 Dashboard Page & Widgets |
| **10. Filament Resources Architecture** | Modular schemas, forms, tables, relation managers | Resources (S03-S10) |
| **11. Livewire Component Patterns** | Public properties, computed properties, query caching | Reactive fields, dynamic tables, widgets |
| **12. Accessibility (WCAG 2.1 AA)** | Contrast ratios (4.5:1 text, 3:1 bold/UI), keyboard nav, screen reader labels | All surfaces & views |
| **13. Animation & Interaction** | Duration timing (200ms, 300ms, 500ms), reduced-motion respect | Modals, Slide-overs, Tooltips, Toasts |
| **14. Dark Mode Support** | Ink Black `#0f0f10`, Charcoal `#18181b`, Zinc `#27272a` tokens | All viewports & view modes |
| **15. Performance Considerations** | Eager loading, indexed searches, optimistic UI | Resource Tables, Select Async lookups |
| **16. Do's and Don'ts** | Translation wrappers `__('...')`, Heroicons, <=10% Blue usage | System-wide audit rules |

---

## 2. Surface & Component Inventory (S01 .. S11)

| Surface ID | Surface / Surface Name | Surface Type | Route / Trigger | Purpose | States Covered | Audited Status |
|---|---|---|---|---|---|---|
| **S01** | **Panel Shell & Navigation** | Panel Shell | `/admin/*` | Global frame: sidebar (280px), topbar, global search, user menu, theme toggle, mobile drawer | Default, Hover, Focus, Active, Collapsed, Dark/Light/System | [ ] Audited |
| **S02** | **Admin Dashboard** | Custom Page | `/admin` | Glassmorphic Bento Grid, Stats Overview, Low Stock Alerts, In-Transit Widget, Recent Movements | Loaded, Filtered (Warehouse), Empty, Loading, Dark Mode | [ ] Audited |
| **S03** | **Product Variants Resource** | Resource (List/Create/Edit/View) | `/admin/products` | Manage product items, SKUs, stock thresholds, prices, unit conversions | List, Filter, Search, Pagination, Create Modal/Page, Edit, Infolist View | [ ] Audited |
| **S04** | **Stock Movements Resource** | Resource (List/View) | `/admin/stock-movements` | Ledger of stock additions, removals, adjustments, and receipts | List, Date/Warehouse Filter, Search, Detail Infolist Modal/View | [ ] Audited |
| **S05** | **Direct Transfers Resource** | Resource (List/Create/View) | `/admin/direct-transfers` | Record direct warehouse-to-warehouse stock transfers | List, Intake Wizard Form, Quantity Editor, Infolist Detail | [ ] Audited |
| **S06** | **Transfer Requisitions Resource** | Resource (List/Create/Edit/View) | `/admin/transfer-requisitions` | Request, approve, reject, and fulfill transfer requisitions | List, Status Badges, Revisions Table, Multi-step Approval Modal | [ ] Audited |
| **S07** | **In-Transits Resource** | Resource (List/View/Action) | `/admin/in-transits` | Track shipments in-transit and trigger Receive / Loss logging actions | List, Ship/Receive Action Modals, Quantity Inspection, Infolist | [ ] Audited |
| **S08** | **Loss Ledgers Resource** | Resource (List/View) | `/admin/loss-ledgers` | Audit records of damaged, lost, or expired stock with financial valuation | List, Financial Masking (Role-restricted), Infolist Detail View | [ ] Audited |
| **S09** | **Warehouses Resource** | Resource (List/Create/Edit/View) | `/admin/warehouses` | Warehouse locations, capacity, assigned managers, user relationships | List, Create/Edit Forms, Manager Relation Manager, Infolist View | [ ] Audited |
| **S10** | **Users & Roles Resource** | Resource (List/Create/Edit) | `/admin/users` | User administration, warehouse assignment, role management | List, Role Filters, User Form, Permission/Warehouse Select | [ ] Audited |
| **S11** | **Authentication / Login** | Auth Page | `/admin/login` | System entry authentication screen | Default, Validation Error, Password Visibility, Processing | [ ] Audited |
