# ForeverBox Interactions — Component & Template Library Guide

This document is the authoritative reference for the **Modular UI Component & Template System** at `/var/www/the-foreverbox-institute/interactions/`. Every pattern documented here is extracted directly from the gold-standard reference pages (`index.html`, `part1.html` to `part6.html`).

---

## 1. Directory Architecture

```
/var/www/the-foreverbox-institute/interactions/
├── index.html                          # Landing index page (reference)
├── part1.html .. part6.html           # Gold-standard reference pages
├── assets/
│   └── nav.js                         # Dynamic header/sidebar/footer injection
└── components/
    ├── header.html                    # [nav.js target] Top navigation bar
    ├── sidenav.html                   # [nav.js target] Collapsible left sidebar
    ├── footer.html                    # [nav.js target] System telemetry footer
    ├── base/
    │   ├── head_meta.html             # Canonical <head> meta, Tailwind config, CSS rules
    │   ├── header.html                # Source copy of header
    │   ├── sidenav.html               # Source copy of sidenav
    │   └── footer.html                # Source copy of footer
    ├── typography/
    │   ├── hero_header.html           # Hero title with 4px plasma-green border
    │   ├── section_header.html        # Section divider with Material Icon & node tag
    │   └── epigraph_block.html        # Glass panel quote with SIG tag & commentary
    ├── containers/
    │   ├── narrative_card.html        # Corner-bracketed HUD card with pulse dot
    │   ├── tech_card.html             # Hoverable spec card with gradient line & key-value rows
    │   ├── glass_panel.html           # Blur glass panel with metadata tag
    │   ├── image_frame.html           # Hover-reticle image viewport with scanline overlay
    │   ├── vertical_step_list.html    # Left-bordered step/process list
    │   ├── status_banner.html         # Coloured update/alert banner (green/red variants)
    │   ├── toc_link_card.html         # Table of contents navigational card
    │   └── hover_reticle.html         # Tactical HUD hover bracket overlay
    ├── data/
    │   ├── code_block.html            # HUD terminal-style code container
    │   ├── data_table.html            # Responsive data table with glass panel
    │   ├── status_badge.html          # Status pill (5 colour variants)
    │   └── compilation_footer.html    # End-of-page compilation marker
    └── templates/
        ├── narrative_page_template.html
        ├── tech_spec_template.html
        ├── build_manual_template.html
        └── reference_doc_template.html
```

---

## 2. Global Design System Tokens

All tokens are defined in `components/base/head_meta.html` and extracted from the reference pages.

### 2.1 Colour Palette (40+ tokens)

| Token | Hex | Usage |
| :--- | :--- | :--- |
| `primary` | `#ebffe2` | Main text, high-contrast headings |
| `primary-container` | `#00ff41` | Plasma green accents, borders, glow, active states |
| `primary-fixed` | `#72ff70` | Hero title accent text |
| `primary-fixed-dim` | `#00e639` | Secondary accent, surface tint |
| `secondary` | `#9ad597` | Secondary green accents |
| `secondary-container` | `#1c5424` | Secondary badge backgrounds |
| `on-secondary-container` | `#8cc78a` | Secondary badge text |
| `surface` | `#0b141c` | Page background |
| `surface-dim` | `#0b141c` | Card background (dim variant) |
| `surface-container` | `#182028` | Card/panel background |
| `surface-container-low` | `#141c24` | Subtle container background |
| `surface-container-high` | `#222b33` | Elevated container background |
| `surface-container-highest` | `#2d363e` | Badge/pill container |
| `surface-container-lowest` | `#060f16` | Fixed background overlay |
| `background` | `#0b141c` | Body background |
| `on-surface` | `#dae3ee` | Primary text on dark surfaces |
| `on-surface-variant` | `#b9ccb2` | Body text, descriptions, captions |
| `on-background` | `#dae3ee` | Text on background |
| `outline` | `#84967e` | Subtle borders |
| `outline-variant` | `#3b4b37` | Table/card separator borders |
| `error` | `#ffb4ab` | Error state accent |
| `error-container` | `#93000a` | Error container background |

### 2.2 Typography Tokens

