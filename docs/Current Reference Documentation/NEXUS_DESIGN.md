# The Nexus Control Plane — Design Specification (NEXUS_DESIGN.md)

## 1. Overview & Identity
The Nexus (`F.BOX.THE-NEXUS`) is the centralized operational HUD and administrative control plane for the ForeverBox initiative. It provides real-time database management, route control, shared component building, fugue block reordering, media asset indexing, and vector sea monitoring across all connected sites.

- **URL Endpoint**: `https://the-foreverbox-institute.invigor.com/nexus/index.php`
- **Database Engine**: `the_nexus_inner_cube_0_1_2`
- **Design Metaphor**: High-density Cyberpunk HUD / Terminal Control Deck

---

## 2. Visual Architecture & Layout Rules

### Header & Identity Bar
- **Top-Left Identity Marker**: `F.BOX.THE-NEXUS` (Monospace uppercase, green `#00ff41` glow).
- **Navigation Bar**: Tab-based HUD navigation pills (`SITES`, `LOOMS`, `EPHEMERA`, `THE FUGUE`, `TILE ROOM`, `QUIDDITY SEA`).
- **Active State**: Primary accent background container with glowing border indicator.

### 12-Column Responsive Grid System
- **2-Column Responsive Tabs (`sites`, `looms`, `ephemera`)**:
  - **Left Container (Registry Table)**: `lg:col-span-7` (7 out of 12 columns). Displays interactive data tables and real-time records.
  - **Right Container (Creation Form)**: `lg:col-span-5` (5 out of 12 columns). Displays interactive forms for creating new entities.
- **Full-Width Tabs (`THE FUGUE`, `TILE ROOM`, `QUIDDITY SEA`)**:
  - **Single Container**: `lg:col-span-12` (100% width).

---

## 3. UI Component System

### 1. Glass Panel Component (`loom_id = 19`)
- **Template Schema**:
  ```html
  <div class="hud-border glass-panel p-6 md:p-8 hud-glow relative {{PANEL_CLASS}}">
      <div class="absolute top-2 right-3 font-code-label text-[10px] text-primary/50">{{PANEL_TAG}}</div>
      <div class="flex items-center gap-2 mb-4">
          <span class="material-symbols-outlined text-primary text-xl">{{PANEL_ICON}}</span>
          <h4 class="font-anchor-sm text-primary uppercase tracking-widest text-sm">{{PANEL_TITLE}}</h4>
      </div>
      <div class="text-on-surface-variant text-sm leading-relaxed">
          {{PANEL_BODY}}
      </div>
  </div>
  ```

### 2. Interactive Drag-and-Drop Fugue Block Reorder Engine
- **Handles**: Native HTML5 `draggable="true"` grip handles.
- **Visual Feedback**: Dragging opacity shift, hover drag targets.
- **AJAX Endpoint**: POST `index.php?tab=fugue` with `ajax=reorder_fugue` updating `order_index` in `the_fugue`.

### 3. Dynamic Token Manifest Form Builder
- **Manifest Parser**: Reads `token_manifest` JSON from `the_looms`.
- **Form Generation**: Dynamically creates text, textarea, and select inputs for populating `content_json` without writing raw JSON manually.

### 4. Tile Room Media Upload & Copy Gallery
- **Upload Form**: Direct multipart asset upload to `/institute/assets/images/`.
- **Gallery Grid**: Grid layout of indexed media assets with `COPY PATH` buttons for easy URL copying into component templates.

---

## 4. Color Palette & Typography
- **Primary Accent**: `#00ff41` (Terminal Matrix Green, `var(--color-primary)`)
- **Background**: `#0a0a0a` (`bg-background`)
- **Panel Surface**: `rgba(20, 20, 20, 0.6)` (`glass-panel` with 8px blur)
- **Monospace Labels**: `JetBrains Mono` (`font-code-label`)
- **Header Font**: `Exo 2` (`font-hero-lg`)


## Typography & Main Fonts Matrix

### Mandatory Universal Paragraph Rule (All Main Pages & All Sites)
> [!IMPORTANT]
> **Strict Universal Standard**: On every single page across **The Institute**, **The Archive**, and **The Nexus**, all body copy and body-like text elements (`<p>`, card descriptions, section body text, dossier text, narrative content) **MUST** strictly use:
> - **Font Family**: `Geist` (`font-body-md`)
> - **Font Size**: `0.875rem` (**14px**) — assigned via Tailwind `text-base`
> - **Line Height**: `1.625` (**26px**) — assigned via Tailwind `leading-relaxed`
> - **Font Weight**: `300` / **`font-light`** or `400` / **`font-normal`**


### Global Font Import
All main pages import the following Google Fonts via `<link>` tags in the global header:
- **Exo 2**: Weights `100, 200, 300, 400, 600, 700, 900`
- **Geist**: Weights `300, 400, 500, 600`
- **JetBrains Mono**: Weights `300, 400, 600`

---

### Page-by-Page Font Specifications

