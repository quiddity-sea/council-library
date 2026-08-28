# Plutus V6: PJAX Hybrid Architecture

This plan addresses the architectural need for a multi-page PHP application (for better SEO, caching, and server-side management) while strictly preserving the rich UX, intricate cyber-HUD sidebars, and GSAP animations of the original V4 SPA.

## Goal Description
The previous V5 architecture correctly moved data fetching and HTML rendering to the server, but failed to preserve the complex frontend UI templates and the deliberate GSAP animations that manage user expectations during data loads. 

This V6 plan introduces a **PJAX (PushState + AJAX) Hybrid Architecture**. The application will function as a true multi-page site (e.g., `/overview`, `/reference`), but `app.js` will intercept navigation to provide smooth GSAP exit/loading/entrance sequences, fetching only the required HTML fragments from the server without full page reloads.

## Proposed Changes

### 1. Front Controller & Routing (`index.php`)
- **[MODIFY] [index.php](file:///var/www/plutus.invigor.com/index.php)**
  - Implement a basic routing system to handle URLs like `?page=overview`, `?page=reference`.
  - If the request is a standard browser load, `index.php` will output the full HTML shell (Header, Navigation) and include the requested page's view template.
  - If the request is an AJAX call (identified by a custom header or `?ajax=1`), `index.php` will *only* output the HTML fragment for that specific view.

### 2. View Templates (`views/pages/*.php`)
We will extract the exact HTML structures from the original `app.js` and convert them into PHP view files. This ensures the sidebars and layouts remain pixel-perfect.
- **[NEW] [overview.php](file:///var/www/plutus.invigor.com/views/pages/overview.php)**: Restores the cyber-HUD scope charts and metric grids.
- **[NEW] [reference.php](file:///var/www/plutus.invigor.com/views/pages/reference.php)**: Restores the 1-column sidebar / 3-column content grid and sub-tabs.
- **[NEW] [transactions.php](file:///var/www/plutus.invigor.com/views/pages/transactions.php)**: A reusable template for Personal and Household tabs.
- **[NEW] [improvements.php](file:///var/www/plutus.invigor.com/views/pages/improvements.php)**

### 3. Server-Side Data Fetching (`controllers/PageController.php`)
- **[NEW] [PageController.php](file:///var/www/plutus.invigor.com/api/controllers/PageController.php)**
  - Responsible for executing the exact queries that `DashboardController.php` used to do, but passing the variables directly to the `include`d view templates.

### 4. PJAX Navigation & GSAP Animations (`app.js`)
- **[MODIFY] [app.js](file:///var/www/plutus.invigor.com/assets/js/app.js)**
  - Intercept clicks on `.nav-tab` elements.
  - Execute a GSAP **Exit Animation** (fading out the current `#dashboard-content`).
  - Display the `"FETCHING DATA..."` loading state.
  - Fetch the new HTML fragment via `fetch('?page=...&ajax=1')`.
  - Update the browser URL using `history.pushState`.
  - Inject the HTML fragment into `#dashboard-content`.
  - Execute a GSAP **Entrance Animation** (staggered fade-in of elements).

## User Review Required

> [!IMPORTANT]
> **URL Structure**: This plan uses query parameters for routing (e.g., `index.php?page=overview`). If you want clean URLs (e.g., `/overview`), we will need to ensure an `.htaccess` file is set up to rewrite requests to `index.php`. Please let me know your preference.

> [!WARNING]
> **Sub-tabs (e.g. Reference Data)**: Should the sub-tabs within Reference Data (Products, Services, Custom) also be distinct URLs (e.g. `?page=reference&sub=products`), or should they remain purely JS-driven within the Reference Data page?

## Verification Plan

### Manual Verification
1. Click between top-level tabs and verify that the URL updates dynamically.
2. Verify that the screen fades out, shows the loading text, and fades the new content in smoothly using GSAP.
3. Refresh the page on a specific tab (e.g., `?page=reference`) and verify the server immediately returns the full page in the correct state without a second JS load.
4. Confirm visually that all sidebars and intricate layout elements match the original V4 design.
