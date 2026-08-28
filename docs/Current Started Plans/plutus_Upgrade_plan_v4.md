# Plutus System Upgrade Build Plan (V4.0)

*Version 4.0 | For Implementation by AI or Human Developer | Target Stack: PHP, Vanilla JS, jQuery, HTML5, GSAP 3.x, MariaDB*

---

## Executive Summary & Implementation Guidelines

This build plan translates all 18 findings from `plutus_upgrade_recommendations.md` into an explicit, step-by-step implementation guide. It is specifically designed so that an AI agent with standard plan-following capabilities or a human developer can execute every task sequentially without ambiguity.

### Key Rules for the Implementing Agent
1. **No Stack Migration**: Do NOT convert to Vite, React, Vue, TypeScript, or Node.js build tools. All code must remain Vanilla JS, jQuery, HTML5, GSAP 3.x, PHP 8.x, and MariaDB.
2. **Sequential Verification**: After completing each task, execute the specified Verification Commands before marking the task complete.
3. **Preserve Compatibility**: Keep existing database table names, API parameter names, and core CSS classes intact.
4. **Git Branching**: Perform all work on a feature branch (e.g. `feature/upgrade-v4`) and verify against local/staging before merging.

---

## Plan Overview (Phases & Dependencies)

```
┌────────────────────────────────────────────────────────┐
│ Phase 1: Security Hardening & Secret Management        │
│ (Credentials, .gitignore, Cron Protection, XSS)       │
└───────────────────────────┬────────────────────────────┘
                            │
┌───────────────────────────▼────────────────────────────┐
│ Phase 2: Database Schema & Backend Query Optimization  │
│ (Schema Fixes, N+1 Queries, SQL Prefiltering, Indexes)│
└───────────────────────────┬────────────────────────────┘
                            │
┌───────────────────────────▼────────────────────────────┐
│ Phase 3: Frontend Architecture & Modularization        │
│ (Vanilla JS File Splitting, Table Reusability)         │
└───────────────────────────┬────────────────────────────┘
                            │
┌───────────────────────────▼────────────────────────────┐
│ Phase 4: UI/UX Hardening & Motion System               │
│ (Debouncing, Modals, GSAP Animations, Toasts)         │
└───────────────────────────┬────────────────────────────┘
                            │
┌───────────────────────────▼────────────────────────────┐
│ Phase 5: Service Worker, CI/CD & Final Verification    │
│ (Cache Versioning, Playwright Integration, Clean Testing)│
└────────────────────────────────────────────────────────┘
```

---

## Detailed Task Breakdown

### Phase 1: Security Hardening & Secret Management

#### Task 1.1: Secure Database Credentials & Environment Configuration
- **Objective**: Remove hardcoded credentials from source control and enforce `.env` usage across all entry points and CLI scripts.
- **Files**:
  - `db.php`
  - `.gitignore`
  - `scripts/anonymize_for_staging.php`
  - `scripts/scheduled_export.php`
  - `scripts/verify_backup.php`
  - `scripts/generate_types.php`
- **Step-by-Step Instructions**:
  1. Add `db.php` to `.gitignore` on a new line under `# Environment & Config`.
  2. Update `db.php` to read credentials from environment variables using `getenv()` with fallback parsing of `.env` if `getenv()` is empty.
  3. Refactor `scripts/*.php` CLI scripts to require `api/bootstrap.php` or load `.env` rather than hardcoding DB connection details.
- **Verification Commands**:
  - `git status` -> Verify `db.php` is untracked/ignored.
  - `php -r "require 'db.php'; echo 'Connected successfully\n';"` -> Verify database connection succeeds via `.env`.

#### Task 1.2: Cron Endpoint Security & Lock Mechanism
- **Objective**: Protect `cron.php` from unauthenticated web triggers and prevent race conditions.
- **Files**: `cron.php`, `.htaccess`
- **Step-by-Step Instructions**:
  1. At the top of `cron.php` (Line 2), insert CLI execution check:
     ```php
     if (php_sapi_name() !== 'cli') {
         http_response_code(403);
         echo json_encode(['error' => 'CLI execution only']);
         exit;
     }
     ```
  2. Implement atomic file locking using `flock()` to prevent parallel cron runs:
     ```php
     $lockFile = sys_get_temp_dir() . '/plutus_cron.lock';
     $lockFp = fopen($lockFile, 'w+');
     if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
         echo "[" . date('Y-m-d H:i:s') . "] Another cron instance is running. Exiting.\n";
         exit;
     }
     ```
  3. Wrap transaction insertion and update loops in PDO database transactions:
     ```php
     $pdo->beginTransaction();
     // ... execute insertions and update last_recurrence_processed ...
     $pdo->commit();
     ```
