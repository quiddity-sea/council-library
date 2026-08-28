# Plutus Upgrade Recommendations

## Executive Summary

Plutus is a functional, feature-rich personal finance tracker with a PHP/MySQL backend, vanilla JS frontend, and a polished HUD-style aesthetic. The core business logic is sound, and the data model is well-structured. However, the codebase has several structural issues that will compound as usage grows:

**Critical risks:**
1. A classic N+1 query problem in the two most-called endpoints (`get_dashboard`, `get_metadata`) will cause severe performance degradation as transaction volume scales.
2. `db.php` containing production credentials is missing from `.gitignore`, exposing the database to anyone with repository access.
3. The frontend is a single 194KB JavaScript monolith (`app.js`) that renders all HTML via template literals with zero output escaping — an XSS attack surface.

**Quick wins (high impact, low effort):**
- Add `db.php` and `cron.php` to `.gitignore` and move credentials to `.env` (30 min).
- Replace the N+1 sub_items loop with a single batched query (1 hour).
- Add an `escapeHtml()` utility and wrap all dynamic template insertions (2 hours).
- Add debounce to all search input handlers (15 min).

**Architectural debt:**
- `app.js` at 194KB / 2965 lines is unmaintainable. The `src/` directory contains the skeleton of a Vite migration that was never completed.
- `periodFilter()` is duplicated verbatim across `DashboardController.php` and `ExportController.php`.
- `cron.php` can be triggered via HTTP and has no lock mechanism, risking duplicate financial transactions.

---

## Actionable Recommendations

---

### 1. N+1 Sub-Items Query in Dashboard and Metadata Endpoints

* **File Path & Location**: `api/controllers/DashboardController.php` (Lines 92-96, 140-144), `api/controllers/ObjectController.php` (Lines 294-300, 317-322)
* **Category & Priority**: Efficiency (Critical)
* **Current Issue**: After fetching all transactions, the code loops through each one and fires an individual SELECT to get its transaction_items. With 500 transactions, this executes 501 queries per page load. The getMetadata endpoint is worse — it fetches every entity type and then runs this N+1 loop for every transaction in the entire database.
* **Proposed Solution**: Batch-fetch all sub-items in one query using `WHERE ti.transaction_id IN (...)`, then group them into a lookup map in PHP:

```php
$txIds = array_column($transactions, 'id');
if (!empty($txIds)) {
    $placeholders = implode(',', array_fill(0, count($txIds), '?'));
    $siStmt = $pdo->prepare("SELECT ti.*, i.name as item_name ... WHERE ti.transaction_id IN ($placeholders) ...");
    $siStmt->execute($txIds);
    $grouped = [];
    foreach ($siStmt->fetchAll() as $si) {
        $grouped[$si['transaction_id']][] = $si;
    }
    foreach ($transactions as &$tx) {
        $tx['sub_items'] = $grouped[$tx['id']] ?? [];
    }
}
```

* **Impact & Trade-Offs**: Reduces query count from O(N) to O(1). For 500 transactions, this eliminates ~500 queries per request.

---

### 2. Database Credentials Committed to Source Control

* **File Path & Location**: `db.php` (Lines 3-5), `.gitignore` (entire file)
* **Category & Priority**: Logic (Critical)
* **Current Issue**: `db.php` contains hardcoded credentials and is NOT listed in `.gitignore`. The `.env` file is correctly gitignored, but `db.php` bypasses it entirely. Additionally, the same credentials are hardcoded in `scripts/anonymize_for_staging.php`, `scripts/scheduled_export.php`, `scripts/verify_backup.php`, and `scripts/generate_types.php`.
* **Proposed Solution**:
  1. Add `db.php` to `.gitignore` immediately.
  2. Move all credentials into `.env` and have `db.php` read from `getenv()`.
  3. Refactor CLI scripts to `require_once __DIR__ . '/../api/bootstrap.php'` instead of duplicating credentials.
  4. Rotate the exposed credentials since they are already in git history.
* **Impact & Trade-Offs**: Eliminates full database access for anyone who clones the repository. Credential rotation is required since git history preserves the old values.

---

### 3. Cross-Site Scripting (XSS) via Unescaped Template Literals

* **File Path & Location**: `assets/js/app.js` (Lines ~1007, 1082, 1145, 2085, 2234), `assets/js/transaction_ui.js` (Line ~483)
* **Category & Priority**: Logic (Critical)
* **Current Issue**: User-provided data (`t.name`, `item.name`, `cleanName`, `c.name`, etc.) is injected directly into HTML via jQuery `.html()` with template literals. A transaction named `<img src=x onerror=alert(1)>` would execute JavaScript in any viewer's browser.
* **Proposed Solution**: Add a global utility:

```javascript
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
```

