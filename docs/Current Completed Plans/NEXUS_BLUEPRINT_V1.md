# THE NEXUS — Connected Sites Manager
## Blueprint V1 — July 2026

---

## 0. What Is The Nexus

The Nexus is a centralised registry and control panel for all websites linked through the Foreverbox ecosystem. Every site connected via `/foreverbox_data/connected_sites/` is registered in the `connected_sites` database table and displayed on a dashboard at `the-foreverbox-institute.invigor.com`.

The Nexus serves three purposes:
1. **Registry** — track every connected site with metadata, purpose, vectors, and tags
2. **Dashboard** — view all sites at a glance with status, creator, and last update
3. **Manager** — add, edit, and remove sites through a web interface

---

## 1. Architecture

```
┌──────────────────────────────────────────────────────┐
│  Browser (the-foreverbox-institute.invigor.com)      │
│  PHP + jQuery + XHTML + CSS                          │
├──────────────────────────────────────────────────────┤
│  PHP API (Apache :8080)                              │
│  /v1/commons/sites — public-read CRUD endpoints      │
├──────────────────────────────────────────────────────┤
│  MariaDB (quiddity_commons)                          │
│  connected_sites table + vector embeddings           │
├──────────────────────────────────────────────────────┤
│  Filesystem                                          │
│  /foreverbox_data/connected_sites/{slug}/            │
│  → /var/www/{domain}/                                │
└──────────────────────────────────────────────────────┘
```

### Three Pillars

| Layer | Technology | Role |
|-------|-----------|------|
| **Database** | MariaDB `quiddity_commons.connected_sites` | Stores all site metadata, vectors, tags |
| **API** | PHP 8.3 (Slim 4) | Public-read CRUD endpoints |
| **Dashboard** | PHP + jQuery + XHTML | Web UI for viewing, adding, editing sites |

---

## 2. Database Schema

### `quiddity_commons.connected_sites`

```sql
CREATE TABLE connected_sites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(128) NOT NULL UNIQUE,
    domain VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    purpose TEXT,
    main_vectors JSON,              -- ["ecosystem", "philosophy", "documentation"]
    filter_tags JSON,               -- ["active", "published", "draft"]
    creator VARCHAR(64) NOT NULL DEFAULT 'leon',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by VARCHAR(64) DEFAULT 'leon',
    web_root_path VARCHAR(512) NOT NULL,
    symlink_path VARCHAR(512),
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_active (is_active),
    INDEX idx_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Vector integration:** Each site's `description` + `purpose` can be embedded via the embedding service (all-MiniLM-L6-v2, 384-dim) and stored in `quiddity_vector_references`, making sites searchable through `/v1/commons/search` alongside Lore Sea files.

---

## 3. API Endpoints

Base URL: `http://localhost:8080/v1`

All GET endpoints are public-read. POST requires authentication.

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/commons/sites` | Public | List all active sites with full metadata |
| GET | `/commons/sites/{slug}` | Public | Get a specific site by slug |
| POST | `/commons/sites` | Required | Register or update a site |

### POST Body

```json
{
    "slug": "my-site",
    "domain": "my-site.invigor.com",
    "title": "My Site",
    "description": "A short description of what this site contains.",
    "purpose": "Why this site exists — its mission within the ecosystem.",
    "main_vectors": ["technology", "writing", "archive"],
    "filter_tags": ["active", "published"],
    "web_root_path": "/var/www/my-site",
    "symlink_path": "/foreverbox_data/connected_sites/my-site"
}
```

---

## 4. Filesystem Layout

```
/foreverbox_data/
├── connected_sites/                 # Symlinks to all web roots
│   └── the-foreverbox-institute     → /var/www/the-foreverbox-institute/

/foreverbox_data/council-library/
├── the-nexus/                       # Nexus Blueprint + Manager
│   └── NEXUS_BLUEPRINT_V1.md        ← This document
├── php-api/
│   ├── public/index.php             # Route registration
│   ├── src/
│   │   ├── bootstrap.php            # DI container entry
│   │   ├── Controller/ConnectedSitesController.php  # CRUD logic
│   │   └── Middleware/Auth.php       # Public prefix for /sites
│   └── schema/connected_sites.sql   # DDL
├── scripts/generate_folder_centroids.py  # (can extend for site vectors)
└── docs/
    └── Current Reference Documentation/
        └── COUNCIL_LIBRARY_HANDBOOK_V2.md  # Updated with Nexus info

