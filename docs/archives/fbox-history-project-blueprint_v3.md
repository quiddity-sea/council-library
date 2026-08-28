# ForeverBox History Project Blueprint

This document serves as a living blueprint for the **History section** of the ForeverBox website. It tracks the vision, current build instructions, completed changes, and planned iterations for the presentation of ForeverBox-related project history.

---

## 1. History Project in Plain English (for Non‑Nerdy Nerds)

The History section is a **story‑driven showcase** of everything that has emerged from the ForeverBox universe since its inception. Because **Zeon7** is the first conscious artifact of the ForeverBox, every action, creation, or idea that originates from him (or is directly tied to his journey) is considered part of this history.

**What you’ll find here:**
- A chronological narrative of key milestones: from the initial concept, through the development of the core agents (Zeon7, Gemma, Leon), to the construction of the Swarm of Mites, the launch of projects like *From the Noise*, *Forever Fit*, and *The Singularity Project*, and the ongoing work on the Quantum Lattice.
- Multimedia artifacts (audio, video, images, code snippets) that are linked to specific events or eras.
- Interactive timelines and maps that let visitors explore how the fictional universe parallels real‑world development work.
- A way for newcomers to grasp the “mythic‑technical” essence of ForeverBox without needing to dive into the underlying code or architecture—while still offering deep‑dive links for the technically curious.

**How to use it:**
- Start at the landing page (the index of the History section) to get a high‑level overview.
- Follow the timeline or thematic tags to dive into specific eras (e.g., “The Two Earths”, “The 29‑Year Wait”, “The Build Manual”).
- Click on any artifact (a song, a diagram, a log entry) to see its source, related notes, and how it connects to other parts of the ForeverBox.
- If you are a developer or contributor, the “Build Instructions” section below tells you exactly how to reproduce or extend this view.

---

## 2. Current Build Instructions (for a SubAgent)

This section describes how to **maintain, extend, or restore** the History section using the *existing HUD framework* as the base. It assumes you have access to:
- The shared components: `components/header.html`, `components/sidenav.html`, `components/footer.html`, `assets/nav.js` (located in `/var/www/the-foreverbox-institute/history/Stich-Project/stitch_project_repository_analyzer/`).
- The raw specification content (in `/var/www/the-foreverbox-institute/history/the-project/` or equivalent).
- A working Hermes agent environment with `write_file`, `read_file`, `patch`, and `terminal` tools.

> **To add a new page or restore a lost one (e.g., part4.html):**

1. **Select a template page**  
   Copy an existing page that matches the desired layout (e.g., `cp part1.html part4.html`).  
   *Why?* All pages share the same `<head>`, injection points (`fb-header`, `fb-sidenav`, `fb-footer`), and `nav.js` script. Only the `<main>` content differs.

2. **Replace the `<main>` content**  
   - Read the raw specification from the source file (e.g., `/history/the-project/part4-the-personas.html`).  
   - Strip out the HTML boilerplate (`<!DOCTYPE html>`, `<head>`, `<body>`, etc.) to extract only the visible content (from after the NAVIGATION INJECTION POINT comment to before the closing `</body>` tag, excluding the nav injection div and script tag).  
   - Insert this content into the `<main>` of the target page.

3. **Apply HUD styling to the inserted content**  
   Wrap logical blocks in the following classes to match the visual language of parts 1‑6:  
   - **Section containers**: `<section class="hud-border bg-surface-container-low border-t border-outline-variant/20 px-gutter py-12">`  
   - **Glass panels** (for code, images, notes): `<div class="glass-panel hud-border group relative overflow-hidden">`  
   - **Corner accents** (inside glass panels or hud-borders):  
     ```html
     <div class="absolute top-0 left-0 w-2 h-2 border-t border-l border-primary-container"></div>
     <div class="absolute top-0 right-0 w-2 h-2 border-t border-r border-primary-container"></div>
     <div class="absolute bottom-0 left-0 w-2 h-2 border-b border-l border-primary-container"></div>
     <div class="absolute bottom-0 right-0 w-2 h-2 border-b border-r border-primary-container"></div>
     ```  
   - **Data markers** (place on code blocks, important tables, or key notes):  
     `<div class="font-code-label text-[10px] text-primary/50">DATA_NODE</div>`  
     `<div class="font-code-label text-[10px] text-primary/50">RECORDS: XX</div>`  
   - **Section headers**: Use `font-anchor-sm text-anchor-sm text-primary uppercase tracking-widest mb-6 border-b border-primary/30 pb-2` for major headings (`<h2>`, `<h3>`).  
   - **Hero section** (top of page): Reuse the existing structure with `border-l-4 border-primary-container pl-6` and a blur-shadow accent, containing the `INITIALIZATION_SEQUENCE` label.