Wrap every dynamic value: `${escapeHtml(t.name.toUpperCase())}`.
* **Impact & Trade-Offs**: Closes the primary XSS vector. Marginal performance cost per render, negligible.

---

### 4. Monolithic Frontend Bundle (194KB Single File)

* **File Path & Location**: `assets/js/app.js` (Lines 1-2965)
* **Category & Priority**: Efficiency / Logic (Critical)
* **Current Issue**: All routing, rendering, schema definitions, API calls, state management, event handlers, and modal logic live in a single 194KB file. The `src/` directory contains the start of a Vite migration that was never completed.
* **Proposed Solution**: Complete the Vite migration. Extract into modules:
  - `schemas.js` — entity schema definitions (lines 58-120)
  - `state.js` — `appState` object
  - `api.js` — `$.get`/`$.post` wrappers
  - `tabs/overview.js`, `tabs/personal.js`, `tabs/accounts.js`, etc.
  - `components/table.js` — reusable table renderer
  - `components/modal.js` — modal management
* **Impact & Trade-Offs**: Enables tree-shaking, code splitting, and lazy loading. Reduces initial parse time.

---

### 5. Cron Job Accessible via HTTP, No Lock, No DB Transaction

* **File Path & Location**: `cron.php` (Lines 1-88)
* **Category & Priority**: Logic (Critical)
* **Current Issue**: `cron.php` sits in the web root and can be triggered by unauthenticated HTTP requests. `.htaccess` blocks `/scripts` but does not block `cron.php`. There is no file lock to prevent overlapping runs, and the insert + update loop (lines 48-80) is not wrapped in a database transaction.
* **Proposed Solution**:
  1. Add `if (php_sapi_name() !== 'cli') { http_response_code(403); exit; }` at line 2.
  2. Add file locking: `$lock = fopen('/tmp/plutus_cron.lock', 'w'); if (!flock($lock, LOCK_EX | LOCK_NB)) exit;`
  3. Wrap the inner loop in `$pdo->beginTransaction()` / `$pdo->commit()`.
* **Impact & Trade-Offs**: Prevents unauthorized triggers, duplicate transactions, and partial-write corruption. Purely additive hardening.

---

### 6. Unbounded getMetadata Endpoint Fetches Entire Database

* **File Path & Location**: `api/controllers/ObjectController.php` (Lines 304-327)
* **Category & Priority**: Efficiency (High)
* **Current Issue**: `getMetadata()` iterates over every entity type in `ALLOWED_SCHEMAS` and dumps the entire contents of each table into a single JSON response. This is called on every tab switch. With 1,000 transactions each having sub-items, this response could easily exceed 5MB.
* **Proposed Solution**: Make `getMetadata` accept an `entities` query parameter to fetch only the required types. For tabs that only need categories and vendors, call `?action=get_metadata&entities=category,vendor`.
* **Impact & Trade-Offs**: Reduces payload size by 80-95% for most tab switches. Small frontend coordination cost.

---

### 7. Duplicated periodFilter() Method

* **File Path & Location**: `api/controllers/DashboardController.php` (Lines 32-52), `api/controllers/ExportController.php` (Lines 66-82)
* **Category & Priority**: Logic (High)
* **Current Issue**: The `periodFilter()` method is copy-pasted verbatim between two controllers. Any date-filtering bug fix must be applied twice.
* **Proposed Solution**: Extract into a shared static utility `api/utils/DateFilter.php`.
* **Impact & Trade-Offs**: Single source of truth for date filtering. Eliminates divergent behaviour risk.

---

### 8. Bank Import Duplicate Detection Is O(N*M)

* **File Path & Location**: `api/controllers/BankImportController.php` (Lines 37-56)
* **Category & Priority**: Efficiency (High)
* **Current Issue**: `findDuplicates()` fetches ALL transactions and compares every imported row against every existing transaction using `similar_text()`. For 5,000 existing transactions and a 200-row import, this runs 1,000,000 string comparisons.
* **Proposed Solution**: Pre-filter with a SQL query using date range and amount, then only run `similar_text()` on the small candidate set returned.
* **Impact & Trade-Offs**: Reduces comparison count by orders of magnitude.

---

### 9. Missing Database Indexes on High-Traffic Columns

* **File Path & Location**: `schema.sql` (Lines 230-262)
* **Category & Priority**: Efficiency (High)
* **Current Issue**: The `transactions` table lacks indexes on `date`, `type`, `status`, and `deleted_at`. Every dashboard load runs date-range filters that result in full table scans. Additionally, `schema.sql` is missing several columns (`deleted_at`, `status`, `projected_amount`, `currency`, `account_id`) that the application code relies on.
* **Proposed Solution**: Add the missing columns and indexes to `schema.sql`. Run ALTER TABLE on the live database.
* **Impact & Trade-Offs**: Prevents full table scans. Fixes schema drift so fresh deployments work. Marginal insert overhead from indexes.

