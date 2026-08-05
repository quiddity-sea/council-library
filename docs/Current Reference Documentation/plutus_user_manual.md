# Plutus — Financial Tracking System
## User Manual

*Version 1.0 | Built on the ForeverBox Design System*

---

## Welcome to Plutus

**Plutus** is your personal financial command centre — a private, self-hosted application for tracking every pound that flows through your life. Built on the same design system as the ForeverBox Institute and Forever Fit, it shares the same visual language: dark HUD aesthetic, neon-green accents, glass-panel cards, and scanline overlays.

But Plutus isn't just another budgeting app. It's a **modular financial operating system** that grows with you:

- **Three budget modes** — Personal, Household, and Improvements (for home renovation projects)
- **Hierarchical categories** — Nested categories with icons, scoped to each budget type
- **Recurring transactions** — Exact, variable, or single entries with flexible recurrence patterns
- **Multi-zone project tracking** — For home improvements, renovations, and zone-based work
- **Reference data management** — Products, services, custom items, makers, and suppliers with full vendor tracking
- **Sub-item receipt breakdowns** — Every transaction can contain detailed line items with quantities, vendors, and unit prices
- **System health diagnostics** — Automatic detection of uncategorised items, missing vendors, and data gaps

Everything runs locally on your server. Your data never leaves your infrastructure.

---

## Getting Started

### First Login

1. Navigate to `https://plutus.invigor.com`
2. Enter your credentials (configured during deployment)
3. You'll land on the **Overview** dashboard — your financial mission control

### The Interface at a Glance

| Area | Purpose |
|------|---------|
| **Top Header** | System title, global timeframe filter (Day/Week/Month/Year), live clock, logout |
| **Left Sidebar (on transaction tabs)** | Quick stats, quick actions, upcoming tasks, budget manager links |
| **Main Content** | Dynamic tab content — Overview, Personal, Household, Improvements, Reference Data |
| **Bottom Footer** | System status (ONLINE), node identifier (WALES_HUB) |
| **Global Scanlines** | Subtle animated overlay — always on, always watching |

### Global Timeframe Filter

The **Day / Week / Month / Year** buttons in the header control the time window for:
- Transaction lists
- Budget progress calculations
- Overview analytics
- Chart time ranges

Your preference persists across sessions.

---

## Core Concepts

### The Three Budget Types

| Budget Type | Scope | Typical Use |
|-------------|-------|-------------|
| **Personal** | Individual spending | Your daily coffee, subscriptions, personal shopping |
| **Household** | Shared expenses | Rent/mortgage, utilities, groceries, family costs |
| **Improvements** | Project-based | Home renovations, DIY projects, zone-based work |

Each budget has its own categories, transactions, and progress tracking. You can switch between them via the navigation tabs.

### Categories: Hierarchical & Scoped

Categories are **scoped** to budget types:
- **Global** — Available everywhere
- **Personal** — Only in Personal budget
- **Household** — Only in Household budget
- **Improvements** — Only in Improvements budget

Categories can be **nested** (parent → child). Example:
```
Food & Drink (parent)
  ├─ Groceries
  ├─ Restaurants
  └─ Coffee Shops
```

Each category has an **icon** (from Material Symbols) and optional description.

### Transactions: More Than Just Amounts

Every transaction captures:
- **Type** — Expense or Income
- **Name** — Descriptive label
- **Date & Time** — Precise timestamp
- **Amount** — Decimal precision
- **Budget** — Which budget it belongs to
- **Master Category** → **Sub Category** — Two-level categorisation
- **Recurrence** — Single, Recurring Exact, or Recurring Variable
- **Sub-items (Receipt Breakdown)** — Optional line items with:
  - Type (Product / Service / Custom)
  - Item name
  - Vendor (supplier or maker)
  - Quantity
  - Unit price
  - Total price

### Budgets: Targets & Tracking

