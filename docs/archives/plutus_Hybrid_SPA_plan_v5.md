# Plutus System Upgrade Build Plan (V5.0) - Deliberate Hybrid Architecture

*Version 5.0 | For Implementation by AI or Human Developer | Target Stack: PHP 8.x, Vanilla JS, HTML5, GSAP 3.x, MariaDB*

---

## Executive Summary & Design Philosophy

This build plan outlines the migration of Plutus from a pure JavaScript-rendered SPA to a **Hybrid Multi-Page / AJAX Architecture**. 

**Core Design Philosophy:**
1. **Structural Integrity:** We are abandoning the 194KB JavaScript monolith in favor of clean, distinct PHP templates for every view and component. This ensures the codebase is strictly modular, highly debuggable, and easy to manage.
2. **Deliberate UX Pacing:** We are utilizing GSAP not just to fake a single-page feel, but to orchestrate deliberate, engaging user experiences. When a user navigates or requests heavy data, they will experience a choreographed sequence: an intentional exit animation, an engaging loading state (masking any network latency and making the system feel robust), followed by a smooth entrance animation.
3. **AJAX for Heavy Lifting:** While the backend serves pre-rendered PHP HTML, JavaScript and AJAX will be used to seamlessly inject this content and manage the data state, combining the best of traditional multi-page stability with modern kinetic UI.

### Key Rules for the Implementing Agent
1. **Strict Stack Adherence**: Do NOT introduce Node.js build tools, React, Vue, HTMX libraries, or TypeScript. This is a Vanilla JS + PHP templating refactor.
2. **Sequential Verification**: After completing each task, execute the specified Verification Commands before marking the task complete.
3. **Never Skip the Loading State**: The GSAP loading animations are deliberate UX choices. Even if an AJAX request returns instantly, the transition must run its course to maintain the pacing of the application.

---

## Detailed Task Breakdown

### Phase 0: Complete Unfinished V4 Security & UX Tasks

#### Task 0.1: Strictly Enforce `.env` for Database Credentials
- **Objective**: Ensure Plutus securely fails if environment variables are missing, removing all hardcoded fallback passwords from source control.
- **Files**: `db.php`
- **Step-by-Step Instructions**:
  1. Open `db.php`.
  2. Locate the hardcoded fallbacks and replace them with strict checks that throw an Exception if empty:
     ```php
     $host = getenv('DB_HOST');
     $db = getenv('DB_NAME');
     $user = getenv('DB_USER'); 
     $pass = getenv('DB_PASS'); 
     if (!$host || !$db || !$user || !$pass) {
         throw new \Exception("Database credentials missing from environment.");
     }
     ```

#### Task 0.2: Implement Universal GSAP Modals (Remove Native Alerts)
- **Objective**: Eliminate blocking browser `alert()` and `confirm()` dialogs in favor of HUD-styled GSAP toasts and modals.
- **Files**: `assets/js/modals.js` (NEW), `index.php`
- **Step-by-Step Instructions**:
  1. Create `assets/js/modals.js` and implement `window.showToast()` and `window.showConfirm()` using GSAP animations.
  2. Replace all remaining instances of native `alert()` and `confirm()` in the codebase.

---

### Phase 1: Templating Engine & Directory Setup

#### Task 1.1: Create Views Directory Structure
- **Objective**: Establish the folder hierarchy for PHP templates.
- **Step-by-Step Instructions**: Create `views/tabs`, `views/modals`, and `views/components`.

#### Task 1.2: Build the `ui.php` View Controller
- **Objective**: Create the API endpoint that will serve pre-rendered HTML fragments to the JavaScript frontend.
- **Files**: `ui.php` (NEW)
- **Step-by-Step Instructions**:
  1. Create `ui.php` to accept a `$_GET['view']` parameter.
  2. Build a `switch` statement that handles valid views. Inside each `case`, fetch the necessary data array using existing methods, assign it to a local variable, and `include` the corresponding PHP template from `views/`.

---

### Phase 2: Tab Extraction (Moving HTML to PHP)

#### Task 2.1: Extract All Tab HTML into PHP Views
- **Objective**: Port the HTML template literal strings from `app.js` into dedicated PHP files (`overview.php`, `budgets.php`, `categories.php`, etc.).
- **Files**: `views/tabs/*.php`
- **Step-by-Step Instructions**:
  1. Copy the HTML strings from `app.js` into their respective PHP files.
  2. Convert JavaScript template variables (`${metric.total}`) into PHP echo statements (`<?= htmlspecialchars($metric['total'] ?? '') ?>`).
  3. Loop over arrays using PHP `foreach` instead of JavaScript `.map()`.