---

### 10. Missing Debounce on Search Inputs

* **File Path & Location**: `assets/js/app.js` (Lines ~918-920, 2128-2130), `assets/js/transaction_ui.js` (Lines ~228-235)
* **Category & Priority**: Efficiency (High)
* **Current Issue**: Every keystroke in a search input triggers immediate DOM re-rendering. Typing "laptop" fires 6 consecutive renders.
* **Proposed Solution**: Wrap input handlers in a `debounce(fn, 250)` utility.
* **Impact & Trade-Offs**: Eliminates UI jank. Barely perceptible 250ms delay feels natural.

---

### 11. Native alert() / confirm() Block the Main Thread

* **File Path & Location**: `assets/js/app.js` (Lines ~2653, 2658, 2728, 2815), `assets/js/transaction_ui.js` (Line ~351)
* **Category & Priority**: UX (Moderate)
* **Current Issue**: Error feedback and destructive action confirmations use native browser `alert()` and `confirm()`, which halt the main thread and break the HUD aesthetic immersion.
* **Proposed Solution**: Replace with styled, non-blocking toast notifications for errors and custom modal prompts for confirmations. The modal infrastructure already exists in `index.php`.
* **Impact & Trade-Offs**: Seamless, professional UX. Modal DOM scaffolding is already in place.

---

### 12. Service Worker Stale-Cache Strategy

* **File Path & Location**: `sw.js` (Lines 91-102)
* **Category & Priority**: Efficiency / UX (Moderate)
* **Current Issue**: Cache-first strategy for static assets without cache-busting hashes. After deployment, users see stale JS/CSS until hard-refresh.
* **Proposed Solution**: Add a version query string to `<script>` and `<link>` tags in `index.php` (e.g. `app.js?v=2.1`) and bump it on each deploy. Update the `CACHE_VERSION` constant in `sw.js` to match, so the `activate` event clears old caches. Alternatively, switch to a network-first strategy for the HTML entry point and primary JS files.
* **Impact & Trade-Offs**: Users always get the latest code after deployment. Small latency cost on first load with network-first, or a manual version bump step with query strings.

---

### 13. Duplicated Table Rendering Logic Across Tabs

* **File Path & Location**: `assets/js/app.js` (Lines ~1004-1019, 1245-1260, 2083-2092, 2232-2241)
* **Category & Priority**: Logic (Moderate)
* **Current Issue**: Table structure, row rendering, and header generation is heavily duplicated across Personal, Household, Accounts, Categories, and Budgets tabs.
* **Proposed Solution**: Create a reusable `renderDataTable(config)` function.
* **Impact & Trade-Offs**: Reduces code volume by ~200 lines and ensures visual consistency.

---

### 14. Modal Height Responsiveness Gaps

* **File Path & Location**: `index.php` (Lines ~320, 347, 365)
* **Category & Priority**: Aesthetics (Moderate)
* **Current Issue**: Modals use hardcoded `h-[90vh]`. On large screens with little content, this creates awkward empty space. On mobile, it stretches uncomfortably.
* **Proposed Solution**: Replace `h-[90vh]` with `max-h-[90vh] h-auto`.
* **Impact & Trade-Offs**: Better visual hierarchy across device sizes. No trade-offs.

---

### 15. CSS Theme Background Source-of-Truth Conflict

* **File Path & Location**: `assets/css/style.css` (Line ~22), `assets/js/tailwind-config.js` (Line ~7)
* **Category & Priority**: Aesthetics (Moderate)
* **Current Issue**: `style.css` enforces a radial gradient on the body element, which fights the Tailwind `bg-background` utility class. Two competing sources of truth.
* **Proposed Solution**: Remove the raw background styling from `style.css` and define the gradient in `tailwind-config.js`.
* **Impact & Trade-Offs**: Prevents visual anomalies. Simplifies theme changes.

---

### 16. CI Pipeline Missing E2E Tests and Dead Test Code

* **File Path & Location**: `.github/workflows/ci.yml`, `tests/rate_limiter_test.php`
* **Category & Priority**: Logic / DevOps (Moderate)
* **Current Issue**: CI omits Playwright E2E tests that exist in `e2e/`. Legacy `tests/rate_limiter_test.php` duplicates `tests/RateLimiterTest.php`.
* **Proposed Solution**: Add a Playwright job to `ci.yml`. Delete `tests/rate_limiter_test.php`.
* **Impact & Trade-Offs**: Catches UI regressions before deployment. Adds ~2 minutes to CI.

---

### 17. budgets.type Enum Out of Sync Between Schema and UI