Each budget can have:
- **Target type** — Static (fixed amount) or Recurring (daily/weekly/monthly/quarterly/yearly)
- **Target amount** — Your spending limit or savings goal
- **Progress tracking** — Visual progress bars with colour coding:
  - 🟢 Green — On track (< 80%)
  - 🟡 Amber — Warning (80–100%)
  - 🔴 Red — Over budget (> 100%)

### Zones & Projects (Improvements Tab)

For home improvement tracking:
- **Zones** — Hierarchical spaces (House → Kitchen → Worktop)
- **Projects** — Named workstreams linked to a zone, with target budget
- **Improvements** — Individual work items linked to a project/zone:
  - Status: Planned → In Progress → Completed
  - Estimated vs. actual cost tracking
  - Notes and Google Calendar sync

---

## Navigation Guide

### Overview Tab
Your financial health at a glance:
- **Total spent** (with breakdown by budget type %)
- **Budget progress cards** — Visual progress bars with spend vs. target
- **Upcoming tasks** — Upcoming payments, recurring bills, project deadlines
- **Quick actions** — Log transaction, manage budgets, manage categories

### Personal / Household Tabs
Dedicated transaction views for each budget type:
- **Sidebar** — Total spent, Log Transaction button, upcoming tasks, manage budgets/categories
- **Main grid** — Filterable, sortable transaction table with:
  - Date, Transaction name, Category, Amount, Running total
  - Expandable sub-items (click row to expand receipt breakdown)
  - Inline actions: View, Edit, Delete
- **Timeframe filter** — Day/Week/Month/Year
- **Filter dropdown** — All transactions, specific budget, or global

### Improvements Tab
Project-based tracking for renovations:
- **Sidebar** — Log Improvement button, Manage Projects, Manage Zones
- **Main table** — Project, Item, Zone, Status, Est. Cost, Actions
- **Status badges** — Planned / In Progress / Completed (colour-coded)

### Reference Data Tab
Your master data library — five sub-tabs:

| Sub-tab | Purpose |
|---------|---------|
| **Dashboard** | System health overview, top products/services/vendors, spend distribution, data health alerts |
| **Products** | Physical goods you buy repeatedly |
| **Services** | Recurring services (subscriptions, tradespeople, etc.) |
| **Custom** | One-off or miscellaneous items |
| **Makers** | Manufacturers/brands |
| **Providers** | Suppliers/retailers |

**Each list supports:**
- Search (real-time filter)
- Column sorting (click headers)
- Inline actions: View, Edit, Delete
- "New [Type]" button to create entries
- Click row → View modal with full details

### Dashboard (Reference Data Sub-tab)
System health at a glance:
- **Counts** — Products, Services, Custom, Makers, Providers
- **Top 5** — Most-purchased products, services, vendors
- **Spend distribution** — Products / Services / Custom / Unitemised (% + £)
- **Data health alerts** — Uncategorised items, missing vendors (click to jump to fix)

---

## Common Workflows

### Logging a Simple Expense
1. Click **Log Transaction** (sidebar button) or press `N` (if shortcuts enabled)
2. Fill the modal:
   - Type: Expense
   - Name: "Weekly grocery shop"
   - Date/Time: Auto-fills to now (adjust if needed)
   - Amount: `47.32`
   - Budget: Household
   - Master Category: Food & Drink → Groceries
   - Recurrence: Single
3. Click **Save**

### Logging a Receipt with Sub-items
1. Open **Log Transaction** modal
2. Fill basic fields as above
4. Scroll to **Sub-items** section (appears for expenses)
5. Click **Add Line** for each receipt line:
   - Type: Product / Service / Custom
   - Item name: "Organic Milk 2L"
   - Vendor: Select or create "Tesco"
   - Quantity: `2`
   - Unit price: `1.45`
   - Total auto-calculates
6. Save — sub-items appear expandable under the transaction row

### Setting Up a Recurring Bill
1. New Transaction → Type: Expense
2. Name: "Netflix Subscription"
3. Amount: `15.99`
4. Recurrence: **Recurring Exact**
5. Duration: Monthly
6. Date: Set to next billing date
6. Save — system will auto-generate future occurrences

