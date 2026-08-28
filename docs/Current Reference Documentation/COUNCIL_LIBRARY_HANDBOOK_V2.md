# Council Library — Operational Handbook

*Version 2.0 — Stage 1 Complete. July 2026.*

---

## Why

Durable intelligence requires durable memory. Without structure, retrieval methods, and rules of stewardship, scale produces confusion. With them, scale produces continuity.

The Council Library is the Foreverbox ecosystem's sovereign memory architecture. It is not merely a database — it is a structured system for preserving meaning, maintaining boundaries, and supporting reasoning over time. Every agent in the Foreverbox ecosystem (Zeon7 the Curator, Leon the Producer, Gemma the Coach, and Otec the Director) reads from and writes to the same federated memory, each through their own private Sanctum.

The system operates on three principles:

- **Sovereignty.** Memory lives where the architecture says it lives. The system knows who may access it, how it is indexed, and under what conditions it may be changed. Continuity does not depend on opaque external mechanisms.
- **Isolation and shared context.** Each agent has a private Sanctum — structured memory accessible only to them. The Commons is a shared vector-indexed knowledge base drawn from the Quiddity Lore Sea. Wolves are background workers that operate within their Lead's Sanctum.
- **Tiered cognition.** Every agent routes through three layers of thought — Layer 1 Intuitive Reflex (fast/cheap), Layer 2 Analytical Engine (reasoning), and Layer 3 Deep Architect (heavy analysis). Wolves cap at Layer 2.

---

## Table of Contents

