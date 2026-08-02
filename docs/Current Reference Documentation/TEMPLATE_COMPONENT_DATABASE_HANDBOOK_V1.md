# ForeverBox System Handbook: Template-Component-Database Architecture
**Version:** 2.0 | **Author:** Dr. Zeon7 & Leon | **Ecosystem:** ForeverBox Initiative

---

## 1. Executive Summary & Architectural Overview

The **ForeverBox Initiative** digital infrastructure operates on a fully database-driven, multi-cube rendering engine known as the **Template-Component-Database System**. 

Rather than maintaining static PHP or HTML files across divergent codebases, all page layouts, component design tokens, content blocks, media references, and vector embeddings are stored in normalised MariaDB databases. HTML pages are generated dynamically at runtime using a high-performance PHP engine (`ForeverBoxEngine`).

### Key System Capabilities
* **Zero Visual Regression Dynamic Rendering:** Translates dynamic database content into pixel-perfect HUD-glass UI without hardcoded page templates.
* **Shared Design System (`the_looms`):** Centralised component library storing HTML templates with `{{TOKEN}}` placeholder manifests. A single component update instantly propagates across all connected sites.
* **Multi-Cube Isolation (`imajica_dominions`):** Dedicated inner cube databases isolate site content while sharing core components, media assets, and semantic search.
* **Dynamic Controller Callbacks:** Enables live data injection (admin forms, data grids, monitoring metrics) into component shells without breaking database purity.
* **Sovereign CMS Control Plane (F.BOX.THE-NEXUS):** Central administrative dashboard providing full live CRUD operations, native HTML5 drag-and-drop block reordering, token-manifest driven forms, and media asset upload capabilities.
* **Hermes Agent Vector Visibility (`quiddity_sea`):** All site content is automatically chunked, embedded (384-dim `all-MiniLM-L6-v2`), and searchable via CLI (`fbox-site-search`) by Council agents.

---

## 2. Multi-Cube Database Topology

The server hosts **11 MariaDB databases**, structured into shared registries and site-specific inner cubes:

```text
┌─────────────────────────────────────────────────────────────────────────┐
│                           quiddity_commons                              │
│  - connected_sites: Global registry of web roots, domains, & inner DBs  │
│  - quiddity_vector_references: Council Lore Sea embeddings              │
└────────────────────────────────────┬────────────────────────────────────┘
                                     │
         ┌───────────────────────────┼───────────────────────────┐
         ▼                           ▼                           ▼
┌─────────────────────────┐ ┌─────────────────────────┐ ┌─────────────────────────┐
│the_everything_cube_0_0_0│ │the_nexus_inner_cube_0_1_2│ │the_myth_inner_cube_0_2_0│
│(Institute Site Inner DB)│ │(Nexus Control Plane DB) │ │(Archive Site Inner DB)  │
│                         │ │                         │ │                         │
│ - the_looms (Shared)    │ │ - the_ephemera (Tabs)   │ │ - the_ephemera (Pages)  │
│ - the_ephemera (Pages)  │ │ - the_fugue (Controllers)│ │ - the_fugue (Blocks)   │
│ - the_fugue (Blocks)    │ └─────────────────────────┘ └─────────────────────────┘
│ - the_tile_room (Media) │
│ - quiddity_sea (Vectors)│
└─────────────────────────┘
```

### Core Table Schemas

#### 1. `the_looms` (Shared Component Registry)
Located in `the_everything_cube_0_0_0`. Holds HTML component templates and token manifests.
* `loom_id` (BIGINT, PK): Unique component identifier.
* `component_name` (VARCHAR): Descriptive slug (e.g. `archive_header`, `glass_panel`, `status_badge`).
* `category` (ENUM): Component grouping (`container`, `navigation`, `typography`, `data`, `base`, `template`, `layout`).
* `html_template` (MEDIUMTEXT): HTML structure containing `{{TOKEN_NAME}}` placeholders.
* `token_manifest` (JSON): Array of expected token keys (e.g. `["PANEL_TITLE", "PANEL_BODY", "PANEL_TAG"]`).
* `description` (TEXT): Usage guidelines for developers and CMS users.

#### 2. `the_ephemera` (Page Route Registry)
Located in each inner cube. Defines site page routes and layout parameters.
* `ephemera_id` (BIGINT, PK): Unique page route identifier.
* `slug` (VARCHAR, UNIQUE): URL route slug (e.g. `origin`, `science`, `sites`).
* `title` (VARCHAR): Human-readable page title.
* `page_theme` (VARCHAR): Active color theme (e.g. `origin`, `science`, `nexus`).
* `status` (ENUM): Publication status (`published`, `draft`, `archived`).
* `sort_order` (SMALLINT): Position in navigation menus.