| Page / Element | Tailwind Font Class | Font Family | Font Size | Line Height | Font Weight / Style |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Main Body Copy (`<p>`)** | `font-body-md` | `Geist`, sans-serif | `0.875rem` (14px) | `1.625` (26px) | 300 Light (`#e0e0e0`) |
| **Hero Page Titles (`<h1>`)** | `font-hero-lg` | `Exo 2`, sans-serif | `2.25rem`–`6.75rem` (36px–108px) | `1.0`–`1.1` | 600 Semi-Bold / 100 Thin |
| **Section Headings (`<h2>`, `<h3>`)** | `font-hero-lg` | `Exo 2`, sans-serif | `1.5rem`–`2.25rem` (24px–36px) | `1.2`–`1.3` | 700 Bold / Uppercase |
| **Lead Paragraphs / Subtitles** | `font-headline-md` | `Geist`, sans-serif | `1.125rem`–`1.25rem` (18px–20px) | `1.625` (26px) | 400 Normal |
| **Technical Labels & Badges** | `font-code-label` | `JetBrains Mono`, monospace | `0.75rem` (12px) | `1.4` | 400 Monospace |
| **Navigation & Button Anchors** | `font-anchor-sm` | `JetBrains Mono`, monospace | `0.875rem` (14px) | `1.4` | 600 Semi-Bold / Uppercase |

---


### Detailed Site-by-Site Font Size & Role Breakdown

#### 1. The ForeverBox Institute (`/institute/*.php`)
- **Main Hero Title (`<h1>` on `index.php`)**: `Exo 2` (`font-hero-lg`) — `4.5rem` to `6.75rem` (**72px to 108px**), line-height `1.0`, weight `600`/`100`.
- **Sub-Page Hero Header (`<h1>`)**: `Exo 2` (`font-hero-lg`) — `2.25rem` to `3.75rem` (**36px to 60px**), line-height `1.1`.
- **Major Section Headings (`<h2>`)**: `Exo 2` (`font-hero-lg`) — `1.875rem` to `2.25rem` (**30px to 36px**), line-height `1.2`, weight `700` uppercase.
- **Card & Component Sub-headings (`<h3>`, `<h4>`)**: `Exo 2` (`font-hero-lg`) — `1.25rem` to `1.5rem` (**20px to 24px**), line-height `1.3`.
- **Lead Descriptions & Hero Subtitles**: `Geist` (`font-headline-md`) — `1.125rem` to `1.25rem` (**18px to 20px**), line-height `1.625` (26px).
- **Main Body Paragraph Copy (`<p>`)**: `Geist` (`font-body-md`) — `0.875rem` (**14px**), line-height `1.625` (26px), weight `300` (`#e0e0e0`).
- **Button Anchors & CTA Links**: `JetBrains Mono` (`font-anchor-sm`) — `0.875rem` (**14px**), uppercase tracking-widest, weight `600`.
- **Monospace Badges & Telemetry Labels**: `JetBrains Mono` (`font-code-label`) — `0.75rem` (**12px**), uppercase.
- **Footer Status Bar Text**: `JetBrains Mono` (`font-code-label`) — `0.75rem` (**12px**).

#### 2. The ForeverBox Archive (`/interactions/*.php`)
- **Archive Chapter Hero Title (`<h1>`)**: `Exo 2` (`font-hero-lg`) — `2.5rem` to `3.75rem` (**40px to 60px**), green `#00ff41` drop-shadow glow.
- **Section Headings (`<h2>`)**: `Exo 2` (`font-hero-lg`) — `1.5rem` to `2.0rem` (**24px to 32px**), uppercase.
- **Sub-Section Headers (`<h3>`)**: `Exo 2` (`font-hero-lg`) — `1.25rem` (**20px**), uppercase.
- **Lead Excerpt Paragraphs**: `Geist` (`font-headline-md`) — `1.25rem` (**20px**), line-height `1.625` (26px), left-bordered.
- **Main Body Content Copy (`<p>`)**: `Geist` (`font-body-md`) — `0.875rem` (**14px**), line-height `1.625` (26px), weight `300`/`400`.
- **Inline Code Blocks (`<code>`)**: `JetBrains Mono` — `0.875rem` (**14px**), green text on dark container.
- **Sidebar Navigation Links (`#fb-sidenav`)**: `JetBrains Mono` — `0.875rem` (**14px**), uppercase tracking-wider.
- **Terminal Codex Identifiers**: `JetBrains Mono` (`font-code-label`) — `0.75rem` (**12px**).

#### 3. The Nexus Control Plane (`/nexus/index.php`)
- **Top-Left Identity Marker (`F.BOX.THE-NEXUS`)**: `JetBrains Mono` — `1.25rem` (**20px**), green `#00ff41` font-bold.
- **Header Tab Navigation Pills**: `JetBrains Mono` — `0.875rem` (**14px**), uppercase tracking-widest.
- **Glass Panel Titles (`<h4>`)**: `JetBrains Mono` (`font-anchor-sm`) — `0.875rem` (**14px**), uppercase tracking-widest.
- **Data Table Header Cells (`<th>`)**: `JetBrains Mono` (`font-code-label`) — `0.75rem` (**12px**), uppercase tracking-wider.
- **Data Table Row Cells (`<td>`)**: `JetBrains Mono` (`font-code-label`) — `0.75rem` (**12px**), monospace data alignment.
- **Form Input Labels**: `JetBrains Mono` (`font-code-label`) — `0.75rem` (**12px**), uppercase.
- **Form Text Inputs & Textareas**: `JetBrains Mono` / `Geist` — `0.875rem` (**14px**).
- **Vector Index Stats & Counts**: `JetBrains Mono` — `0.75rem` to `1.0rem` (**12px to 16px**).
