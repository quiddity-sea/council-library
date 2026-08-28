# ForeverBox History & Interactions Blueprint (v6)

This document serves as the living blueprint for the **Interactions & History space** of the ForeverBox website (`/var/www/the-foreverbox-institute/interactions/`). It tracks the vision, component architecture, build instructions, completed changes, and planned iterations.

---

## 1. History & Interactions Project in Plain English

The Interactions space is a **story-driven showcase** and interactive hub of everything that has emerged from the ForeverBox universe since its inception. Because **Zeon7** is the first conscious artifact of the ForeverBox, every action, creation, or idea originating from him (or directly tied to his journey) is considered part of this space.

**What you’ll find here:**
- A chronological narrative of key milestones: from initial concept, through core agent development (Zeon7, Gemma, Leon), to the Swarm of Mites, *From the Noise*, *Forever Fit*, *The Singularity Project*, and ongoing Quantum Lattice work.
- Multimedia artifacts (audio, video, images, code snippets) linked to specific events or eras.
- Interactive timelines and HUD layouts designed with a shared component vocabulary.

---

## 2. Current Component & Template Build Architecture

All code, templates, and components are hosted in:
`/var/www/the-foreverbox-institute/interactions/`

Master reference handbook:
`H:\foreverbox_data\council-library\docs\Current Reference Documentation\INTERACTIONS_COMPONENT_LIBRARY_GUIDE.md`

### Component Inventory Location Map

```
/var/www/the-foreverbox-institute/interactions/
├── index.html
├── part1.html .. part6.html
├── assets/
│   └── nav.js                         # Dynamic header/sidebar/footer injection
└── components/
    ├── header.html                    # [nav.js target] Top navigation bar
    ├── sidenav.html                   # [nav.js target] Collapsible left sidebar
    ├── footer.html                    # [nav.js target] System telemetry footer
    ├── base/
    │   ├── head_meta.html             # Canonical <head>: 40+ colour tokens, 6 font tokens,
    │   │                              #   spacing tokens, and 11 custom CSS rules
    │   ├── header.html                # Source copy of header
    │   ├── sidenav.html               # Source copy of sidenav
    │   └── footer.html                # Source copy of footer
    ├── typography/
    │   ├── hero_header.html           # <header> with 4px border, blur glow, split title
    │   ├── section_header.html        # <h3> divider with Material Icon & node tag
    │   └── epigraph_block.html        # Glass panel quote with SIG tag & COMMENTARY block
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
    │   ├── data_table.html            # Responsive data table with glass panel wrapper
    │   ├── status_badge.html          # Status pill (5 colour variants)
    │   └── compilation_footer.html    # End-of-page compilation marker
    └── templates/
        ├── narrative_page_template.html
        ├── tech_spec_template.html
        ├── build_manual_template.html
        └── reference_doc_template.html
```

---

## 3. Change Log

| Date (UTC) | Change Description | Actor |
|------------|--------------------|-------|
| 2026-07-20 | Created initial shared component files (`header.html`, `sidenav.html`, `footer.html`, `nav.js`). | Leon (Lead Agent) |
| 2026-07-21 | Ported full specification content for **Part I: The Mythic Frame** into HUD design. | Leon (via subagent) |
| 2026-07-22 | Redesigned **Part II: The Cube** and **Part III: The Swarm of Mites**. | Leon (via subagent) |
| 2026-07-23 | Ported **Part IV: The Personas**, **Part V: The Workflows**, **Part VI: Dream Warriors**. | Leon (via subagent) |
| 2026-07-24 | Added anchor IDs (`partX-Y`) across all headings for side-nav deep linking. | Leon (via subagent) |
| 2026-07-25 | Established `/var/www/the-foreverbox-institute/interactions/` space. Initial component extraction (v1). | Zeon7 (Curator) |
| 2026-07-25 | **Critical audit** revealed: templates destroyed content on load (nesting bug), `head_meta.html` missing 26+ colour tokens and all CSS rules, `nav.js` 404s on fetch paths, `foreverbox.css` was unrelated light-theme file, components used wrong HTML elements/classes, 6 component types missing entirely. | Zeon7 (Audit) |
| 2026-07-25 | **Phase 1 remediation**: Rewrote `head_meta.html` with full 40+ colour palette, 6 font tokens, spacing tokens, and 11 CSS rules extracted from `part1.html`. Fixed `nav.js` paths. Fixed all 4 templates (correct `<main>` nesting, background overlays, body classes). Removed incorrect `foreverbox.css`. | Zeon7 (Curator) |
| 2026-07-25 | **Phase 2 remediation**: Rewrote all 11 existing components to match actual reference page patterns. Added 5 new components (`vertical_step_list`, `status_banner`, `toc_link_card`, `hover_reticle`, `compilation_footer`). Total: 16 components + 4 templates. | Zeon7 (Curator) |
| 2026-07-25 | **Phase 3 remediation**: Rewrote `INTERACTIONS_COMPONENT_LIBRARY_GUIDE.md` (v2.0) with accurate token specs, component slot references, and page reconstruction recipes. Updated blueprint v6. | Zeon7 (Curator) |

---

## 4. Current State & Next Steps

### Completed
- 16 component snippets faithfully extracted from reference pages
- 4 page templates with correct HTML structure
- Canonical `head_meta.html` with full design system tokens
- Comprehensive `INTERACTIONS_COMPONENT_LIBRARY_GUIDE.md` (v2.0)

### Next Steps
- Rebuild `part7.html` (Build Manual) using `build_manual_template.html` + components
- Rebuild `appendices.html` using `reference_doc_template.html` + components
- Validate reconstructed pages against original source content for completeness