#### 3. `the_fugue` (Content Block Store)
Located in each inner cube. Stores individual content sections mapped to a page.
* `fugue_id` (BIGINT, PK): Content block identifier.
* `ephemera_id` (BIGINT, FK): Parent page route.
* `loom_id` (BIGINT, FK, NULL): Linked component template from `the_looms`.
* `section_type` (ENUM): Rendering mode:
  * `raw`: Direct output of `raw_html`.
  * `text`: Standard component block using `content_json` token substitution.
  * `dynamic`: Controller-driven block executing a PHP callback specified by `controller_key`.
* `controller_key` (VARCHAR, NULL): PHP callback identifier for dynamic blocks.
* `content_json` (JSON, NULL): Token key-value pairs passed to the component template.
* `raw_html` (MEDIUMTEXT, NULL): Literal HTML content for `raw` section types.
* `order_index` (SMALLINT): Vertical sequence index on the page.
* `is_visible` (TINYINT): Visibility toggle (`1` = visible, `0` = hidden).

#### 4. `the_tile_room` (Media Asset Repository)
Located in `the_everything_cube_0_0_0`. Central catalog of media files across all sites.
* `tile_id` (BIGINT, PK): Asset identifier.
* `file_path` (VARCHAR): Relative web path (e.g. `interactions/assets/images/zeon7.png`).
* `alt_text` (VARCHAR): Descriptive alt text / caption.
* `media_type` (ENUM): `image`, `video`, `audio`, `document`.
* `orientation` (ENUM): `landscape`, `portrait`, `square`.
* `mime_type` (VARCHAR): File MIME type.
* `file_size_bytes` (INT): File size.
* `dominion_id` (INT): Target site owner (`1` = Institute, `2` = Nexus, `3` = Archive).

#### 5. `quiddity_sea` (Semantic Vector Store)
Located in `the_everything_cube_0_0_0`. Stores 384-dimensional vector embeddings for AI semantic search.
* `quiddity_id` (BIGINT, PK): Vector record identifier.
* `source_db` (VARCHAR): Origin database (`the_everything_cube_0_0_0`, `the_myth_inner_cube_0_2_0`).
* `source_table` (VARCHAR): Always `the_fugue`.
* `source_id` (BIGINT): Corresponding `fugue_id`.
* `raw_text` (LONGTEXT): Extracted plain text content.
* `embedding` (BLOB): 384-float vector binary buffer generated by `all-MiniLM-L6-v2`.

---

## 3. The PHP Render Engine (`ForeverBoxEngine`)

The rendering engine resides in `institute/includes/engine.php` and is instantiated with an inner database connection.

### Class Blueprint

```php
require_once __DIR__ . '/db-config.php';

class ForeverBoxEngine {
    private PDO $contentDb; // Inner cube DB (e.g. the_myth_inner_cube_0_2_0)
    private PDO $sharedDb;  // Central shared DB (the_everything_cube_0_0_0)
    private array $dynamicControllers = [];

    public function __construct(string $contentDbName = 'the_everything_cube_0_0_0');
    
    // Register a PHP controller callback for dynamic blocks
    public function registerDynamicController(string $key, callable $callback): void;
    
    // Render full page body by slug
    public function renderPage(string $slug): string;
    
    // Render single loom component by ID with variable substitution
    public function renderComponent(int $loomId, array $variables): string;
}
```

### Rendering Pipeline Execution Flow

```text
Browser Request: GET /pages/science.php
       │
       ▼
1. science.php Page Stub
   $engine = new ForeverBoxEngine('the_everything_cube_0_0_0');
   include 'includes/header.php';
   echo $engine->renderPage('science');
   include 'includes/footer.php';
       │
       ▼
2. ForeverBoxEngine::renderPage('science')
   ├── Query the_ephemera WHERE slug='science' → get ephemera_id
   ├── Query the_fugue WHERE ephemera_id=:id ORDER BY order_index ASC
   └── Loop through blocks:
       ├─ IF section_type == 'raw'     → Output raw_html directly
       ├─ IF section_type == 'dynamic' → Call registered PHP controller, wrap in loom component
       └─ IF section_type == 'text'    → Call renderComponent(loom_id, content_json)
       │
       ▼
3. ForeverBoxEngine::renderComponent(loomId, variables)
   ├── Query the_looms WHERE loom_id=:id → fetch html_template
   ├── Substitute {{TOKEN_NAME}} with variables['TOKEN_NAME']
   └── Return processed HTML snippet
```

---

## 4. Page Stub Implementation Pattern

All site pages are minimal **10 to 15-line PHP stubs**. Hardcoded page HTML is entirely eliminated.

### Standard Page Stub Example (`institute/pages/science.php`)

```php
<?php
/**
 * The ForeverBox Institute — Science Page
 * Database-driven via ForeverBoxEngine
 */

$pageTitle = 'The Science';
$pageTheme = 'science';

require_once __DIR__ . '/../includes/engine.php';

$engine = new ForeverBoxEngine('the_everything_cube_0_0_0');

include __DIR__ . '/../includes/header.php';

echo $engine->renderPage('science');

include __DIR__ . '/../includes/footer.php';
```