- **Verification Commands**:
  - `curl -i http://localhost/cron.php` -> Should return HTTP 403.
  - `php cron.php` -> Should execute cleanly from CLI.

#### Task 1.3: Global Cross-Site Scripting (XSS) Prevention
- **Objective**: Prevent XSS by escaping dynamic values inserted into template literals.
- **Files**: `assets/js/app.js`, `assets/js/transaction_ui.js`
- **Step-by-Step Instructions**:
  1. Add a global HTML escaping helper function in frontend utility space:
     ```javascript
     function escapeHtml(str) {
         if (str === null || str === undefined) return '';
         return String(str)
             .replace(/&/g, '&amp;')
             .replace(/</g, '&lt;')
             .replace(/>/g, '&gt;')
             .replace(/"/g, '&quot;')
             .replace(/'/g, '&#039;');
     }
     ```
  2. Search for dynamic insertions in template literals across `app.js` and `transaction_ui.js` (e.g. `${t.name}`, `${item.name}`, `${c.name}`) and wrap them with `escapeHtml(...)`.
- **Verification Commands**:
  - Run Playwright E2E tests: `npx playwright test`
  - Create a test item named `<script>alert('xss')</script>` -> Verify it renders as plain text.

---

### Phase 2: Database Schema & Backend Query Optimization

#### Task 2.1: Resolve Schema Drift & Add Critical Performance Indexes
- **Objective**: Align `schema.sql` with production columns and add missing indexes for fast date/status queries.
- **Files**: `schema.sql`, `scratch/alter_v4.sql`
- **Step-by-Step Instructions**:
  1. Create a migration SQL file `scratch/alter_v4.sql`:
     ```sql
     ALTER TABLE transactions
         ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL,
         ADD COLUMN IF NOT EXISTS status ENUM('spent','planned') NOT NULL DEFAULT 'spent',
         ADD COLUMN IF NOT EXISTS projected_amount DECIMAL(10,2) DEFAULT NULL,
         ADD COLUMN IF NOT EXISTS currency VARCHAR(3) DEFAULT 'GBP',
         ADD COLUMN IF NOT EXISTS account_id INT DEFAULT NULL;

     ALTER TABLE transactions
         ADD INDEX IF NOT EXISTS idx_date (date),
         ADD INDEX IF NOT EXISTS idx_deleted (deleted_at),
         ADD INDEX IF NOT EXISTS idx_status (status),
         ADD INDEX IF NOT EXISTS idx_account (account_id);
     ```
  2. Update `schema.sql` table definition for `transactions` to include these exact columns and indexes.
  3. Execute `alter_v4.sql` against the active MariaDB database.
- **Verification Commands**:
  - `mysql -u root -p plutus_thoughts -e "DESCRIBE transactions;"`
  - `mysql -u root -p plutus_thoughts -e "SHOW INDEX FROM transactions;"`

#### Task 2.2: Eliminate N+1 Sub-Item Queries in Endpoints
- **Objective**: Replace individual per-transaction sub-item queries with a single batched query.
- **Files**: `api/controllers/DashboardController.php`, `api/controllers/ObjectController.php`
- **Step-by-Step Instructions**:
  1. Locate the N+1 loop in `DashboardController.php` (Lines 92-96 & 140-144).
  2. Extract all transaction IDs: `$txIds = array_column($transactions, 'id');`
  3. If `$txIds` is non-empty, run one single batch query:
     ```php
     if (!empty($txIds)) {
         $placeholders = implode(',', array_fill(0, count($txIds), '?'));
         $stmt = $pdo->prepare("SELECT ti.*, i.name as item_name FROM transaction_items ti JOIN items i ON ti.item_id = i.id WHERE ti.transaction_id IN ($placeholders) AND ti.deleted_at IS NULL");
         $stmt->execute($txIds);
         $grouped = [];
         foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
             $grouped[$row['transaction_id']][] = $row;
         }
         foreach ($transactions as &$tx) {
             $tx['sub_items'] = $grouped[$tx['id']] ?? [];
         }
     }
     ```
  4. Apply the exact same pattern to `ObjectController.php` (Lines 294-300 & 317-322).