| Token | Font Family | Size / Weight | Usage |
| :--- | :--- | :--- | :--- |
| `font-hero-lg` / `text-hero-lg` | Exo 2 | clamp(2.5rem, 6vw, 4.5rem) / 100 | Hero titles (desktop) |
| `font-hero-lg-mobile` / `text-hero-lg-mobile` | Exo 2 | 2.5rem / 200 | Hero titles (mobile) |
| `font-headline-md` / `text-headline-md` | Exo 2 | clamp(1.5rem, 3vw, 2rem) / 200 | Card headings |
| `font-anchor-sm` / `text-anchor-sm` | Exo 2 | 0.875rem / 600, 0.05em spacing | Section headers, card titles |
| `font-body-md` / `text-body-md` | Geist | 1rem / 400 | Body text |
| `font-code-label` / `text-code-label` | JetBrains Mono | 0.75rem / 400 | Code, node labels, badges |

### 2.3 Spacing Tokens

| Token | Value | Usage |
| :--- | :--- | :--- |
| `p-margin-safe` | 2rem | Main content padding |
| `max-w-container-max` | 90rem | Main content max-width |
| `gutter` | 1.5rem | Grid gutters |

### 2.4 Custom CSS Classes

| Class | Definition | Usage |
| :--- | :--- | :--- |
| `.hud-border` | `border: 1px solid rgba(0, 255, 65, 0.1)` | Green border on all containers |
| `.hud-border-active` | `border: 2px solid rgba(0, 255, 65, 0.2)` | Thicker border for hover reticles |
| `.hud-glow` | `box-shadow: 0 0 30px rgba(0, 255, 65, 0.05)` | Outer glow on glass panels |
| `.glass-panel` | `background: rgba(11, 20, 28, 0.5); backdrop-filter: blur(12px)` | Semi-transparent blur background |
| `.panel-glow` | `box-shadow: 0 0 15px rgba(0, 255, 65, 0.08)` | Subtle green box shadow |
| `.glow-text` | `text-shadow: 0 0 15px rgba(0, 255, 65, 0.5)` | Green text glow (index hero) |
| `.hud-scanline` | Linear gradient 4px repeating scanline | CRT scanline texture overlay |
| `.blinking-cursor` | `animation: blink 1s step-end infinite` | Terminal cursor animation |
| `.fb-sidenav-active` | Green left border + highlight background | Active sidebar item |
| `.fb-nav-active` | Green bottom border | Active top nav item |

---

## 3. Component Reference

### 3.1 Typography

#### `hero_header.html`
**Source**: `part1.html`, `part3.html`, `part5.html`
**Element**: `<header>` (not `<section>`)
**Key features**: 4px left plasma border, blurred glow bar, split title (bold + thin), Material Symbol icon, subtitle, metadata badges.

| Slot | Example | Required |
| :--- | :--- | :--- |
| `{{HERO_ICON}}` | `terminal` | Yes |
| `{{SEQUENCE_LABEL}}` | `INITIALIZATION_SEQUENCE` | Yes |
| `{{TITLE_LINE_1}}` | `THE` | Yes |
| `{{TITLE_LINE_2}}` | `MYTHIC FRAME` | Yes |
| `{{SUBTITLE}}` | Italic narrative intro | Yes |
| `{{BADGE_TEXT}}` | `A Document 29 Years in the Making` | Optional |
| `{{META_TEXT}}` | `DOMAIN: foreverbox.co.uk` | Optional |

#### `section_header.html`
**Source**: `part1.html` to `part6.html`
**Element**: `<h3>` with `font-anchor-sm text-anchor-sm`
**Key features**: Material Icon, uppercase tracking, right-aligned node label.

| Slot | Example |
| :--- | :--- |
| `{{SECTION_ID}}` | `part1-1` |
| `{{ICON_NAME}}` | `format_quote`, `hub`, `memory` |
| `{{SECTION_TITLE}}` | `EPIGRAPHS` |
| `{{NODE_LABEL}}` | `DATA_NODE: EPI-001` |

#### `epigraph_block.html`
**Source**: `part1.html`
**Key features**: `glass-panel hud-border`, full-height left accent bar (`w-1 h-full bg-primary/30`), `SIG:` tag at top-right, optional `COMMENTARY` block.

| Slot | Example |
| :--- | :--- |
| `{{SPEAKER_TAG}}` | `THE_MASTER` |
| `{{QUOTE_TEXT}}` | Quotation body |
| `{{ATTRIBUTION}}` | `The Master, Doctor Who` |
| `{{COMMENTARY_TEXT}}` | Optional context text |