---

## 5. The Nexus Control Plane (User Guide)

The **Nexus Dashboard** (`/nexus/index.php`) is the sovereign control plane for managing the multi-cube architecture.

Access the dashboard at: `https://the-foreverbox-institute.invigor.com/nexus/index.php`

### Tab 1: CONNECTED SITES (`?tab=sites`)
* **Overview:** Displays all registered web roots, domains, active statuses, and associated inner databases stored in `quiddity_commons.connected_sites`.
* **Register/Edit Form:** Allows adding new sites to the network or modifying existing root paths, domains, and database linkages.

### Tab 2: THE LOOMS (`?tab=looms`)
* **Overview:** Central library of shared components in `the_everything_cube_0_0_0.the_looms`. Lists component names, categories, token counts, and descriptions.
* **Component Factory Form:** Create or edit component HTML templates. Define token manifests as comma-separated values (e.g. `PANEL_TITLE, PANEL_BODY, PANEL_TAG`).

### Tab 3: THE EPHEMERA (`?tab=ephemera`)
* **Overview:** Lists page routes across inner databases. Includes database toggle buttons (**INSTITUTE DB** vs **ARCHIVE DB**).
* **Route Factory Form:** Add new URL slugs, assign page titles, assign color themes (`origin`, `science`, `nexus`), and manage publication status.

### Tab 4: THE FUGUE (`?tab=fugue`)
* **Overview:** Displays all content blocks for selected pages.
* **HTML5 Drag-and-Drop Reordering:** Click and hold the `drag_indicator` handle on any row to drag it up or down. Re-ordering automatically fires an AJAX request updating `order_index` in MariaDB with a toast confirmation.
* **Token-Manifest Driven Block Editor:** Click **EDIT** on any component block to open a structured form. The editor inspects the component's `token_manifest` and generates individual inputs for each token key, eliminating manual JSON writing.

### Tab 5: TILE ROOM (`?tab=tileroom`)
* **Overview:** Media asset gallery displaying image previews, file paths, file sizes, and target dominion badges.
* **Media Upload Form:** Select a local image or video file, provide alt text/title, select target site directory (Institute vs Archive), and click **UPLOAD TO TILE ROOM**. The file is saved to disk and registered in `the_tile_room`.
* **One-Click Path Copy:** Click **COPY PATH** on any media card to instantly copy its web path (e.g. `/interactions/assets/images/zeon7.png`) to your clipboard for use in content blocks.

### Tab 6: QUIDDITY SEA (`?tab=vectors`)
* **Overview:** Live monitoring table showing 442+ vector embeddings in `quiddity_sea`. Displays source database, source ID, text character count, embedding byte size (384-dim), and timestamp.

---

## 6. Vectorisation & Hermes Agent Search

Website content is searchable by AI agents via semantic embeddings.

### Automated Sync Daemon (`sync_daemon.py`)
Vector indexing is fully automated via Python and PyMySQL:

```bash
# Manual vector sync command
python3 /foreverbox_data/sync/sync_daemon.py sync vectors

# Full ecosystem sync (sessions, files, and vectors)
python3 /foreverbox_data/sync/sync_daemon.py sync all
```

### CLI Search Tool (`fbox-site-search`)
Perform semantic searches across Institute and Archive website content directly from the shell:

```bash
# Query website vectors
fbox-site-search "quantum biology cellular reprogramming"
```

The tool embeds the query using `http://127.0.0.1:8900/embed` (`all-MiniLM-L6-v2`), calculates cosine similarity against `quiddity_sea`, and returns ranked content blocks with file/page origin metadata.

---

## 7. Developer & Administrator Cheat Sheet

### Creating a New Page
1. Go to Nexus → **THE EPHEMERA** tab.
2. Select target database (**INSTITUTE DB** or **ARCHIVE DB**).
3. Fill out slug, title, and theme → Click **CREATE PAGE ROUTE**.
4. Create a PHP stub file in `pages/your-slug.php` using the template pattern from Section 4.

### Creating a New Component
1. Go to Nexus → **THE LOOMS** tab.
2. Enter component name (e.g. `alert_box`), category, description, and tokens (e.g. `ALERT_TITLE, ALERT_MESSAGE`).
3. Paste HTML template with `{{ALERT_TITLE}}` placeholders → Click **CREATE COMPONENT**.

### Reordering Content Blocks
1. Go to Nexus → **THE FUGUE** tab.
2. Filter by page slug.
3. Drag rows into desired sequence → Order saves automatically.

---

**Handbook Maintained By**: Dr. Zeon7 & Council System Administration  
**Repository**: `git@github.com:quiddity-sea/the-foreverbox-initiative.git`