---

### Phase 3: Modal & Component Extraction

#### Task 3.1: Transaction Modal Extraction
- **Objective**: Move the transaction creation form out of JavaScript.
- **Files**: `views/modals/transaction_modal.php`, `assets/js/transaction_ui.js`
- **Step-by-Step Instructions**: Move the HTML string from `window.openTransactionModal` into the PHP template, replacing JS variables with PHP `foreach` loops.

#### Task 3.2: Universal View Object Template
- **Objective**: Extract the HTML logic inside `window.viewObject`.
- **Files**: `views/modals/view_record.php`
- **Step-by-Step Instructions**: Move the grid-based HTML rendering from `viewObject()` in `app.js` into the PHP file.

---

### Phase 4: JavaScript Refactoring & The Deliberate UX Sequence

#### Task 4.1: Implement the Deliberate GSAP Transition Sequence
- **Objective**: Strip HTML from `app.js` and implement the orchestrated Exit -> Load -> Fetch -> Enter sequence.
- **Files**: `assets/js/app.js`
- **Step-by-Step Instructions**:
  1. Delete all `renderXTab()` functions from `app.js`.
  2. Rewrite `loadTabData(tabName)` to enforce the UX philosophy:
     ```javascript
     function loadTabData(tabName) {
         appState.currentTab = tabName;
         updateNavUI(tabName);
         
         // 1. Deliberate Exit Animation
         const tl = gsap.timeline();
         tl.to("#dashboard-content", { opacity: 0, scale: 0.98, duration: 0.3, ease: "power2.in" })
           .call(() => {
               // 2. Engaging Loading State
               $('#dashboard-content').html(`
                   <div class="flex flex-col items-center justify-center h-64">
                       <div class="loading-pulse-ring mb-4"></div>
                       <div class="font-code-label text-primary/70 tracking-widest text-xs animate-pulse">PROCESSING DATA PACKET...</div>
                   </div>
               `);
               gsap.to("#dashboard-content", { opacity: 1, scale: 1, duration: 0.2 });
           });

         // 3. AJAX Data Fetch (Heavy Lifting)
         const fetchPromise = $.get(`ui.php?view=${tabName}`);
         
         // Force a minimum loading time of 600ms so the UX feels deliberate and un-rushed
         const minTimePromise = new Promise(resolve => setTimeout(resolve, 600));

         Promise.all([fetchPromise, minTimePromise]).then(([htmlResponse]) => {
             // 4. Smooth Entrance Animation
             gsap.to("#dashboard-content", { opacity: 0, duration: 0.2, onComplete: () => {
                 $('#dashboard-content').html(htmlResponse);
                 bindTabEvents(tabName);
                 
                 // Staggered entrance for rows/cards
                 gsap.fromTo("#dashboard-content", { opacity: 0, y: 20 }, { opacity: 1, y: 0, duration: 0.4, ease: "back.out(1.2)" });
                 gsap.fromTo(".stagger-item", { opacity: 0, x: -10 }, { opacity: 1, x: 0, stagger: 0.05, duration: 0.3, ease: "power1.out" });
             }});
         }).catch(() => {
             showToast("Data packet corrupted.", "error");
         });
     }
     ```

#### Task 4.2: Preserve Instant Metadata Autocomplete
- **Objective**: Maintain instant client-side interactions where appropriate.
- **Files**: `assets/js/app.js`
- **Step-by-Step Instructions**: Ensure `appState.metadata` is loaded on initial page hit so local autocomplete fields (like Supplier dropdowns) do not trigger network latency, maintaining the snappy feel of the forms themselves.

---

## Verification Matrix & Sign-Off Checklist

| Task | Category | Key Verification Command / Test | Passed (Y/N) |
|------|----------|---------------------------------|--------------|
| 0.1 | Security | `php -r "require 'db.php';"` fails cleanly without `.env` | [ ] |
| 1.2 | Routing | `curl "http://localhost/ui.php?view=budgets"` returns 200 OK | [ ] |
| 2.1 | Templating | Inspect Network tab -> `ui.php?view=overview` returns HTML string | [ ] |
| 4.1 | Architecture| Check `app.js` file size (should be < 50KB) | [ ] |
| 4.1 | UX Pacing | Click Tabs -> confirm the Exit -> Load Pulse -> Entrance sequence runs perfectly | [ ] |
