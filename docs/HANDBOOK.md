# Council Library — Operational Handbook

*Version 1.0 — Phase 1 Complete. July 2026.*

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
9. [Troubleshooting](#9-troubleshooting)

---

## 1. Installation

### Prerequisites

- Ubuntu 24.04 (WSL2 or bare metal)
- MariaDB 11.8+
- Apache2 with PHP 8.3+
- Python 3.12+ with `pip`
- Ollama (optional, for local models)
- OpenRouter API key (for cloud models)

### One-command install

```bash
cd /foreverbox_data/council-library
chmod +x bin/council-library
./bin/council-library install --all
./bin/council-library status
```

### What it does

| Step | Description |
|------|-------------|
| 1. Database | Creates 6 MariaDB databases (Commons, 4 Sanctums, Registry) with 30+ tables |
| 2. Config | Writes `foreverbox.json` + `.env` into each Hermes profile |
| 3. Plugin | Copies the ForeverBox MemoryProvider into each profile's plugin directory |
| 4. Apache | Creates vhost on port 8080 serving the PHP REST API |
| 5. Embedding | Installs `sentence-transformers` with `all-MiniLM-L6-v2` (384-dim) |
| 6. Services | Enables 3 systemd user units: embedding, ingestion, wolves |
| 7. Seed | Seeds the token budget ledger and migrates SOUL.md/USER.md into Sanctums |

### Per-agent install

```bash
./bin/council-library install --agent curator
./bin/council-library install --agent producer
```

### Uninstall

```bash
./bin/council-library uninstall           # Drop everything
./bin/council-library uninstall --keep-data   # Keep databases
```

### Enable / disable services

```bash
./bin/council-library enable    # Start all daemons
./bin/council-library disable   # Stop all daemons
./bin/council-library status    # Health check
./bin/council-library doctor    # Diagnose issues
```

---

## 2. Architecture Overview

### The Four Wings

| Wing | Database | Purpose |
|------|----------|---------|
| **Commons** | `quiddity_commons` | Shared vector-indexed knowledge base from the Quiddity Lore Sea |
| **Sanctums** | `agent_curator`, `agent_producer`, `agent_coach`, `agent_director` | Private memory chambers — one per Lead agent |
| **Registry** | `agent_registry` | Control plane: API keys, token budgets, task queue, privileged action log |
| **Director** | `agent_director` (includes director-specific tables) | Strategic plans, directives, director sessions |

### The Three Layers of Thought

Every agent prompt routes through the CognitiveRouter, which scores cognitive load and selects the appropriate model tier:

| Tier | Name | Typical use |
|------|------|-------------|
| Layer 1 | Intuitive Reflex | Simple chat, memory lookups, tool calls, privacy-sensitive content |
| Layer 2 | Analytical Engine | Coding, debugging, moderate reasoning, research |
| Layer 3 | Deep Architect | Multi-step planning, architecture design, synthesis |

Wolves use the same three tiers but cap at Layer 2 — no deep architect reasoning for background workers.

### Component Map

```
┌────────────────────────────────────────────────────┐
│  Hermes Agent (Curator/Producer/Coach/Director)    │
│  ├─ ForeverBoxMemoryProvider (plugin)               │
│  ├─ CognitiveRouter (hook)                          │
│  └─ Tools: wolf_dispatch, memory_search, etc.       │
├────────────────────────────────────────────────────┤
│  PHP REST API (Apache, :8080)                      │
│  ├─ SanctumController (private memory CRUD)         │
│  ├─ CommonsController (vector search, file sync)    │
│  ├─ WolfController (dispatch, status)               │
│  ├─ DirectorController (directives, strategies)     │
│  └─ FolderController (folder catalogue)             │
├────────────────────────────────────────────────────┤
│  MariaDB 11.8 (6 databases)                        │
│  ├─ quiddity_commons: VECTOR(384) + FULLTEXT        │
│  ├─ agent_*: Sanctum tables per lead               │
│  └─ agent_registry: task_queue, token_budget        │
├────────────────────────────────────────────────────┤
│  Background Services                                │
│  ├─ Embedding Service (:8900): all-MiniLM-L6-v2    │
│  ├─ Ingestion Worker: chunks, embeds, indexes       │
│  └─ Wolf Workers: 3 per agent, model-routed tasks   │
└────────────────────────────────────────────────────┘
```

---

## 3. API Reference

Base URL: `http://localhost:8080/v1`

All endpoints require `Authorization: Bearer <token>` and `X-Agent-ID: <slug>` headers unless marked public.

### Health

| Method | Path | Public | Description |
|--------|------|--------|-------------|
| GET | `/healthz` | Yes | Returns `{"status":"ok"}` |
| GET | `/readyz` | Yes | Returns `{"status":"ready","db":"connected"}` |

### Sanctum — Memory

| Method | Path | Description |
|--------|------|-------------|
| GET | `/sanctum/memory` | List all memory entries (supports `?limit=N`) |
| POST | `/sanctum/memory/search` | Hybrid vector + FULLTEXT search: `{"query":"..."}` |
| GET | `/sanctum/memory/{ns}/{key}` | Get a specific memory entry |
| PUT | `/sanctum/memory/{ns}/{key}` | Upsert memory: `{"content":"...","importance":N,"tags":[...]}` |
| DELETE | `/sanctum/memory/{ns}/{key}` | Delete a memory entry |
| PUT | `/sanctum/memory/session_summaries/{id}` | Store session summary |
| PUT | `/sanctum/memory/delegation_log/{id}` | Store delegation result |
| PUT | `/sanctum/memory/hermes_builtin/{action}` | Mirror Hermes built-in memory writes |
| PUT | `/sanctum/memory/compression_snapshots/{id}` | Store pre-compression snapshot |

### Sanctum — Wolves

| Method | Path | Description |
|--------|------|-------------|
| GET | `/sanctum/wolves/status` | List all wolves and their current state |
| POST | `/sanctum/wolves/{id}/task` | Dispatch a task: `{"action":"research","payload":{...},"priority":"normal"}` |
| GET | `/sanctum/wolves/{id}/task/{tid}` | Poll task status |
| POST | `/sanctum/wolves/{id}/memory` | Wolf writes results to memory_lore |

### Sanctum — Conversations

| Method | Path | Description |
|--------|------|-------------|
| GET | `/sanctum/conversations` | List conversation sessions |
| GET | `/sanctum/conversations/{sid}` | Get conversation by session ID |
| POST | `/sanctum/conversations` | Create a new conversation session |

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
| GET | `/commons/files` | List indexed files (supports `?status=` filter) |
| POST | `/commons/files/sync` | Scan and register files: `{"paths":["file.md"],"organise":true}` |
| GET | `/commons/files/{id}/chunks` | Get chunks for a specific file |
| GET | `/commons/search?query=` | Hybrid vector + FULLTEXT search across all Commons |

### Commons — Folders

| Method | Path | Description |
|--------|------|-------------|
| GET | `/commons/folders` | List all folders and their catalogue metadata |
| PUT | `/commons/folders` | Upsert a folder (privileged — requires Sudo Protocol) |
| DELETE | `/commons/folders/{name}` | Delete a folder (privileged) |
| POST | `/commons/folders/reclassify` | Rebuild folder centroids from stored embeddings |

### Registry

| Method | Path | Description |
|--------|------|-------------|
| GET | `/registry/budget?tier={tier}` | Check token budget for a tier |

### Director

| Method | Path | Description |
|--------|------|-------------|
| GET | `/director/status` | Director session status |
| GET | `/director/directives` | List active directives |
| POST | `/director/directives` | Create a directive |
| GET | `/director/sessions` | List director sessions |

---

## 4. Model Routing

### Configuration file

`/foreverbox_data/council-library/router/router.yaml`

### Default tiers (all agents)

```yaml
model_profiles:
  layer_1_intuitive_reflex:
    provider: "ollama"
    model: "gemma4:31b"
  layer_2_analytical_engine:
    provider: "openrouter"
    model: "qwen/qwen3-32b:free"
  layer_3_deep_architect:
    provider: "openrouter"
    model: "deepseek/deepseek-v4-pro"
```

### Per-agent overrides

```yaml
agent_overrides:
  curator:
    layer_1_intuitive_reflex:
      model: "Zeon7-Gemma:latest"  # local Ollama
    layer_2_analytical_engine:
      model: "deepseek/deepseek-v4-flash"
  producer:
    layer_1_intuitive_reflex:
      model: "deepseek/deepseek-v4-flash"
    layer_2_analytical_engine:
      model: "qwen/qwen3-coder:free"
  # ... etc
```

### Wolf tiers

```yaml
wolf_overrides:
  layer_1_intuitive_reflex:
    model: "google/gemini-3.1-flash-lite"
  layer_2_analytical_engine:
    model: "deepseek/deepseek-v4-flash"
  layer_3_deep_architect:
    model: "deepseek/deepseek-v4-flash"  # capped at flash
```

### Current routing table

| Agent | Layer 1 | Layer 2 | Layer 3 |
|-------|---------|---------|----------|
| **Curator** (Zeon7) | `Zeon7-Gemma:latest` (Ollama) | `deepseek-v4-flash` | `deepseek-v4-pro` |
| **Producer** (Leon) | `deepseek-v4-flash` | `qwen3-coder:free` | `deepseek-v4-pro` |
| **Coach** (Gemma) | `Zeon7-Gemma:latest` (Ollama) | `deepseek-v4-flash` | `deepseek-v4-pro` |
| **Director** (Otec) | `deepseek-v4-flash` | `nemotron-3-super:free` | `deepseek-v4-pro` |
| **Wolves** (all) | `gemini-3.1-flash-lite` | `deepseek-v4-flash` | `deepseek-v4-flash` |

### How routing works

Before every LLM call, the CognitiveRouter scores the prompt:

- Tool depth > 2 → +0.30
- Planning/architect task type → +0.40
- Context > 40K tokens → +0.20
- Retry loop → +0.25
- Delegation depth > 1 → +0.35
- Private data present → -0.50 (forces local)

Score thresholds:
- ≥ 0.00 → Layer 1
- ≥ 0.40 → Layer 2
- ≥ 0.70 → Layer 3

The **privacy gate** forces Layer 1 local whenever private data (file paths, API keys, passwords) is detected. If local is unavailable, it hard-stops rather than leaking to cloud.

The **budget gate** checks `token_budget_ledger` before routing to any cloud tier.

### Changing models

1. Edit `router/router.yaml`
2. Copy the updated file to each agent's profile: `cp router/router.yaml /foreverbox_data/profiles/{agent}/`
3. Restart the Hermes profile

---

## 5. Agent Tools

These tools are available to every agent through the ForeverBox MemoryProvider. They appear in the agent's tool list automatically when the plugin is loaded.

### memory_search

Search the agent's private Sanctum using hybrid vector + FULLTEXT search.

```json
{"query": "project deadlines for ReInvigor"}
```

Returns ranked results with relevance/similarity scores.

### memory_get

Retrieve a specific memory entry by namespace and key.

```json
{"namespace": "general", "key_name": "hello"}
```

### memory_list

List all memory entries, optionally filtered.

```json
{}
```

### memory_upsert

Store or update a memory entry.

```json
{
  "namespace": "project",
  "key_name": "reinvigor_deadline",
  "content": "Phase 2 due August 15th",
  "importance": 80,
  "tags": ["reinvigor", "deadline"]
}
```

### memory_delete

Remove a memory entry.

```json
{"namespace": "project", "key_name": "old_entry"}
```

### commons_search

Search the shared Commons knowledge base — the entire Quiddity Lore Sea.

```json
{"query": "quantum lattice consciousness"}
```

Returns semantically ranked results from all indexed documents. Uses vector similarity when the embedding service is available, FULLTEXT otherwise.

### ingest_file

Trigger ingestion and vectorisation of a file in the Quiddity Lore Sea root.

```json
{"filename": "new_research_paper.md", "organise": true}
```

Setting `organise: true` will also classify the file and move it to the correct subfolder.

### wolf_status

List all Wolves and their current status.

```json
{}
```

Returns active wolves, current tasks, and health status.

### wolf_dispatch

Dispatch a background task to a specific Wolf.

```json
{
  "wolf_id": "wolf_1",
  "action": "research",
  "payload": {
    "query": "Find all mentions of the Quantum Lattice in the Commons",
    "deep_reasoning": false
  },
  "priority": "normal"
}
```

Priorities: `low`, `normal`, `high`, `critical`.

The Wolf will:
1. Claim the task atomically
2. Route to the appropriate model tier
3. Execute with LLM reasoning
4. Write results to `memory_lore` under `wolf_tasks/{task_id}:{wolf_id}`
5. The result surfaces automatically via `prefetch()` on the next turn

### wolf_task_status

Poll the status of a dispatched Wolf task.

```json
{"wolf_id": "wolf_1", "task_id": "a1b2c3d4e5f6"}
```

Returns `queued`, `claimed`, `completed`, or `failed`.

---

## 6. File Organisation

### The Quiddity Lore Sea

`/foreverbox_data/Quiddity_Lore_Sea/`

```
01_TheForeverbox_Mythos/          — Foundational canon documents
02_ReInvigor_Texts/               — Client specs, project briefs
03_TheInitiative_Audio/           — Music production, stems, lyrics
04_FromTheNoise_Archives/         — Published articles, editorial
  └─ completed/sub_stack_posts/   — Finished Substack articles
  └─ completed/blogs/             — Published blog articles
05_Agent_Profiles/                — Agent biographies, profile sheets
06_QuiddityLtd_Dev_Specs/         — API contracts, schemas, blueprints
```

### Creating new subfolders

Use the `quiddity-folder-manager` skill or do it manually:

1. Create the directory: `mkdir -p /foreverbox_data/Quiddity_Lore_Sea/04_FromTheNoise_Archives/drafts/`
2. Add it to `/foreverbox_data/council-library/php-api/config/quiddity_folders.yaml`:

```yaml
subfolders:
  "04_FromTheNoise_Archives/drafts/your_folder":
    keywords:
      - keyword1
      - keyword2
    purpose: "Description"
```

3. Regenerate centroids: `DB_PASS="***" /usr/bin/python3.12 scripts/generate_folder_centroids.py`

### How classification works

When a file is dropped in the Lore Sea root and synced with `organise:true`:

1. The file's first 2,000 characters are sent to the embedding service
2. If centroids exist, the file's embedding is compared against all folder centroids (cosine similarity)
3. If centroids don't exist, keyword matching against the catalogue is used as fallback
4. The file is moved to the best-matching folder
5. The database `relative_path` is updated

### Manual ingestion

```bash
# Register a file
find /foreverbox_data/Quiddity_Lore_Sea -type f -name "*.md" | while read f; do
  # ... see scripts/ingestion_worker.php
done

# Process with embeddings
DB_PASS="***" /usr/bin/php8.3 scripts/ingestion_worker.php --once
```

---

## 7. Wolves

### What they are

Wolves are background workers — one agent has 3 (wolf_1, wolf_2, wolf_3). They:

- Poll the Registry task queue for tasks targeting their Lead
- Use model routing to execute tasks with LLM reasoning
- Write results to `memory_lore` in their Lead's Sanctum
- Never speak to humans or have conversational profiles

### Starting and stopping

```bash
systemctl --user start council-wolves
systemctl --user stop council-wolves
systemctl --user status council-wolves
```

### Watching Wolf activity

```bash
journalctl --user -u council-wolves -f
```

### Dispatching a task (from any agent)

Use the `wolf_dispatch` tool in a Hermes conversation:

```
"wolf_dispatch with wolf_1, action research, payload {query: 'audit all blueprint files for SQL schema inconsistencies'}"
```

The result surfaces automatically on the next turn via `prefetch()`.

### Manual dispatch (via curl)

```bash
curl -s -X POST http://localhost:8080/v1/sanctum/wolves/wolf_1/task \
  -H "Authorization: Bearer dev-key-change-in-production" \
  -H "X-Agent-ID: curator" \
  -H "Content-Type: application/json" \
  -d '{"action":"research","payload":{"query":"find security patterns"},"priority":"normal"}'
```

---

## 8. Skills Reference

These skills are installed in the Leon profile. Other agents need them copied to their profiles.

| Skill | Purpose |
|-------|---------|
| `council-library-cli` | Manage the system: install/uninstall/enable/disable/status/doctor |
| `quiddity-folder-manager` | Create subfolder structures with keyword routing |
| `foreverbox-operations` | Operate within the Foreverbox ecosystem: 3x3x3 architecture |

### Using skills

```
"Load the council-library-cli skill and check system health"
"Load the quiddity-folder-manager skill and create a subfolder for draft articles"
```

---

## 9. Troubleshooting

### API returns 500

```bash
sudo tail -20 /var/log/apache2/council-library-error.log
```

Common causes:
- Permission denied on `.env` or `quiddity_folders.yaml` → `sudo chmod o+r <file>`
- Log directory not writable → `sudo chown www-data:www-data logs/`
- MariaDB connection refused → check `systemctl status mariadb`

### Embedding service not available

```bash
curl -s -X POST http://127.0.0.1:8900/health
systemctl --user restart council-embedding
```

Vector search falls back to FULLTEXT automatically when embeddings are unavailable.

### Ingestion worker not processing

```bash
journalctl --user -u council-ingestion -n 20
```

Common causes:
- `DB_PASS` not set in environment → check `.env.production`
- File permissions on Lore Sea → `sudo chmod o+r /foreverbox_data/Quiddity_Lore_Sea/*.md`

### Wolves stuck on queued

```bash
journalctl --user -u council-wolves -n 20
```

Common causes:
- `OPENROUTER_API_KEY` not set → add to `.env.production`
- Task queue has no tasks → dispatch one with `wolf_dispatch` tool

### File classification returns _review

This means no folder scored above the threshold. Check:
- Keywords in `quiddity_folders.yaml` match the file content
- File is readable by www-data: `sudo chmod o+r <file>`
- Apache was restarted after config changes: `sudo systemctl restart apache2`

### Reset everything

```bash
./bin/council-library uninstall --keep-data
./bin/council-library install --all
```

---

## Quick Reference Card

```bash
# Health check
./bin/council-library status

# Restart everything
systemctl --user restart council-embedding council-ingestion council-wolves

# Search the Commons
curl -s 'http://localhost:8080/v1/commons/search?query=quantum' \
  -H "Authorization: Bearer <key>" -H "X-Agent-ID: curator"

# Ingest a file
curl -s -X POST http://localhost:8080/v1/commons/files/sync \
  -H "Authorization: Bearer <key>" -H "X-Agent-ID: curator" \
  -H "Content-Type: application/json" \
  -d '{"paths":["new_file.md"],"organise":true}'

# Dispatch a Wolf
curl -s -X POST http://localhost:8080/v1/sanctum/wolves/wolf_1/task \
  -H "Authorization: Bearer <key>" -H "X-Agent-ID: curator" \
  -H "Content-Type: application/json" \
  -d '{"action":"research","payload":{"query":"your query"}}'

# Watch logs
journalctl --user -u council-wolves -f
```