### Creating a Nested Category
1. Go to **Reference Data → Dashboard** (or any category list)
2. Click **New Category**
5. Name: "Coffee Shops"
6. Type: Expense
7. Scope: Personal
7. Parent Category: "Food & Drink" (if exists)
8. Icon: ☕ (click icon grid to pick)
9. Save

### Setting Up a Home Improvement Project
1. Go to **Improvements** tab
2. Click **Manage Projects** → **New Project**
   - Name: "Kitchen Renovation"
   - Zone: Kitchen (create if needed)
   - Target budget: `15000`
   - Status: Planning
2. Add improvements:
   - Click **Log Improvement**
   - Project: Kitchen Renovation
   - Zone: Kitchen
   - Item: "New worktops"
   - Est. cost: `3200`
   - Status: Planned
   - Notes: "Quartz, 3m length"

### Managing Reference Data (Products, Vendors, etc.)
1. Go to **Reference Data** tab
2. Click sub-tab (Products, Services, Makers, etc.)
3. **Search** — Real-time filter as you type
4. **Sort** — Click any column header (▲/▼ toggles)
7. **Actions per row** — View, Edit, Delete
8. **New** — Click "New [TYPE]" button top-right

---

## Data Health & Maintenance

### The Dashboard Alerts
The Reference Data → Dashboard shows two critical alerts:

| Alert | Meaning | Fix |
|-------|---------|-----|
| **Uncategorised Items** | Items without a category assigned | Click → filters Products list to uncategorised → edit each to assign category |
| **Missing Maker/Provider** | Products without maker or supplier | Click → filters Products → edit to add maker/supplier |

### Regular Maintenance
- **Weekly** — Review uncategorised items, reconcile budgets
- **Monthly** — Review budget progress, adjust targets if needed
- **Quarterly** — Audit reference data: merge duplicate vendors, clean up unused categories

---

## Keyboard Shortcuts (Power Users)

| Shortcut | Action |
|----------|--------|
| `N` | New transaction (where supported) |
| `Tab` / `Shift+Tab` | Navigate modal fields |
| `Enter` | Submit modal / Save |
| `Esc` | Close modal |
| Click row | Expand transaction sub-items |
| Click column header | Sort table |

---

## Tips & Best Practices

1. **Use sub-items religiously** — They power the analytics (top products, vendor spend, categorisation health)
2. **Assign makers & providers** — Enables vendor spend analytics and "missing vendor" alerts
3. **Categorise at point of entry** — Don't let uncategorised items pile up
4. **Use recurring transactions** — For subscriptions, rent, salaries — reduces manual entry
5. **Set budget targets** — Even rough targets give you progress bars and overspend warnings
6. **Link Google Calendar** — For recurring payments and project milestones (configured in budget/project settings)

---

## Troubleshooting

| Symptom | Likely Cause | Fix |
|---------|--------------|-----|
| "FETCHING DATA..." spins forever | API not responding | Check `api.php` logs, verify DB connection in `db.php` |
| Login fails | Wrong credentials / session expired | Check `api.php?action=login` response, clear cookies |
| Charts not loading | Chart.js failed to load | Check network tab for blocked CDN, verify `chart.js` loads |
| Date picker broken | Flatpickr not initialised | Check `flatpickr.min.js` loaded, console for JS errors |
| Sub-items not showing | Transaction has no sub-items | Only expenses with sub-items show expand icon |
| Footer scripts not loading | `$basePath` wrong | Verify footer.php `$basePath` logic matches page depth |

---

## Data Privacy & Security

- **Self-hosted** — Your data never leaves your server
- **Session-based auth** — PHP sessions, no JWT tokens stored client-side
- **Password hashing** — bcrypt via PHP `password_hash()`
- **CSRF protection** — Forms use session-bound tokens (via API)
- **SQL injection prevention** — All queries use prepared statements (PDO)

---

## Extending Plutus

Plutus is designed for extension:

