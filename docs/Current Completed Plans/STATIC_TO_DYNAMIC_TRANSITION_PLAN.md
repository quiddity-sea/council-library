# Static-to-Dynamic Build Plan v1
## Milestone A: Faithful Reproduction

> **Reference Document:** `/var/www/the-foreverbox-institute/why-how-and-what-to-do.md`
> **Target:** Convert the current static HTML/PHP sites into database-driven dynamic sites with zero visual regression.

---

## Pre-Requisites & Environment

- **OS:** Ubuntu 24.04 running inside WSL2 on Windows
- **Web Server:** Apache2, PHP 8.3
- **Database:** MariaDB 11.8 (accessed via `wsl -u root bash -c "mariadb ..."` or via PHP PDO)
- **DB User:** `zeon7_user` (already has privileges on all relevant databases)
- **Site Root:** `/var/www/the-foreverbox-institute/`
- **Institute Site:** `/var/www/the-foreverbox-institute/institute/`
- **Archive Site:** `/var/www/the-foreverbox-institute/interactions/`
- **Databases (already created, currently empty):**
  - `the_everything_cube_0_0_0` — Shared components + Institute content
  - `the_myth_inner_cube_0_2_0` — Archive content
  - `the_initative_band_inner_cube_0_1_1` — The Band content (future)
  - `the_nexus_inner_cube_0_1_2` — Nexus data (future)

### Critical Rules

1. **DO NOT modify any CSS files** — `components.css`, `pages.css`, `header.css`, `sidebar.css`, `footer.css` must remain untouched
2. **DO NOT modify any `tailwind-config.js` files** — each site's design tokens are sacrosanct
3. **DO NOT modify `nav.js`** on either site unless absolutely necessary for routing
4. **DO NOT add new content** — only migrate content that currently exists in the static files
5. **The rendered HTML output of every page must be byte-for-byte identical** to what the current static files produce
6. **Create a Git safety branch** before making any file changes: `git checkout -b static-archive && git checkout main`

---

## Phase 1: Database Scaffolding

### Task 1.1: Create shared tables in `the_everything_cube_0_0_0`

Execute the following SQL via: `wsl -u root bash -c "mariadb the_everything_cube_0_0_0 < /path/to/script.sql"`

Create a file at `/var/www/the-foreverbox-institute/institute/config/db-files/cube_schema.sql`:

```sql
-- ═══════════════════════════════════════════════════════
-- THE EVERYTHING CUBE 0.0.0 — Shared Tables
-- ═══════════════════════════════════════════════════════

-- Component Templates (shared across all sites)
CREATE TABLE IF NOT EXISTS the_looms (
    loom_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    component_name  VARCHAR(128) NOT NULL UNIQUE,
    category        ENUM('base','container','data','typography','template','layout') NOT NULL,
    html_template   MEDIUMTEXT NOT NULL,
    token_manifest  JSON NOT NULL COMMENT 'Array of {{TOKEN}} names this component expects',
    css_dependencies TEXT NULL COMMENT 'Additional CSS classes this component requires',
    description     TEXT NULL,
    version         SMALLINT UNSIGNED DEFAULT 1,
    is_active       TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Media Assets (shared across all sites)
CREATE TABLE IF NOT EXISTS the_tile_room (
    tile_id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_path       VARCHAR(512) NOT NULL,
    alt_text        VARCHAR(512) NOT NULL,
    media_type      ENUM('image','video','audio','document') NOT NULL DEFAULT 'image',
    orientation     ENUM('portrait','landscape','square') NULL,
    mime_type       VARCHAR(64) NULL,
    file_size_bytes INT UNSIGNED NULL,
    dominion_id     INT UNSIGNED NULL COMMENT 'Which site owns this asset, NULL = shared',
    tags            JSON NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (media_type),
    INDEX idx_dominion (dominion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Design Tokens / Theme Config
CREATE TABLE IF NOT EXISTS the_reconciliation (
    token_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token_scope     ENUM('global','site','page') NOT NULL DEFAULT 'global',
    scope_key       VARCHAR(128) NULL COMMENT 'Site slug or page slug, NULL for global',
    token_name      VARCHAR(128) NOT NULL,
    token_value     TEXT NOT NULL,
    token_type      ENUM('color','font','spacing','shadow','animation','custom') NOT NULL,
    description     TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_scope_token (token_scope, scope_key, token_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vector embeddings for Hermes (populated in Milestone B)
CREATE TABLE IF NOT EXISTS quiddity_sea (
    quiddity_id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_table    ENUM('the_fugue','the_ephemera','the_looms','the_tile_room') NOT NULL,
    source_id       BIGINT UNSIGNED NOT NULL,
    source_db       VARCHAR(128) NOT NULL COMMENT 'Which inner cube database this came from',
    raw_text        MEDIUMTEXT NOT NULL,
    embedding       BLOB NULL COMMENT 'Vector embedding as binary, populated in Milestone B',
    semantic_tags   JSON NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_source (source_table, source_id),
    INDEX idx_db (source_db)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Task 1.2: Create per-site content tables

These tables must be created in BOTH `the_everything_cube_0_0_0` AND `the_myth_inner_cube_0_2_0`.

Create a file at `/var/www/the-foreverbox-institute/institute/config/db-files/content_schema.sql`:

```sql
-- ═══════════════════════════════════════════════════════
-- PER-SITE CONTENT TABLES
-- Run this against EACH inner cube database
-- ═══════════════════════════════════════════════════════