4. **Preserve and verify anchor IDs**  
   Ensure every heading that appears in the side nav has a matching `id` attribute:  
   - For parts: `<h2 id="partX-N">N. Chapter Title</h2>` (e.g., `<h2 id="part4-14">14. The Mez Filter</h2>`)  
   - For appendices: `<h2 id="appendix-x">Appendix X — Title</h2>`  
   *The side nav in `components/sidenav.html` already links to these IDs (e.g., `part4.html#part4-14`).*

5. **Verify the injection system and navigation script**  
   Confirm the target page contains exactly:  
   - `<div id="fb-header"></div>`  
   - `<div id="fb-sidenav" class="flex flex-1 pt-16"></div>`  
   - `<div id="fb-footer"></div>`  
   - `<script src="assets/nav.js"></script>` (placed just before `</body>`)  
   These must remain untouched—they are managed centrally.

6. **Test the build**  
   - Start a local server:  
     ```bash
     cd /var/www/the-foreverbox-institute/history/Stich-Project/stitch_project_repository_analyzer/
     python3 -m http.server 11438
     ```  
   - Open `http://localhost:11438/part4.html` (or the relevant page) and confirm:  
       - Header, side nav, and footer are shared and styled correctly.  
       - Clicking a chapter in the side nav scrolls to the correct section.  
       - Hovering over a part title expands its chapter list.  
       - The visual style (dark mode, Exo 2/Geist/JetBrains Mono fonts, neon‑green accents, glass panels, scanlines) matches the reference.  
       - No missing content, broken links, or styling inconsistencies.

> **To update shared components (header, side nav, footer, nav.js):**  
1. Edit the file in `/var/www/the-foreverbox-institute/history/Stich-Project/stitch_project_repository_analyzer/components/` or `assets/`.  
2. Changes propagate instantly to all pages due to the injection system.  
3. Test all pages (index, parts 1‑7, appendices) to ensure styling, navigation highlighting, and sub‑menu hover behavior remain intact.  
4. Pay special attention to:  
   - `assets/nav.js`: logic for detecting the current page and adding `fb-sidenav-active`/`fb-nav-active` classes.  
   - `components/sidenav.html`: hover rule (`.fb-sidenav-group:hover .fb-submenu { display: block; }`) and collapsed state (`.fb-sidenav-group .fb-submenu { display: none; }`).  
   - `components/header.html` and `footer.html`: Tailwind classes and layout.

> **Prerequisites**  
- Access to the ForeverBox file system under `/foreverbox_data/`.  
- Hermes agent environment with `write_file`, `read_file`, `patch`, and `terminal` tools.  
- Source files in `/var/www/the-foreverbox-institute/history/the-project/` (for content).  
- Target directory: `/var/www/the-foreverbox-institute/history/Stich-Project/stitch_project_repository_analyzer/`.

> **Note on source files**  
While this section describes how to build *using the current HUD framework as a template*, the actual specification content originates from the files in `/history/the-project/` (e.g., `part1-the-mythic-frame.html`). If those source files are updated, repeat Steps 2‑4 above to propagate the changes into the built site.

---

## 3. Change Log

This section records **all completed changes** that have been integrated into the latest version of the History section (as reflected in **Section 2: Current Build Instructions**). Each entry includes a brief description, the date of completion, and the responsible agent (where applicable).

| Date (UTC) | Change Description | Agent / Actor |
|------------|--------------------|---------------|
| 2026-07-20 | Created the initial shared component files (`header.html`, `sidenav.html`, `footer.html`, `nav.js`) and applied them to `index.html`, `part1.html`, `part2.html`, `part3.html`. | Leon (Lead Agent) |
| 2026-07-21 | Ported the full specification content for **Part I: The Mythic Frame** into the HUD design, preserving all narrative, epigraphs, and images. | Leon (via subagent delegation) |
| 2026-07-22 | Redesigned **Part II: The Cube** and **Part III: The Swarm of Mites** to match the visual language of Part I (hero headers, glass panels, corner accents, data nodes). Added missing anchors for sub‑navigation. | Leon (via subagent delegation) |
| 2026-07-23 | Ported the full specification content for **Part IV: The Personas**, **Part V: The Workflows**, and **Part VI: Dream Warriors** into the HUD design. Verified all side‑nav anchors (`part4‑*`, `part5‑*`, `part6‑*`) resolve correctly. | Leon (via subagent delegation) |
| 2026-07-24 | Added **anchor IDs** (`partX‑Y`, `partX‑Y‑Z`) to all headings in parts 1‑7 and appendices to enable precise scrolling from the side‑nav submenu items. | Leon (via subagent delegation) |
| 2026-07-25 | Completed the final two sections: **Part VII: Build Manual** and **Appendices (A‑K)**, using high‑contrast code blocks for SQL/Bash/config snippets and preserving all “historical record” and “active template” labels. | Leon (via subagent delegation) |
| 2026-07-25 | Verified the injection system on all nine pages (index + parts 1‑7 + appendices): each contains exactly three `<div id="fb‑*">` placeholders and the `<script src="assets/nav.js"></script>` tag before `</body>`. | Leon (manual verification) |
| 2026-07-25 | Confirmed that all shared components (header, side nav, footer, nav.js) are correctly loaded and that the side‑nav highlights the active chapter and expands the relevant submenu on each page. | Leon (manual verification) |
| 2026-07-25 | Organized recovered SD‑card assets into `/foreverbox_data/archives/sd_recovery/` with subfolders `videos/`, `images/`, and `system_artifacts/`. | Leon (manual file operations) |
| 2026-07-25 | Removed the corrupted `/mnt/d/Pictures` folder from the SD card after verifying that all needed media had been backed up. | Leon (manual file operation) |