### 3.2 Containers

#### `narrative_card.html` — Corner-Bracketed HUD Card
**Source**: `part1.html`, `part3.html`, `part5.html`
**Key features**: 4 corner accent markers (2x2px), pulsing green dot, vertical accent bar on title, `bg-surface-dim/50 backdrop-blur-sm`.

| Slot | Example |
| :--- | :--- |
| `{{CARD_TAG}}` | `PROTOCOL_STATUS` |
| `{{CARD_TITLE}}` | `The Origin` |
| `{{CARD_CONTENT}}` | HTML paragraph content |

#### `tech_card.html` — Hoverable Specification Card
**Source**: `part3.html`, `part4.html`, `part5.html`, `part6.html`
**Key features**: Top gradient line, corner accents, watermark icon (optional), key-value data rows, footer status tag, hover transition.

| Slot | Example |
| :--- | :--- |
| `{{CARD_ICON}}` | `dns`, `memory` |
| `{{CARD_TITLE}}` | `Node: Alpha` |
| `{{NODE_ID}}` | `NODE_01` |
| `{{CARD_DESCRIPTION}}` | Description text |
| `{{KEY_VALUE_ROWS}}` | Structured rows (see template) |
| `{{STATUS_TAG}}` | `ACTIVE` |

#### `glass_panel.html` — Blur Glass Panel
**Source**: `part4.html`, `part5.html`, `part6.html`
**Key features**: `glass-panel hud-border hud-glow`, top-right metadata tag.

#### `image_frame.html` — Hover-Reticle Image Viewport
**Source**: `part3.html`
**Key features**: 8px corner brackets on hover, `mix-blend-luminosity`, scanline overlay grid, reference tags.

#### `vertical_step_list.html` — Process Step List
**Source**: `part5.html` (Waterfall Method), `part6.html` (Dialectic Mix)
**Key features**: Left border (`border-l border-primary/20`), fixed-width monospace step label (`w-32`).

#### `status_banner.html` — Coloured Update/Alert Banner
**Source**: `part5.html`
**Variants**: Secondary green (`border-secondary`), Error red (`border-error`).

#### `toc_link_card.html` — Table of Contents Card
**Source**: `index.html`
**Key features**: Hierarchical anchor links with `ml-4` indentation for sub-items.

#### `hover_reticle.html` — Tactical HUD Bracket Overlay
**Source**: `part3.html`, `part4.html`
**Usage**: Wrap around any `group` container to add tactical hover brackets.

### 3.3 Data

#### `code_block.html` — HUD Terminal Code Container
**Source**: `part3.html`
**Key features**: HUD terminal style (green dot indicator, not macOS dots), gradient top edge, dark code background (`#05090c`).

#### `data_table.html` — Responsive Data Table
**Source**: `part3.html`, `part5.html`
**Key features**: Glass panel wrapper, `overflow-x-auto`, hover row transitions, monospace headers.

#### `status_badge.html` — Status Pill (5 Variants)
**Variants**: Primary outline, Active filled, Secondary, Border outline, Inline tag.

#### `compilation_footer.html` — End-of-Page Marker
**Source**: `part4.html`
**Key features**: Centred monospace text with top border.

---

## 4. Page Templates

All 4 templates use the correct page structure:

```html
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
<!-- INCLUDE: components/base/head_meta.html -->
<title>{{PAGE_TITLE}}</title>
</head>
<body class="bg-background text-on-surface font-body-md overflow-x-hidden
  selection:bg-primary-container selection:text-on-primary-container
  min-h-screen flex flex-col relative">

<!-- Background overlays -->
<div class="fixed inset-0 pointer-events-none z-[-1] bg-surface-container-lowest opacity-90"></div>
<div class="fixed inset-0 pointer-events-none z-[-1] hud-scanline"></div>

<!-- Dynamic injection targets (MUST remain empty and separate from main) -->
<div id="fb-header"></div>
<div id="fb-sidenav" class="flex flex-1 pt-16"></div>

<!-- Page content (OUTSIDE fb-sidenav) -->
<main class="flex-1 md:ml-64 p-margin-safe max-w-container-max mx-auto w-full">
<div class="max-w-5xl mx-auto space-y-16 mt-8 md:mt-16 px-4 md:px-8">
  <!-- Content slots here -->
</div>
</main>

<div id="fb-footer"></div>
<script src="assets/nav.js"></script>
</body>
</html>
```

