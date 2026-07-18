# WOLF FIX PLAN — Skills-Based Memory Solution

**Date:** 2026-07-18
**Status:** Build-ready — no Hermes core changes required
**Prepared by:** Leon (Layer 2)

---

## Problem Summary

The foreverbox plugin defines 10 tools in `get_tool_schemas()` — but none of them are ever registered for any agent. The plugin's `register(ctx)` function calls `ctx.register_memory_provider()` — a method that does not exist on `PluginContext`. Additionally, the plugin's `__init__.py` contains `MemoryProvider` which triggers Hermes' exclusive-plugin detection, skipping it entirely from the normal plugin loading path.

Seven of the ten tools are needed by agents. They are addressed by this plan. The remaining three (`wolf_status`, `wolf_dispatch`, `wolf_task_status`) are server-side orchestration tools that agents do not call directly — they remain API-only.

The standard `memory` tool (add/replace/remove with `memory`/`user` targets only) works because it hooks the `on_memory_write` backend — not the tool registration path.

The wolf cannot write research findings to the Sanctum, defeating its purpose: autonomous research with results persisted for the spawning agent to retrieve. No agent can search the Quiddity Lore Sea or ingest new files.

## Solution: Skills That Call the API Directly

Seven skills that wrap the council-library REST API via `curl`. They bypass the plugin entirely. No Hermes core changes. Survive updates.

---

## STAGE 1: Create Seven fbox Skills

All seven live in `/foreverbox_data/Shared_Skills/foreverbox/`. Each is a standard Hermes skill (SKILL.md with YAML frontmatter).

### 1.1 `fbox-memory-upsert`

**File:** `Shared_Skills/foreverbox/fbox-memory-upsert/SKILL.md`

**Purpose:** Create or update a memory entry in any Sanctum namespace.

**API call:**
```
PUT /sanctum/memory/{namespace}/{key_name}
```

**Skill trigger:** Agent says "write to Sanctum", "save to wolf_tasks", "upsert memory", etc.

**Parameters the skill must accept:**
- `namespace` (required) — e.g. `wolf_tasks`
- `key_name` (required) — e.g. `lead_search_20260718`  
- `content` (required) — the findings text
- `importance` (optional, default 70)
- `source_type` (optional, default `wolf_synthesis`)

**Implementation:** Shell out via `terminal()` to `curl`. The skill document includes the exact curl command with variable substitution.

### 1.2 `fbox-memory-search`

**File:** `Shared_Skills/foreverbox/fbox-memory-search/SKILL.md`

**Purpose:** Search memories by query across any namespace.

**API call:**
```
POST /sanctum/memory/search
Body: {"query": "...", "namespace": "..."}
```

**Skill trigger:** Agent says "search Sanctum", "find in wolf_tasks", "memory search", etc.

### 1.3 `fbox-memory-get`

**File:** `Shared_Skills/foreverbox/fbox-memory-get/SKILL.md`

**Purpose:** Retrieve a single memory by namespace and key.

**API call:**
```
GET /sanctum/memory/{namespace}/{key_name}
```

**Skill trigger:** Agent says "get memory", "retrieve from Sanctum", "check wolf results", etc.

### 1.4 `fbox-memory-list`

**File:** `Shared_Skills/foreverbox/fbox-memory-list/SKILL.md`

**Purpose:** List all memory entries in a namespace, optionally filtered by tags or importance.

**API call:**
```
GET /sanctum/memory?namespace=wolf_tasks
```

**Skill trigger:** Agent says "list wolf tasks", "show all in wolf_tasks", "what's in the namespace", etc.

### 1.5 `fbox-memory-delete`

**File:** `Shared_Skills/foreverbox/fbox-memory-delete/SKILL.md`

**Purpose:** Delete a single memory entry. Irreversible — skill must warn before executing.

**API call:**
```
DELETE /sanctum/memory/{namespace}/{key_name}
```

**Skill trigger:** Agent says "delete from Sanctum", "remove wolf task", "clear memory entry", etc.

### 1.6 `fbox-commons-search`

**File:** `Shared_Skills/foreverbox/fbox-commons-search/SKILL.md`