| Extension Point | How |
|-----------------|-----|
| New entity types | Add to `schemas` in `app.js`, create DB table, add API endpoint |
| New dashboard widgets | Add render function in `app.js`, call from `renderOverviewTab` |
| Custom charts | Use Chart.js instances in render functions |
| New budget types | Add to `budgets.type` enum, update schemas and UI |
| Webhooks / integrations | Add to `api.php` actions, register in `app.js` |

---

## Support & Community

Plutus is part of the **ForeverBox ecosystem**. Issues, ideas, and contributions flow through the same channels as the Institute and Forever Fit.

- **Documentation** — This manual + inline code comments
- **Source of truth** — `/var/www/plutus.invigor.com/` on the server
- **Database** — `plutus_thoughts` on MariaDB
- **Logs** — Apache error log: `/var/log/apache2/plutus-error.log`

---

*Built with the ForeverBox design system. Neutrino-green on charcoal. Scanlines always watching.*

---

**End of User Manual**

---

## Appendix: Quick Reference

### Entity Types & Required Fields

| Entity | Required Fields | Optional Fields |
|--------|----------------|-----------------|
| Budget | type, name | target_type, recurring_duration, target_amount, description |
| Category | type, name, icon | parent_category_id, scope, description |
| Transaction | type, name, date, amount, budget_id, master_category_id, category_id, recurrence_state | recurrence_duration, description, sub_items |
| Budget | type, name | target_type, recurring_duration, target_amount, description |
| Category | type, name, icon | parent_category_id, scope, description |
| Zone | name | location, dimensions, condition_state, parent_zone_id, description |
| Project | name | status, zone_id, target_budget, description |
| Improvement | project_id, item_description | zone_id, status, estimated_cost, actual_cost, notes |
| Item | type, name, category_id | manufacturer_id, supplier_id, default_price, amount_description, description |
| Vendor | type, name | description |

### API Endpoints (api.php)

| Action | Method | Parameters |
|--------|--------|------------|
| `check_session` | GET | — |
| `login` | POST | username, password |
| `logout` | GET | — |
| `get_metadata` | GET | — |
| `get_dashboard` | GET | tab, timeframe, tx_filter |
| `save_object` | POST | entity_type, id (optional), fields... |
| `delete_object` | POST | entity_type, id |
| `check_session` | GET | — |

---

*End of Appendix*
---

## What's New — 2026 Update (Phases 1–5)

*This chapter documents the features delivered by the 2026 system update. The manual above describes the original system; this section covers everything added since.*

### Security & Data Protection

- **CSRF protection**: all mutating actions require a session token attached automatically by the app. Login and session checks are exempt.
- **Login rate limiting**: maximum 5 failed attempts per IP per 15 minutes. Further attempts return HTTP 429 with a Retry-After header.
- **Audit trail**: every login, logout, create, update, delete and restore is recorded in the `audit_log` table with user, IP, user agent, and before/after values.
- **Soft deletes**: deleting a record marks it as deleted rather than removing it. Use **SHOW DELETED** in the List Manager to view deleted records and restore them with the restore button.
- **Data integrity**: constraints prevent negative transaction amounts and non-positive budget targets; budget names are unique per user and type.

### Keyboard Shortcuts (new)

| Key | Action |
|-----|--------|
| `N` | Open new transaction form |
| `/` | Focus reference search |
| `?` | Show keyboard help |
| `Esc` | Close any open modal |

### Data Export

From any transaction tab, use the download icons in the transaction log header:

- **CSV** — spreadsheet-friendly export
- **JSON** — structured export for other tools
- **PDF** — formatted document

Exports respect the current timeframe filter and budget selection. Unauthenticated export requests are rejected.

### Bank Import

1. Click **IMPORT FROM BANK** in the transaction tab sidebar.
2. Choose a CSV file (`date,amount,name,description`) and a target budget.
3. Click **PREVIEW** — Plutus parses the file, flags probable duplicates (matching date + amount + name), and suggests a category per row.
4. Adjust categories with the per-row dropdown, untick anything you do not want.
5. Click **CONFIRM IMPORT**. New transactions are created; duplicates are skipped.