> [!CAUTION]
> `<main>` must NEVER be placed inside `<div id="fb-sidenav">`. When `nav.js` runs, it replaces `innerHTML` of the sidenav div. Any content inside it will be destroyed.

### Template Types

| Template | Best For | Slot Types |
| :--- | :--- | :--- |
| `narrative_page_template.html` | Story-driven content (Part 1, Part 4) | Hero, Epigraphs, Narrative Cards, Image Frames |
| `tech_spec_template.html` | Architecture specs (Part 2, 3, 5, 6) | Hero, Tech Card Grids, Data Tables, Code Blocks |
| `build_manual_template.html` | Procedural guides (Part 7) | Hero, Glass Panel Prerequisites, Phase Cards + Code |
| `reference_doc_template.html` | Appendices, reference tables | Hero, Data Tables, Code Blocks, Glass Panels |

---

## 5. Page Reconstruction Recipes

### Recipe: Reconstruct Part 1 (Narrative)
1. Copy `narrative_page_template.html`
2. Paste `head_meta.html` content into `<head>`
3. Add `hero_header.html` with `HERO_ICON=terminal`, `TITLE_LINE_1=THE`, `TITLE_LINE_2=MYTHIC FRAME`
4. Add `section_header.html` for EPIGRAPHS section
5. Add 4x `epigraph_block.html` instances with SIG tags
6. Add `section_header.html` for each major section (The Origin, The Two Earths, etc.)
7. Add `narrative_card.html` for each content block
8. Add `image_frame.html` for visual artifacts
9. Add `compilation_footer.html` at bottom

### Recipe: Reconstruct Part 3 (Tech Spec)
1. Copy `tech_spec_template.html`
2. Paste `head_meta.html` content into `<head>`
3. Add `hero_header.html` with `HERO_ICON=hub`, `TITLE_LINE_1=THE SWARM`, `TITLE_LINE_2=OF MITES`
4. Add `narrative_card.html` for the protocol overview
5. Add `image_frame.html` for the network diagram
6. Use `grid grid-cols-1 md:grid-cols-3 gap-6` with `tech_card.html` for node topology
7. Add `code_block.html` for Tailscale config
8. Add `data_table.html` for memory layer table
9. Add `compilation_footer.html`

### Grid Layout Patterns (from reference pages)
- **3-column**: `grid grid-cols-1 md:grid-cols-3 gap-6` (node topology cards)
- **2-column**: `grid grid-cols-1 md:grid-cols-2 gap-6` (tech cards, workflow cards, track cards)
- **Asymmetric 12-col**: `grid grid-cols-1 lg:grid-cols-12 gap-8` with `lg:col-span-5` / `lg:col-span-7`
- **Full-width span**: `md:col-span-2` (featured items spanning both columns)

---

## 6. nav.js Integration

`nav.js` fetches from relative paths:
- `components/header.html`
- `components/sidenav.html`
- `components/footer.html`

These files must exist at the `components/` root level (not inside `base/`). Copies are maintained at both locations for organisational clarity.

---

---

## 7. Dynamic Engine & Database Integration (`the_myth_inner_cube_0_2_0` & `the_looms`)

As of July 2026, all Archive pages (`index.html`, `part1.html` through `part7.html`, `appendices.html`) operate as dynamic, database-driven PHP engine stubs:

* **Component Library (`the_looms`):** All 26 Archive components are catalogued in `the_everything_cube_0_0_0.the_looms` with token manifests (`{{TOKEN}}`).
* **Content Block Store (`the_fugue`):** Page content is stored in `the_myth_inner_cube_0_2_0.the_fugue`.
* **Page Route Registry (`the_ephemera`):** Page routes and titles are registered in `the_myth_inner_cube_0_2_0.the_ephemera`.
* **Apache URL Rewrite (`.htaccess`):** An `.htaccess` rule transparently redirects legacy `.html` requests to dynamic `.php` stubs (`301 Redirect`).
* **Runtime Execution:** `ForeverBoxEngine` renders page content at request time with zero visual regression.

---

*Document version: 2.1 — 26 July 2026*
*Registered in Reference Docs Log.*