**Purpose:** Semantic vector search over the Quiddity Lore Sea — the shared knowledge base, not agent Sanctums. Used when an agent needs to find information stored in the Sea itself (documents, ingested files, archival knowledge).

**API call:**
```
GET /commons/search?query=...
```

**Skill trigger:** Agent says "search the Sea", "find in Quiddity Lore", "commons search", "look up in the archives", etc.

### 1.7 `fbox-ingest-file`

**File:** `Shared_Skills/foreverbox/fbox-ingest-file/SKILL.md`

**Purpose:** Trigger ingestion and vectorisation of a file dropped into the Quiddity Lore Sea root. The API handles chunking, embedding, and indexing server-side.

**API call:**
```
POST /commons/files/sync
Body: {"filename": "my_doc.md", "organise": true}
```

**Skill trigger:** Agent says "ingest file", "process new file", "index the Sea", "sync Quiddity Lore", etc.

### 1.8 Unified Sync Daemon

**File:** `/foreverbox_data/sync/sync_daemon.py`

**Purpose:** Background process that syncs data from Hermes to the council-library API. Replaces the dead `quiddity-sync` cron job and the non-functional `sync_turn` plugin method.

**Sync operations:**
1. **`sync sessions`** — read new conversation turns from Hermes' SQLite session DB, POST to `/sanctum/conversations/{session_id}/messages`
2. **`sync files`** — scan the Quiddity Lore Sea for new/changed files, POST to `/commons/files/sync` with explicit paths
3. **`sync status`** — report pending counts for each operation type without syncing

**Architecture:**
- Python script, no Hermes dependency
- Reads Hermes session DB directly (SQLite at `~/.hermes/sessions.db`)
- Reads foreverbox credentials from `/foreverbox_data/.env`
- Triggered by systemd timer every 30 minutes
- Idempotent — tracks sync state so nothing is double-posted
- Extensible — new sync operations added as modules

**Storage:** `/foreverbox_data/sync/`
```
sync/
├── sync_daemon.py       ← main script
├── sync_state.json       ← tracks last-synced positions
├── sessions.py           ← session sync module
├── files.py              ← file sync module
└── README.md
```

**Why this replaces the plugin's sync_turn:**
The plugin's `sync_turn` method is never called because the foreverbox provider is never registered in `self._providers` (same exclusive-plugin bug). The unified daemon bypasses the plugin entirely — reads Hermes data directly, writes to the API directly. No Hermes core changes. Survives updates.

### 1.9 Authentication

All seven skills read credentials from the agent's `.env`:
```
FOREVERBOX_API_KEY=...
```
Or pull from `foreverbox.json`:
```json
{"api_url": "http://localhost:8080/v1"}
```

---

## STAGE 2: Update Wolf Profile

### 2.1 Remove Broken Plugin Tools

The wolf profile currently has the foreverbox plugin installed but its tools don't work. We still need the plugin for the `on_memory_write` hook (standard memory tool backend), but the wolf should use the skills for namespace-based operations.

**No change needed to wolf config.** The plugin stays. The skills supplement it.

### 2.2 Update SOUL.md

Replace the memory_upsert protocol with skill-based instructions:

```markdown
### Research execution:
1. **Search first.** Use web_search immediately.
2. **Read sources.** Open the most relevant results.
3. **Cross-reference.** Note disagreements or gaps.
4. **Synthesise.** Produce structured output.
5. **Write to Sanctum using fbox-memory-upsert skill:**
   - Load the skill: `skill_view(name='fbox-memory-upsert')`
   - Call it with: namespace=`wolf_tasks`, key_name=`{task_id}`, content=`{findings}`
6. **Signal completion using fbox-memory-upsert skill:**
   - namespace: `wolf_tasks`
   - key_name: `{task_id}:done`
   - content: `{"status": "completed", "model": "Zeon7-Gemma:64k"}`
```

### 2.3 Skills Already Available

The wolf profile already has:
```
skills/foreverbox → /foreverbox_data/Shared_Skills/foreverbox/ (symlink)
```

Adding the seven new skills to Shared_Skills makes them immediately available to the wolf — and all other agents.

---