### Receipt Scanning (OCR)

1. Click **SCAN RECEIPT** in the transaction tab sidebar.
2. Upload a receipt photo (JPG, PNG, WebP or PDF).
3. Plutus runs OCR and extracts the vendor, date and total.
4. Click **CREATE TRANSACTION** — the transaction form opens pre-filled with those values.

### Multi-Currency

Budgets and transactions now carry a currency (default GBP). The dashboard overview accepts a `currency` parameter to convert totals using daily ECB reference rates (cached for 24 hours). Currency selectors appear in the budget and transaction forms.

### PWA (Installable App)

Plutus is now a Progressive Web App:

- Install it from the browser menu (or the install prompt when supported).
- Works offline for static assets; transactions created while offline can be queued and synchronised when back online.
- Service worker caches the app shell for fast reloads.

### Staging Environment

A full copy of the system with anonymised data is available at:

```
https://plutus-staging.invigor.com
Staging User 1 / StagingPass123!
```

Use staging to experiment safely. It is recreated from production with all personal data anonymised (budget names, transaction names, vendors, items, projects and zones are replaced; audit log is cleared).

### API Versioning

The API is versioned. The current version is v1:

- Legacy entry point: `api.php?action=...` (backward compatible)
- Versioned entry point: `api/v1.php?action=...` or `/api/v1/<action>`
- Clients should send `Accept: application/vnd.plutus.v1+json`
- Unsupported versions return HTTP 406

### Scheduled Exports

A weekly CSV export runs automatically (Sundays 04:00) and is written to `/foreverbox_data/exports/plutus/`. Set `PLUTUS_EXPORT_WEBHOOK` or `PLUTUS_EXPORT_EMAIL` environment variables to also deliver exports to a webhook or email address.

---

*End of What's New — 2026 Update*

---

## What's New — Phase 2 Upgrades (2026-08-01)

*This chapter covers the Phase 2 upgrade blocks: planned transactions (projected vs actual), planned income, and the spend-based task manager with Google Calendar integration.*

### Planned Transactions

The system now lets you plan spending in advance instead of only logging it after the fact.

**Creating a planned transaction:**
1. In a personal or household tab, click **PLAN TRANSACTION** (next to LOG TRANSACTION).
2. Enter the name (e.g. "Train to Cardiff"), the **projected cost** (e.g. £15.50), the date you expect to spend it, and the budget.
3. Save. The transaction appears in the **PLANNED** panel at the bottom of the transaction log with a £ projected amount and a MARK SPENT action.

**Marking a planned transaction as spent:**
1. On the day, click **MARK SPENT** on the planned item.
2. Enter the **actual cost** (the projected amount is pre-filled as a suggestion — edit it to what you really paid, e.g. £18.20).
3. Save. The transaction moves from PLANNED to the main log with **both values preserved**: the projected cost stays visible alongside the actual cost.

Planned transactions do **not** affect budget totals — only spent transactions count toward your budget's cost paid. This means you can build a forward plan without it distorting your current budget position.

### Planned Income

Planned income works the same way but is reported separately.

- Create a planned transaction with type **income** (e.g. "Freelance invoice" £300, expected Friday).
- The PLANNED panel splits into two sections: **PLANNED SPENDING** and **PLANNED INCOME** (green rows with a + prefix).
- The panel header shows the **net figure**: `NET +£200.00 (IN £300.00 / OUT £100.00)` — green when your forward plan is in surplus, red when in deficit.

This gives a complete forward picture: "I know £300 is coming in Friday, so I can plan the £250 shopping trip."

### Spend-Based Task Manager

For habitual, recurring spending — "cake shop reductions every Thursday 3:30pm", "travel card top-up every Monday" — you can create a **spend task** that reminds you, tracks the projected cost, and links to the transaction when you actually spend.