* **File Path & Location**: `schema.sql` (Line 29), `assets/js/app.js` (Line 66)
* **Category & Priority**: Logic (Moderate)
* **Current Issue**: The budgets table in `schema.sql` defines type as `ENUM('personal','household','improvement')` (singular), but the frontend uses `'improvements'` (plural). The Improvements filter may return zero results on a fresh database.
* **Proposed Solution**: Align the enum. Check which value exists in the live database and update the other side to match.
* **Impact & Trade-Offs**: Prevents silent data mismatches.

---

### 18. GSAP Is Loaded But Barely Used — Major Visual Engagement Opportunity

* **File Path & Location**: `assets/js/app.js` (15 call sites across the file), `index.php` (Line 25 — loads full GSAP 3.12.2)
* **Category & Priority**: Aesthetics / UX (High)
* **Current Issue**: The full GSAP 3.12.2 library is loaded but used for only a single repetitive pattern — a basic `{y: 10, opacity: 0, duration: 0.4}` fade-in — copied 15 times across the codebase. No GSAP timelines, no ScrollTrigger, no morphing, no stagger sequences beyond two instances, no exit/leave animations, no interactive hover effects, and no attention-drawing animations for important state changes. The HUD aesthetic of the application is begging for kinetic energy, but the animations feel static and uniform.
* **Proposed Solution**: Expand GSAP usage across these specific areas (all vanilla JS, no framework changes):

  **A. Tab Transitions (enter + exit):** Currently tabs just snap-replace via `.html()`. Add coordinated exit → enter sequences:
  ```javascript
  function switchTab(renderFn) {
      gsap.to("#dashboard-content", { opacity: 0, y: -8, duration: 0.2, onComplete: () => {
          renderFn();
          gsap.from("#dashboard-content", { opacity: 0, y: 12, duration: 0.35, ease: "power2.out" });
      }});
  }
  ```

  **B. Staggered Table Row Entry:** When transaction rows or category lists load, stagger them in instead of appearing all at once:
  ```javascript
  gsap.from("#cat-list-container tr", { opacity: 0, x: -15, duration: 0.3, stagger: 0.04, ease: "power2.out" });
  ```

  **C. Summary Card Count-Up Animations:** The overview dashboard shows totals (£1,234.56). Animate the numbers counting up from 0 using GSAP:
  ```javascript
  gsap.from(el, { textContent: 0, duration: 1.2, ease: "power1.out", snap: { textContent: 0.01 },
      onUpdate: function() { el.textContent = "£" + parseFloat(el.textContent).toFixed(2); }
  });
  ```

  **D. Sidebar Button Hover Micro-Animations:** Add scale/glow pulses on sidebar nav hover:
  ```javascript
  document.querySelectorAll('.ref-sub-tab').forEach(btn => {
      btn.addEventListener('mouseenter', () => gsap.to(btn, { scale: 1.03, duration: 0.2 }));
      btn.addEventListener('mouseleave', () => gsap.to(btn, { scale: 1, duration: 0.2 }));
  });
  ```

  **E. Modal Entry/Exit:** Currently modals only animate in. Add a coordinated exit before `.addClass('hidden')`:
  ```javascript
  function closeModal(modalId) {
      gsap.to(`#${modalId} > div`, { y: 20, opacity: 0, scale: 0.97, duration: 0.25, onComplete: () => {
          $(`#${modalId}`).addClass('hidden');
      }});
  }
  ```

  **F. Receipt Scan Loading Pulse:** During OCR processing, animate the scan button or a progress indicator:
  ```javascript
  const pulse = gsap.to("#scan-btn", { boxShadow: "0 0 20px rgba(0,255,157,0.6)", repeat: -1, yoyo: true, duration: 0.8 });
  // On complete: pulse.kill();
  ```

  **G. HUD Scanline & Ambient Effects:** Add subtle ambient motion to the HUD scanline overlay or corner accents to make the interface feel alive even when idle:
  ```javascript
  gsap.to(".hud-border::before", { backgroundPosition: "0 100%", duration: 4, repeat: -1, ease: "none" });
  ```

  **H. Notification/Toast Slide-In:** When implementing the toast system (Finding #11), use GSAP for slide-in from the top-right with auto-dismiss:
  ```javascript
  gsap.from(toast, { x: 100, opacity: 0, duration: 0.4, ease: "back.out(1.4)" });
  gsap.to(toast, { opacity: 0, x: 50, delay: 3, duration: 0.3, onComplete: () => toast.remove() });
  ```

* **Impact & Trade-Offs**: Transforms the application from a static page-swap experience into a fluid, kinetic interface that matches the premium HUD aesthetic. The library is already loaded and paid for in bundle size — these additions are pure upside. The only risk is over-animating; keep durations short (0.2–0.5s) and reserve longer animations (1s+) for summary count-ups and first-load sequences only.