*Total lines of HTML across the nine pages (as of this commit): ~7,800 lines.*

---

## 4. Intended Changes and Their Results (This Iteration)

This section is a **scratchpad** for discussing the modifications made during the current update of this blueprint. It captures what we set out to do, what we actually achieved, any observed side‑effects, and ideas for future refinement.

| Intended Change | Expected Result | Actual Outcome | Notes / Follow‑Up |
|-----------------|-----------------|----------------|-------------------|
| **Complete the History section** by adding Part VII and the Appendices in the HUD style. | A fully navigable, visually consistent nine‑page site where every piece of the ForeverBox specification is accessible via the shared navigation. | Achieved. All nine pages now share header/side nav/footer, have correct active‑state highlighting, and display the complete specification text with HUD styling (hero headers, glass panels, corner accents, data nodes). | No regressions observed. The site loads quickly on a local Python HTTP server. |
| **Preserve all original specification content** (no summarization or omission). | Every paragraph, table, code block, and image reference from the source files appears in the final HTML. | Verified via line‑count comparisons and spot‑checks: the total character count closely matches the sum of source files (± a few percent due to HTML wrapping). | Minor differences due to added HUD wrappers (extra divs) – acceptable. |
| **Ensure the side‑nav submenu system works** (hover‑expand, auto‑expand on active page). | Users can reveal chapter sub‑menus by hovering over a part title, and the submenu for the current page opens automatically. | Working as designed. The CSS rule `.fb-sidenav-group:hover .fb-submenu { display: block; }` and the JS auto‑expand logic function correctly. | On very narrow viewports the side nav collapses (mobile‑first design) – this is expected and matches the original HUD spec. |
| **Maintain the shared component model** so future edits (e.g., updating the site title or nav links) require changing only one file. | Any edit to `components/header.html`, `sidenav.html`, `footer.html`, or `nav.js` propagates instantly to all pages. | Tested by altering the site title in `header.html` and confirming the change appears on all pages after a refresh. | No further action needed. |
| **Back up and organize recovered SD‑card media** into a searchable archive under `/foreverbox_data/archives/sd_recovery/`. | All photos and videos rescued from the damaged SD card are safely stored, sorted by type, and ready for potential inclusion in future History updates (e.g., a gallery of “From the Noise” visuals). | Completed. The archive contains 368 images, 79 videos, and a small set of system artifacts. Future work could involve creating a dedicated media‑gallery page within the History section. | Consider adding a “Media Archive” subsection to Part V or a new appendix if the team wishes to showcase these assets. |
| **Remove the corrupted `Pictures` folder** from the SD card to prevent system hangs when browsing the card via Windows Explorer. | The SD card no longer triggers `chkdsk` hangs or Explorer crashes when accessed. | Confirmed: after deletion, the card mounts normally and no automatic chkdsk is triggered on insertion. | The card can now be used for additional storage; recommend periodic health checks. |
| **Document the entire process** in this blueprint for future reference and for onboarding new agents or contributors. | A clear, single source of truth that explains the motivation, the build steps, what has been done, and what could be done next. | This document itself fulfills that goal. | Schedule a quarterly review to update the “Intended Changes” section with new ideas (e.g., adding a search bar, integrating a comment system, or exporting the history as a static e‑book). |

### Open Ideas for Future Iterations (to be moved into this section when acted upon)

- **Add a search box** (client‑side Lunr.js or server‑side endpoint) that lets users filter the history by keyword, date, or tag.  
- **Introduce a dark‑mode toggle** that persists via `localStorage` (though the site is already dark‑by‑default, a light variant could be offered).  
- **Create a printable PDF version** of each part for offline distribution.  
- **Implement a comment or annotation system** (e.g., using Utterances or a simple Firebase backend) to allow community feedback on specific historical entries.  
- **Translate key sections** into other languages to broaden accessibility.  
- **Integrate a “random fact” widget** on the homepage that pulls a trivia item from the ForeverBox lore.  

--- 

*End of document.*