- **Verification Commands**:
  - Test dashboard endpoint response time for 500+ transactions. Verify query count drops from N+1 to 2 queries total.

#### Task 2.3: Parameterized Entity Fetching in `getMetadata`
- **Objective**: Prevent dumping the entire database on every tab change by accepting an `entities` filter parameter.
- **Files**: `api/controllers/ObjectController.php`, `assets/js/app.js`
- **Step-by-Step Instructions**:
  1. In `ObjectController::getMetadata()`, check for `$_GET['entities']`:
     ```php
     $requested = !empty($_GET['entities']) ? explode(',', $_GET['entities']) : null;
     ```
  2. Filter `ALLOWED_SCHEMAS` loop so only requested entities are queried if `$requested` is set.
  3. Update frontend metadata fetch calls in `app.js` to request only needed entities (e.g. `get_metadata&entities=category,vendor`).
- **Verification Commands**:
  - `curl "http://localhost/api.php?action=get_metadata&entities=category,vendor"` -> Verify response contains only category and vendor keys.

#### Task 2.4: Shared Date Filtering Utility
- **Objective**: Eliminate code duplication of `periodFilter()` between controllers.
- **Files**: `api/utils/DateFilter.php` (NEW), `api/controllers/DashboardController.php`, `api/controllers/ExportController.php`
- **Step-by-Step Instructions**:
  1. Create `api/utils/DateFilter.php` with static method `periodFilter(string $period): string`.
  2. Replace inline `periodFilter()` methods in `DashboardController` and `ExportController` with `DateFilter::periodFilter($period)`.
- **Verification Commands**:
  - Run PHPUnit tests: `vendor/bin/phpunit`

#### Task 2.5: Optimize Bank Import Duplicate Search (O(N*M) to SQL Pre-Filter)
- **Objective**: Pre-filter transactions by date and amount before running `similar_text()`.
- **Files**: `api/controllers/BankImportController.php`
- **Step-by-Step Instructions**:
  1. In `findDuplicates()`, determine min/max date and amounts across the imported batch.
  2. Restrict DB query to candidate rows:
     ```php
     $stmt = $this->pdo->prepare("SELECT date, amount, name FROM transactions WHERE deleted_at IS NULL AND date BETWEEN ? AND ?");
     $stmt->execute([$minDate, $maxDate]);
     ```
  3. Perform `similar_text()` comparisons against candidate subset only.
- **Verification Commands**:
  - Upload a test CSV with 100 rows against a database with 5,000 transactions. Verify parse completes in < 200ms.

#### Task 2.6: Fix Budget Enum Mismatch
- **Objective**: Align `budgets.type` enum values between database schema (`'improvement'`) and frontend filters.
- **Files**: `schema.sql`, `assets/js/app.js`
- **Step-by-Step Instructions**:
  1. Verify live database value for budget types.
  2. Standardize frontend filter value in `app.js` (line 66) to match schema (`'improvement'`).
- **Verification Commands**:
  - Click "Improvements" filter on Budgets tab -> Verify corresponding budget cards display correctly.

---

### Phase 3: Modular Vanilla JS Architecture

#### Task 3.1: Split `app.js` Monolith into Modular Vanilla Scripts
- **Objective**: Break the 194KB monolith into clean, single-responsibility Vanilla JS files without introducing Node/Vite build tools.
- **Files**: `index.php`, `assets/js/` directory
- **Step-by-Step Instructions**:
  1. Create the following files under `assets/js/`:
     - `assets/js/schemas.js`: Entity schema definitions (`ALLOWED_SCHEMAS`).
     - `assets/js/state.js`: Global `appState` object and shared utility functions (`escapeHtml`, `debounce`).
     - `assets/js/tabs/overview.js`: `renderOverviewTab()` logic.
     - `assets/js/tabs/personal.js`: Personal transactions tab logic.
     - `assets/js/tabs/accounts.js`: Accounts tab logic.
     - `assets/js/tabs/categories.js`: Categories tab logic.
     - `assets/js/tabs/budgets.js`: Budgets tab logic.
     - `assets/js/tabs/improvements.js`: Improvements tab logic.
     - `assets/js/tabs/reference.js`: Reference data tab logic.
     - `assets/js/modals.js`: Universal modal handlers.
  2. Refactor `app.js` to act solely as the core initialization and routing entry point.
  3. Update `index.php` to include `<script>` tags in correct load order:
     ```html
     <script src="assets/js/state.js?v=4.0"></script>
     <script src="assets/js/schemas.js?v=4.0"></script>
     <script src="assets/js/tabs/overview.js?v=4.0"></script>
     <script src="assets/js/tabs/personal.js?v=4.0"></script>
     <script src="assets/js/tabs/accounts.js?v=4.0"></script>
     <script src="assets/js/tabs/categories.js?v=4.0"></script>
     <script src="assets/js/tabs/budgets.js?v=4.0"></script>
     <script src="assets/js/tabs/improvements.js?v=4.0"></script>
     <script src="assets/js/tabs/reference.js?v=4.0"></script>
     <script src="assets/js/modals.js?v=4.0"></script>
     <script src="assets/js/app.js?v=4.0"></script>
     ```