-- Sites / Namespaces
CREATE TABLE IF NOT EXISTS imajica_dominions (
    dominion_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_name       VARCHAR(128) NOT NULL UNIQUE,
    site_slug       VARCHAR(64) NOT NULL UNIQUE,
    domain_url      VARCHAR(255) NOT NULL,
    web_root_path   VARCHAR(512) NOT NULL,
    theme_config    JSON NULL COMMENT 'Per-site CSS variable overrides',
    description     TEXT NULL,
    is_active       TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pages / Routes
CREATE TABLE IF NOT EXISTS the_ephemera (
    ephemera_id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dominion_id     INT UNSIGNED NOT NULL,
    slug            VARCHAR(128) NOT NULL,
    title           VARCHAR(255) NOT NULL,
    meta_description TEXT NULL,
    page_theme      VARCHAR(64) NULL COMMENT 'CSS theme class name (e.g. science, cases, fit)',
    theme_overrides JSON NULL COMMENT 'Per-page CSS variable overrides',
    sort_order      SMALLINT UNSIGNED DEFAULT 0,
    status          ENUM('draft','published','archived') DEFAULT 'published',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dominion_slug (dominion_id, slug),
    FOREIGN KEY (dominion_id) REFERENCES imajica_dominions(dominion_id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Content Blocks
CREATE TABLE IF NOT EXISTS the_fugue (
    fugue_id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ephemera_id     BIGINT UNSIGNED NOT NULL,
    loom_id         INT UNSIGNED NULL COMMENT 'FK to the_everything_cube_0_0_0.the_looms, NULL for raw HTML blocks',
    content_json    JSON NOT NULL COMMENT 'Token values or raw HTML content',
    raw_html        MEDIUMTEXT NULL COMMENT 'Pre-rendered HTML for faithful reproduction phase',
    order_index     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    section_type    VARCHAR(64) NULL COMMENT 'hero, text, quote, cards, stats, timeline, raw',
    css_overrides   TEXT NULL,
    is_visible      TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ephemera_id) REFERENCES the_ephemera(ephemera_id) ON DELETE CASCADE,
    INDEX idx_page_order (ephemera_id, order_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Content Revisions / History
CREATE TABLE IF NOT EXISTS the_nuncio (
    nuncio_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_table    ENUM('the_fugue','the_ephemera','the_looms') NOT NULL,
    source_id       BIGINT UNSIGNED NOT NULL,
    previous_data   JSON NOT NULL,
    changed_by      VARCHAR(64) NOT NULL DEFAULT 'system',
    changed_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    change_reason   TEXT NULL,
    INDEX idx_source (source_table, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Task 1.3: Execute the schemas

Run these commands in order:

```bash
# Shared tables
wsl -u root bash -c "mariadb the_everything_cube_0_0_0 < /var/www/the-foreverbox-institute/institute/config/db-files/cube_schema.sql"

# Content tables in the_everything_cube (Institute)
wsl -u root bash -c "mariadb the_everything_cube_0_0_0 < /var/www/the-foreverbox-institute/institute/config/db-files/content_schema.sql"

# Content tables in the_myth_inner_cube (Archive)
wsl -u root bash -c "mariadb the_myth_inner_cube_0_2_0 < /var/www/the-foreverbox-institute/institute/config/db-files/content_schema.sql"

# Verify
wsl -u root bash -c "mariadb -e 'USE the_everything_cube_0_0_0; SHOW TABLES;'"
wsl -u root bash -c "mariadb -e 'USE the_myth_inner_cube_0_2_0; SHOW TABLES;'"
```

**Expected output for each:** 8 tables in `the_everything_cube`, 4 tables in `the_myth_inner_cube`.

### Task 1.4: Seed site records

```sql
-- In the_everything_cube_0_0_0
INSERT INTO imajica_dominions (site_name, site_slug, domain_url, web_root_path, description)
VALUES ('The ForeverBox Institute', 'institute', 'https://the-foreverbox-institute.invigor.com/institute', '/var/www/the-foreverbox-institute/institute', 'The public-facing Institute website');

-- In the_myth_inner_cube_0_2_0
INSERT INTO imajica_dominions (site_name, site_slug, domain_url, web_root_path, description)
VALUES ('The Archive', 'archive', 'https://the-foreverbox-institute.invigor.com/interactions', '/var/www/the-foreverbox-institute/interactions', 'The restricted Archive / Interactions site');
```

---

## Phase 2: Component Extraction & Seeding

### Task 2.1: Extract Archive components into `the_looms`

Write a Python script that:
1. Iterates over every `.html` file in `/var/www/the-foreverbox-institute/interactions/components/` (recursively)
2. Reads the file contents
3. Extracts all `{{TOKEN_NAME}}` patterns using regex `\{\{([A-Z_]+)\}\}`
4. Maps the subdirectory name to the `category` enum (`base`, `containers`→`container`, `data`, `typography`, `templates`→`template`)
5. Inserts a row into `the_everything_cube_0_0_0.the_looms`

**Component files to process (23 total):**

| File Path | component_name | category |
|:---|:---|:---|
| `components/base/header.html` | `archive_header` | `base` |
| `components/base/footer.html` | `archive_footer` | `base` |
| `components/base/sidenav.html` | `archive_sidenav` | `base` |
| `components/base/head_meta.html` | `archive_head_meta` | `base` |
| `components/containers/glass_panel.html` | `glass_panel` | `container` |
| `components/containers/hover_reticle.html` | `hover_reticle` | `container` |
| `components/containers/image_frame.html` | `image_frame` | `container` |
| `components/containers/narrative_card.html` | `narrative_card` | `container` |
| `components/containers/status_banner.html` | `status_banner` | `container` |
| `components/containers/tech_card.html` | `tech_card` | `container` |
| `components/containers/toc_link_card.html` | `toc_link_card` | `container` |
| `components/containers/vertical_step_list.html` | `vertical_step_list` | `container` |
| `components/data/code_block.html` | `code_block` | `data` |
| `components/data/compilation_footer.html` | `compilation_footer` | `data` |
| `components/data/data_table.html` | `data_table` | `data` |
| `components/data/status_badge.html` | `status_badge` | `data` |
| `components/typography/epigraph_block.html` | `epigraph_block` | `typography` |
| `components/typography/hero_header.html` | `hero_header` | `typography` |
| `components/typography/section_header.html` | `section_header` | `typography` |
| `components/templates/build_manual_template.html` | `build_manual_template` | `template` |
| `components/templates/narrative_page_template.html` | `narrative_page_template` | `template` |
| `components/templates/reference_doc_template.html` | `reference_doc_template` | `template` |
| `components/templates/tech_spec_template.html` | `tech_spec_template` | `template` |

### Task 2.2: Extract ContentRenderer templates into `the_looms`

Read `/var/www/the-foreverbox-institute/institute/includes/content-renderer.php` and extract the 6 heredoc HTML templates from the render methods. Insert each as a row in `the_looms`:

| Method | component_name | category |
|:---|:---|:---|
| `renderHero()` | `institute_hero` | `typography` |
| `renderText()` | `institute_text_panel` | `container` |
| `renderQuote()` | `institute_quote` | `container` |
| `renderCards()` | `institute_card_grid` | `container` |
| `renderStats()` | `institute_stat_counter` | `data` |
| `renderTimeline()` | `institute_timeline` | `container` |

### Task 2.3: Verify component seeding

```sql
SELECT loom_id, component_name, category, 
       JSON_LENGTH(token_manifest) as token_count 
FROM the_everything_cube_0_0_0.the_looms 
ORDER BY category, component_name;
```

**Expected:** 29 rows (23 archive + 6 institute).

---

## Phase 3: Content Migration (Existing Content Only)

### Task 3.1: Strategy — Raw HTML Blocks

For the **faithful reproduction** phase, we use a pragmatic approach:

Rather than decomposing every page into fine-grained component-mapped content blocks (which risks introducing subtle rendering differences), we store the **entire body content** of each page as a single `raw_html` block in `the_fugue`. This guarantees byte-for-byte identical output.

The fine-grained component mapping can be done incrementally in Milestone B after the faithful reproduction is verified.

### Task 3.2: Migrate Institute pages

Write a Python script that for each Institute page file:
1. Reads the full `.php` file
2. Extracts the content between `include __DIR__ . '/../includes/header.php';` and `include __DIR__ . '/../includes/footer.php';` — this is the page body
3. Also extracts the `$pageTheme` variable value
4. Creates a row in `the_ephemera` with the page slug, title, and theme
5. Creates a single row in `the_fugue` with `section_type = 'raw'` and the full body HTML in `raw_html`

**Institute pages to migrate (8 total):**

| File | slug | title | pageTheme |
|:---|:---|:---|:---|
| `index.php` | `index` | The ForeverBox Institute | `institute` |
| `pages/about.php` | `about` | About | `about` |
| `pages/case-studies.php` | `case-studies` | Case Studies | `cases` |
| `pages/forever-fit.php` | `forever-fit` | Forever Fit | `fit` |
| `pages/investment.php` | `investment` | Investment | `investment` |
| `pages/origin.php` | `origin` | Origin | `origin` |
| `pages/science.php` | `science` | The Science | `science` |
| `pages/vision.php` | `vision` | Vision | `vision` |

### Task 3.3: Migrate Archive pages

Write a Python script that for each Archive HTML file:
1. Reads the full `.html` file
2. Extracts the `<main>` content (between the header injection div and the footer injection div)
3. Creates a row in `the_myth_inner_cube_0_2_0.the_ephemera`
4. Creates a single row in `the_myth_inner_cube_0_2_0.the_fugue` with `section_type = 'raw'` and full body HTML

**Archive pages to migrate (9 total):**

| File | slug | title |
|:---|:---|:---|
| `index.html` | `index` | The ForeverBox Archive |
| `part1.html` | `part1` | Part I: The Mythic Frame |
| `part2.html` | `part2` | Part II: The Cube |
| `part3.html` | `part3` | Part III: The Swarm of Mites |
| `part4.html` | `part4` | Part IV: The Personas |
| `part5.html` | `part5` | Part V: The Workflows |
| `part6.html` | `part6` | Part VI: Dream Warriors |
| `part7.html` | `part7` | Part VII: Build Manual |
| `appendices.html` | `appendices` | Appendices |

### Task 3.4: Verify content migration

```sql
-- Institute
SELECT e.slug, e.title, e.page_theme, COUNT(f.fugue_id) as blocks
FROM the_everything_cube_0_0_0.the_ephemera e
LEFT JOIN the_everything_cube_0_0_0.the_fugue f ON e.ephemera_id = f.ephemera_id
GROUP BY e.ephemera_id;

-- Archive
SELECT e.slug, e.title, COUNT(f.fugue_id) as blocks
FROM the_myth_inner_cube_0_2_0.the_ephemera e
LEFT JOIN the_myth_inner_cube_0_2_0.the_fugue f ON e.ephemera_id = f.ephemera_id
GROUP BY e.ephemera_id;
```

**Expected:** 8 Institute pages each with 1 block. 9 Archive pages each with 1 block.

---

## Phase 4: Engine Development

### Task 4.1: Create multi-database config

Create `/var/www/the-foreverbox-institute/institute/includes/db-config.php`:

```php
<?php
/**
 * Multi-Database Configuration
 * Connects to the cube databases for dynamic content rendering
 */

class CubeDB {
    private static $connections = [];
    
    public static function get(string $dbName): PDO {
        if (!isset(self::$connections[$dbName])) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $user = getenv('DB_USER') ?: 'zeon7_user';
            $pass = getenv('DB_PASS') ?: 'F0reverb0x#2o26sql';
            
            self::$connections[$dbName] = new PDO(
                "mysql:host={$host};dbname={$dbName};charset=utf8mb4",
                $user, $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        }
        return self::$connections[$dbName];
    }
    
    public static function cube(): PDO {
        return self::get('the_everything_cube_0_0_0');
    }
    
    public static function myth(): PDO {
        return self::get('the_myth_inner_cube_0_2_0');
    }
}
```

### Task 4.2: Create the rendering engine

Create `/var/www/the-foreverbox-institute/institute/includes/engine.php`:

```php
<?php
/**
 * ForeverBox Dynamic Rendering Engine
 * Fetches page content from the database and renders HTML
 */

require_once __DIR__ . '/db-config.php';

class ForeverBoxEngine {
    
    private PDO $contentDb;  // The site's own inner cube
    private PDO $sharedDb;   // the_everything_cube_0_0_0
    
    public function __construct(string $contentDbName = 'the_everything_cube_0_0_0') {
        $this->contentDb = CubeDB::get($contentDbName);
        $this->sharedDb = CubeDB::cube();
    }
    
    /**
     * Render a full page by slug
     * Returns the HTML body content (everything between header and footer includes)
     */
    public function renderPage(string $slug): string {
        // 1. Fetch page record
        $stmt = $this->contentDb->prepare(
            "SELECT * FROM the_ephemera WHERE slug = :slug AND status = 'published' LIMIT 1"
        );
        $stmt->execute(['slug' => $slug]);
        $page = $stmt->fetch();
        
        if (!$page) {
            return '<div class="text-center py-20 text-on-surface-variant">Page not found.</div>';
        }
        
        // 2. Fetch all content blocks ordered by position
        $stmt = $this->contentDb->prepare(
            "SELECT * FROM the_fugue WHERE ephemera_id = :eid AND is_visible = 1 ORDER BY order_index ASC"
        );
        $stmt->execute(['eid' => $page['ephemera_id']]);
        $blocks = $stmt->fetchAll();
        
        // 3. Render each block
        $html = '';
        foreach ($blocks as $block) {
            if ($block['section_type'] === 'raw' && !empty($block['raw_html'])) {
                // Faithful reproduction: output raw HTML directly
                $html .= $block['raw_html'];
            } elseif ($block['loom_id']) {
                // Component-based rendering
                $html .= $this->renderComponent(
                    (int)$block['loom_id'],
                    json_decode($block['content_json'], true) ?: []
                );
            }
        }
        
        return $html;
    }
    
    /**
     * Render a single component by loom_id with variable substitution
     */
    public function renderComponent(int $loomId, array $variables): string {
        $stmt = $this->sharedDb->prepare(
            "SELECT html_template FROM the_looms WHERE loom_id = :id AND is_active = 1 LIMIT 1"
        );
        $stmt->execute(['id' => $loomId]);
        $loom = $stmt->fetch();
        
        if (!$loom) {
            return '<!-- Component not found: loom_id=' . $loomId . ' -->';
        }
        
        $html = $loom['html_template'];
        
        // Replace {{TOKEN}} placeholders with values
        foreach ($variables as $key => $value) {
            $html = str_replace('{{' . strtoupper($key) . '}}', $value, $html);
        }
        
        // Remove any unreplaced tokens
        $html = preg_replace('/\{\{[A-Z_]+\}\}/', '', $html);
        
        return $html;
    }
    
    /**
     * Get page theme overrides as CSS custom properties
     */
    public function getPageTheme(string $slug): string {
        $stmt = $this->contentDb->prepare(
            "SELECT page_theme, theme_overrides FROM the_ephemera WHERE slug = :slug LIMIT 1"
        );
        $stmt->execute(['slug' => $slug]);
        $page = $stmt->fetch();
        
        if (!$page || empty($page['theme_overrides'])) {
            return '';
        }
        
        $overrides = json_decode($page['theme_overrides'], true);
        if (!$overrides) return '';
        
        $css = '<style>:root {';
        foreach ($overrides as $prop => $value) {
            $css .= $prop . ': ' . $value . '; ';
        }
        $css .= '}</style>';
        
        return $css;
    }
}
```

### Task 4.3: Verify engine can connect and read

Write a quick test PHP script and run it via CLI:

```bash
wsl bash -c "php -r \"
require '/var/www/the-foreverbox-institute/institute/includes/engine.php';
\\\$engine = new ForeverBoxEngine();
\\\$html = \\\$engine->renderPage('science');
echo 'Rendered ' . strlen(\\\$html) . ' bytes for science page';
\""
```

**Expected:** A positive byte count matching approximately the line count of the current `science.php` body content.

---

## Phase 5: Page Refactoring & Visual Verification

### Task 5.1: Create safety branch

```bash
wsl bash -c "cd /var/www/the-foreverbox-institute && git checkout -b static-archive && git checkout main"
```

### Task 5.2: Refactor Institute pages

For each Institute page file, replace the hardcoded body content with a call to the engine. The page files should become minimal stubs.

**Example: `pages/science.php` should become:**

```php
<?php
/**
 * Science Page — Dynamic Rendering
 */
$pageTheme = 'science';
include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/engine.php';
$engine = new ForeverBoxEngine('the_everything_cube_0_0_0');
echo $engine->renderPage('science');

include __DIR__ . '/../includes/footer.php';
?>
```

**Apply this pattern to ALL 8 Institute pages**, changing only `$pageTheme` and the slug string for each:

| File | $pageTheme | Slug |
|:---|:---|:---|
| `index.php` | `institute` | `index` |
| `pages/about.php` | `about` | `about` |
| `pages/case-studies.php` | `cases` | `case-studies` |
| `pages/forever-fit.php` | `fit` | `forever-fit` |
| `pages/investment.php` | `investment` | `investment` |
| `pages/origin.php` | `origin` | `origin` |
| `pages/science.php` | `science` | `science` |
| `pages/vision.php` | `vision` | `vision` |

> **IMPORTANT:** Before overwriting each file, verify the engine output matches the current static output. Do this by:
> 1. Saving the current rendered output (via `curl` or browser) as a reference
> 2. Deploying the engine version
> 3. Comparing the two outputs
> 4. Only proceeding if they match

### Task 5.3: DO NOT refactor Archive pages yet

The Archive site uses client-side HTML fetching (`nav.js` + `fetch()`), not PHP server-side rendering. Converting it requires a different approach:
1. Create a thin PHP wrapper (`interactions/render.php`) that accepts a `?page=part1` query parameter
2. Uses the engine to fetch from `the_myth_inner_cube_0_2_0`
3. Returns the rendered HTML
4. Update `nav.js` to call this endpoint instead of fetching static `.html` files

**However**, this is complex and should be deferred. For Milestone A, the Archive pages can remain as static HTML files while their content is mirrored in the database for future use. The priority is getting the Institute pages rendering dynamically.

### Task 5.4: Visual verification

For each Institute page, use a browser to:
1. Visit the page at `https://the-foreverbox-institute.invigor.com/institute/pages/{slug}.php`
2. Compare against the `static-archive` branch version
3. Check: layout, colours, borders, text visibility, images, hover effects, animations
4. Document any discrepancies

**Pages to verify:**
- [ ] `/institute/index.php`
- [ ] `/institute/pages/about.php`
- [ ] `/institute/pages/case-studies.php`
- [ ] `/institute/pages/forever-fit.php`
- [ ] `/institute/pages/investment.php`
- [ ] `/institute/pages/origin.php`
- [ ] `/institute/pages/science.php`
- [ ] `/institute/pages/vision.php`

---

## Phase 5.5: Git Commit & Review Gate

### Task 5.5.1: Commit all changes

```bash
wsl bash -c "cd /var/www/the-foreverbox-institute && git add -A && git commit -m 'feat(engine): Milestone A — Dynamic database-driven rendering for Institute pages'"
```

### Task 5.5.2: Present for review

At this point, **stop all work** and present the results to the user for examination. The user will:
1. Browse every Institute page
2. Confirm visual fidelity
3. Either approve (proceed to Milestone B) or request fixes

---

## Appendix A: File Inventory

### Files to CREATE

| Path | Purpose |
|:---|:---|
| `institute/config/db-files/cube_schema.sql` | Shared table definitions |
| `institute/config/db-files/content_schema.sql` | Per-site content table definitions |
| `institute/includes/db-config.php` | Multi-database PDO connection manager |
| `institute/includes/engine.php` | Core rendering engine |

### Files to MODIFY

| Path | Change |
|:---|:---|
| `institute/index.php` | Replace body HTML with engine call |
| `institute/pages/about.php` | Replace body HTML with engine call |
| `institute/pages/case-studies.php` | Replace body HTML with engine call |
| `institute/pages/forever-fit.php` | Replace body HTML with engine call |
| `institute/pages/investment.php` | Replace body HTML with engine call |
| `institute/pages/origin.php` | Replace body HTML with engine call |
| `institute/pages/science.php` | Replace body HTML with engine call |
| `institute/pages/vision.php` | Replace body HTML with engine call |

### Files to NEVER TOUCH

| Path | Reason |
|:---|:---|
| `institute/assets/js/tailwind-config.js` | Site-specific design tokens |
| `institute/assets/css/components.css` | Site-specific HUD effects |
| `institute/assets/css/pages.css` | Site-specific animations |
| `institute/assets/js/nav.js` | Navigation logic |
| `institute/includes/header.php` | HTML skeleton opener |
| `institute/includes/footer.php` | HTML skeleton closer |
| `interactions/assets/js/tailwind-config.js` | Archive design tokens |
| `interactions/assets/css/*` | Archive CSS files |
| `interactions/assets/nav.js` | Archive navigation |
| `interactions/*.html` | Archive static pages (kept as-is for Milestone A) |

---

## Appendix B: Troubleshooting

### Common Issues

1. **"Page not found" from engine:** The slug in the database doesn't match the slug passed in the PHP file. Check `SELECT slug FROM the_ephemera;` and compare.

2. **Visual differences after refactoring:** The content extraction script may have missed or corrupted HTML. Compare `raw_html` in the database against the original file content using `diff`.

3. **Database connection refused:** Ensure MariaDB is running (`wsl -u root bash -c "systemctl status mariadb"`) and that `zeon7_user` has privileges on the cube databases.

4. **PHP include path errors:** The engine uses `require_once __DIR__ . '/db-config.php'`. Ensure `db-config.php` is in the same directory as `engine.php` (`institute/includes/`).

5. **Tailwind CDN opacity bug:** If you see grey/white borders where there should be coloured ones, the issue is Tailwind CDN not processing `primary/60` syntax. Use arbitrary RGBA: `border-[rgba(var(--color-primary-rgb),0.6)]`. This was a major issue in the original static build — do NOT introduce new instances of this pattern.

---

> **End of Milestone A Build Plan.** Milestone B (content enrichment, vectorisation, admin interface) will be planned after Milestone A review gate approval.

---

## ═══ MILESTONE B: CONTENT ENRICHMENT & INTEGRATION ═══
*Goal: Ingest new content from external documents, context files, and the Lore Sea into the database-driven sites.*

---

## Phase 6: Document Ingestion

### Task 6.1: Catalogue Documents
1. Examine /foreverbox_data/Quiddity_Lore_Sea/ and other /documents/ folders for new content.
2. Identify target pages and sections where this content belongs (e.g., adding a new case study to the Case Studies page, or expanding The Science page).

### Task 6.2: Content Decomposition
1. Write a script to parse the markdown/text documents.
2. Break the content down into logical blocks corresponding to the 	he_looms components (e.g., extracting headings for institute_hero, text for institute_text_panel, data points for institute_stat_counter).

### Task 6.3: Database Insertion
1. Generate the content_json payloads for each block.
2. Insert new rows into 	he_fugue mapped to the appropriate ephemera_id (page) and loom_id (component).
3. If new pages are needed, insert rows into 	he_ephemera first.
4. Set order_index appropriately to position the new blocks within the page.

### Task 6.4: Media Asset Handling
1. If the new content references images or other media, copy the files to the appropriate web-accessible assets directory.
2. Insert rows into 	he_tile_room for each media asset.
3. Update the content_json in 	he_fugue to reference the ile_path from 	he_tile_room.

---

## Phase 7: Vectorisation

### Task 7.1: Text Extraction Script
1. Write a PHP CLI script (institute/scripts/sync_vectors.php) that connects to all inner cube databases (	he_everything_cube_0_0_0, 	he_myth_inner_cube_0_2_0, etc.).
2. Iterate through all rows in 	he_fugue.
3. For rows with section_type = 'raw', extract text from aw_html (strip HTML tags).
4. For component rows, parse content_json and extract the text values (ignoring purely structural tokens like classes or IDs).

### Task 7.2: Embedding Generation
1. The sync script should send the extracted text chunks to the existing Embedding Service (port :8900).
2. Receive the vector embeddings (e.g., 384-dim ll-MiniLM-L6-v2).

### Task 7.3: Database Storage
1. Insert the text chunks and their embeddings into 	he_everything_cube_0_0_0.quiddity_sea.
2. Populate source_table ('the_fugue'), source_id (fugue_id), and source_db (the specific inner cube name).

### Task 7.4: Shell Wrapper Integration
1. Create a new shell wrapper script /foreverbox_data/bin/fbox-site-search.
2. This script should take a search query, get its embedding from the Embedding Service, and perform a vector similarity search (cosine distance) against quiddity_sea.
3. Return the matching text chunks and their source locations.
4. Add the sync_vectors.php script to the existing cron sync daemon (sync/sync_daemon.py) to keep the index up to date.

---

## Phase 8: Admin Interface (Nexus Dashboard)

### Task 8.1: Extend Nexus Routing
1. Update /var/www/the-foreverbox-institute/nexus/index.php to include routes for managing the new CMS tables.
2. Use the existing Slim 4 REST API pattern or standard PHP routing.

### Task 8.2: Component Management UI
1. Create a view to list, edit, and add component templates (	he_looms).
2. Include syntax highlighting for the HTML templates.

### Task 8.3: Page Management UI
1. Create a view to manage pages (	he_ephemera) across all sites (imajica_dominions).
2. Allow editing of page metadata (title, slug, theme, status).

### Task 8.4: Content Block Management UI
1. Create a view to manage content blocks (	he_fugue) for a given page.
2. Implement a drag-and-drop interface for reordering (order_index).
3. Create dynamic forms based on the component's 	oken_manifest to easily edit the content_json values without writing raw JSON.

### Task 8.5: Media Library UI
1. Create a view to upload and manage media assets in 	he_tile_room.
2. Provide a way to select assets when editing content blocks.

---

## Appendix C: Milestone B Verification

1. **Content Accuracy:** Verify newly ingested document content is correctly mapped to pages and rendered with appropriate components on the frontend.
2. **Vector Search Validation:**
   - Run box-site-search " test query in


---

## ═══ MILESTONE B: CONTENT ENRICHMENT & INTEGRATION ═══
*Goal: Ingest new content from external documents, context files, and the Lore Sea into the database-driven sites.*

---

## Phase 6: Document Ingestion

### Task 6.1: Catalogue Documents
1. Examine `/foreverbox_data/Quiddity_Lore_Sea/` and other `/documents/` folders for new content.
2. Identify target pages and sections where this content belongs (e.g., adding a new case study to the Case Studies page, or expanding The Science page).

### Task 6.2: Content Decomposition
1. Write a script to parse the markdown/text documents.
2. Break the content down into logical blocks corresponding to the `the_looms` components (e.g., extracting headings for `institute_hero`, text for `institute_text_panel`, data points for `institute_stat_counter`).

### Task 6.3: Database Insertion
1. Generate the `content_json` payloads for each block.
2. Insert new rows into `the_fugue` mapped to the appropriate `ephemera_id` (page) and `loom_id` (component).
3. If new pages are needed, insert rows into `the_ephemera` first.
4. Set `order_index` appropriately to position the new blocks within the page.

### Task 6.4: Media Asset Handling
1. If the new content references images or other media, copy the files to the appropriate web-accessible assets directory.
2. Insert rows into `the_tile_room` for each media asset.
3. Update the `content_json` in `the_fugue` to reference the `file_path` from `the_tile_room`.

---

## Phase 7: Vectorisation

### Task 7.1: Text Extraction Script
1. Write a PHP CLI script (`institute/scripts/sync_vectors.php`) that connects to all inner cube databases (`the_everything_cube_0_0_0`, `the_myth_inner_cube_0_2_0`, etc.).
2. Iterate through all rows in `the_fugue`.
3. For rows with `section_type = 'raw'`, extract text from `raw_html` (strip HTML tags).
4. For component rows, parse `content_json` and extract the text values (ignoring purely structural tokens like classes or IDs).

### Task 7.2: Embedding Generation
1. The sync script should send the extracted text chunks to the existing Embedding Service (port `:8900`).
2. Receive the vector embeddings (e.g., 384-dim `all-MiniLM-L6-v2`).

### Task 7.3: Database Storage
1. Insert the text chunks and their embeddings into `the_everything_cube_0_0_0.quiddity_sea`.
2. Populate `source_table` ('the_fugue'), `source_id` (fugue_id), and `source_db` (the specific inner cube name).

### Task 7.4: Shell Wrapper Integration
1. Create a new shell wrapper script `/foreverbox_data/bin/fbox-site-search`.
2. This script should take a search query, get its embedding from the Embedding Service, and perform a vector similarity search (cosine distance) against `quiddity_sea`.
3. Return the matching text chunks and their source locations.
4. Add the `sync_vectors.php` script to the existing cron sync daemon (`sync/sync_daemon.py`) to keep the index up to date.

---

## Phase 8: Admin Interface (Nexus Dashboard)

### Task 8.1: Extend Nexus Routing
1. Update `/var/www/the-foreverbox-institute/nexus/index.php` to include routes for managing the new CMS tables.
2. Use the existing Slim 4 REST API pattern or standard PHP routing.

### Task 8.2: Component Management UI
1. Create a view to list, edit, and add component templates (`the_looms`).
2. Include syntax highlighting for the HTML templates.

### Task 8.3: Page Management UI
1. Create a view to manage pages (`the_ephemera`) across all sites (`imajica_dominions`).
2. Allow editing of page metadata (title, slug, theme, status).

### Task 8.4: Content Block Management UI
1. Create a view to manage content blocks (`the_fugue`) for a given page.
2. Implement a drag-and-drop interface for reordering (`order_index`).
3. Create dynamic forms based on the component's `token_manifest` to easily edit the `content_json` values without writing raw JSON.

### Task 8.5: Media Library UI
1. Create a view to upload and manage media assets in `the_tile_room`.
2. Provide a way to select assets when editing content blocks.

---

## Appendix C: Milestone B Verification

1. **Content Accuracy:** Verify newly ingested document content is correctly mapped to pages and rendered with appropriate components on the frontend.
2. **Vector Search Validation:**
   - Run `fbox-site-search "test query"` in the terminal.
   - Verify it returns relevant results from the newly ingested website content.
   - Confirm the source metadata points to the correct database and page.
3. **No Regression (Again):** Re-run the visual checks from Milestone A to confirm that existing pages are unaffected by the new content and system changes.
4. **Admin UI Testing:** Perform CRUD operations in the Nexus dashboard and verify changes are immediately reflected on the live sites.