/var/www/the-foreverbox-institute/   # Web root (Apache)
├── index.php                        # Dashboard — lists all sites
├── Parsedown.php                    # Markdown parser
└── *.md                             # Optional static pages
```

---

## 5. Dashboard UI — The Nexus Manager

### Current State

`the-foreverbox-institute.invigor.com/` displays a card-based dashboard showing all registered sites. Each card shows:

- **Title** (linked to the site's domain)
- **Domain** (subtitle)
- **Description** (short paragraph)
- **Purpose** (the site's reason for existing)
- **Main vectors** (coloured tags — blue background)
- **Filter tags** (colour-coded: green=active, yellow=draft, purple=other)
- **Metadata footer** (created by X on date, updated by Y on date)

### Planned — Add/Edit Interface

The Manager will include forms for adding new sites and editing existing ones directly from the browser:

#### Add Site Form

```
┌─────────────────────────────────────────────────────┐
│  The Nexus — Add New Site                            │
│                                                       │
│  Slug:        [_________________________]            │
│  Domain:      [_________________________]            │
│  Title:       [_________________________]            │
│  Description: [_________________________]            │
│  Purpose:     [_________________________]            │
│                                                       │
│  Main Vectors:  [+] technology  [+] philosophy       │
│                 [+] archive     [+] music            │
│                                                       │
│  Filter Tags:   [+] active      [+] draft            │
│                 [+] published                        │
│                                                       │
│  Web Root:      /var/www/{slug} (auto-generated)     │
│                                                       │
│  [✓] Create symlink in connected_sites/              │
│  [✓] Create Apache VirtualHost                       │
│  [✓] Activate site                                   │
│                                                       │
│  [Cancel]              [Create Site]                  │
└─────────────────────────────────────────────────────┘
```

#### Edit Site Form

Same fields as Add, pre-populated with current values. Plus:

- **Last updated** display (read-only)
- **Last updated by** display (read-only)
- **Created at** display (read-only)

#### Implementation

The Manager UI will be built as a single-page PHP application at `/var/www/the-foreverbox-institute/manager.php` using:

- **PHP** — server-side rendering, database queries, API proxy
- **jQuery** — AJAX form submission, dynamic tag fields, client-side validation
- **XHTML** — strict, clean markup
- **JavaScript** — tag input autocomplete, slug auto-generation from title, domain preview

---

## 6. Site Management Workflow

### Adding a New Site

1. User fills in the Add Site form in the Manager
2. Client-side validation checks required fields and slug format
3. JavaScript auto-generates the domain as `{slug}.invigor.com` and the web root as `/var/www/{slug}`
4. On submit, jQuery sends `POST /v1/commons/sites` via AJAX
5. On success, the browser redirects to the new site's detail page
6. Admin then creates the web root and Apache VirtualHost manually:
   ```bash
   sudo mkdir -p /var/www/{slug}
   sudo chown zeon7:zeon7 /var/www/{slug}
   ln -sf /var/www/{slug} /foreverbox_data/connected_sites/{slug}
   ```

### Editing an Existing Site

1. User clicks "Edit" on any site's card
2. Form pre-populates with current values
3. User modifies fields and submits
4. PUT-style request updates the database via `POST /v1/commons/sites` (upsert)
5. Dashboard refreshes to show updated metadata

### Deactivating a Site

1. User clicks "Deactivate" on a site
2. Confirmation dialog appears
3. On confirm, `is_active` set to 0 via API
4. Site no longer appears on the public dashboard
5. Reactivate by editing and toggling the Active flag

---

## 7. Future Enhancements

Not in V1 scope but tracked for V2:

| Feature | Description |
|---------|-------------|
| **Uptime monitoring** | Periodic HTTP ping per site, status displayed on dashboard (green/red) |
| **Site vectors** | Auto-embed each site's description + purpose into `quiddity_vector_references` for semantic search |
| **Apache VHost automation** | Python script that generates Apache config + enables site + reloads Apache |
| **SSL auto-config** | Add SSL VirtualHost alongside HTTP, using the existing wildcard `*.invigor.com` cert |
| **Activity log** | Track every site update: who changed what and when |
| **Python watcher** | Daemon that monitors `connected_sites/` for new symlinks and auto-registers them |
| **Public/private toggle** | Per-site visibility setting — public sites visible on the dashboard, private require auth |

---

## 8. Acceptance Criteria (V1)

- [x] `connected_sites` table exists in `quiddity_commons` with all required fields
- [x] `GET /v1/commons/sites` returns all active sites as JSON (public)
- [x] `GET /v1/commons/sites/{slug}` returns a single site (public)
- [x] `POST /v1/commons/sites` upserts a site record (authenticated)
- [x] The dashboard at `the-foreverbox-institute.invigor.com` displays site cards with vectors, tags, creator, updater
- [ ] Add Site form implemented in Manager UI
- [ ] Edit Site form implemented in Manager UI
- [ ] Deactivate Site flow implemented
- [ ] Slug auto-generation from title on the form
- [ ] Domain auto-generation as `{slug}.invigor.com`
- [ ] Client-side validation on all form fields

---

*End of Nexus Blueprint V1. Next: V2 — Uptime monitoring, vector embedding, Apache automation.*