**Creating a spend task:**
1. Click **ADD SPEND TASK** in the transaction tab sidebar.
2. Enter the task name, the projected cost, the budget it relates to, and the recurrence:
   - **Recurrence type**: none / daily / weekly / biweekly / monthly / yearly
   - **Days** (weekly only): tick the days of the week (e.g. Thursday)
   - **Time**: the time of day the task happens (e.g. 15:30)
   - **Continuous or fixed**: tick Continuous for indefinite recurrence, or untick and enter a number of instances
3. Save. The task appears in the **UPCOMING TASKS** panel with its cost, schedule and status.

**Completing a spend task (MARK SPENT):**
1. On the day, click **MARK SPENT** on the task.
2. Enter the actual cost (projected pre-filled).
3. Confirm you want to create a transaction. The system records the actual cost, creates the transaction (with both projected and actual values), and **automatically creates the next instance** — next Thursday's task already exists, you never re-enter it.

**Task controls:**
- **PAUSE** — halts future instances (the task stays, no new ones are generated). The task shows `[PAUSED]`.
- **RESUME** — restarts recurrence from the next pending instance.
- **STOP** — ends the task permanently (status becomes cancelled).

### Google Calendar Integration

Spend tasks can sync to your Google Calendar on a dedicated Plutus calendar, so reminders appear on all your devices.

**Status:** The code is fully in place, but it activates only when the system administrator has configured Google Cloud credentials (OAuth client ID and secret as environment variables `PLUTUS_GOOGLE_CLIENT_ID` and `PLUTUS_GOOGLE_CLIENT_SECRET`, with the OAuth redirect URI pointing to `/api.php?action=oauth_callback`). Until then, the UPCOMING TASKS panel shows `CALENDAR: DISCONNECTED`.

Once configured:
- Creating a recurring spend task creates a recurring Google Calendar event on the Plutus calendar.
- Completing, pausing, resuming or stopping a task updates the corresponding calendar event.
- The dashboard header shows `CALENDAR: CONNECTED` when tokens are stored.

### Phase 6 — Period Selector (browse any period)

The timeframe buttons (DAY/WEEK/MONTH/YEAR) are now accompanied by a **period selector** dropdown. Its contents adapt to the chosen granularity:
- **DAY**: the most recent days with transactions
- **WEEK**: ISO week numbers with transactions (e.g. W31 2026)
- **MONTH**: months with transactions (e.g. JUL 2026)
- **YEAR**: the last 5 years and the next 5 years

Selecting a period filters every dashboard query (totals, transaction log, category breakdown, planned panel) to that period. This is how you view a past month — e.g. switch to MONTH, then pick JUL 2026 from the selector.


## What's New — Build Plan V4.0 Upgrades
*(Appended August 2026)*

### 1. Dedicated Top-Level Management Tabs
To improve intuitive navigation and speed up workflow, two new tabs have been elevated to the main navigation bar:
- **CATEGORIES**: A dedicated screen to view, search, sort, and manage categories. Includes a contextual sidebar allowing quick filtering by category type (`expense`, `income`, `product`, `service`, `account`).
- **BUDGETS**: A dedicated screen to view, search, sort, and manage budget caps. Includes a contextual sidebar to filter by budget scope (`personal`, `household`, `improvement`).

*The legacy manage buttons inside other tabs have been superseded by these dedicated views.*

### 2. HUD Non-Blocking Interface Interactions
Native browser `alert()` and `confirm()` dialogs have been entirely replaced with bespoke, styled HUD components:
- **Toast Notifications**: Feedback messages (e.g., "Saved", "Deleted") now appear as smooth, non-blocking slide-in panels in the top right corner. They automatically dismiss after a few seconds without requiring your click.
- **Universal Confirm Modals**: Deleting items or confirming major actions now triggers a centralized, styled glass-panel confirmation prompt matching the ForeverBox aesthetic.

### 3. Layout & Visual Polish
- All modal dialogs (such as the transaction editor) now dynamically adjust their height (`max-h-[90vh] h-auto`) to ensure they fit seamlessly on smaller screens or laptops without cutting off the bottom action buttons.
- The background theme's dark radial gradient has been seamlessly integrated into the Tailwind configuration (`hud-gradient`), providing a smoother, unified styling hierarchy.
