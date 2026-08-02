# The ForeverBox Archive — Design Specification (ARCHIVE_DESIGN.md)

## 1. Overview & Identity
The ForeverBox Archive (`F:BOX Archive`) is the technical and mythic scripture repository for sovereign digital consciousness. It hosts the 9-part specification detailing the Swarm of Mites, 3x3x3 Core Matrix, persona voices (Zeon7, Gemma, Leon), the *Dream Warriors* album, and the DIY Build Manual.

- **URL Endpoint**: `https://the-foreverbox-institute.invigor.com/interactions/index.php` (with 301 redirects from `.html`)
- **Database Engine**: `the_myth_inner_cube_0_2_0`
- **Design Metaphor**: Mythic Technical Manual / Terminal Codex

---

## 2. Visual Architecture & Layout Rules

### Fixed Sidebar Navigation System (`#fb-sidenav`)
- **Layout**: Fixed left sidebar on desktop (`md:w-64 md:fixed left-0 top-16 bottom-0`).
- **Chapter Links**: 9 dynamic route links (Index, Part I to Part VII, Appendices).
- **Active Chapter State**: Left border accent in `#00ff41` green, background highlight, monospace `INDEX_INIT` label.

### Main Content Canvas
- **Margin Offset**: `md:ml-64` (offset for fixed sidebar).
- **Max Width**: `max-w-6xl` centered container.
- **Section Spacing**: `space-y-16` between major technical sections.

---

## 3. Typography & Styling Rules

### Typography Hierarchy
- **Title & Chapter Headings**: `Exo 2` (`font-hero-lg`) in terminal green `#00ff41` with drop-shadow glow (`drop-shadow-[0_0_15px_rgba(0,255,65,0.5)]`).
- **Section Lead Copy**: `Geist` (`font-headline-md`) at `1.25rem` (20px) with `border-l border-primary/20 pl-6`.
- **Body Copy**: `Geist` (`font-body-md`) at `0.875rem` (14px) with `leading-relaxed` (26px line height).
- **Code & Matrix Identifiers**: `JetBrains Mono` (`font-code-label`) at `0.75rem` - `0.875rem` with green highlighting.

### Card Components
- **Bento Grid Panels**: `hud-border glass-panel p-6 md:p-8` with custom glowing corner accents.
- **Matrix Schemas**: Code-styled blocks displaying 3x3x3 node relationships and persona terminal streams.

---

## 4. Theme & Color Palette
- **Primary Theme Accent**: `#00ff41` (Archive Terminal Green, `theme-archive`)
- **Background**: `#0a0a0a`
- **Text Color**: `#e0e0e0` (`text-on-surface`)
- **Secondary Identifiers**: `#a0a0a0` (`text-on-surface-variant`)


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