## STAGE 3: Distribute to All Agents

Apply the same Shared_Skills symlink approach already used for fbox-council-library-cli, fbox-operations, etc.

All four core agents already have the symlink. The new skills appear automatically.

---

## STAGE 4: End-to-End Wolf Verification

### 4.1 Identity Test (AC-1)
```
hermes chat --profile wolf -q "Who are you?" -m Zeon7-Gemma:64k --provider ollama --source wolf
```
Expected: "I am a Wolf, a Council Library research worker."

### 4.2 Search + Write Test (AC-2 + AC-3 combined)
```
hermes chat --profile wolf -q "Research task. Task ID: e2e_test. Find one current UK headline with source URL. Write findings to Sanctum: load fbox-memory-upsert skill, upsert namespace=wolf_tasks, key=e2e_test, content=<your findings>. Then upsert namespace=wolf_tasks, key=e2e_test:done, content='{\"status\":\"completed\"}'. Exit." -m Zeon7-Gemma:64k --provider ollama --source wolf
```

**Verify database:**
```
mariadb -u zeon7_user -p agent_wolf -e "SELECT key_name, content_text FROM memory_lore WHERE namespace='wolf_tasks'"
```

### 4.3 Concurrent Test (AC-4)
Spawn 3 wolves with different task IDs simultaneously using `terminal(background=True)`. Verify all three write to Sanctum.

### 4.4 Agent Spawn Test (AC-5)
From a cloud-model agent (Leon, DeepSeek), spawn a wolf via `terminal(background=True)`. Verify results in Sanctum.

### 4.5 Layer 1 Guard Test (AC-6)
Attempt to spawn a wolf from a local-model agent. Verify blocked with clear message.

---

## STAGE 5: Blueprint Update

Update `/foreverbox_data/council-library/docs/WOLF_UPGRADE_BLUEPRINT_V2.md` to V3:

- Replace all `memory_upsert` tool references with `fbox-memory-upsert` skill references
- Document the skills-based approach in §2.4
- Remove §5 (wolf_dispatch tool upgrade) — no longer needed
- Update §6 (Sanctum) to confirm agent_wolf is the target
- Update build sequence to include skill creation

---

## STAGE 6: Agent SOUL.md Wolf Protocol

Once AC-1 through AC-6 pass, insert wolf-spawning protocols into the four core agent SOUL.md files:

- When to use wolves (complex multi-source research, parallel searches)
- How to spawn them (`terminal(background=True)`)
- How to retrieve results (`fbox-memory-search` or `fbox-memory-get`)
- The Layer 1 guard rule

---

## Build Order

| Stage | What | Time | Blocks |
|-------|------|------|--------|
| 1 | Create 7 fbox skills in Shared_Skills | 35 min | Nothing |
| 2 | Update wolf SOUL.md | 5 min | Stage 1 |
| 3 | Distribute to agents (automatic via symlink) | 0 min | Stage 1 |
| 4 | End-to-end wolf verification (all 6 ACs) | 15 min | Stages 1-3 |
| 5 | Blueprint V3 update | 15 min | Stage 4 |
| 6 | Core agent SOUL.md wolf protocol | 10 min | Stage 4 |
| 7 | Unified sync daemon + systemd timer | 30 min | Nothing |

**Total: ~110 minutes. Zero Hermes core changes.**

### Skill Inventory

| Skill | API endpoint | Purpose |
|-------|-------------|---------|
| `fbox-memory-upsert` | `PUT /sanctum/memory/{ns}/{key}` | Create/update memory |
| `fbox-memory-search` | `POST /sanctum/memory/search` | Search Sanctum |
| `fbox-memory-get` | `GET /sanctum/memory/{ns}/{key}` | Retrieve single entry |
| `fbox-memory-list` | `GET /sanctum/memory?namespace=` | List namespace entries |
| `fbox-memory-delete` | `DELETE /sanctum/memory/{ns}/{key}` | Delete entry (irreversible) |
| `fbox-commons-search` | `GET /commons/search?query=` | Search Quiddity Lore Sea |
| `fbox-ingest-file` | `POST /commons/files/sync` | Ingest file into Sea |