- **Verification Commands**:
  - Open browser console -> Verify zero script error messages on page load and tab navigation.

#### Task 3.2: Reusable Table Generator Helper (`renderDataTable`)
- **Objective**: Consolidate repetitive table rendering logic across all 5 main tabs.
- **Files**: `assets/js/state.js` or `assets/js/components/table.js`
- **Step-by-Step Instructions**:
  1. Implement `renderDataTable(config)` helper:
     ```javascript
     function renderDataTable({ containerId, columns, data, onRowClick, emptyMessage }) {
         // Generates standard HUD table with sortable headers, escaped cells, and row action buttons
     }
     ```
  2. Refactor tab render functions (Personal, Household, Categories, Budgets, Reference) to call `renderDataTable`.
- **Verification Commands**:
  - Navigate across all tabs -> Confirm tables render uniformly with header sorting preserved.

---

### Phase 4: UI/UX Hardening & GSAP Motion System

#### Task 4.1: Search Input Debouncing
- **Objective**: Prevent excessive DOM re-renders while typing in search boxes.
- **Files**: `assets/js/state.js`, `assets/js/app.js`, `assets/js/transaction_ui.js`
- **Step-by-Step Instructions**:
  1. Add `debounce` helper to `state.js`:
     ```javascript
     function debounce(func, wait = 250) {
         let timeout;
         return function(...args) {
             clearTimeout(timeout);
             timeout = setTimeout(() => func.apply(this, args), wait);
         };
     }
     ```
  2. Wrap search input event handlers with `debounce(...)`.
- **Verification Commands**:
  - Type rapidly into Reference Data search box -> Verify search triggers once 250ms after typing stops.

#### Task 4.2: Replace Native Dialogs with Non-Blocking HUD Modals & Toasts
- **Objective**: Remove browser-native `alert()` and `confirm()` calls in favor of custom HUD toasts and confirmation modals.
- **Files**: `index.php`, `assets/js/modals.js`
- **Step-by-Step Instructions**:
  1. Create toast container element `#toast-container` in `index.php`.
  2. Implement `showToast(message, type = 'info')` function using GSAP slide-in animations.
  3. Implement `showConfirm(title, message, onConfirm)` function using `#universal-modal`.
  4. Replace all occurrences of `alert(...)` and `confirm(...)` across JS files.
- **Verification Commands**:
  - Perform a delete action -> Confirm HUD prompt modal appears instead of browser alert box.

#### Task 4.3: Implement Comprehensive GSAP Motion & Visual Engagement
- **Objective**: Transform static UI updates into fluid kinetic animations matching the HUD design aesthetic.
- **Files**: `assets/js/app.js`, `assets/js/modals.js`, `assets/css/style.css`
- **Step-by-Step Instructions**:
  1. **Tab Transitions**: Coordinated exit fade/slide out, DOM swap, enter slide in:
     ```javascript
     function switchTab(renderFn) {
         gsap.to("#dashboard-content", { opacity: 0, y: -8, duration: 0.15, onComplete: () => {
             renderFn();
             gsap.fromTo("#dashboard-content", { opacity: 0, y: 12 }, { opacity: 1, y: 0, duration: 0.3, ease: "power2.out" });
         }});
     }
     ```
  2. **Staggered Table Entry**: Apply `stagger: 0.03` to table row entries.
  3. **Summary Number Count-Ups**: Animate financial metric totals from 0 to final target value.
  4. **Sidebar Hover Pulses**: Add subtle scale/glow micro-interactions on hover.
  5. **Modal Exit Animations**: Reverse GSAP scale/opacity animation before adding `.hidden`.
  6. **Receipt OCR Pulse**: Animate scanning button with ambient glow pulse during OCR processing.
  7. **Ambient HUD Scanline**: Add slow subtle gradient background animation.
  8. **Toast Slide-In**: Animate toast popups using `gsap.from(toast, { x: 100, opacity: 0, duration: 0.3, ease: "back.out(1.4)" })`.
