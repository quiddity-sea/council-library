# Plutus — Financial Tracking System
## Developer / AI Build Blueprint

*Version 1.0 | For reconstruction from zero*

---

## System Overview

**Plutus** is a self-hosted financial tracking application built on the **ForeverBox design system**. It is a standalone PHP/MySQL application that shares the visual language (HUD components, neon-green theme, scanlines) with the ForeverBox Institute and Forever Fit, but operates independently with its own database (`plutus_thoughts`) and Apache vhost.

**Purpose**: Personal/household financial tracking with budget management, hierarchical categories, receipt-level transaction breakdowns, improvement/project tracking, and reference data management.

**Stack**:
- **Backend**: PHP 8.x (PDO, sessions)
- **Database**: MariaDB 11+ (`plutus_thoughts` database)
- **Frontend**: Vanilla JS + jQuery 3.7, GSAP 3.12, Chart.js, Flatpickr
- **CSS**: Tailwind CSS (CDN) + custom HUD component system
- **Server**: Apache 2.4 + mod_ssl (SSL via Let's Encrypt / custom CA)
- **Auth**: Session-based PHP auth, bcrypt passwords

---

## Architecture

```
plutus.invigor.com/
├── index.php              # Entry point, SPA shell, modals, auth gate
├── api.php                # REST-ish JSON API (all data operations)
├── db.php                 # PDO connection singleton
├── cron.php               # Scheduled tasks (recurring tx processing)
├── schema.sql             # Canonical database schema
├── assets/
│   ├── css/
│   │   ├── pages.css      # Page-specific styles
│   │   └── components.css # HUD component utilities
│   └── js/
│       ├── app.js         # Core SPA logic (~1900 lines)
│       ├── transaction_ui.js # Transaction-specific UI helpers
│       └── tailwind-config.js # Tailwind theme extension
└── assets/images/         # Placeholder images
```

### Request Flow

```
Browser → Apache (SSL) → index.php
  → session_start()
  → require 'db.php' (PDO)
  → require 'api.php' functions
  → session check → renderLogin() OR initApp()
    → initApp() → get_metadata → renderDashboardLayout()
      │
                                  ├─ renderOverviewTab
                                  ├─ renderTransactionTab (personal/household/improvements)
                                  ├─ renderReferenceDataTab
                                  └─ bindEvents()
  → All data via api.php?action=...
    → JSON response → JS renders via renderComponent() / direct DOM
```

---

## Database Schema (`plutus_thoughts`)

### Core Tables

| Table | Purpose | Key Fields |
|-------|---------|------------|
| `users` | Authentication | `id`, `username`, `password_hash`, `is_prime_user`, Google OAuth tokens |
| `budgets` | Budget definitions | `type` (personal/household/improvement), `name`, `target_type`, `recurring_duration`, `target_amount`, `description` |
| `categories` | Hierarchical categories | `parent_category_id`, `type` (income/expense), `scope` (global/personal/household/improvements), `icon` |
| `transactions` | Financial entries | `budget_id`, `category_id`, `type` (expense/income), `name`, `amount`, `date`, `recurrence_state`, `recurrence_duration`, `sub_items` (JSON) |
| `items` | Reference data (products/services/custom) | `type`, `name`, `category_id`, `manufacturer_id`, `supplier_id`, `default_price`, `amount_description` |
| `vendors` | Makers & suppliers | `type` (manufacturer/supplier), `name`, `description` |
| `projects` | Improvement projects | `name`, `zone_id`, `status`, `target_budget`, `target_date` |
| `project_zones` | Hierarchical zones | `parent_zone_id`, `name`, `location`, `dimensions`, `condition_state` |
| `improvements` | Work items | `project_id`, `zone_id`, `item_description`, `estimated_cost`, `actual_cost`, `status` |
| `tasks` | Reminders/recurring | `type`, `title`, `due_date`, `is_recurring`, `related_*_id` |

### Key Relationships

```
users 1──∞ budgets
users 1──∞ transactions (via budgets)
budgets 1──∞ transactions
categories ◁──▷ categories (self-referential parent)
categories 1──∞ transactions
transactions 1──∞ sub_items (JSON array in transactions.sub_items)
items ◁──▷ categories (N:1)
items ◁──▷ vendors (manufacturer_id, supplier_id)
vendors 1──∞ items (manufacturer/supplier)
projects 1──∞ improvements
project_zones ◁──▷ project_zones (self-referential)
projects 1──∞ project_zones
improvements N:1 projects, N:1 project_zones
```

### Critical Columns

| Table | Column | Notes |
|-------|--------|-------|
| `transactions` | `sub_items` | JSON array of receipt line items (legacy/display path) |
| `transaction_items` | *(table)* | Normalised relational table for receipt line items — linked via FK to `transactions.id`. Used for dependency checking (e.g. preventing deletion of items still referenced). Source of truth for analytics. |
| `transactions` | `recurrence_state` | `single` / `recurring_exact` / `recurring_variable` |
| `transactions` | `recurrence_duration` | `daily`/`weekly`/`monthly`/`quarterly`/`yearly` |
| `budgets` | `target_type` | `static` / `recurring` |
| `categories` | `scope` | `global`/`personal`/`household`/`improvements` |
| `categories` | `parent_category_id` | Self-referential FK for nesting |
| `items` | `type` | `product`/`service`/`custom` |
| `vendors` | `type` | `manufacturer` / `supplier` |

---

## API Specification (`api.php`)

### Actions

| Action | Method | Auth | Parameters | Returns |
|--------|--------|------|------------|---------|
| `check_session` | GET | ✓ | — | `{logged_in: bool}` |
| `login` | POST | ✗ | `username`, `password` | `{success: bool, error?: string}` |
| `logout` | GET | ✓ | — | `{success: bool}` |
| `get_metadata` | GET | ✓ | — | `{success: bool, metadata: {...}}` |
| `get_dashboard` | GET | ✓ | `tab`, `timeframe`, `tx_filter` | `{success: bool, data: {...}}` |
| `save_object` | POST | ✓ | `entity_type`, `id?`, fields... | `{success: bool, id?: int, error?: string}` |
| `delete_object` | POST | ✓ | `entity_type`, `id` | `{success: bool, error?: string}` |

### Metadata Structure (`get_metadata`)

```json
{
  "success": true,
  "metadata": {
    "budget": [{"id": 1, "type": "personal", "name": "...", "target_type": "recurring", "target_amount": 500, "spent": 342.10}],
    "category": [{"id": 1, "name": "Food & Drink", "type": "expense", "scope": "household", "parent_category_id": null, "icon": "restaurant"}],
    "transaction": [{"id": 1, "budget_id": 1, "category_id": 3, "type": "expense", "name": "Weekly shop", "amount": 47.32, "date": "2026-07-30", "sub_items": [...]}],
    "item": [{"id": 1, "type": "product", "name": "Organic Milk 2L", "category_id": 2, "manufacturer_id": null, "supplier_id": 1, "default_price": 1.45}],
    "vendor": [{"id": 1, "type": "supplier", "name": "Tesco", "description": null}],
    "project": [{"id": 1, "name": "Kitchen Renovation", "zone_id": 2, "status": "in_progress", "target_budget": 15000}],
    "zone": [{"id": 1, "name": "Kitchen", "parent_zone_id": null, "location": "Ground floor"}],
    "improvement": [{"id": 1, "project_id": 1, "zone_id": 1, "item_description": "New worktops", "estimated_cost": 3200, "status": "planned"}],
    "task": [{"id": 1, "title": "Pay council tax", "due_date": "2026-08-01", "is_recurring": true}]
  }
}
```

### Dashboard Response (`get_dashboard`)

Varies by `tab` parameter:
- `overview` → `{total_spent, personal_spent, household_spent, improvements_spent, budgets[], tasks[]}`
- `personal`/`household` → `{transactions[], budgets[], tasks[], total_spent}`
- `improvements` → `{improvements[], projects[], zones[]}`
- `reference_data` → Handled client-side via metadata

---

## Frontend Architecture (`assets/js/app.js`)

### State Management

```javascript
window.appState = {
    metadata: {},           // Full metadata from get_metadata
    currentTab: 'overview', // overview | personal | household | improvements | reference_data
    currentTimeframe: 'month', // day | week | month | year
    currentListEntity: null, // For list manager modal
    transactionFilter: 'tab' // tab | all | budget_id
}
```

### Core Functions

| Function | Purpose |
|----------|---------|
| `initApp()` | Boot sequence: fetch metadata → renderDashboardLayout → bindEvents → loadTabData |
| `renderDashboardLayout()` | Builds header, nav tabs, sidebar, content container |
| `loadTabData(tab)` | Fetches dashboard data → delegates to renderXxxTab |
| `renderOverviewTab(data)` | Budget cards, spend distribution, upcoming tasks |
| `renderTransactionTab(context, data)` | Sidebar + transaction table with sub-items |
| `renderImprovementsTab(data)` | Sidebar + improvements table |
| `renderReferenceDataTab()` | 4-col layout: sidebar tabs + dynamic content |
| `renderRefDataDashboard()` | Analytics: counts, top products/services/vendors, spend bars, health alerts |
| `renderRefDataList(entityType, subType)` | Sortable, searchable, sortable table with inline actions |
| `renderComponent(loomId, variables)` | Server-side template rendering via `engine.php` |

### Schema Definitions (`schemas` object)

Defines form fields for each entity type in the universal modal:

```javascript
const schemas = {
    'budget': [...],
    'category': [...],
    'zone': [...],
    'transaction': [...],
    'project': [...],
    'improvement': [...],
    'item': [...],
    'vendor': [...]
}
```

Field types: `text`, `number`, `select`, `textarea`, `datetime`, `icon_grid`

### Key UI Components (Client-Side Rendered)

| Component | Trigger | Description |
|-----------|---------|-------------|
| Universal Modal | `openObjectManager(entityType)` | Create/edit any entity via schema |
| View Modal | `viewObject(entityType, id)` | Read-only detail view |
| List Manager Modal | `openListManager(entityType)` | Full CRUD table for reference data |
| Transaction Sub-items | Click transaction row | Expandable receipt breakdown |

### Critical Event Bindings

```javascript
$('.nav-tab').on('click')           // Tab switching
$('.time-filter').on('click')       // Day/Week/Month/Year
$('#tx-filter-select').on('change') // Transaction filter dropdown
$('#logout-btn').on('click')        // Session termination
$('#universal-form').on('submit')   // Save any entity
$('.ref-sub-tab').on('click')       // Reference data sub-tabs
$('#ref-search-input').on('input')  // Live search filter
$('#ref-header-row th[data-sort]')  // Column sorting
```

---

## Deployment & Operations

### Server Requirements

- Ubuntu 22.04+ / Debian 12+
- Apache 2.4 + mod_ssl + mod_rewrite
- PHP 8.1+ with: `pdo_mysql`, `session`, `json`, `mbstring`
- MariaDB 11+ / MySQL 8+
- SSL cert (Let's Encrypt or custom CA)

### Installation

```bash
# 1. Clone / copy to /var/www/plutus.invigor.com
# 2. Create database
mysql -u root -p < schema.sql

# 3. Create user
CREATE USER 'zeon7_user'@'localhost' IDENTIFIED BY 'F0reverb0x#2o26sql';
GRANT ALL ON plutus_thoughts.* TO 'zeon7_user'@'localhost';

# 4. Configure Apache vhost (see /etc/apache2/sites-available/plutus.invigor.com.conf)
# 5. Enable site: a2ensite plutus.invigor.com-ssl.conf && systemctl reload apache2

# 6. Set permissions
chown -R www-data:www-data /var/www/plutus.invigor.com
chmod 755 /var/www/plutus.invigor.com
chmod 644 /var/www/plutus.invigor.com/*.php
```

### Cron Job (Recurring Transaction Processing)

```bash
# Add to crontab (runs every hour)
0 * * * * www-data /usr/bin/php /var/www/plutus.invigor.com/cron.php
```

**cron.php** processes:
- Recurring transactions (exact & variable)
- Generates next occurrence based on `recurrence_duration`
- Handles `recurring_variable` (amount varies) vs `recurring_exact` (fixed amount)

### Environment Variables

Create `.env` in `/var/www/plutus.invigor.com/` (optional, overrides db.php defaults):

```env
DB_HOST=localhost
DB_NAME=plutus_thoughts
DB_USER=zeon7_user
DB_PASS=F0reverb0x#2o26sql
```

---

## Database Maintenance

### Backup

```bash
mysqldump -u zeon7_user -p plutus_thoughts > plutus_backup_$(date +%F).sql
```

### Restore

```bash
mysql -u zeon7_user -p plutus_thoughts < backup_file.sql
```

### Schema Changes

**Never edit schema.sql directly for production changes.** Instead:

1. Create migration script in `/var/www/plutus.invigor.com/migrations/`
2. Run manually: `mysql -u zeon7_user -p plutus_thoughts < migration_XXX.sql`
3. Update `schema.sql` to match for fresh installs

### Common Queries

```sql
-- Find uncategorised items
SELECT * FROM items WHERE category_id IS NULL AND type != 'custom';

-- Find products missing vendor
SELECT * FROM items WHERE type='product' AND manufacturer_id IS NULL AND supplier_id IS NULL;

-- Transaction sub-items breakdown
SELECT t.id, t.name, si.item_name, si.quantity, si.unit_price, si.total_price
FROM transactions t
JOIN JSON_TABLE(t.sub_items, '$[*]' COLUMNS (
    item_name VARCHAR(100) PATH '$.item_name',
    quantity INT PATH '$.quantity',
    unit_price DECIMAL(10,2) PATH '$.unit_price',
    total_price DECIMAL(10,2) PATH '$.total_price'
)) AS si;
```

---

## Frontend Development Guide

### Adding a New Entity Type

1. **Database**: Add table to `schema.sql` with FKs
2. **API**: Add CRUD cases in `api.php` (`save_object`/`delete_object`)
3. **Schema**: Add to `schemas` object in `app.js`
4. **UI**: Add nav tab / reference data sub-tab / list manager support
5. **Metadata**: Ensure `api.php?action=get_metadata` returns new entity array

### Adding a Dashboard Widget

1. Create render function: `renderXxxWidget(data)`
2. Call from `renderOverviewTab()` or `renderRefDataDashboard()`
3. Use Chart.js for charts:
   ```javascript
   new Chart(ctx, { type: 'doughnut', data: {...}, options: {...} });
   ```

### Styling Conventions

| Pattern | Usage |
|---------|-------|
| `hud-border` | Container border + corner accents |
| `glass-panel` | Semi-transparent backdrop-blur panel |
| `hud-glow` | Neon green box-shadow |
| `hud-border-active` | Hover state border highlight |
| `panel-glow` | Subtle green glow |
| `hud-scanline` | Global animated scanline overlay |
| `btn-primary` | Neon green primary action |
| `btn-secondary` | Muted secondary action |
| `btn-icon` | Icon-only button |
| `btn-group` / `btn-group-item` | Segmented controls |
| `hud-border-active` | Hover overlay on images/cards |

### CSS Variables (Tailwind Config)

```javascript
// tailwind-config.js
colors: {
  primary: '#ebffe2',           // Mint text
  'primary-container': '#00ff41', // Neon green accent
  background: '#0b141c',        // Near-black
  surface: '#0b141c',
  'surface-container-low': '#141c24',
  'surface-container': '#222b33',
  'on-surface-variant': '#b9ccb2',
  'primary-fixed': '#72ff70',
  'primary-fixed-dim': '#00e639'
}
```

### JavaScript Conventions

- **ES5 syntax** (jQuery + ES5) for broad compatibility
- **IIFE not used** — global `window.appState`, `schemas`
- **jQuery** for DOM, AJAX, events
- **GSAP** for animations (`gsap.from()`, `gsap.to()`)
- **Flatpickr** for date/time inputs
- **Chart.js** for charts
- **Template literals** for HTML generation (backticks)
- **Event delegation** via `$(document).on('event', 'selector')`

---

## Testing Checklist

### Pre-Deployment

- [ ] `php -l` on all `.php` files (syntax check)
- [ ] `schema.sql` imports cleanly on fresh MariaDB
- [ ] `api.php?action=check_session` returns JSON
- [ ] Login → logout cycle works
- [ ] All 7 tabs load: Overview, Personal, Household, Improvements, Reference Data (5 sub-tabs)
- [ ] CRUD works for: Budget, Category, Transaction (with sub-items), Project, Zone, Improvement, Item (3 types), Vendor (2 types)
- [ ] Recurring transaction processing via `cron.php`
- [ ] Sub-items expand/collapse in transaction table
- [ ] Reference data: search, sort, pagination, CRUD
- [ ] Budget progress bars calculate correctly
- [ ] Sub-items expand in transaction rows
- [ ] Reference data dashboard analytics render
- [ ] Mobile responsive (test 375px, 768px, 1440px)
- [ ] Dark theme only (no light mode)
- [ ] Scanline overlay visible
- [ ] GSAP animations trigger on load/scroll

---

## Troubleshooting Guide

| Issue | Diagnosis | Fix |
|-------|-----------|-----|
| White screen / 500 | PHP error | Check `/var/log/apache2/plutus-error.log` |
| "FETCHING DATA..." hangs | API error | Check Network tab → `api.php` response |
| Login fails | Wrong credentials / DB | Check `db.php` credentials, user exists in `users` |
| Charts blank | Chart.js not loaded | Check Network tab for `chart.js`, console for JS errors |
| Date picker broken | Flatpickr init failed | Check `flatpickr.min.js` loaded, console for `$().flatpickr is not a function` |
| Sub-items not expanding | JS error in `renderTransactionTab` | Console for errors, check `t.sub_items` exists |
| Charts not updating | Chart instance not destroyed | Call `chart.destroy()` before re-creating |
| Session drops | PHP session config | Check `session.gc_maxlifetime`, cookie domain |
| DB connection fails | Credentials / socket | Check `db.php` credentials, `unix_socket` path |

---

## Reconstruction Checklist (From Zero)

If the entire `/var/www/plutus.invigor.com` is deleted:

- [ ] Provision server (Ubuntu + Apache + PHP + MariaDB)
- [ ] Create `plutus_thoughts` DB + `zeon7_user`
- [ ] Run `schema.sql`
- [ ] Deploy `/var/www/plutus.invigor.com/` with all files
- [ ] Configure Apache vhost + SSL
- [ ] Set permissions (`www-data:www-data`)
- [ ] Test login → all tabs → CRUD operations
- [ ] Configure cron job for `cron.php`
- [ ] Verify Apache logs clean
- [ ] Document any local customisations

---

## Key Files Reference

| File | Purpose |
|------|---------|
| `index.php` | SPA shell, auth gate, modals, script loading |
| `api.php` | All JSON endpoints, DB operations |
| `db.php` | PDO connection singleton |
| `cron.php` | Recurring transaction processor |
| `schema.sql` | Canonical schema (source of truth) |
| `assets/js/app.js` | Core SPA logic (~1900 lines) |
| `assets/js/transaction_ui.js` | Transaction-specific UI helpers |
| `assets/js/tailwind-config.js` | Tailwind theme extension |
| `assets/css/pages.css` | Page-specific styles |
| `assets/css/components.css` | HUD component utilities |
| `db.php` | DB connection config |
| `schema.sql` | Canonical schema dump |

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-07 | Initial deployment: budgets, transactions, categories, reference data, improvements |
| 1.1 | 2026-07 | Added sub-items (receipt breakdowns), recurring transactions, reference data dashboard |
| 1.2 | 2026-07 | Added improvements/projects/zones, reference data dashboard analytics, system health alerts |

---

## Support Contacts

- **Architecture/Design**: ForeverBox Council (Zeon7, Leon, Gemma, Otec)
- **Infrastructure**: Merrill Leo (server admin)
- **Codebase**: `/var/www/plutus.invigor.com/` on Wales Hub

---

*Built on the ForeverBox Design System. Neon-green on charcoal. Scanlines always watching.*

---

**End of Build Blueprint**
---

## Architecture Update — 2026 (Phases 1–5)

*This section supersedes the earlier architecture description where they differ. It documents the modular structure introduced by the 2026 update.*

### Directory Structure (post-update)

```
/var/www/plutus.invigor.com/
├── api.php                    # legacy entry point (thin wrapper -> api/api.php)
├── index.php                  # SPA shell (modals, PWA links, CSRF meta)
├── db.php                     # PDO connection
├── RateLimiter.php            # file-based window + token-bucket limiter
├── AuditLog.php               # legacy include (-> api/utils/AuditLog.php)
├── api/
│   ├── api.php                # modular dispatcher (bootstrap -> middleware -> route)
│   ├── v1.php                 # versioned entry point (/api/v1/)
│   ├── bootstrap.php          # session, CSRF token, shared includes
│   ├── routes.php             # declarative route table
│   ├── .htaccess              # /api/v1/<action> rewrite
│   ├── middleware/
│   │   ├── Middleware.php     # interface
│   │   ├── CsrfMiddleware.php
│   │   └── RateLimitMiddleware.php
│   ├── controllers/
│   │   ├── AuthController.php
│   │   ├── ObjectController.php   # universal CRUD + soft deletes
│   │   ├── DashboardController.php
│   │   ├── ExportController.php   # CSV/JSON/PDF
│   │   ├── BankImportController.php # CSV parse/duplicate/confirm
│   │   ├── CurrencyController.php
│   │   └── ReceiptOcrController.php
│   └── utils/
│       ├── Response.php
│       ├── Validator.php          # rule-based validation
│       ├── AuditLog.php
│       └── CurrencyService.php    # ECB rates, 24h cache
├── scripts/
│   ├── verify_backup.php          # restore + integrity checks
│   ├── backfill_audit.php         # idempotent audit backfill
│   ├── anonymize_for_staging.php  # prod -> staging with PII scrubbed
│   ├── smoke_staging.sh           # 7-check deploy smoke test
│   ├── scheduled_export.php       # timeframe CSV/JSON + webhook/email
│   └── generate_types.php         # DB schema -> JSDoc typedefs
├── src/                           # Vite ES modules
│   ├── main.js
│   ├── state/appState.js
│   ├── api/api.js
│   ├── utils/format.js
│   ├── types/schema.js            # generated from DB
│   └── __tests__/                 # Vitest suites
├── e2e/                           # Playwright specs
├── tests/                         # PHPUnit suites
├── uploads/receipts/              # OCR uploads
├── runtime/                       # rate limit state, ECB cache (gitignored)
├── dist/                          # Vite build output (gitignored)
├── composer.json / phpunit.xml / phpstan.neon
├── package.json / vite.config.js / vitest.config.js / playwright.config.js
├── tsconfig.json
├── manifest.json / sw.js          # PWA
└── .github/workflows/ci.yml
```

### Request Flow (modular)

```
client -> api.php (legacy) or api/v1.php (versioned)
        -> bootstrap.php (session + CSRF token)
        -> middleware chain (CsrfMiddleware, optional RateLimitMiddleware)
        -> routes.php lookup (action -> controller/handler)
        -> controller method
        -> Response helpers (response/errorResponse)
```

### Testing Stack

| Layer | Tool | Coverage |
|-------|------|----------|
| PHP unit | PHPUnit 10 (`vendor/bin/phpunit`) | 12 tests, 33 assertions |
| PHP static analysis | PHPStan level 5 (`vendor/bin/phpstan analyse`) | 0 errors |
| JS unit | Vitest (`npm test`) | 13 tests, 100% on extracted modules |
| JS types | TypeScript checkJs (`npx tsc --noEmit`) | clean |
| E2E | Playwright (`npm run e2e`, against staging) | login, dashboard, shortcuts |
| Deploy smoke | `scripts/smoke_staging.sh` | 7 checks incl. anonymisation guard |

### CI Pipeline (GitHub Actions)

`.github/workflows/ci.yml` runs on push/PR to `main`:

1. PHP: `php -l` all files → PHPStan → PHPUnit
2. JS: ESLint → `tsc --noEmit` → Vite build → artifact upload

### Database Migrations (applied)

| Migration | Content |
|-----------|---------|
| `001_audit_log.sql` | audit_log table |
| `002_soft_deletes.sql` | deleted_at on 9 entity tables |
| `004_integrity.sql` | CHECK + UNIQUE constraints |
| `005_currency.sql` | currency CHAR(3) on budgets, transactions |
| `006_indexes.sql` | 16 performance indexes |

### Environment Variables (optional)

| Variable | Used by |
|----------|---------|
| `PLUTUS_EXPORT_WEBHOOK` | scheduled_export.php delivery |
| `PLUTUS_EXPORT_EMAIL` | scheduled_export.php mail delivery |
| `PLUTUS_BACKUP_WEBHOOK` | verify_backup.php failure alerts |

### Operations

- **Daily backup**: 03:00 dump (gzip, 30-day retention), 03:15 cleanup, 03:30 verify restore.
- **Weekly export**: Sunday 04:00 CSV to `/foreverbox_data/exports/plutus/`.
- **Staging refresh**: `php scripts/anonymize_for_staging.php` then rsync code; smoke test after deploy.
- **Type regeneration**: after any migration, run `php scripts/generate_types.php`.

---

**End of Build Blueprint — 2026 Update**

---

## Architecture Update — Phase 2 Upgrades (2026-08-01)

*This section documents the Phase 2 architectural changes. It supersedes earlier descriptions where they differ.*

### Database Changes

**`transactions` table — planned transaction support:**

| Column | Type | Purpose |
|--------|------|---------|
| `projected_amount` | DECIMAL(10,2) NULL | The planned/projected cost when the transaction was pre-planned |
| `status` | ENUM('planned','spent') DEFAULT 'spent' | Whether the row is a forward plan or actual spend |

`amount` was changed from NOT NULL to NULL so planned rows can exist without an actual cost. Planned rows: `status='planned'`, `amount=NULL`, `projected_amount` set. Spent rows from a plan: `status='spent'`, `amount` = actual, `projected_amount` retained. Normal unplanned transactions: `status='spent'`, `projected_amount=NULL` (fully backward compatible).

**`tasks` table — spend-task support:**

| Column | Type | Purpose |
|--------|------|---------|
| `type` | ENUM(...,'spend') | New 'spend' value for spend-tasks |
| `projected_cost` | DECIMAL(10,2) NULL | Projected cost of the recurring spend |
| `actual_cost` | DECIMAL(10,2) NULL | Actual cost when marked spent |
| `spent_at` | DATETIME NULL | When the task was completed |
| `recurrence_type` | ENUM('none','daily','weekly','biweekly','monthly','yearly') | Recurrence frequency |
| `recurrence_days` | VARCHAR(14) NULL | Comma-separated weekdays (weekly only) |
| `recurrence_time` | TIME NULL | Time of day |
| `recurrence_count` | INT UNSIGNED NULL | NULL = continuous, N = fixed instances |
| `recurrence_completed` | INT UNSIGNED DEFAULT 0 | Completed instances counter |
| `paused_at` | DATETIME NULL | Pause state |
| `google_calendar_id` / `google_recurring_id` | VARCHAR(255) NULL | Google Calendar event references |

**`users` table:** `google_access_token`, `google_refresh_token`, `google_token_expires` columns store OAuth credentials (were already present from Phase 4 groundwork).

### New Controllers and Services

| File | Purpose |
|------|---------|
| `api/controllers/TaskController.php` | Spend-task lifecycle: complete/pause/resume/stop, next-instance generation, google_auth + oauth_callback |
| `api/utils/GoogleCalendarService.php` | Google Calendar API v3 wrapper: OAuth flow, token storage/refresh, recurring event CRUD, RRULE builder. Degrades gracefully (`isConfigured()` false) when credentials absent |
| `api/controllers/PeriodsController.php` | Phase 6 period lists (data-driven days/weeks/months, last/next 5 years) |

### New API Actions

| Action | Method | Purpose |
|--------|--------|---------|
| `complete_task` | POST | Record actual cost, optionally create transaction (projected + actual), generate next recurrence instance |
| `pause_task` | POST | Set paused_at, remove Google recurrence rule |
| `resume_task` | POST | Clear paused_at, re-add Google recurrence rule |
| `stop_task` | POST | Set status='cancelled', delete Google event series |
| `google_auth` | GET | Return OAuth consent URL (auth required) |
| `oauth_callback` | GET | Exchange OAuth code for tokens, store, redirect |
| `get_periods` | GET | Period lists per granularity (Phase 6) |

### Dashboard Response Additions

| Field | Meaning |
|-------|---------|
| `planned_total` | SUM of projected_amount for planned EXPENSE transactions in period |
| `planned_income_total` | SUM of projected_amount for planned INCOME transactions in period |
| `planned_transactions` | Array of planned transaction rows (both types, distinguished by `type`) |

### Key Logic Notes

- **Budget totals (`updateBudgetTotals`)**: only `status='spent'` transactions count toward cost_paid. Planned transactions never affect budget position.
- **complete_task next-instance generation**: increments recurrence_completed; creates a new task row for the next occurrence when recurrence is continuous or count not reached. Weekly-with-days computes the strictly-next matching weekday.
- **Validation**: full required-field validation applies on CREATE only; partial updates (mark-spent, single-field edits) skip base required checks. Planned transactions require projected_amount; spent transactions require amount.
- **Google credentials**: read from environment variables `PLUTUS_GOOGLE_CLIENT_ID` / `PLUTUS_GOOGLE_CLIENT_SECRET` only (no hardcoded constants). OAuth redirect URI is `https://<host>/api.php?action=oauth_callback`. When unconfigured, all GoogleCalendarService methods return false and the UI shows CALENDAR: DISCONNECTED.


## Architecture Update — Build Plan V4.0 (2026-08-05)

### Database Schema Alignment
- **`transactions` table**: Missing legacy columns were permanently added to `schema.sql`: `deleted_at`, `status`, `projected_amount`, `currency`, `account_id`.
- **Performance Indexes**: High-usage lookup queries were optimized by applying indexes to live MariaDB: `idx_date`, `idx_deleted`, `idx_status`.
- **Enum Synchronization**: The `budgets.type` enum was aligned perfectly with frontend validation as singular `'improvement'`.

### Backend & API Optimizations
- **N+1 Query Elimination**: The `DashboardController` and `ObjectController` were refactored to remove per-row sub-item fetches. They now execute a single, batched query (`WHERE transaction_id IN (...)`) mapped via PHP associative arrays, significantly reducing database load on high-volume tabs.
- **Parameterized Metadata Filtering**: `ObjectController::getMetadata()` now parses the `?entities=category,vendor` query string. The API only executes and returns schemas for the explicitly requested tables, drastically reducing payload sizes for targeted component refreshes.
- **Centralized Date Parsing**: Duplicate date boundary calculations were extracted into `api/utils/DateFilter.php::periodFilter()`, creating a DRY standard shared across `DashboardController` and `ExportController`.
- **Bank Import Duplicate Optimization**: In `BankImportController`, duplicate candidate searches now pre-filter rows natively in SQL (`WHERE DATE(date) BETWEEN minDate AND maxDate`) prior to running expensive fuzzy-matching string logic (`similar_text`) in memory.

### Frontend Component & Motion Architecture
- **`renderDataTable(config)`**: A unified JS renderer factory that manages column alignment, sorting headers, empty-state rendering, and click event bubbling across arbitrary data sets.
- **Non-Blocking UI System**: 
  - `showToast(msg, type)` uses GSAP to construct and slide in temporary DOM notifications.
  - `showConfirm(title, msg, onConfirm)` manages a dedicated modal overlay, hijacking confirmations to keep users in the HUD ecosystem.
- **Tailwind Extension Strategy**: Core background overrides (like the dark radial gradient) were shifted out of standard CSS and integrated directly into the `tailwind-config.js` (`backgroundImage: { 'hud-gradient': ... }`) to establish Tailwind as the singular styling source of truth.