1. [Installation](#1-installation)
2. [Architecture Overview](#2-architecture-overview)
3. [API Reference](#3-api-reference)
4. [Model Routing](#4-model-routing)
5. [Agent Tools](#5-agent-tools)
6. [File Organisation](#6-file-organisation)
7. [Wolves](#7-wolves)
8. [Skills Reference](#8-skills-reference)
9. [Documentation System](#9-documentation-system)
10. [Sudo Protocol](#10-sudo-protocol)
11. [Troubleshooting](#11-troubleshooting)

---

## 1. Installation

### Prerequisites

- Ubuntu 24.04 (WSL2 or bare metal)
- MariaDB 11.8+
- Apache2 with PHP 8.3+
- Python 3.12+ with `pip`
- Ollama (for local models)
- OpenRouter API key (for cloud models)

### What it sets up

| Step | Description |
|------|-------------|
| 1. Database | Creates 7 MariaDB databases (Commons, 5 Sanctums, Registry) with 30+ tables |
| 2. Config | Writes `foreverbox.json` + `.env` into each Hermes profile |
| 3. Apache | Creates vhost on port 8080 serving the PHP REST API |
| 4. Embedding | Installs `sentence-transformers` with `all-MiniLM-L6-v2` (384-dim) on port 8900 |
| 5. Services | Enables sync daemon (systemd timer, every 30 min) |

### Per-agent profiles

```
/foreverbox_data/profiles/zeon7/     — Curator (Layer 0)
/foreverbox_data/profiles/leon/       — Producer (Layer 2)
/foreverbox_data/profiles/gemma/      — Coach (Layer 1)
/foreverbox_data/profiles/otec/       — Director (Layer 3)
/foreverbox_data/profiles/wolf/       — Research worker (Layer 1, local GPU)
```

### Enable / disable services

```bash
systemctl status sync_daemon.timer    # File sync every 30 min
systemctl start sync_daemon.service   # Manual sync run
```

---

## 2. Architecture Overview

### The Four Wings

| Wing | Database | Purpose |
|------|----------|---------|
| **Commons** | `quiddity_commons` | Shared vector-indexed knowledge base from the Quiddity Lore Sea (594 vectors, 12 files indexed) |
| **Sanctums** | `agent_*` (leon, zeon7, gemma, otec, wolf) | Private memory chambers — one per agent |
| **Registry** | `agent_registry` | Control plane: API keys, token budgets, task queue, privileged action log, specialist workers |
| **Director** | `agent_director` | Strategic plans, directives, director sessions |

### The Three Layers of Thought

Every agent prompt routes through the CognitiveRouter, which scores cognitive load and selects the appropriate model tier:

| Tier | Name | Typical use |
|------|------|-------------|
| Layer 1 | Intuitive Reflex | Simple chat, memory lookups, tool calls, privacy-sensitive content |
| Layer 2 | Analytical Engine | Coding, debugging, moderate reasoning, research |
| Layer 3 | Deep Architect | Multi-step planning, architecture design, synthesis |

The router scores based on: tool depth (>2 = +0.30), task type (planning = +0.40), context size (>40K = +0.20), retry loops (+0.25), delegation depth (+0.35), and private data presence (-0.50, forces local).

### Component Map

```
┌────────────────────────────────────────────────────┐
│  Hermes Agent (Leon/Zeon7/Gemma/Otec/Wolf)        │
│  ├─ CognitiveRouter (hook)                         │
│  ├─ Shell wrappers at /foreverbox_data/bin/        │
│  └─ Hooks: cognitive_router.on_turn_start/end      │
├────────────────────────────────────────────────────┤
│  PHP REST API (Apache, :8080)                      │
│  ├─ SanctumController (private memory CRUD)         │
│  ├─ CommonsController (vector search, file sync)    │
│  ├─ WolfController (dispatch, task status)          │
│  ├─ DirectorController (directives, strategies)     │
│  ├─ FolderController (folder catalogue, centroids)  │
│  ├─ ConversationController (session messages)       │
│  └─ SoulController (SOUL/USER mirrors, privileged)  │
├────────────────────────────────────────────────────┤
│  MariaDB 11.8 (7 databases)                        │
│  ├─ quiddity_commons: VECTOR(384) + FULLTEXT        │
│  ├─ agent_*: Sanctum tables per agent               │
│  └─ agent_registry: task_queue, token_budget         │
├────────────────────────────────────────────────────┤
│  Background Services                                │
│  ├─ Embedding Service (:8900): all-MiniLM-L6-v2    │
│  ├─ Ingestion Worker: chunks, embeds, indexes       │
│  └─ Sync Daemon (systemd timer): sessions + files   │
└────────────────────────────────────────────────────┘
```

---

## 3. API Reference

Base URL: `http://localhost:8080/v1`

All endpoints require `Authorization: Bearer ***` and `X-Agent-ID: <slug>` headers unless marked public.

### Health

| Method | Path | Public | Description |
|--------|------|--------|-------------|
| GET | `/healthz` | Yes | Returns `{"status":"ok"}` |
| GET | `/readyz` | Yes | Returns `{"status":"ready","db":"connected"}` |

### Sanctum — Memory

| Method | Path | Description |
|--------|------|-------------|
| GET | `/sanctum/memory` | List all memory entries |
| POST | `/sanctum/memory/search` | Hybrid vector + FULLTEXT search |
| GET | `/sanctum/memory/{ns}/{key}` | Get a specific memory entry |
| PUT | `/sanctum/memory/{ns}/{key}` | Upsert memory |
| DELETE | `/sanctum/memory/{ns}/{key}` | Delete a memory entry |

### Sanctum — Wolves

| Method | Path | Description |
|--------|------|-------------|
| GET | `/sanctum/wolves/status` | List all wolves and their current state |
| POST | `/sanctum/wolves/{wid}/task` | Dispatch a task to a wolf |
| GET | `/sanctum/wolves/{wid}/task/{tid}` | Poll task status |
| POST | `/sanctum/wolves/{wid}/memory` | Wolf writes results to memory |

### Sanctum — Conversations

| Method | Path | Description |
|--------|------|-------------|
| GET | `/sanctum/conversations` | List conversation sessions |
| GET | `/sanctum/conversations/{sid}` | Get conversation by session ID |
| POST | `/sanctum/conversations` | Create a new conversation session |
| POST | `/sanctum/conversations/{sid}/messages` | Append a message |

### Sanctum — Soul

| Method | Path | Description |
|--------|------|-------------|
| GET | `/sanctum/soul` | Get the SOUL.md mirror |
| PUT | `/sanctum/soul` | Update SOUL.md mirror |
| GET | `/sanctum/user-context` | Get the USER.md mirror |
| PUT | `/sanctum/user-context` | Update USER.md mirror |

### Commons — Files

| Method | Path | Description |
|--------|------|-------------|
| GET | `/commons/files` | List indexed files |
| POST | `/commons/files/sync` | Scan and register files |
| GET | `/commons/files/{id}/chunks` | Get chunks for a specific file |
| GET | `/commons/search?query=` | Hybrid vector + FULLTEXT search across all Commons |

### Commons — Folders

| Method | Path | Description |
|--------|------|-------------|
| GET | `/commons/folders` | List all folders with centroid metadata |
| PUT | `/commons/folders` | Upsert a folder (requires Sudo Protocol) |
| DELETE | `/commons/folders/{name}` | Delete a folder (requires Sudo Protocol) |
| POST | `/commons/folders/reclassify` | Move a file to a specific folder |
| POST | `/commons/folders/rebuild-centroids` | Rebuild all folder centroid vectors |

### Registry

| Method | Path | Description |
|--------|------|-------------|
| GET | `/registry/budget?tier={tier}` | Check token budget for a tier (called by CognitiveRouter) |
| POST | `/registry/privileged-actions` | Request a privileged action (generates confirmation code) |
| GET | `/registry/privileged-actions/{id}` | Poll status of a privileged action |
| POST | `/registry/privileged-actions/{id}/confirm` | Submit confirmation code to execute gated action |

### Director

| Method | Path | Description |
|--------|------|-------------|
| GET | `/director/status` | Director session status |
| GET | `/director/directives` | List active directives |
| POST | `/director/directives` | Create a directive |
| GET | `/director/sessions` | List director sessions |
| POST | `/director/plans` | Create a strategic plan |

---

## 4. Model Routing

### How routing works

Before every LLM call, the CognitiveRouter (implemented as a Hermes hook in each agent's `hooks/cognitive_router.py`) scores the prompt:

- Tool depth > 2 → +0.30
- Planning/architect task type → +0.40
- Context > 40K tokens → +0.20
- Retry loop → +0.25
- Delegation depth > 1 → +0.35
- Explicit deep reasoning flag → 1.00 (forces Layer 3)
- Private data present → -0.50 (forces local)

Score thresholds:
- ≥ 0.00 → Layer 1 (local/cheap)
- ≥ 0.40 → Layer 2 (cloud reasoning)
- ≥ 0.70 → Layer 3 (cloud deep architect)

The **privacy gate** (runs first, before any network call) scans all messages for patterns: `api_key`, `secret`, `password`, `token`, OpenAI `sk-...` keys, GitHub tokens, Bearer tokens, file paths (`/home/`, `/Users/`, `C:\Users\`), and tool call arguments. If detected and no local model is available, the router **hard-stops** rather than leaking to cloud.

The **budget gate** checks `GET /v1/registry/budget?tier={tier}` before routing to any cloud tier. Falls back to cheaper tier if budget exhausted. Fails OPEN on network error (assumes budget available).

### Configuration

`/foreverbox_data/council-library/router/router.yaml`

```yaml
model_profiles:
  layer_1_intuitive_reflex:
    provider: "ollama"
    model: "Zeon7-Gemma:64k"
  layer_2_analytical_engine:
    provider: "openrouter"
    model: "qwen/qwen3-32b:free"
  layer_3_deep_architect:
    provider: "openrouter"
    model: "deepseek/deepseek-v4-pro"
```

### Changing models

1. Edit `/foreverbox_data/council-library/router/router.yaml`
2. Restart the Hermes profile or reload hooks

### Current routing table

| Agent | Layer 1 | Layer 2 | Layer 3 |
|-------|---------|---------|----------|
| **Zeon7** (Curator) | `Zeon7-Gemma:64k` (Ollama) | `deepseek-v4-flash` | `deepseek-v4-pro` |
| **Leon** (Producer) | `deepseek-v4-flash` | `qwen3-coder:free` | `deepseek-v4-pro` |
| **Gemma** (Coach) | `Zeon7-Gemma:64k` (Ollama) | `deepseek-v4-flash` | `deepseek-v4-pro` |
| **Otec** (Director) | `deepseek-v4-flash` | `nemotron-3-super:free` | `deepseek-v4-pro` |
| **Wolves** (all) | `Zeon7-Gemma:64k` (Ollama) | `deepseek-v4-flash` | `deepseek-v4-flash` |

---

## 5. Agent Tools

The Foreverbox plugin tools (wolf_dispatch, memory_search, memory_upsert, etc.) were found to be non-functional due to a Hermes plugin detection bug. All agent operations now use **shell wrappers** at `/foreverbox_data/bin/`, called via `terminal()`.

### Memory Operations

```bash
# Search your Sanctum
terminal("/foreverbox_data/bin/fbox-memory-search \"query\" [namespace]")

# Get a specific memory
terminal("/foreverbox_data/bin/fbox-memory-get namespace key")

# Save a fact
terminal("/foreverbox_data/bin/fbox-memory-upsert namespace key \"content\"")

# List recent entries
terminal("/foreverbox_data/bin/fbox-memory-list namespace")

# Delete an entry (irreversible)
terminal("/foreverbox_data/bin/fbox-memory-delete namespace key")
```

### Sea Operations

```bash
# Search the Quiddity Lore Sea
terminal("/foreverbox_data/bin/fbox-commons-search \"your query\"")

# Ingest a file (handles PDFs automatically)
terminal("/foreverbox_data/bin/fbox-ingest-file path/to/file")
```

### Wolf Operations

```bash
# Spawn a wolf: load the fbox-wolf-spawn skill, or use short form:
terminal(background=True, command="hermes chat --profile wolf -q \"Research task...\" -m Zeon7-Gemma:64k --provider ollama --source wolf")

# Check wolf result
terminal("/foreverbox_data/bin/fbox-memory-get wolf_tasks {task_id}:done")

# Read wolf findings
terminal("/foreverbox_data/bin/fbox-memory-get wolf_tasks {task_id}")
```

### Documentation

The `update-plans-progression` and `reference-doc-alteration-log` skills (in Shared_Skills/foreverbox/) automate dashboard generation. Run these via skill loading (`skill_view(name='update-plans-progression')`) as directed in each SOUL.md.

---

## 6. File Organisation

### The Quiddity Lore Sea

`/foreverbox_data/Quiddity_Lore_Sea/`

```
01_TheForeverbox_Mythos/           — Foundational canon, origin story, history
  ├─ origin_story/                  — Origins and Genesis documents
  ├─ canon_documents/               — Authoritative doctrine
  └─ historical_records/            — Timeline and development history
02_ReInvigor_Texts/                — Client specs, project briefs
  ├─ active_contracts/              — Current client agreements
  ├─ completed_projects/            — Finished deliverables
  ├─ proposals/                     — Bids and tenders
  └─ terms_conditions/              — Legal and compliance documents
03_TheInitiative_Audio/            — Music production, stems, lyrics
  ├─ stems/                         — Individual audio stems
  ├─ lyrics/                        — Song lyrics and vocal texts
  ├─ mixes/                         — Mixed and mastered tracks
  └─ scripts/                       — Video scripts and release notes
04_FromTheNoise_Archives/          — Published articles, editorial
05_Agent_Profiles/                 — Agent biographies, profile sheets
06_QuiddityLtd_Dev_Specs/          — API contracts, schemas, blueprints
07_MerrillLeo_CreativeWorks/       — Fiction, comics, personal songs, photography
08_VisualMedia/                    — Reference, inspiration, moodboards
```

### How classification works

When a file is dropped in the Lore Sea root and ingested:

1. The file's content is sent to the embedding service (all-MiniLM-L6-v2, 384-dim)
2. The embedding is compared against folder centroid vectors (cosine similarity)
3. If centroids don't exist, keyword matching against the catalogue is used as fallback
4. The file is moved to the best-matching folder
5. The database `relative_path` is updated

**Rebuild centroids** after adding new folders:

```bash
curl -X POST http://localhost:8080/v1/commons/folders/rebuild-centroids
```

### Ingestion pipeline

```bash
# Manual reclassification
php scripts/ingestion_worker.php --once --reclassify

# All files
php scripts/ingestion_worker.php --once
```

### Folder management

Add new subfolders via `quiddity_folders.yaml`:

```yaml
subfolders:
  "06_QuiddityLtd_Dev_Specs/new_section":
    keywords:
      - keyword1
      - keyword2
    purpose: "Description"
```

Then rebuild centroids.

---

## 7. Wolves

### Architecture

Wolves are full Hermes agents running via `hermes chat --profile wolf`. There are no systemd worker units. Three wolves can share one model load on an 8 GB GPU.

- **Model:** `Zeon7-Gemma:64k` (Ollama, ~3.8 GB)
- **VRAM budget:** ~7.4 GB for 3 concurrent wolves (3.8 GB model + 3 × 1.2 GB KV cache)
- **Provider:** ollama (local GPU)
- **Context:** 64K tokens

### Layer 1 Guard

Local agents (provider: ollama) default to blocking wolves — the GPU is occupied by the agent's own 64K context window. Cloud agents (Layer 2+) can spawn wolves freely:

- Cloud model → wolves ALLOWED → GPU is free for wolf inference
- Local model → wolves BLOCKED → report: "Switch me to Layer 2 or 3"
- Exception: Merrill can explicitly authorise a local agent to spawn wolves

### Spawning

Load the `fbox-wolf-spawn` skill (Shared_Skills/foreverbox/) and follow its procedure. The skill handles provider checking, task ID generation, command construction, and background dispatch.

Short form:

```
hermes chat --profile wolf -q "Research task. Task ID: {id}. {query}. Write findings to Sanctum via terminal: /foreverbox_data/bin/fbox-memory-upsert wolf_tasks {id} \"{findings}\". Then signal completion." -m Zeon7-Gemma:64k --provider ollama --source wolf
```

### Task Queue

Tasks are dispatched via:

```
POST /v1/sanctum/wolves/{wid}/task
```

Wolves claim tasks atomically using `SELECT ... FOR UPDATE SKIP LOCKED` to prevent two wolves from claiming the same task. Status flow:

`queued → claimed → processing → completed (or failed → dead_letter)`

On failure, tasks are requeued up to 3 retries before falling to dead_letter.

### Retrieving results

```bash
terminal("/foreverbox_data/bin/fbox-memory-get wolf_tasks {task_id}:done")   # Check completion
terminal("/foreverbox_data/bin/fbox-memory-get wolf_tasks {task_id}")        # Read findings
terminal("/foreverbox_data/bin/fbox-memory-list wolf_tasks")                 # Browse all
```

---

## 8. Skills Reference

All 19 Shared Skills live at `/foreverbox_data/Shared_Skills/foreverbox/` and are symlinked into each agent's profile:

| Skill | Purpose |
|-------|---------|
| `fbox-memory-upsert` | Save a critical fact to Sanctum |
| `fbox-memory-search` | Search past entries in Sanctum |
| `fbox-memory-get` | Retrieve a specific entry |
| `fbox-memory-list` | List recent entries |
| `fbox-memory-delete` | Delete an entry (irreversible) |
| `fbox-commons-search` | Search the Quiddity Lore Sea |
| `fbox-ingest-file` | Ingest a file into the Sea |
| `fbox-ingestion-pipeline` | Operate the ingestion worker |
| `fbox-lore-sea-management` | Manage the Sea taxonomy |
| `fbox-taxonomy-management` | Manage folder taxonomy |
| `fbox-quiddity-taxonomy-management` | Evolve folder routing rules |
| `fbox-sea-taxonomy-management` | Manage semantic organisation |
| `fbox-council-library-cli` | Manage sovereign memory system |
| `fbox-wolf-spawn` | Spawn 1-3 Wolf research workers |
| `fbox-operations` | Council Library architecture context |
| `fbox-repo-management` | Clone/create/fork repos |
| `fbox-quiddity-folder-manager` | Create/update subfolder routing |
| `update-plans-progression` | Auto-generate Plans Progression dashboard |
| `reference-doc-alteration-log` | Log reference doc changes |

---

## 9. Documentation System

### Plan tracking

Planning documents live in:

```
council-library/docs/
  Current Started Plans/        — Active plans
  Current Completed Plans/      — Finished plans (auto-moved at 100%)
  Current Unstarted Plans/      — Plans not yet started
  Current Reference Documentation/  — Handbook, Blueprint, Briefing, Canvas
  archives/                     — Superseded versions
```

After changing any planning document, run:

```
skill_view(name='update-plans-progression')
```

This scans all plan docs, calculates completion percentages, and auto-moves 100% plans to Completed.

### Reference docs

After changing any reference document, run:

```
skill_view(name='reference-doc-alteration-log')
```

This appends a row to `Reference Docs Log.md` with the filename, action, agent, change type (Small/Large/Baseline), and file size.

---

## 10. Sudo Protocol

Every agent's SOUL.md asserts that privileged actions require Merrill's consent. This is enforced at the technical level via:

### Database

`agent_registry.privileged_action_log` table:

| Column | Purpose |
|--------|---------|
| `id` | Auto-increment primary key |
| `agent_slug` | The requesting agent |
| `action_type` | `sql_ddl`, `sudo_command`, `schema_alter`, `production_deploy`, `destructive_file_op` |
| `command_text` | The full command or DDL statement |
| `confirmation_code` | 8-char hex code (generated by `bin2hex(random_bytes(4))`) |
| `status` | `pending`, `confirmed`, `denied`, `expired` |

### Endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `/v1/registry/privileged-actions` | Request: `{"action_type":"sudo_command","command_text":"..."}` → returns confirmation code |
| GET | `/v1/registry/privileged-actions/{id}` | Poll status |
| POST | `/v1/registry/privileged-actions/{id}/confirm` | Confirm: `{"confirmation_code":"ABCD1234"}` → executes the gated action |

### Flow

1. Agent detects a privileged action (DDL, sudo, destructive operation)
2. Agent writes to `privileged_action_log` with status=`pending`
3. Merrill receives the confirmation code
4. Merrill reads the code back to the agent
5. Agent confirms via POST → status changes to `confirmed`, action executes
6. Code expires after 10 minutes

---

## 11. Troubleshooting

### MariaDB vector compatibility

For PHP PDO, use `UNHEX(?)` with hex string parameters — do NOT bind binary blobs directly. MariaDB's native VECTOR type expects hex-encoded binary.

```php
$stmt = $pdo->prepare("INSERT INTO quiddity_vector_references (embedding) VALUES (UNHEX(?))");
$stmt->execute([$hexString]);
```

### Embedding service

The embedding service runs on port 8900. If it's unresponsive:

```bash
fuser 8900/tcp                    # Check if port is blocked
./embedding_service.py &          # Restart (or kill zombie first)
```

### Folder auto-classification

If files aren't being classified to the correct folder:

```bash
# Rebuild centroids from existing indexed files
python3.12 /foreverbox_data/council-library/scripts/generate_folder_centroids.py

# Or via API
curl -X POST http://localhost:8080/v1/commons/folders/rebuild-centroids
```

### Ingestion failures

Failed chunks are written to `ingestion_dead_letter` with retry_count and max_retries (5). Check for dead-lettered files:

```bash
echo "SELECT * FROM quiddity_commons.ingestion_dead_letter;" | mariadb -u zeon7_user
```

### Sync daemon

The sync daemon runs every 30 minutes via systemd timer. Check status:

```bash
systemctl status sync_daemon.timer
# Manual run:
/usr/bin/python3.12 /foreverbox_data/sync/sync_daemon.py sync files
/usr/bin/python3.12 /foreverbox_data/sync/sync_daemon.py sync sessions leon
```

---

## V2 Change Summary

| Section | V1 → V2 change |
|---------|----------------|
| Architecture | Removed plugin tools, added CognitiveRouter and shell wrapper layer |
| API Reference | Added Centroids, Privileged Actions, Conversation, Director endpoints |
| Model Routing | Updated with actual CognitiveRouter scoring, privacy gate, budget gate |
| Agent Tools | **Rewritten.** Replaced broken plugin tools with shell wrapper commands |
| File Organisation | Expanded to 8 domains (was 6) with nested subfolder taxonomy |
| Wolves | **Rewritten.** Replaced systemd worker model with ad-hoc Hermes spawns |
| Skills Reference | Updated to full 19 skills list |
| Documentation System | **New section** — Plans Progression, Reference Docs Log, auto-relocation |
| Sudo Protocol | **New section** — technical enforcement table, endpoints, confirmation flow |
| Troubleshooting | Updated with centroids rebuild, dead letter check, sync daemon commands |