# The ForeverBox Institute — Design Specification (INSTITUTE_DESIGN.md)

## 1. Overview & Identity
The ForeverBox Institute is the primary portal for quantum biological research, clinical transformation case studies, the Forever Fit digital health platform, and Series A investment.

- **URL Endpoint**: `https://the-foreverbox-institute.invigor.com/institute/index.php`
- **Database Engine**: `the_everything_cube_0_0_0`
- **Design Metaphor**: Multi-Theme Neurodivergent-First HUD Portal

---

## 2. Visual Architecture & Layout Rules

### Dynamic 6-Theme Color Engine
The Institute dynamically adapts its primary color palette based on the active page route (`$pageTheme`):

| Page Route | Theme Class | Primary Accent Hex | Primary RGB | Metaphor / Focus |
| :--- | :--- | :--- | :--- | :--- |
| `index.php` | `theme-origin` | `#00ff41` | `0, 255, 65` | Quantum Matrix Green |
| `origin.php` | `theme-origin` | `#00ff41` | `0, 255, 65` | Snowdonia Sanctuary |
| `science.php` | `theme-science` | `#00897b` | `0, 137, 123` | Deep Teal Research |
| `case-studies.php` | `theme-cases` | `#e53935` | `229, 57, 53` | Clinical Crimson Dossier |
| `forever-fit.php` | `theme-fit` | `#fbc02d` | `251, 192, 45` | Energetic Amber App |
| `investment.php` | `theme-investment` | `#7b1fa2` | `123, 31, 162` | Sovereign Purple Capital |
| `vision.php` | `theme-vision` | `#0288d1` | `2, 136, 209` | Horizon Cyan Future |

---

## 3. Standardized HUD Layout Components

### 1. Global Scanline & Header Container
- **Scanline Overlay**: Fixed pointer-events-none HUD scanline (`hud-scanline`).
- **Global Header**: `#foreverbox-nav` fixed top navigation bar with dynamic theme accenting.
- **Global Footer**: Fixed bottom status footer (`STATUS: ONLINE`, `NODE: WALES_HUB`).

### 2. Standardized HUD Hero Header Component
Used across all sub-pages (`science`, `case-studies`, `forever-fit`, `investment`, `vision`, `about`, `origin`):
```html
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <header class="mb-12">
        <div class="font-code-label text-code-label text-secondary mb-4 flex items-center space-x-2">
            <span class="material-symbols-outlined text-[1rem]">ICON_NAME</span>
            <span>CATEGORY_LABEL <span class="blinking-cursor w-2 h-3 inline-block ml-1 align-middle bg-primary"></span></span>
        </div>
        <h1 class="font-hero-lg text-hero-lg text-on-surface mb-6">
            <span class="font-semibold text-primary">TITLE_PART_1</span> 
            <span class="font-thin glow-text text-primary drop-shadow-[0_0_15px_rgba(var(--color-primary-rgb),0.5)]">TITLE_PART_2</span>
        </h1>
        <p class="font-headline-md text-[1.25rem] md:text-headline-md text-on-surface-variant max-w-3xl leading-relaxed border-l border-primary/20 pl-6 mb-8">
            LEAD_DESCRIPTION_TEXT
        </p>
    </header>
```

### 3. Glass Panel & Dossier Cards
- **Panels**: `hud-border glass-panel p-8 md:p-10 panel-glow`
- **Typography**:
  - Headings: `Exo 2` (`font-hero-lg`)
  - Sub-headings: `Geist` (`font-headline-md`)
  - Body: `Geist` (`font-body-md`, 16px, 26px line height, `#e0e0e0`)
  - Monospace: `JetBrains Mono` (`font-code-label`)


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