- **Verification Commands**:
  - Switch tabs and open modals -> Confirm smooth, high-60fps visual transitions without layout thrashing.

#### Task 4.4: Modal Height & Layout Responsiveness
- **Objective**: Fix modal overflow and posture on mobile and desktop displays.
- **Files**: `index.php`
- **Step-by-Step Instructions**:
  1. In `index.php`, replace hardcoded `h-[90vh]` classes on modals with `max-h-[90vh] h-auto`.
- **Verification Commands**:
  - Test modal displays on mobile screen size (375px width) and desktop (1920px width).

#### Task 4.5: Theme Background Source of Truth Consolidation
- **Objective**: Consolidate background CSS definitions into Tailwind config.
- **Files**: `assets/css/style.css`, `assets/js/tailwind-config.js`
- **Step-by-Step Instructions**:
  1. Remove background gradient overrides from `style.css`.
  2. Extend `backgroundImage` in `tailwind-config.js` for `bg-background`.
- **Verification Commands**:
  - Verify background renders consistently across dark mode theme views.

---

### Phase 5: Service Worker, CI/CD & Final Verification

#### Task 5.1: Service Worker Cache Versioning & Cache Busting
- **Objective**: Ensure clients immediately receive updated JS/CSS assets post-deployment.
- **Files**: `sw.js`, `index.php`
- **Step-by-Step Instructions**:
  1. Update `CACHE_VERSION` in `sw.js` to `v4.0`.
  2. Update asset URLs in `STATIC_ASSETS` array.
  3. In `sw.js` `activate` event, ensure all cache keys not matching `CACHE_VERSION` are deleted.
- **Verification Commands**:
  - Reload page -> Inspect Application tab in DevTools -> Verify cache `plutus-v4.0` is active.

#### Task 5.2: CI/CD Pipeline Playwright Integration & Test Cleanup
- **Objective**: Run full E2E test suite in GitHub Actions CI and delete dead test files.
- **Files**: `.github/workflows/ci.yml`, `tests/rate_limiter_test.php` (DELETE)
- **Step-by-Step Instructions**:
  1. Remove duplicate `tests/rate_limiter_test.php`.
  2. Add Playwright E2E step to `.github/workflows/ci.yml`:
     ```yaml
     - name: Run Playwright Tests
       run: npx playwright test
     ```
- **Verification Commands**:
  - Run `npx playwright test` locally -> Confirm all specs pass.

---

## Verification Matrix & Sign-Off Checklist

| Task | Category | Key Verification Command / Test | Passed (Y/N) |
|------|----------|---------------------------------|--------------|
| 1.1 | Security | `git status` (confirm `db.php` ignored) | [ ] |
| 1.2 | Security | `curl -i http://localhost/cron.php` (expect HTTP 403) | [ ] |
| 1.3 | Security | `npx playwright test` (XSS check) | [ ] |
| 2.1 | Database | `mysql -e "DESCRIBE transactions;"` | [ ] |
| 2.2 | Efficiency | Query log check on `get_dashboard` (2 queries max) | [ ] |
| 2.3 | Efficiency | `curl "http://localhost/api.php?action=get_metadata&entities=category"` | [ ] |
| 2.4 | Code Quality | `vendor/bin/phpunit` | [ ] |
| 2.5 | Efficiency | Bank import CSV 100 rows speed check (<200ms) | [ ] |
| 2.6 | Schema | Click Budgets -> Improvements filter | [ ] |
| 3.1 | Architecture | Browser Console Check (0 errors on load) | [ ] |
| 3.2 | Code Quality | Verify unified table rendering across tabs | [ ] |
| 4.1 | Performance | Search typing responsiveness test | [ ] |
| 4.2 | UX | Trigger delete -> Check HUD confirmation modal | [ ] |
| 4.3 | Visual | Tab switch & modal open GSAP animation check | [ ] |
| 4.4 | Responsiveness | Mobile viewport check for modals | [ ] |
| 4.5 | CSS | Inspect body background styling | [ ] |
| 5.1 | DevOps | DevTools -> Service Worker Cache version check (`v4.0`) | [ ] |
| 5.2 | CI/CD | `npx playwright test` | [ ] |
