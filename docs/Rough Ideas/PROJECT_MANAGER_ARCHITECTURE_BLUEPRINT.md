# Project & Life Management System — Architecture Blueprint

**Stack:** MariaDB · PHP 8.1+ · Vanilla AJAX (fetch) · HTML5 **Status:** Draft v1 **Parent ecosystem:** ForeverBox (Identity/Core) · Plutus (Finance) · Project Manager (this system)

---

## 1\. Product Definition

**Core concept:** A standalone Project and Life Management application that operates independently but integrates deeply with Plutus (Finance) and the wider ForeverBox ecosystem.

**What it answers:** *What am I trying to accomplish, what needs to happen to accomplish it, what is happening now, and what happens next?*

**What it is NOT:**

- Not a financial tracker (that's Plutus)  
- Not a bare to-do list  
- Not a module bolted onto Plutus

**System responsibilities:**

| System | Owns |
| :---- | :---- |
| Project Manager | Projects, tasks, goals, work, deadlines, activities |
| Plutus | Money, finances, budgets, financial planning |
| ForeverBox (Core) | Identity, shared infrastructure, services, cross-application data |

---

## 2\. Domain Model

A single underlying **Activity Model** viewed through different lenses (List, Kanban, Calendar, Project).

**Conceptual hierarchy:**

Workspace

 └─ Goals            (high-level objectives)

     └─ Projects      (bound initiatives to achieve a goal)

         └─ Milestones (major checkpoints)

             └─ Tasks      (individual units of work)

                 └─ Subtasks & Dependencies

**Cross-cutting dimensions:**

- **Time** — deadlines, recurrence, events, appointments, time-tracking  
- **Organization** — Areas (Personal/Work/Home), Contexts, Tags, People/Resources  
- **Assets** — Notes, Files, Templates

**Key principle — one activity, many lenses:**

| Lens | Example view |
| :---- | :---- |
| Calendar | Tuesday 14:00 → Fix website |
| Kanban | To Do → Doing → Done |
| Project | Website redesign → Fix website |

---

## 3\. System Architecture

                    ForeverBox

                 (Identity / Core)

                       │

          ┌────────────┴────────────┐

          │                         │

       PLUTUS                 PROJECT MANAGER

      (Finance)              (Projects / Tasks)

          │                         │

          └───────────┬─────────────┘

                       │

               Integration Layer

**Architectural rules:**

1. **Standalone capability** — Plutus works fully without the Project Manager, and vice versa.  
2. **Authoritative sources** — Project Manager is sole owner of tasks/deadlines/milestones. Plutus is sole owner of transactions/budgets/receipts.  
3. **No data duplication** — data lives in its authoritative system and is *referenced*, never copied.

---

## 4\. MVP Scope

To avoid the "software cupboard" anti-pattern, the initial build covers only:

- Create Projects and standalone Tasks  
- Break Projects into Milestones and Subtasks  
- A unified Time/Activity view (List \+ Calendar)  
- Basic Contexts/Tags  
- API foundation for external querying

---

## 5\. Database Schema (MariaDB)

InnoDB throughout for FK integrity.

CREATE TABLE workspaces (

  id INT UNSIGNED AUTO\_INCREMENT PRIMARY KEY,

  user\_id INT UNSIGNED NOT NULL,       \-- FK to ForeverBox users table

  name VARCHAR(120) NOT NULL,

  created\_at DATETIME DEFAULT CURRENT\_TIMESTAMP

) ENGINE=InnoDB;

CREATE TABLE goals (

  id INT UNSIGNED AUTO\_INCREMENT PRIMARY KEY,

  workspace\_id INT UNSIGNED NOT NULL,

  title VARCHAR(200) NOT NULL,

  description TEXT,

  status ENUM('active','paused','achieved','abandoned') DEFAULT 'active',

  created\_at DATETIME DEFAULT CURRENT\_TIMESTAMP,

  FOREIGN KEY (workspace\_id) REFERENCES workspaces(id)

) ENGINE=InnoDB;

CREATE TABLE projects (

  id INT UNSIGNED AUTO\_INCREMENT PRIMARY KEY,

  goal\_id INT UNSIGNED NULL,           \-- nullable: projects can exist without a parent goal

  workspace\_id INT UNSIGNED NOT NULL,

  name VARCHAR(200) NOT NULL,

  status ENUM('planned','active','on\_hold','done') DEFAULT 'planned',

  start\_date DATE NULL,

  due\_date DATE NULL,

  FOREIGN KEY (goal\_id) REFERENCES goals(id),

  FOREIGN KEY (workspace\_id) REFERENCES workspaces(id)

) ENGINE=InnoDB;

CREATE TABLE milestones (

  id INT UNSIGNED AUTO\_INCREMENT PRIMARY KEY,

  project\_id INT UNSIGNED NOT NULL,

  title VARCHAR(200) NOT NULL,

  due\_date DATE NULL,

  sort\_order INT DEFAULT 0,

  FOREIGN KEY (project\_id) REFERENCES projects(id)

) ENGINE=InnoDB;

CREATE TABLE tasks (

  id INT UNSIGNED AUTO\_INCREMENT PRIMARY KEY,

  workspace\_id INT UNSIGNED NOT NULL,

  project\_id INT UNSIGNED NULL,        \-- nullable: standalone tasks allowed

  milestone\_id INT UNSIGNED NULL,

  parent\_task\_id INT UNSIGNED NULL,    \-- self-reference for subtasks

  title VARCHAR(200) NOT NULL,

  notes TEXT,

  status ENUM('todo','doing','done') DEFAULT 'todo',

  due\_date DATETIME NULL,

  sort\_order INT DEFAULT 0,

  created\_at DATETIME DEFAULT CURRENT\_TIMESTAMP,

  FOREIGN KEY (project\_id) REFERENCES projects(id),

  FOREIGN KEY (milestone\_id) REFERENCES milestones(id),

  FOREIGN KEY (parent\_task\_id) REFERENCES tasks(id)

) ENGINE=InnoDB;

CREATE TABLE task\_dependencies (

  task\_id INT UNSIGNED NOT NULL,

  depends\_on\_task\_id INT UNSIGNED NOT NULL,

  PRIMARY KEY (task\_id, depends\_on\_task\_id),

  FOREIGN KEY (task\_id) REFERENCES tasks(id),

  FOREIGN KEY (depends\_on\_task\_id) REFERENCES tasks(id)

) ENGINE=InnoDB;

CREATE TABLE tags (

  id INT UNSIGNED AUTO\_INCREMENT PRIMARY KEY,

  workspace\_id INT UNSIGNED NOT NULL,

  name VARCHAR(60) NOT NULL,

  type ENUM('area','context','tag') DEFAULT 'tag'

) ENGINE=InnoDB;

CREATE TABLE task\_tags (

  task\_id INT UNSIGNED NOT NULL,

  tag\_id INT UNSIGNED NOT NULL,

  PRIMARY KEY (task\_id, tag\_id),

  FOREIGN KEY (task\_id) REFERENCES tasks(id),

  FOREIGN KEY (tag\_id) REFERENCES tags(id)

) ENGINE=InnoDB;

CREATE TABLE calendar\_events (

  id INT UNSIGNED AUTO\_INCREMENT PRIMARY KEY,

  workspace\_id INT UNSIGNED NOT NULL,

  task\_id INT UNSIGNED NULL,           \-- optional link back to a task

  title VARCHAR(200) NOT NULL,

  starts\_at DATETIME NOT NULL,

  ends\_at DATETIME NULL,

  recurrence\_rule VARCHAR(200) NULL,   \-- simple RRULE-style string

  FOREIGN KEY (task\_id) REFERENCES tasks(id)

) ENGINE=InnoDB;

CREATE TABLE assets (

  id INT UNSIGNED AUTO\_INCREMENT PRIMARY KEY,

  workspace\_id INT UNSIGNED NOT NULL,

  project\_id INT UNSIGNED NULL,

  task\_id INT UNSIGNED NULL,

  type ENUM('note','file','template') NOT NULL,

  title VARCHAR(200) NOT NULL,

  content TEXT NULL,                   \-- for notes/templates

  file\_path VARCHAR(255) NULL,         \-- for uploaded files

  created\_at DATETIME DEFAULT CURRENT\_TIMESTAMP,

  FOREIGN KEY (project\_id) REFERENCES projects(id),

  FOREIGN KEY (task\_id) REFERENCES tasks(id)

) ENGINE=InnoDB;

CREATE TABLE integration\_refs (

  id INT UNSIGNED AUTO\_INCREMENT PRIMARY KEY,

  task\_id INT UNSIGNED NOT NULL,

  external\_system VARCHAR(40) NOT NULL,  \-- e.g. 'plutus'

  external\_id VARCHAR(60) NOT NULL,      \-- e.g. project\_id in Plutus

  relation VARCHAR(40) NOT NULL,         \-- e.g. 'budget', 'expense'

  FOREIGN KEY (task\_id) REFERENCES tasks(id)

) ENGINE=InnoDB;

**Indexing notes:**

- Composite index on `tasks (workspace_id, status)` for fast Kanban column pulls.  
- Composite index on `calendar_events (workspace_id, starts_at)` for range queries.  
- `task_dependencies` and `task_tags` are natural many-to-many join tables — no surrogate key needed.

---

## 6\. Backend Structure (PHP)

Front-controller pattern, no framework dependency:

/public

  index.php               — front controller, routes everything

  /api

    tasks.php

    projects.php

    goals.php

    milestones.php

    calendar.php

    tags.php

    assets.php

/src

  /Db

    Connection.php        — PDO wrapper, MariaDB DSN

  /Models

    Task.php

    Project.php

    Goal.php

    Milestone.php

    Tag.php

    CalendarEvent.php

  /Services

    AuthService.php       — verifies ForeverBox Core token

    IntegrationService.php — talks to Plutus API

  /Views

    list.php

    kanban.php

    calendar.php

    project.php

**Auth:** every request carries the ForeverBox session token (cookie or header). `AuthService` validates it against Core — the app never manages its own login state.

**Routing:** `.htaccess` rewrites `/api/*` to the matching PHP file; each file inspects `$_SERVER['REQUEST_METHOD']` plus a JSON body or `?action=` param to resolve the CRUD verb.

**Data access:** plain PDO, prepared statements. No ORM — at this scale it keeps SQL visible and debuggable, which matters once dependency chains and recurrence rules are in play.

---

## 7\. API Contract

All endpoints return JSON. The HTML5 shell never full-reloads once loaded.

GET    /api/tasks.php?workspace\_id=1\&view=kanban

POST   /api/tasks.php              { "title": "...", "project\_id": 4 }

PATCH  /api/tasks.php?id=12         { "status": "doing" }

DELETE /api/tasks.php?id=12

GET    /api/projects.php?workspace\_id=1

POST   /api/projects.php           { "name": "...", "goal\_id": 2 }

GET    /api/goals.php?workspace\_id=1

GET    /api/milestones.php?project\_id=4

POST   /api/milestones.php         { "project\_id": 4, "title": "..." }

GET    /api/calendar.php?from=2026-08-01\&to=2026-08-31

GET    /api/tags.php?workspace\_id=1

POST   /api/tags.php               { "name": "Kitchen", "type": "context" }

GET    /api/assets.php?project\_id=4

POST   /api/assets.php             { "type": "note", "title": "...", "content": "..." }

Kanban card moves are optimistic client-side, backed by `PATCH /api/tasks.php?id=X { "status": "..." }` with rollback on failure.

---

## 8\. Frontend Architecture (HTML5 \+ Vanilla JS)

Four view templates sharing one shell (`header.php` / `footer.php` includes), one JS module per view:

| View | Rendering approach |
| :---- | :---- |
| `list.php` | Semantic table/list, sortable via drag handles |
| `kanban.php` | CSS grid columns per status, `draggable="true"` cards, drop → `PATCH` |
| `calendar.php` | Month/week grid populated from `/api/calendar.php` |
| `project.php` | Milestones → Tasks tree, plus read-only "spent on this project" figure from Plutus |

No SPA framework required — each view's JS module fetches JSON and re-renders only the affected DOM fragment. This is what makes "one activity, many lenses" real: a single `tasks` payload shape feeds three different renderers.

---

## 9\. Shared Design System (Archive \+ Plutus Parity)

**Directive:** the Project Manager does not invent its own visual language. It inherits whatever templating/component system already drives Plutus and the `foreverbox.co.uk` archive site, extended rather than duplicated.

Because the build AI for this project has direct access to both live properties, the practical path is extraction, not description:

1. **Treat Plutus as the primary source.** It's the working database-driven app, not a documentation site — pull its actual header/nav/footer includes, colour variables, typography scale, spacing, and component classes (cards, tables, buttons, form fields, status badges) straight from its templates and stylesheets.  
2. **Cross-check against the archive site for the wider ForeverBox voice.** What's visible from the public index alone already signals a consistent aesthetic worth preserving: monospace/terminal type, section markers (`SEC: OMEGA`), system-status footer lines (`SYS.MEM: 4096TB // UPTIME: 99.999%`, `CONNECTION SECURE`), grid-based navigation. If Plutus shares these patterns, they're canonical for Project Manager too.  
3. **Where the two diverge, Plutus wins.** It's the functioning precedent for a live, data-backed app; the archive is closer to a spec document and may take stylistic liberties Plutus doesn't.  
4. **Extract shared patterns into one place, not three.** If Plutus already externalises its theme (a `theme.css`, shared PHP includes for header/nav/footer, a components stylesheet for cards/tables/badges), Project Manager should include from that same shared location at the ForeverBox Core level rather than copying files into its own tree. This is the "No Data Duplication" principle from Section 3, extended to UI: styling lives in one authoritative place and is referenced, not re-implemented.  
5. **If Plutus hasn't externalised its theme yet,** that extraction becomes a small zero-phase task ahead of Phase 1 in the roadmap below — pull the shared pieces out of Plutus first, then point both Plutus and Project Manager at them, so neither app regresses.  
6. **New components only where there's a genuine gap.** Project Manager introduces Kanban cards and calendar grids that may not exist in Plutus yet — build those as natural extensions of the existing component vocabulary (same button styles, same card chrome, same spacing scale) rather than a new design language bolted on beside it.

---

## 10\. Integration Layer

### Shared Identity

Both apps trust ForeverBox Core for authentication. `user_id: 994` in Plutus is the same user in the Project Manager. When the Project Manager needs financial data, it passes the user's token to the Plutus API — neither app manages the other's sessions or permissions.

### Deep Linking

Deep links are the connective UI tissue, avoiding embedded cross-app UI.

// Rendering a budget tag inside a task view:

$link \= "/plutus/budgets/project-{$project\_id}?return\_context=pm-task-{$task\_id}";

- **Handoff:** clicking a "Budget: Kitchen" tag fires this link.  
- **Return:** Plutus reads `return_context` and shows a native "Return to Task" button on completion.

Result: the illusion of one application for the user, with fully separated codebases underneath.

### Directional API Relationships

| Direction | Behaviour |
| :---- | :---- |
| Plutus → Project Manager | Plutus stores external references (e.g. an expense knows `project_id: 482`) |
| Project Manager → Plutus | PM queries Plutus for read-only financial context (e.g. "spent on this project") via `IntegrationService` |

`IntegrationService::getSpendForProject($project_id, $token)` calls the Plutus API server-side and caches the result briefly (a few minutes) to avoid hammering Plutus on every project-view load.

### Cross-Application Workflows

- A task like "Buy replacement washing machine" carries a deep link directly into the relevant Plutus budget/purchase UI.  
- A shared ForeverBox calendar service pulls PM activities and Plutus billing dates into one unified view, assembled at the **presentation layer**, not the database layer.

---

## 11\. Build Roadmap

| Phase | Deliverable |
| :---- | :---- |
| 0 | Extract Plutus's existing theme/components into a shared location (skip if already externalised) |
| 1 | Schema \+ `tasks`/`projects` CRUD via API, List view only — proves the data model |
| 2 | Kanban \+ Calendar lenses on the same underlying data |
| 3 | Goals/Milestones/Dependencies \+ Tags/Contexts |
| 4 | Integration layer — deep links \+ Plutus read calls |
| 5 | Polish — recurrence rules, drag-drop refinement, external-query API foundation |

---

## 12\. Open Questions

- Recurrence rule format: full RRULE (RFC 5545\) or a simplified in-house grammar?  
- Multi-workspace-per-user support in v1, or single workspace with Areas standing in for separation?  
- Asset file storage: local filesystem path under `/uploads`, or deferred to a ForeverBox shared storage service?  
- Has Plutus already externalised its theme/components into a shared include location, or does Phase 0 need to do that extraction first?

