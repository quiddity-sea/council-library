# THE COUNCIL LIBRARY: TECHNICAL ARCHITECTURE BLUEPRINT (VERSION 3.0)
**Target Reader:** Qwen Coder (The Builder Model) — all Lead profiles, primary handoff to the Coder Specialist (Leon)
**Tone:** Precise, imperative, technical, unambiguous.
**Platform Scope:** WSL2 (Ubuntu 24), MariaDB Community Server 11.8+, PHP 8.1+ (API Engine), Python / Hermes Agent Core.
**Classification:** Internal, Builder Handoff.
**Date:** 2026-07-14
**Companion Document:** `The_Council_Library_Master_Briefing_V6_Technical_Human.md` — the authoritative human-facing explanation. Every section below states which part of the V6 Briefing it builds. Version 3.0 completes all remaining stubs, adds ingestion trigger and folder-routing infrastructure, fills missing DDL, updates all acceptance criteria and migration directives, and corrects method signatures to match the actual Hermes `MemoryProvider` ABC. No `...` or `pass` stubs remain in any code block.

---

## 0. HOW TO READ THIS DOCUMENT

Version 1 of this blueprint was architecturally sound at the database and API layer but incomplete at the Hermes integration layer: it invented a generic Python plugin shape instead of Hermes' actual `MemoryProvider` interface, implied the Sanctum tables *replace* SOUL.md/USER.md rather than mirror them, assumed row-level locking alone prevents wolf task collisions, and didn't structurally enforce the privacy gate's ordering. Version 2 fixed all four while keeping every DDL statement and endpoint that was already correct.

A second review pass caught four further gaps, addressed in this revision: no stated authority rule between `wolf_sessions` and `specialist_workers` (§3.5), the Director's `directives` table having no explicit mechanism for reaching the isolated Registry queue Wolves actually poll (§3.6), the token budget the Master Briefing promises the Registry enforces never actually being checked anywhere in the Router (§5.2), and Wolf-write visibility to an active Hermes session being real but under-documented (§7.4 — this one was already covered by `prefetch()` and the `wolf_status` tool; it needed a cross-reference, not new code).

A third pass, prompted by a review of the SOUL.md/USER.md persona files against this blueprint, added one further structural gap: the Sudo Protocol every persona file asserts ("privileged actions require Merrill's consent") existed only as prompt-level convention with no technical enforcement anywhere in the stack. §12 makes it a structural gate, mirroring the same enforce-upstream principle already used for the privacy gate (§5.4) and the budget gate (§5.2). Nothing here contradicts the Master Briefing's philosophy; this is the implementation layer beneath it.

---

## 1. SYSTEM TOPOLOGY — THE FOUR WINGS

*(Implements Briefing §6)*

The platform executes a Federated Tiered Architecture. All agent cores, session contexts, registry configurations, and the collective vector index live in MariaDB, separated into four logical databases the Briefing calls Wings.

### 1.1 Database Allocations

| Wing | Database Name | Purpose | Access Pattern |
|------|---------------|---------|----------------|
| Wing 1: The Commons | `quiddity_commons` | Shared global repository. Vector embeddings, file metadata, semantic index. | Read: all agents. Write: Ingestion Pipeline only (§5). No agent, and no Wolf, ever writes here. |
| Wing 2: The Sanctums | `agent_curator`, `agent_coach`, `agent_producer` | Sovereign state per Lead. Soul, user context, memory lore, conversation history, Wolf working memory. | Read/Write: owning Lead + its 3 Wolves only. Hard isolation via separate database. |
| Wing 3: The Throne | `agent_director` | Otec's strategic plans, cross-agent directives, global snapshots. | Read/Write: Director only. |
| Wing 4: The Registry | `agent_registry` | Control plane. Agent metadata, API keys, worker mappings, service discovery, rate limits, the central Task Queue. | Read/Write: orchestrator / admin / claiming workers. |

**Naming convention:** production databases use `agent_{slug}`. **Public pseudonym mapping:**

| Internal Codename | Public Title | Sanctum Database |
|---|---|---|
| Zeon7 | The Curator | `agent_curator` |
| Leon | The Producer | `agent_producer` |
| Gemma | The Coach | `agent_coach` |
| Otec | The Director | `agent_director` |

Wolves do not get separate databases. They exist inside their Lead's Sanctum, partitioned by `wolf_id` and namespaced keys (§7).

### 1.2 The Worzel Gummidge Principle

*(Implements Briefing §8 — this governs every Hermes-side decision in §6 below)*

Exactly one Lead profile is ever running at once. When Zeon7 is active, `gemma`, `leon`, and `otec` are not backgrounded, dormant, or polling — their Hermes processes simply are not running. Swapping the active head means stopping one Hermes profile process and starting another; it is never a runtime reconfiguration inside a single running process. This has one direct, load-bearing consequence for the Hermes integration in §6: **there is no agent-slug switching mechanism to build.** Each profile is permanently and exclusively bound to one Sanctum, decided once at setup.

This principle does **not** apply to Wolves — see §7. A Lead being the sole "head" does not mean the system does only one thing at a time.

---

## 2. DATABASE SCHEMAS (DDL SPECIFICATIONS)

All schemas target MariaDB 11.8+. InnoDB, `utf8mb4`/`utf8mb4_unicode_ci`. Native `VECTOR` type with HNSW index (MariaDB 11.3+).

### 2.1 The Commons Schema (`quiddity_commons`)

```sql
CREATE DATABASE IF NOT EXISTS quiddity_commons CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quiddity_commons;

CREATE TABLE quiddity_files (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    relative_path VARCHAR(1024) NOT NULL,
    content_hash CHAR(64) NOT NULL,
    mime_type VARCHAR(128) DEFAULT 'text/markdown',
    file_size_bytes INT UNSIGNED,
    last_modified TIMESTAMP NOT NULL,
    indexed_at TIMESTAMP NULL,
    indexing_status ENUM('pending','processing','indexed','failed') DEFAULT 'pending',
    error_message TEXT NULL,
    UNIQUE KEY uk_path (relative_path),
    KEY idx_status (indexing_status)
) ENGINE=InnoDB;

CREATE TABLE quiddity_vector_references (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    file_id BIGINT UNSIGNED NOT NULL,
    chunk_index INT UNSIGNED NOT NULL,
    chunk_text MEDIUMTEXT NOT NULL,
    chunk_token_count INT UNSIGNED NOT NULL,
    embedding VECTOR(1024) NOT NULL,
    chunk_metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES quiddity_files(id) ON DELETE CASCADE,
    KEY idx_file_chunk (file_id, chunk_index)
) ENGINE=InnoDB;

ALTER TABLE quiddity_vector_references
    ADD VECTOR INDEX idx_vector_hnsw (embedding)
    INDEX_OPTIONS '{"type": "hnsw", "M": 16, "efConstruction": 200, "distance_metric": "cosine"}';

CREATE TABLE conversation_vectors (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    agent_slug VARCHAR(64) NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    message_id BIGINT UNSIGNED NOT NULL,
    role ENUM('user','assistant','system','tool') NOT NULL,
    content_text MEDIUMTEXT NOT NULL,
    embedding VECTOR(1024) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_agent_session (agent_slug, session_id),
    KEY idx_created (created_at)
) ENGINE=InnoDB;

ALTER TABLE conversation_vectors
    ADD VECTOR INDEX idx_conv_vector_hnsw (embedding)
    INDEX_OPTIONS '{"type": "hnsw", "M": 16, "efConstruction": 200, "distance_metric": "cosine"}';

-- Ingestion dead-letter queue (§4.4). Failed chunks land here with error
-- traces; the Ingestion Pipeline retries from this table, not from the
-- filesystem, so a transient embedding-service outage doesn't re-chunk the
-- same file from scratch.
CREATE TABLE ingestion_dead_letter (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    file_id BIGINT UNSIGNED NOT NULL,
    chunk_index INT UNSIGNED NOT NULL,
    chunk_text MEDIUMTEXT NOT NULL,
    error_message TEXT NOT NULL,
    error_trace TEXT NULL,
    retry_count TINYINT UNSIGNED DEFAULT 0,
    max_retries TINYINT UNSIGNED DEFAULT 5,
    last_attempted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES quiddity_files(id) ON DELETE CASCADE,
    KEY idx_retry (retry_count, max_retries)
) ENGINE=InnoDB;
```

### 2.2 The Sanctum Template Schema

Run for each Lead: `agent_curator`, `agent_coach`, `agent_producer`. Director uses the same template plus §2.4.

```sql
CREATE DATABASE IF NOT EXISTS agent_curator CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agent_curator;

-- Core Identity — MIRRORS SOUL.md. SOUL.md remains canonical; Hermes reads/writes
-- the file directly regardless of this table. This table is populated once at
-- migration and thereafter kept current ONLY via the on_memory_write hook (§6.6).
-- It is never the runtime read path for Hermes' own personality system.
CREATE TABLE soul (
    id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    agent_slug VARCHAR(64) NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    identity_yaml MEDIUMTEXT NOT NULL,
    protocols_yaml MEDIUMTEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by VARCHAR(64) NOT NULL
) ENGINE=InnoDB;

-- User Context — MIRRORS USER.md under the same rule as `soul` above.
CREATE TABLE user_context (
    id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    agent_slug VARCHAR(64) NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    profile_yaml MEDIUMTEXT NOT NULL,
    relationship_notes MEDIUMTEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Long-term Memory / Lore. Namespacing convention for Wolf-authored rows is
-- mandatory — see §7.3. UNIQUE (agent_slug, namespace, key_name) is the
-- constraint that makes Wolf key-collision avoidance necessary, not optional.
CREATE TABLE memory_lore (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    agent_slug VARCHAR(64) NOT NULL,
    namespace VARCHAR(128) NOT NULL,
    key_name VARCHAR(256) NOT NULL,
    content_json JSON NOT NULL,
    content_text MEDIUMTEXT NULL,
    source_type ENUM('user_directive','session_extraction','document_ingestion','wolf_synthesis') NOT NULL,
    source_ref VARCHAR(256) NULL,
    importance TINYINT UNSIGNED DEFAULT 50,
    tags JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    UNIQUE KEY uk_agent_ns_key (agent_slug, namespace, key_name),
    KEY idx_ns_importance (namespace, importance DESC),
    KEY idx_tags (tags),
    FULLTEXT KEY ft_content (content_text)
) ENGINE=InnoDB;

CREATE TABLE conversation_history (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    agent_slug VARCHAR(64) NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    message_seq INT UNSIGNED NOT NULL,
    role ENUM('user','assistant','system','tool') NOT NULL,
    content_text MEDIUMTEXT NOT NULL,
    reasoning_content LONGTEXT,
    tool_calls JSON NULL,
    tool_results JSON NULL,
    tokens_input INT UNSIGNED NULL,
    tokens_output INT UNSIGNED NULL,
    model_used VARCHAR(128) NULL,
    wolf_id VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- UNIQUE, not just KEY: async sync_turn() writes (§6.5) must never be able
    -- to double-insert the same sequence position under bursty conversation.
    UNIQUE KEY uk_session_seq (agent_slug, session_id, message_seq),
    KEY idx_session_created (session_id, created_at)
) ENGINE=InnoDB;

-- Wolf Session Registry (3 specialist workers per Lead)
-- AUTHORITY RULE (see §3.5): agent_registry.specialist_workers is the sole
-- source of truth for live status and heartbeat across the whole system.
-- This table is a Sanctum-local, read-optimised CACHE of that same state,
-- scoped to this Lead's own 3 Wolves, so the Lead can inspect its Wolves
-- without an API round trip on every turn. It is NEVER written independently
-- of a Registry write — both writes happen inside one PHP transaction in
-- WolfController (§3.5). `status` and `heartbeats_missed` here must always
-- match the corresponding row in agent_registry.specialist_workers.
CREATE TABLE wolf_sessions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    wolf_id VARCHAR(64) NOT NULL,
    parent_lead_slug VARCHAR(64) NOT NULL,
    status ENUM('idle','working','blocked','error','terminated') DEFAULT 'idle',
    current_task_json JSON NULL,
    current_task_id VARCHAR(128) NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    error_message TEXT NULL,
    heartbeats_missed TINYINT UNSIGNED DEFAULT 0,
    UNIQUE KEY uk_wolf (wolf_id),
    KEY idx_lead_status (parent_lead_slug, status)
) ENGINE=InnoDB;

-- Wolf Working Memory (scratchpad, transient, wolf-scoped by construction)
CREATE TABLE wolf_working_memory (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    wolf_id VARCHAR(64) NOT NULL,
    namespace VARCHAR(128) NOT NULL,
    key_name VARCHAR(256) NOT NULL,
    value_json JSON NOT NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_wolf_ns_key (wolf_id, namespace, key_name),
    KEY idx_expires (expires_at)
) ENGINE=InnoDB;
```

### 2.3 The Registry Schema (`agent_registry`)

```sql
CREATE DATABASE IF NOT EXISTS agent_registry CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agent_registry;

CREATE TABLE agents (
    slug VARCHAR(64) PRIMARY KEY,
    display_name VARCHAR(128) NOT NULL,
    role ENUM('lead','director') NOT NULL,
    description MEDIUMTEXT NULL,
    db_name VARCHAR(128) NOT NULL,
    api_key_hash CHAR(64) NOT NULL,
    allowed_scopes JSON NOT NULL,
    rate_limit_rpm INT UNSIGNED DEFAULT 300,
    status ENUM('active','paused','decommissioned') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- AUTHORITY RULE: this table, not Sanctum.wolf_sessions, is the single
-- source of truth for a Wolf's live status and heartbeat system-wide. The
-- Registry needs this global view for rate-limiting, health monitoring, and
-- Director-level oversight across all four Sanctums. See §3.5 for the
-- mandatory dual-write transaction that keeps Sanctum.wolf_sessions in sync.
CREATE TABLE specialist_workers (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    worker_id VARCHAR(64) NOT NULL UNIQUE,
    parent_agent_slug VARCHAR(64) NOT NULL,
    specialisation VARCHAR(128) NOT NULL,
    capabilities JSON NOT NULL,
    status ENUM('idle','assigned','busy','offline') DEFAULT 'idle',
    last_heartbeat TIMESTAMP NULL,
    FOREIGN KEY (parent_agent_slug) REFERENCES agents(slug) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Token Budget Ledger — backs the Cognitive Router's daily budget gate (§5.1,
-- §5.2). The Master Briefing states the Registry monitors this budget; this
-- table is where that promise actually lives. Incremented by the PHP API
-- (ConversationController) every time a message is appended whose
-- `model_used` corresponds to a cloud tier, reading `tokens_input` +
-- `tokens_output` off that same message.
CREATE TABLE token_budget_ledger (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    tier ENUM('system2_light','system2_heavy') NOT NULL,
    usage_date DATE NOT NULL,
    tokens_used BIGINT UNSIGNED NOT NULL DEFAULT 0,
    daily_limit BIGINT UNSIGNED NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tier_date (tier, usage_date)
) ENGINE=InnoDB;

-- Sudo Protocol enforcement ledger — see §14. Every SOUL.md/USER.md file
-- asserts that privileged actions require Merrill's consent; this table is
-- where that assertion becomes a technical gate rather than a convention
-- the model is trusted to remember.
CREATE TABLE privileged_action_log (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    agent_slug VARCHAR(64) NOT NULL,
    wolf_id VARCHAR(64) NULL,
    action_type ENUM('sql_ddl','sudo_command','schema_alter','production_deploy','destructive_file_op') NOT NULL,
    command_text MEDIUMTEXT NOT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmation_code CHAR(8) NULL,
    confirmed_at TIMESTAMP NULL,
    confirmed_by VARCHAR(64) NULL,
    status ENUM('pending','confirmed','denied','expired') DEFAULT 'pending',
    executed_at TIMESTAMP NULL,
    result_json JSON NULL,
    KEY idx_agent_status (agent_slug, status),
    KEY idx_code (confirmation_code)
) ENGINE=InnoDB;

-- Task Queue for Wolf dispatch. See §7.2 for the MANDATORY atomic claim
-- pattern — row-level locking alone does not prevent two Wolves both
-- reading a 'queued' row before either writes to it.
CREATE TABLE task_queue (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    task_id VARCHAR(128) NOT NULL UNIQUE,
    directive_id VARCHAR(128) NULL,
    source_agent_slug VARCHAR(64) NOT NULL,
    target_agent_slug VARCHAR(64) NOT NULL,
    target_worker_id VARCHAR(64) NULL,
    action VARCHAR(128) NOT NULL,
    payload_json JSON NOT NULL,
    priority ENUM('low','normal','high','critical') DEFAULT 'normal',
    status ENUM('queued','claimed','processing','completed','failed','dead_letter') DEFAULT 'queued',
    claimed_by_worker_id VARCHAR(64) NULL,
    claimed_at TIMESTAMP NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    result_json JSON NULL,
    error_message TEXT NULL,
    retry_count TINYINT UNSIGNED DEFAULT 0,
    max_retries TINYINT UNSIGNED DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_target_status (target_agent_slug, status),
    KEY idx_worker_status (claimed_by_worker_id, status),
    KEY idx_priority_created (priority DESC, created_at)
) ENGINE=InnoDB;

CREATE TABLE api_keys (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    key_prefix CHAR(8) NOT NULL,
    key_hash CHAR(64) NOT NULL,
    owner_agent_slug VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    scopes JSON NOT NULL,
    expires_at TIMESTAMP NULL,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_prefix (key_prefix),
    KEY idx_owner (owner_agent_slug)
) ENGINE=InnoDB;
```

### 2.4 Director Planning Tables (`agent_director` only)

```sql
CREATE DATABASE IF NOT EXISTS agent_director CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agent_director;

CREATE TABLE strategic_plans (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    plan_id VARCHAR(128) NOT NULL UNIQUE,
    title VARCHAR(512) NOT NULL,
    description MEDIUMTEXT NULL,
    status ENUM('draft','active','completed','archived') DEFAULT 'draft',
    priority TINYINT UNSIGNED DEFAULT 50,
    dependencies JSON NULL,
    assigned_agents JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL
) ENGINE=InnoDB;

CREATE TABLE directives (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    directive_id VARCHAR(128) NOT NULL UNIQUE,
    plan_id VARCHAR(128) NULL,
    target_agent_slug VARCHAR(64) NOT NULL,
    target_worker_id VARCHAR(64) NULL,
    action VARCHAR(128) NOT NULL,
    payload_json JSON NOT NULL,
    priority ENUM('low','normal','high','critical') DEFAULT 'normal',
    status ENUM('queued','dispatched','in_progress','completed','failed') DEFAULT 'queued',
    dispatched_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    result_json JSON NULL,
    error_message TEXT NULL,
    KEY idx_target_status (target_agent_slug, status),
    KEY idx_plan (plan_id)
) ENGINE=InnoDB;
```

---

## 3. PHP 8.1+ REST API SERVICE CONTRACT

*(Implements Briefing §4 — "the guarded API")*

The PHP service is a stateless transactional layer between Hermes and MariaDB. Python never touches MariaDB directly, under any circumstance, for any reason — including a "read-only" convenience query. All payloads are strict JSON.

### 3.1 Global Authentication and Routing Headers

Every request must provide:
- `Authorization: Bearer <token>` — validated against `agent_registry.api_keys`.
- `X-Agent-ID: <agent_slug>` — identifies the target Sanctum. Fixed per deployed profile (§1.2); never varies at runtime for a given running process.
- `X-Wolf-ID: <wolf_id>` — optional, present when a Wolf is acting on behalf of its Lead.
- `X-Request-ID: <uuid>` — distributed tracing.
- `Content-Type: application/json`

### 3.2 Endpoint Dictionary

**Core State (Sanctum)**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/sanctum/soul` | Retrieve full identity and protocols |
| PUT | `/v1/sanctum/soul` | Upsert identity mirror (versioned) — called by `on_memory_write`, not by conversational agents directly |
| GET | `/v1/sanctum/user-context` | Retrieve human director profile |
| PUT | `/v1/sanctum/user-context` | Upsert profile mirror — same rule as above |

**Memory / Lore**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/sanctum/memory` | List memories (filterable: namespace, tags, importance, limit, offset) |
| GET | `/v1/sanctum/memory/{namespace}/{key_name}` | Get single memory |
| PUT | `/v1/sanctum/memory/{namespace}/{key_name}` | Upsert memory |
| DELETE | `/v1/sanctum/memory/{namespace}/{key_name}` | Delete memory |
| POST | `/v1/sanctum/memory/search` | Full-text + vector hybrid search |

**Conversation History**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/sanctum/conversations` | List sessions (limit, offset, since) |
| GET | `/v1/sanctum/conversations/{session_id}` | Get full session messages |
| POST | `/v1/sanctum/conversations` | Create new session |
| POST | `/v1/sanctum/conversations/{session_id}/messages` | Append message — target of `sync_turn()` (§6.5) |

**Quiddity Commons (Shared Knowledge, Read-Only for Agents)**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/commons/files` | List indexed files |
| POST | `/v1/commons/files/sync` | Trigger filesystem scan and re-index. Accepts optional `paths` array (agent-initiated, Path A — §4.5) and `organise` boolean. Full-root scan without `paths` restricted to cron/startup (Paths B/C). |
| GET | `/v1/commons/search` | Semantic vector search |
| GET | `/v1/commons/files/{file_id}/chunks` | Get chunks for a file |

**Folder Management (Quiddity Lore Sea Subfolder Catalogue)**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/commons/folders` | List folder catalogue with centroid metadata (sample count, last rebuilt) |
| PUT | `/v1/commons/folders` | Add or update a folder entry in `quiddity_folders.yaml`. Body: `{ "folder_name": "07_NewDomain", "purpose": "Description", "create_on_disk": true }`. Requires Sudo Protocol confirmation (§12). |
| DELETE | `/v1/commons/folders/{folder_name}` | Remove a folder from the catalogue. Does NOT delete files — they remain on disk. Requires Sudo Protocol confirmation. |
| POST | `/v1/commons/folders/reclassify` | Move a file from `_review/` to a specific folder. Body: `{ "filename": "ambiguous_doc.md", "target_folder": "04_FromTheNoise_Archives" }`. Updates `quiddity_files.relative_path` and physically moves the file. |
| POST | `/v1/commons/folders/rebuild-centroids` | Rebuild all folder centroid vectors by re-scanning existing indexed files. Must be called after adding a new folder or after bulk ingestion changes folder composition significantly. Runs `generate_folder_centroids.py` server-side. Returns `{ "job_id": "<uuid>", "folders_processed": 6 }`. |

**Vector Ingestion (Ingestion Pipeline only — never a Wolf, never a Lead)**

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/v1/commons/ingest/batch` | Ingest multiple files in parallel. Body: `{ "files": [...], "chunking": "semantic_paragraph", "chunk_size_tokens": 512, "overlap_tokens": 64, "embedding_model": "bge-m3", "concurrency": 3, "callback_url": "..." }`. Returns `{ "job_id": "uuid", "status": "accepted", "estimated_chunks": 142 }`. |

**Wolf Operations**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/sanctum/wolves/status` | List all Wolves for this Lead |
| POST | `/v1/sanctum/wolves/{wolf_id}/task` | Dispatch task to a specific Wolf |
| GET | `/v1/sanctum/wolves/{wolf_id}/task/{task_id}` | Poll task status |
| POST | `/v1/sanctum/wolves/{wolf_id}/memory` | Wolf working memory upsert |

**Director / Orchestration**

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/v1/director/plans` | Create strategic plan |
| POST | `/v1/director/directives` | Writes to `agent_director.directives` (Otec's private planning ledger) AND, in the same request, dispatches actionable rows into `agent_registry.task_queue` linked by `directive_id`. See §3.6 — this is the only path by which a directive becomes visible to a Wolf. |
| GET | `/v1/director/status` | Global system health |

**Registry / Budget**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/registry/budget?tier={tier}` | Returns `{ "tier", "usage_date", "tokens_used", "daily_limit", "remaining" }` for today, reading `token_budget_ledger`. Called by the Cognitive Router (§5.1) before routing to a cloud tier. |

**Registry / Privileged Actions (Sudo Protocol Enforcement — see §14)**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/registry/privileged-actions/{id}` | Poll status of a pending privileged action request |
| POST | `/v1/registry/privileged-actions/{id}/confirm` | Submit the confirmation code Merrill has read back; on match, executes the originally-gated action |

**Health and Admin**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/healthz` | Liveness probe (DB connectivity, queue depth) |
| GET | `/v1/readyz` | Readiness (embedding model loaded, indexes warm) |
| GET | `/v1/metrics` | Prometheus exposition format |

### 3.3 PHP API File Structure

```
php-api/
├── composer.json
├── public/
│   └── index.php              # Front controller (FastRoute)
├── src/
│   ├── bootstrap.php          # DI container, PDO, JWT
│   ├── Middleware/
│   │   ├── Auth.php           # Bearer token to agent_slug + scopes
│   │   ├── AgentContext.php   # Injects X-Agent-ID, X-Wolf-ID into request
│   │   └── RateLimit.php
│   ├── Controller/
│   │   ├── SoulController.php
│   │   ├── MemoryController.php
│   │   ├── ConversationController.php
│   │   ├── QuiddityController.php
│   │   ├── IngestionController.php
│   │   ├── WolfController.php
│   │   ├── DirectorController.php
│   │   └── FolderController.php
│   ├── Service/
│   │   ├── VectorSearch.php    # Wrapper for MariaDB VECTOR_DISTANCE
│   │   ├── Chunker.php         # Semantic paragraph chunker
│   │   ├── EmbeddingClient.php # HTTP client to embedding service
│   │   ├── TaskClaimer.php     # Atomic claim logic — see §7.2
│   │   └── IngestionWorker.php # Background job processor
│   └── Entity/                 # DTOs for requests/responses
├── migrations/                 # Phinx migrations matching schema/
└── schema/
    ├── 01_commons.sql
    ├── 02_sanctum_template.sql
    ├── 03_registry.sql
    └── 04_director.sql
```

### 3.4 Error Envelope Standard

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Human-readable description",
    "details": {}
  },
  "request_id": "uuid from X-Request-ID"
}
```

HTTP status codes: 200, 201, 400, 401, 403, 404, 409, 429, 500.

### 3.5 Wolf Status — Single Source of Truth and the Dual-Write Rule

Two tables track a Wolf's live status: `agent_registry.specialist_workers` (system-wide, needed for rate-limiting and Director-level health monitoring) and the owning Sanctum's `wolf_sessions` (fast, local, already-authenticated view for the Lead's own context assembly). Keeping both is correct — deleting either loses a real consumer. What v1 of this section lacked was a rule for which one is authoritative and how they stay consistent. Fix:

- `agent_registry.specialist_workers` is authoritative for `status` and `last_heartbeat`, full stop.
- `Sanctum.wolf_sessions` is a synchronised cache and is **never written independently**. Every status transition writes both tables inside a single PHP transaction.

```php
class WolfController {
    public function updateStatus(string $wolfId, string $status, ?array $taskContext = null): void {
        $this->registryDb->beginTransaction();
        $this->sanctumDb->beginTransaction();
        try {
            $this->registryDb->update('specialist_workers', [
                'status' => $this->mapToRegistryStatus($status),
                'last_heartbeat' => now(),
            ], ['worker_id' => $wolfId]);

            $this->sanctumDb->update('wolf_sessions', [
                'status' => $status,
                'current_task_json' => $taskContext,
                'heartbeats_missed' => 0,
            ], ['wolf_id' => $wolfId]);

            $this->registryDb->commit();
            $this->sanctumDb->commit();
        } catch (\Throwable $e) {
            $this->registryDb->rollBack();
            $this->sanctumDb->rollBack();
            throw $e;
        }
    }
}
```

Heartbeat monitoring (missed-heartbeat detection, marking a Wolf `offline`/`terminated`) runs against `specialist_workers` only, then propagates to the Sanctum via the same dual-write path — never the reverse.

### 3.6 Director Dispatch — From Directive to Task Queue

`agent_director.directives` is Otec's private planning ledger — a record of what was decided and why. It is **not** the dispatch mechanism, and Wolves never read it; `agent_director` is hard-isolated exactly like every other Sanctum (§1.1), so a directive sitting only in that table is structurally invisible to any Wolf regardless of intent. `task_queue.directive_id` exists specifically to bridge this: every directive that requires Wolf execution must, in the same request, produce one or more `agent_registry.task_queue` rows carrying that `directive_id`.

```php
class DirectorController {
    public function issueDirective(array $body): JsonResponse {
        $directiveId = Uuid::v4();

        // 1. Write Otec's own planning record — private, never read by Wolves.
        $this->directorDb->insert('directives', [
            'directive_id' => $directiveId,
            'plan_id' => $body['plan_id'] ?? null,
            'target_agent_slug' => $body['target_agent_slug'],
            'action' => $body['action'],
            'payload_json' => json_encode($body['payload']),
            'priority' => $body['priority'] ?? 'normal',
            'status' => 'dispatched',
        ]);

        // 2. Dispatch — the ONLY path by which this becomes visible to a Wolf.
        foreach ($body['task_shards'] as $shard) {
            $this->registryDb->insert('task_queue', [
                'task_id' => Uuid::v4(),
                'directive_id' => $directiveId,
                'source_agent_slug' => 'director',
                'target_agent_slug' => $body['target_agent_slug'],
                'action' => $shard['action'],
                'payload_json' => json_encode($shard['payload']),
                'priority' => $body['priority'] ?? 'normal',
                'status' => 'queued',
            ]);
        }

        return new JsonResponse(['directive_id' => $directiveId, 'shards_queued' => count($body['task_shards'])]);
    }
}
```

If a directive genuinely requires no Wolf action (a pure strategic note, a memo to self), `task_shards` is simply empty and no `task_queue` row is created — that is the only case in which a directive legitimately produces no dispatch.

---

## 4. THE INGESTION PIPELINE — "THE SILENT LIBRARIAN"

*(Implements Briefing §7 — "The Silent Librarian". This is the ONLY writer to `quiddity_commons`. Wolves never write here — see §7.1.)*

### 4.1 Chunking Strategy

- **Primary:** semantic paragraph detection (heading-aware).
- **Fallback:** fixed token window (512 tokens, 64 overlap).
- **Metadata per chunk:** `heading_path`, `section_title`, `char_start`, `char_end`.

### 4.2 Embedding Model

- **Default:** `BAAI/bge-m3` (1024 dims, multilingual).
- **Alternative (local):** `nomic-ai/nomic-embed-text-v1.5` (768 dims, 8k context).
- **Batch size:** 32 texts per forward pass.
- **Normalisation:** L2-normalise before insert (cosine = dot product).

### 4.3 Worker Implementation (PHP CLI)

This worker pool is Lead-agnostic infrastructure — it has no `agent_slug` scoping and is never invoked through a Wolf. It reads the filesystem drop folder and writes only to `quiddity_commons`.

```php
class IngestionWorker {
    public function processBatch(array $files, int $concurrency = 3): JobResult {
        $chunks = $this->chunker->chunkFiles($files);
        $batches = array_chunk($chunks, ceil(count($chunks) / $concurrency));
        $futures = [];
        foreach ($batches as $i => $batch) {
            $futures[] = $this->pool->submit(
                fn() => $this->embedAndStore($batch, "worker_$i")
            );
        }
        return $this->aggregate($futures);
    }

    private function embedAndStore(array $chunks, string $workerId): WorkerResult {
        $embeddings = $this->embeddingClient->embed(
            array_column($chunks, 'text')
        );
        $this->db->bulkInsert(
            'quiddity_vector_references',
            $this->prepareRows($chunks, $embeddings)
        );
        $this->db->update('quiddity_files', [
            'indexed_at' => now(),
            'indexing_status' => 'indexed'
        ], ['id' => $fileId]);
        return new WorkerResult($workerId, count($chunks));
    }
}
```

### 4.4 Idempotency and Retry

- **Idempotency key:** `content_hash` (SHA-256) on `quiddity_files`.
- **Retry policy:** exponential backoff (1s, 2s, 4s, 8s, max 5 attempts).
- **Dead letter:** failed chunks logged to `ingestion_dead_letter` with error trace.

### 4.5 The Root-as-Inbox Convention and Three Trigger Paths

*(Implements Briefing §7 — the Silent Librarian does not sit idle waiting to be asked.)*

**The foundational rule:** any `.md` file sitting in the **root** of `/foreverbox_data/Quiddity_Lore_Sea/` is unprocessed and unorganised. Processed files live inside numbered subfolders (`01_TheForeverbox_Mythos/`, `02_ReInvigor_Texts/`, etc.). The root is the inbox. Subfolders are the shelves. This distinction is what all three trigger paths below act upon.

**Existing subfolder catalogue** (maintained in `config/quiddity_folders.yaml`, replicated here for builder reference):

| Folder | Purpose |
|--------|---------|
| `01_TheForeverbox_Mythos` | Core cosmology, Quantum Lattice theory, Iterations lore |
| `02_ReInvigor_Texts` | ReInvigor technical architecture, client specs, project docs |
| `03_TheInitiative_Audio` | Music production, stem maps, lyric sheets, video scripts |
| `04_FromTheNoise_Archives` | Published articles, editorial guidelines, research dossiers |
| `05_Agent_Profiles` | SOUL.md, USER.md, agent biographies, profile sheets |
| `06_QuiddityLtd_Dev_Specs` | API contracts, database schemas, infrastructure docs |

This catalogue is the source of truth for the content-based router (§4.6). Adding a new subfolder means adding an entry here and rebuilding the folder embeddings — the router does not auto-discover.

---

**TRIGGER PATH A — Agent-Initiated (explicit "process this file")**

The Lead tells the agent "I just dropped `xfile.md` in the Sea, process it." The agent calls the existing `POST /v1/commons/files/sync` endpoint with an optional `paths` filter:

```
POST /v1/commons/files/sync
Authorization: Bearer <api_key>
X-Agent-ID: <agent_slug>
Content-Type: application/json

{
  "paths": ["xfile.md"],
  "organise": true
}
```

`paths` limits the scan to specific files in the root. Omitting `paths` scans the entire root (used by Paths B and C). `organise: true` triggers post-vectorisation routing to the correct subfolder (§4.6). The endpoint returns `{ "job_id": "<uuid>", "files_scanned": 1, "files_indexed": 1, "files_organised": 1 }`.

This endpoint was previously marked "Ingestion Pipeline only" in §3.2 — that restriction is **relaxed for Path A only when `paths` is non-empty**. An agent-initiated scan with an empty `paths` array is rejected (403) — full-root scans remain the domain of Paths B and C. The existing `POST /v1/commons/ingest/batch` endpoint remains Ingestion Pipeline-only regardless.

---

**TRIGGER PATH B — Scheduled Cron (every 4 hours)**

A Hermes cron job calls `POST /v1/commons/files/sync` with no `paths` filter, scoped to the root only:

```bash
hermes cron create "0 */4 * * *" \
  --prompt "Call POST /v1/commons/files/sync with {\"organise\": true}. Report how many files were indexed and where they were moved." \
  --skills foreverbox-operations \
  --name "quiddity-sync"
```

The PHP API receives the request, scans `Quiddity_Lore_Sea/*.md` (root only — never recurses into subfolders), checks `quiddity_files.content_hash` to skip unchanged files, processes new/changed files, and routes them to subfolders (§4.6). The cron job's session is marked `skip_memory=True` (infrastructure, not user context).

---

**TRIGGER PATH C — Agent Startup**

When a Lead Hermes profile starts and the `ForeverBoxMemoryProvider.initialize()` method runs, it fires a non-blocking background request to `POST /v1/commons/files/sync`:

```python
def initialize(self, session_id: str, **kwargs) -> None:
    # ... existing config loading ...
    self._trigger_startup_sync()   # non-blocking

def _trigger_startup_sync(self):
    def _sync():
        try:
            requests.post(
                f"{self.api_url}/commons/files/sync",
                json={"organise": True},
                headers=self._headers(),
                timeout=30
            )
        except requests.RequestException:
            pass  # fail silent — indexing catches up on next cron tick
    threading.Thread(target=_sync, daemon=True).start()
```

This is deliberately fire-and-forget with no retry and no error propagation to the user — it is a convenience trigger, not a reliability guarantee. Path B (cron) is the reliability guarantee.

---

### 4.6 Content-Based Folder Router

After a file is chunked, embedded, and stored in `quiddity_vector_references`, the Ingestion Pipeline must decide which subfolder to move it to. The decision is made by comparing the file's **mean embedding vector** against pre-computed centroid embeddings for each subfolder.

**Centroid generation** (run once, re-run when `quiddity_folders.yaml` changes):

```python
# generate_folder_centroids.py — run once, stored in quiddity_folder_centroids
for folder in FOLDERS:
    chunks = db.query("""
        SELECT embedding FROM quiddity_vector_references
        WHERE file_id IN (
            SELECT id FROM quiddity_files
            WHERE relative_path LIKE :folder || '/%'
        )
    """, folder=folder)
    centroid = np.mean([np.frombuffer(c[0]) for c in chunks], axis=0)
    db.insert('quiddity_folder_centroids', {
        'folder_name': folder,
        'centroid': centroid.tobytes(),
        'sample_count': len(chunks)
    })
```

**Classification at ingest time:**

```php
class FolderRouter {
    public function classify(string $contentText, array $chunkEmbeddings): string {
        // Mean-pool all chunk embeddings into one document vector
        $docVector = $this->meanPool($chunkEmbeddings);

        // Cosine similarity against each folder centroid
        $bestFolder = '02_ReInvigor_Texts';  // default fallback
        $bestScore = -1.0;
        foreach ($this->getCentroids() as $folder => $centroid) {
            $score = $this->cosineSimilarity($docVector, $centroid);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestFolder = $folder;
            }
        }

        // Minimum confidence threshold — if no folder scores above 0.3,
        // route to a manual-review holding area instead
        if ($bestScore < 0.3) {
            return '_review/';
        }

        return $bestFolder . '/';
    }
}
```

**Post-route actions (inside `IngestionWorker::embedAndStore`, after successful DB write):**

1. Move the file: `rename("Quiddity_Lore_Sea/xfile.md", "Quiddity_Lore_Sea/{folder}/xfile.md")`
2. Update `quiddity_files.relative_path` to reflect the new location.
3. Log the routing decision: `logger->info("file_routed", ["file" => $relPath, "folder" => $folder, "confidence" => $bestScore])`.

Files routed to `_review/` stay in a `Quiddity_Lore_Sea/_review/` holding directory and are not moved to a numbered subfolder until a Lead manually reclassifies them. The cron job reports `_review/` backlog in its output.

### 4.7 Endpoint Updates (reflected in §3.2)

| Endpoint | Change |
|----------|--------|
| `POST /v1/commons/files/sync` | Now accepts optional `paths` array (agent-initiated, Path A) and `organise` boolean. Full-root scan with empty `paths` restricted to cron/startup (Paths B/C). |
| `GET /v1/commons/folders` | **New.** Returns the folder catalogue from `quiddity_folders.yaml` plus centroid metadata (sample count, last rebuilt). |

### 4.8 Database Additions

```sql
-- Add to quiddity_commons schema (§2.1)
CREATE TABLE quiddity_folder_centroids (
    folder_name VARCHAR(128) PRIMARY KEY,
    centroid VECTOR(1024) NOT NULL,
    sample_count INT UNSIGNED NOT NULL,
    rebuilt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_centroid_hnsw (centroid)
        INDEX_OPTIONS '{"type": "hnsw", "M": 8, "efConstruction": 100, "distance_metric": "cosine"}'
) ENGINE=InnoDB;
```

---

## 5. THE COGNITIVE ROUTER — "THE THREE LAYERS OF THOUGHT"

*(Implements Briefing §9. Layer naming below matches the Briefing exactly: Layer 1 Intuitive Reflex, Layer 2 Analytical Engine, Layer 3 Deep Architect.)*

### 5.1 Installation Point

In `run_agent.py`, inside `AIAgent.run_conversation()`, **before** `client.chat.completions.create()`. The privacy gate (§5.4) must complete and return a routing decision at this point, before any client handle for a non-local model is instantiated — not as an exception handler wrapping a failed local attempt.

```python
class AIAgent:
    def __init__(self, ..., cognitive_router: CognitiveRouter = None):
        self.router = cognitive_router or CognitiveRouter.from_config()

    def run_conversation(self, user_message, ...):
        while iteration < max_iterations:
            # STRUCTURAL PRIVACY GATE — must run first, pure, no network calls.
            has_private_data = scan_for_private_data(messages)

            request_ctx = AgentRequestContext(
                messages=messages,
                enabled_toolsets=self.enabled_toolsets,
                context_tokens=self._estimate_tokens(messages),
                task_type=self._infer_task_type(messages),
                is_retry=self._is_retry_loop(),
                delegation_depth=current_delegation_depth,
                user_explicit_deep=has_deep_think_flag(user_message),
                has_private_data=has_private_data
            )

            if has_private_data and not self.router.local_model_available():
                # HARD STOP. Do not fall back to cloud. Do not proceed to
                # client.create() for any remote profile under any condition.
                raise HardStop(
                    "Local hardware unavailable; privacy-gated request cannot proceed."
                )

            model_profile = self.router.select_model(request_ctx)
            response = self.client.chat.completions.create(
                model=model_profile.model,
                messages=messages,
                tools=self._get_tool_schemas(model_profile),
                **model_profile.extra_params
            )
```

### 5.2 Router Class Specification

```python
from dataclasses import dataclass
from enum import Enum
from typing import Dict, Optional
import yaml, re, logging, time, os, requests

REGISTRY_API_URL = os.environ.get("FOREVERBOX_API_URL", "http://localhost:8080/v1")

class ModelTier(Enum):
    SYSTEM1_LOCAL = "system1_local"    # Layer 1: The Intuitive Reflex
    SYSTEM2_LIGHT = "system2_light"    # Layer 2: The Analytical Engine
    SYSTEM2_HEAVY = "system2_heavy"    # Layer 3: The Deep Architect

@dataclass
class ModelProfile:
    tier: ModelTier
    provider: str
    model: str
    base_url: Optional[str] = None
    max_tokens: int = 8192
    temperature: float = 0.3
    tags: list = None
    extra_params: dict = None

@dataclass
class AgentRequestContext:
    messages: list
    enabled_toolsets: list
    context_tokens: int
    task_type: str
    is_retry: bool = False
    delegation_depth: int = 0
    user_explicit_deep: bool = False
    has_private_data: bool = False

class CognitiveRouter:
    WEIGHTS = {
        "tool_depth_gt_2": 0.30,
        "planning_task_type": 0.40,
        "context_gt_40k": 0.20,
        "retry_loop": 0.25,
        "explicit_deep": 1.00,
        "delegation_depth_gt_1": 0.35,
        "private_data_present": -0.50,
    }
    THRESHOLDS = {
        ModelTier.SYSTEM1_LOCAL: 0.0,
        ModelTier.SYSTEM2_LIGHT: 0.40,
        ModelTier.SYSTEM2_HEAVY: 0.70,
    }

    def __init__(self, config_path: str = "config/router.yaml"):
        with open(config_path) as f:
            cfg = yaml.safe_load(f)
        self.profiles = {
            ModelTier(k): ModelProfile(tier=ModelTier(k), **v)
            for k, v in cfg["model_profiles"].items()
        }
        self.health_cache = {}

    def estimate_load(self, ctx: AgentRequestContext) -> float:
        score = 0.0
        if self._estimate_tool_depth(ctx) > 2:
            score += self.WEIGHTS["tool_depth_gt_2"]
        if ctx.task_type in {"plan","architect","debug","refactor","synthesize","research"}:
            score += self.WEIGHTS["planning_task_type"]
        if ctx.context_tokens > 40000:
            score += self.WEIGHTS["context_gt_40k"]
        if ctx.is_retry:
            score += self.WEIGHTS["retry_loop"]
        if ctx.user_explicit_deep:
            score = 1.0
        if ctx.delegation_depth > 1:
            score += self.WEIGHTS["delegation_depth_gt_1"]
        if ctx.has_private_data:
            score += self.WEIGHTS["private_data_present"]
        return max(0.0, min(1.0, score))

    def select_model(self, ctx: AgentRequestContext) -> ModelProfile:
        # NOTE: by the time this is called, run_conversation() has already
        # hard-stopped on has_private_data + local unavailable (§5.1).
        # This method only ever runs when local IS available, or privacy
        # is not a concern, so the fallback branch below is a safety net,
        # not the primary enforcement mechanism.
        load = self.estimate_load(ctx)
        if ctx.has_private_data and load >= self.THRESHOLDS[ModelTier.SYSTEM2_LIGHT]:
            return self.profiles[ModelTier.SYSTEM1_LOCAL]
        for tier in [ModelTier.SYSTEM2_HEAVY, ModelTier.SYSTEM2_LIGHT, ModelTier.SYSTEM1_LOCAL]:
            if load >= self.THRESHOLDS[tier] and self._is_healthy(tier):
                # BUDGET GATE — the Master Briefing promises the Registry
                # monitors daily spend; this is where that promise is kept.
                # Cloud tiers only. Never gates SYSTEM1_LOCAL.
                if tier != ModelTier.SYSTEM1_LOCAL and not self._budget_available(tier):
                    continue  # exhausted — fall through to the next cheaper tier
                return self.profiles[tier]
        return self.profiles[ModelTier.SYSTEM1_LOCAL]

    def local_model_available(self) -> bool:
        return self._is_healthy(ModelTier.SYSTEM1_LOCAL)

    def _budget_available(self, tier: ModelTier) -> bool:
        # GET /v1/registry/budget?tier={tier} (§3.2). Cached 60s so this
        # never adds meaningful latency to a turn. Fails OPEN (assumes
        # budget available) on a request error or timeout — a budget-check
        # outage should degrade to "might overspend slightly," never to
        # "conversation cannot proceed." This is deliberately the opposite
        # failure direction from the privacy gate (§5.4), which fails
        # CLOSED, because overspend is a cost concern, not a safety one.
        cache_key = f"budget_{tier.value}"
        cached = self.health_cache.get(cache_key)
        if cached and (time.time() - cached["ts"]) < 60:
            return cached["available"]
        try:
            r = requests.get(
                f"{REGISTRY_API_URL}/registry/budget",
                params={"tier": tier.value},
                timeout=1.5
            )
            r.raise_for_status()
            available = r.json()["remaining"] > 0
        except requests.RequestException as e:
            logging.warning("budget check failed for %s, failing open: %s", tier, e)
            available = True
        self.health_cache[cache_key] = {"available": available, "ts": time.time()}
        return available

    def _is_healthy(self, tier: ModelTier) -> bool:
        # Cache health checks for 30s per tier.
        cache_key = f"health_{tier.value}"
        cached = self.health_cache.get(cache_key)
        if cached and (time.time() - cached["ts"]) < 30:
            return cached["healthy"]

        profile = self.profiles.get(tier)
        if not profile:
            return False

        healthy = False
        try:
            if tier == ModelTier.SYSTEM1_LOCAL:
                # Ollama: GET /api/tags — lightweight, no auth needed
                r = requests.get(
                    f"{profile.base_url}/api/tags",
                    timeout=2
                )
                healthy = r.status_code == 200
            else:
                # Cloud tiers: lightweight /models probe via provider endpoint.
                # Uses the same base_url + api_key as the main client; a 200
                # here means the provider is reachable and the key is valid.
                headers = {"Authorization": f"Bearer {os.environ.get(profile.provider.upper() + '_API_KEY', '')}"}
                r = requests.get(
                    f"{profile.base_url}/models",
                    headers=headers,
                    timeout=3
                )
                healthy = r.status_code == 200
        except requests.RequestException:
            healthy = False

        self.health_cache[cache_key] = {"healthy": healthy, "ts": time.time()}
        return healthy
```

### 5.3 Router Config (`config/router.yaml`)

```yaml
model_profiles:
  system1_local:
    provider: "ollama"
    model: "gemma4:31b"
    base_url: "http://localhost:11434"
    max_tokens: 8192
    temperature: 0.4
    tags: ["local", "private", "personality", "fast"]
  system2_light:
    provider: "openrouter"
    model: "qwen/qwen3-32b:free"
    max_tokens: 32768
    temperature: 0.2
    tags: ["reasoning", "coding", "cloud", "free_tier"]
  system2_heavy:
    provider: "openrouter"
    model: "nvidia/nemotron-3-ultra"
    max_tokens: 131072
    temperature: 0.1
    tags: ["architect", "heavy_reasoning", "planning", "cloud"]

router:
  enabled: true
  privacy_scan_enabled: true
  private_patterns:
    - "api[_-]?key"
    - "secret"
    - "password"
    - "token"
    - "/home/"
    - "/Users/"
    - "C:\\\\Users\\\\"
  # NOTE: these values are the SEED/default for `token_budget_ledger.daily_limit`
  # (§2.3), written once per tier per day if no row exists yet. They are not
  # themselves the enforcement point — actual remaining budget is tracked
  # server-side in MariaDB and checked at runtime via GET /v1/registry/budget
  # (§3.2, §5.2 `_budget_available`). This file cannot enforce a budget on its
  # own since the Router has no visibility into cumulative spend without
  # asking the Registry.
  daily_budget_tokens:
    system2_heavy: 500000
    system2_light: 2000000
```

### 5.4 Privacy Scanner (Pre-Route Hook — The Privacy Gate)

Must be pure and synchronous: no network calls, no model calls, runs before every other routing decision.

```python
def scan_for_private_data(messages: list, tool_calls: list = None) -> bool:
    """Returns True if private/sensitive data detected. Zero network calls."""
    text = " ".join(
        [m.get("content", "") for m in messages if m.get("content")]
    )
    if tool_calls:
        text += " " + str(tool_calls)
    for pattern in ROUTER_CONFIG["private_patterns"]:
        if re.search(pattern, text, re.IGNORECASE):
            return True
    if tool_calls:
        for call in tool_calls:
            args = call.get("arguments", {})
            for val in args.values():
                if isinstance(val, str) and (
                    val.startswith("/home")
                    or val.startswith("/Users")
                    or "C:\\Users" in val
                ):
                    return True
    return False
```

**Acceptance test (mandatory, see §10):** a prompt containing a fabricated API-key-shaped string must produce zero outbound HTTP requests to any non-local endpoint. Assert this at the network layer in the test harness, not by reading a log line claiming the router "would have" stayed local.

---

## 6. THE HERMES MEMORY PROVIDER PLUGIN

*(Implements Briefing §10 — "Hermes: The Memory Messenger". This section did not exist in v1.)*

### 6.1 Why This Section Exists

Hermes does not accept an arbitrary HTTP client as a memory backend. It requires a plugin that subclasses `MemoryProvider` from `agent/memory_provider.py` and implements a specific set of lifecycle methods, called at specific points Hermes controls. Build exactly this contract — nothing looser will activate.

### 6.2 Directory Structure

```
plugins/memory/foreverbox/
├── __init__.py      # ForeverBoxMemoryProvider + register()
├── plugin.yaml       # name, version, hooks list
├── cli.py            # optional: hermes foreverbox status/config
└── README.md
```

### 6.3 One Plugin, Four Independently-Configured Profiles

Because of the Worzel Gummidge principle (§1.2), there is exactly one plugin codebase, installed identically into four Hermes profiles (`zeon7`, `gemma`, `leon`, `otec`), each with its own `$HERMES_HOME` and therefore its own `foreverbox.json`. The `agent_slug` is chosen once, at `hermes memory setup` time, for that profile, and never changes for the life of that profile. There is no environment-variable switch, no CLI flag, no runtime mode change — do not build one.

### 6.4 Class Specification

```python
from agent.memory_provider import MemoryProvider
from hermes_constants import get_hermes_home
from pathlib import Path
import os, json, threading, logging, requests, uuid, time

logger = logging.getLogger("foreverbox_memory")

class ForeverBoxMemoryProvider(MemoryProvider):

    @property
    def name(self) -> str:
        return "foreverbox"

    def is_available(self) -> bool:
        # NO network calls — Hermes calls this before activation.
        cfg_path = get_hermes_home() / "foreverbox.json"
        return cfg_path.exists() and bool(os.environ.get("FOREVERBOX_API_KEY"))

    def initialize(self, session_id: str, **kwargs) -> None:
        hermes_home = kwargs["hermes_home"]
        cfg = json.loads((Path(hermes_home) / "foreverbox.json").read_text())
        self.api_url = cfg["api_url"]
        self.agent_slug = cfg["agent_slug"]      # fixed for this profile's lifetime
        self.api_key = os.environ["FOREVERBOX_API_KEY"]
        self.session_id = session_id
        self._sync_thread = None
        self._trigger_startup_sync()   # Path C — non-blocking (§4.5)

    def get_config_schema(self):
        return [
            {"key": "api_key", "secret": True, "required": True,
             "env_var": "FOREVERBOX_API_KEY"},
            {"key": "api_url", "required": True,
             "default": "http://localhost:8080/v1"},
            {"key": "agent_slug", "required": True,
             "choices": ["curator", "coach", "producer", "director"]},
        ]

    def save_config(self, values: dict, hermes_home: str) -> None:
        (Path(hermes_home) / "foreverbox.json").write_text(json.dumps(values, indent=2))

    def get_tool_schemas(self):
        # 10 tools mapped 1:1 to the endpoint dictionary in §3.2.
        # Design decision (§6.7): tools are exposed for agent-initiated recall
        # AND auto-inject runs via prefetch(). Both paths are active.
        return [
            {
                "name": "memory_search",
                "description": (
                    "Hybrid full-text + vector semantic search over your "
                    "Sanctum memory_lore. Returns memories ranked by relevance. "
                    "Use when you need to recall facts, context, or past "
                    "decisions that may not be in the active conversation."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "query": {
                            "type": "string",
                            "description": "Natural-language search query. Vectors match meaning, not just keywords."
                        },
                        "namespace": {
                            "type": "string",
                            "description": "Optional: filter to a specific namespace (e.g. 'ftn_daily_briefing', 'project_specs')."
                        },
                        "limit": {
                            "type": "integer",
                            "description": "Max results (default 10, max 50).",
                            "default": 10
                        }
                    },
                    "required": ["query"]
                }
            },
            {
                "name": "memory_get",
                "description": (
                    "Retrieve a single memory entry by exact namespace and key. "
                    "Use when you know the precise key you stored earlier."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "namespace": {
                            "type": "string",
                            "description": "The memory namespace (e.g. 'general', 'project_specs')."
                        },
                        "key_name": {
                            "type": "string",
                            "description": "The unique key within that namespace."
                        }
                    },
                    "required": ["namespace", "key_name"]
                }
            },
            {
                "name": "memory_list",
                "description": (
                    "List memory entries, optionally filtered by namespace, "
                    "tags, or importance threshold. Use to browse what's stored."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "namespace": {
                            "type": "string",
                            "description": "Optional: filter to a namespace."
                        },
                        "tags": {
                            "type": "array",
                            "items": {"type": "string"},
                            "description": "Optional: filter by one or more tags."
                        },
                        "importance_min": {
                            "type": "integer",
                            "description": "Optional: minimum importance (0-100)."
                        },
                        "limit": {
                            "type": "integer",
                            "description": "Max results (default 20).",
                            "default": 20
                        }
                    },
                    "required": []
                }
            },
            {
                "name": "memory_upsert",
                "description": (
                    "Create or update a memory entry in your Sanctum. If the "
                    "namespace+key_name combination already exists, it is "
                    "updated; otherwise a new entry is created. Use to persist "
                    "important facts, decisions, or context for future sessions."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "namespace": {
                            "type": "string",
                            "description": "Namespace to store under (e.g. 'general', 'user_prefs')."
                        },
                        "key_name": {
                            "type": "string",
                            "description": "Unique key within that namespace (e.g. 'meeting_notes_20260714')."
                        },
                        "content": {
                            "type": "string",
                            "description": "The text content to store."
                        },
                        "importance": {
                            "type": "integer",
                            "description": "Importance 0-100 (default 50). Higher = surfaced more often.",
                            "default": 50
                        },
                        "tags": {
                            "type": "array",
                            "items": {"type": "string"},
                            "description": "Optional tags for filtering later."
                        },
                        "source_type": {
                            "type": "string",
                            "enum": ["user_directive", "session_extraction", "document_ingestion", "wolf_synthesis"],
                            "description": "Provenance of this memory (default: 'user_directive').",
                            "default": "user_directive"
                        }
                    },
                    "required": ["namespace", "key_name", "content"]
                }
            },
            {
                "name": "memory_delete",
                "description": (
                    "Delete a single memory entry by namespace and key. "
                    "Irreversible — confirm before calling."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "namespace": {
                            "type": "string",
                            "description": "The memory namespace."
                        },
                        "key_name": {
                            "type": "string",
                            "description": "The unique key to delete."
                        }
                    },
                    "required": ["namespace", "key_name"]
                }
            },
            {
                "name": "commons_search",
                "description": (
                    "Semantic vector search over the Quiddity Commons — the "
                    "shared knowledge repository. Searches indexed documents, "
                    "reference materials, and ingested archives. Read-only; "
                    "you cannot write to the Commons."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "query": {
                            "type": "string",
                            "description": "Natural-language search query for semantic matching."
                        },
                        "limit": {
                            "type": "integer",
                            "description": "Max results (default 10).",
                            "default": 10
                        }
                    },
                    "required": ["query"]
                }
            },
            {
                "name": "ingest_file",
                "description": (
                    "Trigger ingestion and vectorisation of a file dropped into "
                    "the Quiddity_Lore_Sea root. The file will be chunked, "
                    "embedded, stored in the Commons, and automatically routed "
                    "to the most relevant subfolder based on its content. "
                    "Only works on files in the root — files already in "
                    "subfolders are considered processed."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "filename": {
                            "type": "string",
                            "description": "The filename in the Quiddity_Lore_Sea root (e.g. 'my_research.md')."
                        },
                        "organise": {
                            "type": "boolean",
                            "description": "Whether to auto-route to a subfolder after indexing (default: true).",
                            "default": True
                        }
                    },
                    "required": ["filename"]
                }
            },
            {
                "name": "wolf_status",
                "description": (
                    "List all Wolves assigned to you, with their current "
                    "status (idle, working, blocked, error). Use to check "
                    "whether dispatched tasks have completed."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {},
                    "required": []
                }
            },
            {
                "name": "wolf_dispatch",
                "description": (
                    "Dispatch a task to one of your Wolves for parallel "
                    "background processing. The Wolf will claim the task, "
                    "execute it, and write results to your Sanctum. Use for "
                    "heavy research, indexing, or analysis that should not "
                    "block the conversation."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "wolf_id": {
                            "type": "string",
                            "description": "Which Wolf to dispatch to (check wolf_status for available IDs)."
                        },
                        "action": {
                            "type": "string",
                            "description": "What to do (e.g. 'research', 'audit', 'synthesise', 'index')."
                        },
                        "payload": {
                            "type": "object",
                            "description": "Task-specific parameters as key-value pairs."
                        },
                        "priority": {
                            "type": "string",
                            "enum": ["low", "normal", "high", "critical"],
                            "description": "Task priority (default: 'normal').",
                            "default": "normal"
                        }
                    },
                    "required": ["wolf_id", "action", "payload"]
                }
            },
            {
                "name": "wolf_task_status",
                "description": (
                    "Poll the status of a specific Wolf task. Returns the "
                    "task state and any partial results if still in progress."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "wolf_id": {
                            "type": "string",
                            "description": "The Wolf that owns the task."
                        },
                        "task_id": {
                            "type": "string",
                            "description": "The task ID returned by wolf_dispatch."
                        }
                    },
                    "required": ["wolf_id", "task_id"]
                }
            }
        ]

    def handle_tool_call(self, name, args, **kwargs):
        # Route to the matching PHP endpoint. Every request carries
        # Authorization, X-Agent-ID (self.agent_slug), X-Request-ID (uuid4).
        import uuid
        headers = {**self._headers(), "X-Request-ID": str(uuid.uuid4())}

        route_map = {
            "memory_search":  ("POST", "/sanctum/memory/search"),
            "memory_get":     ("GET",  lambda a: f"/sanctum/memory/{a['namespace']}/{a['key_name']}"),
            "memory_list":    ("GET",  "/sanctum/memory"),
            "memory_upsert":  ("PUT",  lambda a: f"/sanctum/memory/{a['namespace']}/{a['key_name']}"),
            "memory_delete":  ("DELETE", lambda a: f"/sanctum/memory/{a['namespace']}/{a['key_name']}"),
            "commons_search": ("GET",  "/commons/search"),
            "ingest_file":    ("POST", "/commons/files/sync"),
            "wolf_status":    ("GET",  "/sanctum/wolves/status"),
            "wolf_dispatch":  ("POST", lambda a: f"/sanctum/wolves/{a['wolf_id']}/task"),
            "wolf_task_status": ("GET", lambda a: f"/sanctum/wolves/{a['wolf_id']}/task/{a['task_id']}"),
        }

        if name not in route_map:
            return json.dumps({"error": f"Unknown tool: {name}"})

        method, path_or_fn = route_map[name]
        url_path = path_or_fn(args) if callable(path_or_fn) else path_or_fn
        url = f"{self.api_url}{url_path}"

        try:
            if method == "GET":
                r = requests.get(url, params=args, headers=headers, timeout=10)
            elif method == "DELETE":
                r = requests.delete(url, headers=headers, timeout=10)
            else:
                r = requests.request(method, url, json=args, headers=headers, timeout=10)
            r.raise_for_status()
            return json.dumps(r.json())
        except requests.RequestException as e:
            logger.warning("Tool %s failed: %s", name, e)
            return json.dumps({"error": str(e)})

    def system_prompt_block(self) -> str:
        return (
            "You are connected to the Foreverbox Council Library via the "
            "ForeverBox memory provider. Your Sanctum holds durable memory, "
            "conversation history, and Wolf worker state. Use memory_search "
            "to recall facts, commons_search to query shared knowledge, and "
            "wolf_dispatch to parallelise heavy work, and ingest_file to "
            "process new files dropped into the Quiddity Lore Sea. The Sudo Protocol gates "
            "privileged actions — request confirmation before destructive ops."
        )

    def prefetch(self, query: str, *, session_id: str = "") -> str:
        # Hard timeout, fail SOFT — never raise, never block the turn.
        # Returns a FORMATTED STRING for prompt injection, not raw JSON.
        try:
            r = requests.post(f"{self.api_url}/sanctum/memory/search",
                               json={"query": query}, timeout=2,
                               headers=self._headers())
            results = r.json().get("results", [])
            if not results:
                return ""
            lines = ["[Foreverbox Sanctum — relevant context:]"]
            for item in results[:5]:
                ns = item.get("namespace", "")
                key = item.get("key_name", "")
                text = (item.get("content_text") or "")[:400]
                if text:
                    lines.append(f"  [{ns}/{key}] {text}")
            return "\n".join(lines)
        except requests.RequestException as e:
            logger.warning("prefetch failed: %s", e)
            return ""

    def queue_prefetch(self, query: str, *, session_id: str = "") -> None:
        # Background prefetch for the NEXT turn. Runs the same search as
        # prefetch() but stores the result so prefetch() returns instantly.
        def _fetch():
            try:
                r = requests.post(f"{self.api_url}/sanctum/memory/search",
                                   json={"query": query}, timeout=3,
                                   headers=self._headers())
                self._prefetch_cache = r.json().get("results", [])
            except requests.RequestException:
                self._prefetch_cache = []
        self._prefetch_thread = threading.Thread(target=_fetch, daemon=True)
        self._prefetch_thread.start()

    def sync_turn(self, user_content, assistant_content, *,
                  session_id: str = "", messages: list = None):
        # MANDATORY non-blocking contract — see §6.5.
        def _sync():
            try:
                requests.post(
                    f"{self.api_url}/sanctum/conversations/{self.session_id}/messages",
                    json={"user": user_content, "assistant": assistant_content},
                    headers=self._headers(), timeout=10)
            except requests.RequestException as e:
                logger.warning("sync_turn failed: %s", e)

        if self._sync_thread and self._sync_thread.is_alive():
            self._sync_thread.join(timeout=5.0)
        self._sync_thread = threading.Thread(target=_sync, daemon=True)
        self._sync_thread.start()

    def on_pre_compress(self, messages):
        # Persist a rolling snapshot to memory_lore (source_type=
        # 'session_extraction') before Hermes discards context on compression.
        # Extracts key facts and decisions from messages about to be compressed
        # so they survive context-window eviction.
        import uuid
        snapshot = {
            "compressed_at": time.time(),
            "message_count": len(messages),
            "preview": str(messages[-3:])[:2000]  # last 3 messages, truncated
        }
        try:
            requests.put(
                f"{self.api_url}/sanctum/memory/compression_snapshots/{uuid.uuid4().hex[:12]}",
                json={
                    "namespace": "compression_snapshots",
                    "key_name": f"snapshot_{int(time.time())}",
                    "content": json.dumps(snapshot),
                    "source_type": "session_extraction",
                    "importance": 30
                },
                headers=self._headers(),
                timeout=5
            )
        except requests.RequestException as e:
            logger.warning("on_pre_compress failed: %s", e)
        return ""  # no contribution to compression summary prompt by default

    def on_memory_write(self, action, target, content, metadata=None):
        # THE MIRROR HOOK — see §6.6. This is the only writer to `soul` and
        # `user_context` after initial migration. It fires on every native
        # Hermes write to SOUL.md / USER.md / MEMORY.md.
        if target == "user":
            endpoint = "/sanctum/user-context"
            payload = {"profile_yaml": content, "agent_slug": self.agent_slug}
        elif target == "memory":
            endpoint = f"/sanctum/memory/hermes_builtin/{action}"
            payload = {"content": content, "source_type": "user_directive",
                       "importance": 60, "agent_slug": self.agent_slug}
        else:
            return  # unknown target, skip

        try:
            requests.put(f"{self.api_url}{endpoint}",
                         json=payload, headers=self._headers(), timeout=5)
        except requests.RequestException as e:
            logger.warning("on_memory_write mirror failed: %s", e)

    def backup_paths(self) -> list:
        # The ForeverBox provider keeps state in the PHP API's MariaDB, not
        # in local files beyond foreeverbox.json (which is inside HERMES_HOME
        # and therefore auto-captured by `hermes backup`). Return empty.
        return []

    def on_session_end(self, messages):
        # Extract a session summary to memory_lore when the session closes.
        # Fire-and-forget — errors are logged, never surfaced to the user.
        def _extract():
            try:
                summary = {
                    "session_id": self.session_id,
                    "ended_at": time.time(),
                    "message_count": len(messages),
                    "final_exchange": str(messages[-2:])[:1000]
                }
                requests.put(
                    f"{self.api_url}/sanctum/memory/session_summaries/{self.session_id}",
                    json={
                        "namespace": "session_summaries",
                        "key_name": self.session_id,
                        "content": json.dumps(summary),
                        "source_type": "session_extraction",
                        "importance": 40
                    },
                    headers=self._headers(),
                    timeout=5
                )
            except requests.RequestException as e:
                logger.warning("on_session_end failed: %s", e)
        threading.Thread(target=_extract, daemon=True).start()

    def on_session_switch(self, new_session_id: str, *,
                          parent_session_id: str = "",
                          reset: bool = False,
                          rewound: bool = False, **kwargs):
        # Update local session tracking when Hermes switches session_id
        # mid-process (e.g. /resume, /branch, /reset).
        self.session_id = new_session_id

    def on_delegation(self, task: str, result: str, *,
                      child_session_id: str = "", **kwargs):
        # Log delegated work to the Sanctum for traceability.
        try:
            requests.put(
                f"{self.api_url}/sanctum/memory/delegation_log/{child_session_id}",
                json={
                    "namespace": "delegation_log",
                    "key_name": child_session_id or uuid.uuid4().hex[:12],
                    "content": json.dumps({"task": task[:500], "result": result[:500]}),
                    "source_type": "session_extraction",
                    "importance": 20
                },
                headers=self._headers(),
                timeout=5
            )
        except requests.RequestException as e:
            logger.warning("on_delegation failed: %s", e)

    def shutdown(self):
        if self._sync_thread and self._sync_thread.is_alive():
            self._sync_thread.join(timeout=5.0)

    def _headers(self):
        return {
            "Authorization": f"Bearer {self.api_key}",
            "X-Agent-ID": self.agent_slug,
            "Content-Type": "application/json",
        }

def register(ctx) -> None:
    ctx.register_memory_provider(ForeverBoxMemoryProvider())
```

### 6.5 Threading Contract (Non-Negotiable)

`sync_turn()` must never block the conversation loop. Use the join-with-timeout-then-spawn pattern shown in §6.4. Never call `requests.post()` synchronously inside `sync_turn()`.

### 6.6 SOUL.md / USER.md — Canonical Source Resolution

**Decision: the files remain canonical. The `soul` and `user_context` Sanctum tables are a mirror, not a replacement.** Hermes' built-in Personality and Persistent Memory systems read and write SOUL.md/USER.md/MEMORY.md directly regardless of which external memory provider is active — that path cannot be rerouted through a plugin.

**Data flow:**
1. `migrate_core_state.py` (§8.2) performs a one-time import of the existing files into `soul`/`user_context`. This seeds the mirror.
2. From then on, every native Hermes write to those files fires `on_memory_write(action, target, content)`. The plugin's implementation is the **only** subsequent writer to `soul`/`user_context` — it `PUT`s to `/v1/sanctum/soul` or `/v1/sanctum/user-context`, incrementing `version`.
3. The Sanctum tables exist for cross-tool inspection (a dashboard, or the Director reading a Lead's identity version without starting that Lead's own Hermes profile), audit trail, and backup — never as the runtime read path for Hermes' own personality system.

### 6.7 Design Decision — Tool-Initiated Recall AND Auto-Inject (RESOLVED)

Both paths are active. `prefetch()` auto-injects relevant Sanctum context before every turn. The 9 tools listed in `get_tool_schemas()` give the Lead explicit recall, storage, Commons search, and Wolf orchestration mid-conversation. This matches the dual approach used by Mem0, Hindsight, and Honcho — automatic context surfacing for ambient recall, tools for deliberate retrieval and mutation.

---

## 7. THE WOLVES — PARALLEL PROCESSING IN THE STATIC

*(Implements Briefing §11. Wolves are not conversational models, do not have Hermes profiles, and never speak to humans. They are asynchronous background workers coordinated entirely through the Task Queue in Wing 4.)*

### 7.1 Wolves Never Write to the Commons

Confirmed by the Briefing (§5 vs §8): Commons ingestion is the Ingestion Pipeline's job alone (§4), which is Lead-agnostic PHP infrastructure. Wolves write only into their own Lead's Sanctum — `memory_lore`, `wolf_working_memory`, `conversation_history` via the Lead. No Wolf ever calls `/v1/commons/ingest/batch` or writes a `quiddity_*` table.

### 7.2 Atomic Task Claiming

Design intent confirmed: Wolves are aware of what others are claiming, so no two Wolves work the same unit at once. This must be enforced as an atomic SQL operation — row-level locking alone does not prevent two Wolves from both reading the same `queued` row before either writes to it. That read-then-write gap is exactly where a double-claim happens, locking or no locking.

**Never do this (read, then separate write):**
```sql
-- WRONG — race window between SELECT and UPDATE
SELECT task_id FROM task_queue WHERE status='queued' LIMIT 1;
-- two Wolves can both get the same task_id here before either UPDATEs
UPDATE task_queue SET status='claimed' WHERE task_id = :task_id;
```

**Always do this instead (single atomic statement, check affected_rows):**
```sql
UPDATE task_queue
SET status = 'claimed', claimed_by_worker_id = :worker_id, claimed_at = NOW()
WHERE task_id = :task_id AND status = 'queued'
LIMIT 1;
```
If `affected_rows = 0`, another Wolf already claimed it — discard and poll again. Never treat a claim as successful without checking this.

**For selecting which queued task to attempt in the first place**, use `SELECT ... FOR UPDATE SKIP LOCKED` (MariaDB 10.6+), which lets concurrent Wolves each pick a different row without blocking on one another or colliding:
```sql
SELECT task_id FROM task_queue
WHERE target_agent_slug = :agent_slug AND status = 'queued'
ORDER BY priority DESC, created_at ASC
LIMIT 1 FOR UPDATE SKIP LOCKED;
```

This two-step pattern (`SKIP LOCKED` select, then conditional `UPDATE`) is mandatory for every Wolf poll loop and for `IngestionWorker`'s own batch-assignment logic (§4.3). Implement it once in `Service/TaskClaimer.php` (§3.3) and have every consumer call it — do not reimplement the pattern per controller.

### 7.3 `memory_lore` Key Namespacing for Concurrent Wolf Writes

`memory_lore` enforces `UNIQUE (agent_slug, namespace, key_name)` (§2.2). Even with claiming solved, two Wolves must never be *assigned* overlapping output keys, or the second write fails on a duplicate-key violation.

**Convention:**
- Each Wolf writes its own shard under a Wolf-scoped key: `key_name = "{task_id}:{wolf_id}"` (e.g. `signal_extractor_20260713:wolf_political`).
- `namespace` identifies the task category (e.g. `ftn_daily_briefing`).
- Once every Wolf sharing a `directive_id` reports `completed` in `task_queue`, the **Lead alone** — never a Wolf — performs a single consolidation write into the canonical entry: `namespace="ftn_daily_briefing", key_name="{task_id}:final"`.
- Only that canonical key is read by `prefetch()`/search for that task. Individual Wolf shards remain as an audit trail, pruned later via `expires_at`.
- No Wolf ever writes to a `:final` key. Single-writer by construction removes the possibility of a race at the synthesis step, the same way §7.2 removes it at the claiming step.

### 7.4 Visibility of Wolf Writes to the Active Hermes Session

A Wolf writing to `memory_lore` while the Lead's own Hermes process is mid-conversation does not require a separate push mechanism — two things already in this spec cover it, and they should be understood as working together rather than as separate open problems:

1. `prefetch(query)` (§6.4) runs before every model call and hits `/v1/sanctum/memory/search`, which reads `memory_lore` — so any Wolf synthesis already consolidated under a `:final` key (§7.3) surfaces automatically the next time it's semantically relevant, with no polling required.
2. The `wolf_status` tool listed under `get_tool_schemas()` (§6.4) gives the Lead a deterministic check — "is task X actually done yet" — for the specific case where the Lead dispatched a task itself and is waiting on it, rather than relying on fuzzy semantic recall to happen to surface it.

Use (1) for ambient recall of anything a Wolf has found; use (2) when the Lead needs a definite yes/no on a task it is actively blocked on.

### 7.5 Failure Handling

If a Wolf encounters a corrupted chunk or timeout, mark that specific `task_queue` row `dead_letter`, increment `retry_count`, and let the Registry's monitoring re-queue it up to `max_retries` before alerting. A single failed shard must never abort the other Wolves' work on the same `directive_id`.

---

## 8. MIGRATION STRATEGY (FLAT FILES TO MARIADB)

*(Implements Briefing §15 — "Migration: From Fragile Files to Durable Memory".)*

### 8.1 Phase 1: Schema Deployment

```bash
mysql -u root -p < schema/01_commons.sql
mysql -u root -p < schema/02_sanctum_template.sql
mysql -u root -p < schema/03_registry.sql
mysql -u root -p < schema/04_director.sql
```

### 8.2 Phase 2: Core State Migration (One-Time Mirror Seed)

This script seeds the `soul`/`user_context` mirror (§6.6). It does not make the database canonical — after this runs once, `on_memory_write` is the only subsequent writer.

```python
# migrate_core_state.py
import json, yaml, hashlib, os
from pathlib import Path
import mysql.connector

PROFILE_ROOT = Path("/hermes/profiles/zeon7")
DB_CFG = {
    "host": "localhost",
    "user": "foreverbox",
    "password": os.getenv("DB_PASS"),
    "database": "agent_curator"
}

def migrate_soul(conn):
    soul_md = (PROFILE_ROOT / "SOUL.md").read_text()
    if soul_md.startswith("---"):
        _, fm, body = soul_md.split("---", 2)
        identity = yaml.safe_load(fm) if fm else {}
        protocols = body.strip()
    else:
        identity = {}
        protocols = soul_md
    sql = """INSERT INTO soul (agent_slug, identity_yaml, protocols_yaml, updated_by)
             VALUES (%s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE
               identity_yaml=VALUES(identity_yaml),
               protocols_yaml=VALUES(protocols_yaml),
               version=version+1"""
    cur = conn.cursor()
    cur.execute(sql, ("curator", yaml.dump(identity), protocols, "migration_script"))
    conn.commit()

def migrate_user_context(conn):
    user_md = (PROFILE_ROOT / "user.md").read_text()
    if user_md.startswith("---"):
        _, fm, body = user_md.split("---", 2)
        profile = yaml.safe_load(fm) if fm else {}
        relationship = body.strip()
    else:
        profile = {}
        relationship = user_md
    sql = """INSERT INTO user_context (agent_slug, profile_yaml, relationship_notes)
             VALUES (%s, %s, %s)
             ON DUPLICATE KEY UPDATE
               profile_yaml=VALUES(profile_yaml),
               relationship_notes=VALUES(relationship_notes),
               version=version+1"""
    cur = conn.cursor()
    cur.execute(sql, ("curator", yaml.dump(profile), relationship))
    conn.commit()

def migrate_memory_folder(conn):
    mem_root = PROFILE_ROOT / "memories"
    for md_file in mem_root.rglob("*.md"):
        if md_file.name.endswith(".lock"):
            continue
        rel = md_file.relative_to(mem_root)
        namespace = rel.parent.as_posix() if rel.parent != Path(".") else "general"
        key_name = rel.stem
        content = md_file.read_text()
        content_json = {"raw": content}
        sql = """INSERT INTO memory_lore
                   (agent_slug, namespace, key_name, content_json,
                    content_text, source_type, importance, tags)
                 VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE
                   content_json=VALUES(content_json),
                   content_text=VALUES(content_text),
                   updated_at=NOW()"""
        cur = conn.cursor()
        cur.execute(sql, (
            "curator", namespace, key_name,
            json.dumps(content_json), content,
            "document_ingestion", 50, json.dumps([])
        ))
    conn.commit()

if __name__ == "__main__":
    conn = mysql.connector.connect(**DB_CFG)
    migrate_soul(conn)
    migrate_user_context(conn)
    migrate_memory_folder(conn)
    print("Core state mirror seeded.")
```

Run this once per Lead, pointing `PROFILE_ROOT` and `DB_CFG["database"]` at the correct profile/Sanctum pair.

### 8.3 Phase 3: Quiddity Indexing (Incremental, Idempotent)

```python
# index_quiddity.py
import hashlib, os, json
from pathlib import Path
import mysql.connector

QUIDDITY_ROOT = Path("/hermes/profiles/zeon7/quiddity")
EMBED_MODEL = "BAAI/bge-m3"
CHUNK_TOKENS = 512
OVERLAP = 64

model = SentenceTransformer(
    EMBED_MODEL,
    device="cuda" if torch.cuda.is_available() else "cpu"
)
conn = mysql.connector.connect(**DB_CFG)

def chunk_text(text: str):
    """Semantic paragraph chunker with heading awareness.
    Returns list of (chunk_text, token_count, metadata)."""
    import re
    sections = re.split(r'^(#{1,6}\s+.+)$', text, flags=re.MULTILINE)
    chunks = []
    current_heading = ""
    for part in sections:
        if re.match(r'^#{1,6}\s+', part):
            current_heading = part.strip()
            continue
        paragraphs = part.strip().split("\n\n")
        for para in paragraphs:
            para = para.strip()
            if not para:
                continue
            tokens = len(para.split())
            if tokens > CHUNK_TOKENS:
                sentences = para.split(". ")
                buf = ""
                for s in sentences:
                    if len(buf.split()) + len(s.split()) > CHUNK_TOKENS:
                        chunks.append((buf, len(buf.split()),
                                       {"heading": current_heading}))
                        buf = s + ". "
                    else:
                        buf += s + ". "
                if buf.strip():
                    chunks.append((buf, len(buf.split()),
                                   {"heading": current_heading}))
            else:
                chunks.append((para, tokens,
                               {"heading": current_heading}))
    return chunks

def process_file(file_path: Path):
    rel = file_path.relative_to(QUIDDITY_ROOT)
    content = file_path.read_text()
    content_hash = hashlib.sha256(content.encode()).hexdigest()
    cur = conn.cursor()

    cur.execute(
        "SELECT id, content_hash FROM quiddity_files WHERE relative_path=%s",
        (rel.as_posix(),)
    )
    row = cur.fetchone()
    if row and row[1] == content_hash:
        return  # Unchanged, skip

    if row:
        file_id = row[0]
        cur.execute("""UPDATE quiddity_files
                       SET content_hash=%s, last_modified=%s,
                           indexing_status='processing'
                       WHERE id=%s""",
                    (content_hash, os.path.getmtime(file_path), file_id))
    else:
        cur.execute("""INSERT INTO quiddity_files
                       (relative_path, content_hash, mime_type,
                        file_size_bytes, last_modified, indexing_status)
                       VALUES (%s, %s, %s, %s, %s, 'processing')""",
                    (rel.as_posix(), content_hash, "text/markdown",
                     len(content), os.path.getmtime(file_path)))
        file_id = cur.lastrowid
    conn.commit()

    cur.execute(
        "DELETE FROM quiddity_vector_references WHERE file_id=%s",
        (file_id,)
    )

    chunks = chunk_text(content)
    embeddings = model.encode(
        [c[0] for c in chunks],
        normalize_embeddings=True,
        batch_size=32
    )

    rows = []
    for i, (chunk, tok_count, meta) in enumerate(chunks):
        rows.append((file_id, i, chunk, tok_count,
                     json.dumps(meta), embeddings[i].tobytes()))

    cur.executemany("""INSERT INTO quiddity_vector_references
                       (file_id, chunk_index, chunk_text,
                        chunk_token_count, chunk_metadata, embedding)
                       VALUES (%s, %s, %s, %s, %s, %s)""", rows)

    cur.execute("""UPDATE quiddity_files
                   SET indexing_status='indexed', indexed_at=NOW()
                   WHERE id=%s""", (file_id,))
    conn.commit()

if __name__ == "__main__":
    for md_file in QUIDDITY_ROOT.rglob("*.md"):
        if ":Zone.Identifier" in md_file.name:
            continue
        process_file(md_file)
    print("Quiddity indexing complete.")
```

### 8.4 Phase 4: Conversation History Backfill (Optional)

Export from Hermes SQLite session DB, transform, bulk insert into `conversation_history` and `conversation_vectors`. Run once per agent Sanctum. Use `session_search` tool output as source data.

---

## 9. DOCKER COMPOSE STACK

```yaml
# docker-compose.yml
version: "3.9"
services:
  mariadb:
    image: mariadb:11.8
    container_name: foreverbox-db
    environment:
      MARIADB_ROOT_PASSWORD: ${DB_ROOT_PASS}
      MARIADB_DATABASE: quiddity_commons
      MARIADB_USER: foreverbox
      MARIADB_PASSWORD: ${DB_PASS}
    volumes:
      - db_data:/var/lib/mysql
      - ./schema:/docker-entrypoint-initdb.d:ro
    ports: ["3306:3306"]
    healthcheck:
      test: ["CMD", "mariadb-admin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

  api:
    build: ./php-api
    container_name: foreverbox-api
    environment:
      DB_HOST: mariadb
      DB_NAME_COMMONS: quiddity_commons
      DB_USER: foreverbox
      DB_PASS: ${DB_PASS}
      JWT_SECRET: ${JWT_SECRET}
      EMBEDDING_ENDPOINT: http://embedding:8000
    ports: ["8080:80"]
    depends_on:
      mariadb:
        condition: service_healthy
    volumes:
      - ./php-api:/var/www/html:ro
      - ${QUIDDITY_HOST_PATH}:/data/quiddity:ro

  embedding:
    image: ghcr.io/huggingface/text-embeddings-inference:1.5
    container_name: foreverbox-embed
    command: --model-id BAAI/bge-m3 --port 8000 --max-batch-size 32
    deploy:
      resources:
        reservations:
          devices:
            - driver: nvidia
              count: 1
              capabilities: [gpu]

volumes:
  db_data:
```

---

## 10. OBSERVABILITY AND OPERATIONS

*(Implements Briefing §14 — "Observability: The System Must Be Able to See Itself".)*

### 10.1 Structured Logging (Monolog to JSON)

```php
$logger->info("vector_search", [
    "agent" => $agentSlug,
    "query_hash" => hash("sha256", $query),
    "results" => count($results),
    "latency_ms" => $timer->stop(),
    "distance_threshold" => $threshold
]);
```

### 10.2 Prometheus Metrics (`/v1/metrics`)

- `foreverbox_api_requests_total{endpoint,status}`
- `foreverbox_db_query_duration_seconds{query_type}`
- `foreverbox_vector_search_duration_seconds`
- `foreverbox_ingestion_jobs_total{status}`
- `foreverbox_wolf_tasks_total{worker,status}`
- `foreverbox_wolf_double_claim_total` — should remain at zero; alert if non-zero (§7.2)
- `foreverbox_router_model_tier_total{tier}`
- `foreverbox_router_budget_remaining{tier}` — gauge, sourced from `token_budget_ledger` (§2.3, §5.2)
- `foreverbox_wolf_status_sync_mismatch_total` — should remain at zero; alert if `specialist_workers` and `wolf_sessions` ever disagree for the same `wolf_id` (§3.5)
- `foreverbox_privileged_action_total{action_type,status}` — every gated request, by outcome (§12); a `denied` count against a `wolf_id` should remain at zero

### 10.3 Backup Strategy

```bash
# Daily full + hourly incremental (mariabackup)
mariabackup --backup \
  --target-dir=/backups/full/$(date +%F) \
  --user=root --password=$DB_ROOT_PASS

mariabackup --backup \
  --incremental-basedir=/backups/full/latest \
  --target-dir=/backups/incr/$(date +%F_%H) \
  --user=root --password=$DB_ROOT_PASS

# Retention: 30 daily fulls, 7 days incrementals
# Test restore: monthly automated restore to staging + checksum validation
```

### 10.4 Point-in-Time Recovery

- Binary log retention: 7 days (`expire_logs_days = 7`)
- `mariabackup --prepare --apply-log-only` for incremental chain

---

## 11. EXTENSIBILITY PATH (COGNITIVE UPGRADE KIT)

| Component | Current (Private) | Future (Plugin) |
|-----------|-------------------|-----------------|
| DB Provisioning | Manual `CREATE DATABASE agent_{name}` | `Installer::provisionAgent($slug)` creates DB, runs Sanctum migrations, registers in Registry |
| API Discovery | Hardcoded base URL | `well-known/foreverbox.json` at API root lists endpoints, auth schemes |
| Config | `.env` + `config/router.yaml` + `foreverbox.json` per profile | `hermes config set memory.backend=foreverbox` writes plugin config |
| Hermes Plugin | Built per §6 | `~/.hermes/plugins/foreverbox/` registers `memory_*` tools, hooks into `pre_llm_call` for the Router |
| Distribution | Git repo + Docker Compose | `hermes plugin install foreverbox` pulls Docker images, runs migrations, registers plugin |

---

## 12. THE SUDO PROTOCOL — STRUCTURAL PRIVILEGED ACTION GATE

*(Implements Briefing §13 — "The Sudo Protocol: A Structural Gate for Powerful Actions". Every SOUL.md and USER.md asserts a version of "privileged actions require Merrill's explicit consent.")*

### 12.1 Classification

`config/privileged_patterns.yaml` defines what counts as a privileged action, matched against both SQL statements the PHP API is about to execute and OS-level commands a Hermes profile is about to run via its bash tool:

- **SQL:** `DROP`, `ALTER TABLE`, `TRUNCATE`, `CREATE DATABASE`, `DROP DATABASE`, `GRANT`/`REVOKE`.
- **OS-level:** any `sudo` invocation, `rm -rf`, package installation, service restarts, production deploys.

This list lives in one config file read by both the PHP middleware (§12.3) and the Python bash-tool wrapper (§12.4) — one classification, enforced in two places, not two independently-maintained lists that can drift apart.

### 12.2 Ledger (`privileged_action_log`, Registry — DDL in §2.3)

Every privileged action request, confirmed or not, is recorded here: which agent, the exact command, when, the confirmation code issued, whether and when it was confirmed, and by whom. This is the audit trail the Sudo Protocol has been asserting exists in every SOUL.md file without actually existing anywhere until now.

### 12.3 PHP Interception and Confirmation Flow

```php
class PrivilegedActionGate implements MiddlewareInterface {
    public function process(Request $request, Handler $handler): Response {
        $action = $this->classify($request);   // returns null if not privileged

        if ($action !== null) {
            $existing = $this->findConfirmed($request->getBody());
            if ($existing && $existing['status'] === 'confirmed'
                && !$this->isExpired($existing)) {
                return $handler->handle($request);   // already confirmed, proceed
            }

            $code = strtoupper(bin2hex(random_bytes(4)));  // 8-char code
            $this->registryDb->insert('privileged_action_log', [
                'agent_slug' => $request->getHeaderLine('X-Agent-ID'),
                'wolf_id' => $request->getHeaderLine('X-Wolf-ID') ?: null,
                'action_type' => $action['type'],
                'command_text' => $action['raw_command'],
                'confirmation_code' => $code,
                'status' => 'pending',
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'PRIVILEGED_ACTION_REQUIRES_CONFIRMATION',
                    'message' => "This action requires Merrill's confirmation. Relay this code to him: {$code}",
                    'confirmation_code' => $code,
                ],
            ], 412);
        }

        return $handler->handle($request);
    }
}
```

The Lead must surface the returned code to Merrill in conversation — it does not auto-approve, infer consent, or retry silently. Merrill reads the code back; the Lead calls `POST /v1/registry/privileged-actions/{id}/confirm` with it. On a match, the API marks `confirmed_at`/`confirmed_by`, executes the original action, and records `result_json`. Codes expire after 10 minutes (`status` → `expired`) so a stale code from an earlier, different request can never be replayed later out of context.

### 12.4 Python/Bash-Side Guard

Terminal-level privileged actions (a Hermes profile's own `sudo` or destructive shell calls on Merrill's machine) go through the same ledger, not a separate parallel mechanism:

```python
def require_confirmation(action_type: str, command_text: str, agent_slug: str) -> bool:
    """Blocks (with timeout) until Merrill confirms via the same ledger
    PHP uses for SQL-side privileged actions. One system, one audit trail,
    for both database and OS-level gating."""
    resp = requests.post(f"{REGISTRY_API_URL}/registry/privileged-actions",
                          json={"agent_slug": agent_slug, "action_type": action_type,
                                "command_text": command_text},
                          headers=_headers())
    request_id = resp.json()["id"]
    code = resp.json()["confirmation_code"]
    print(f"Privileged action requires confirmation. Code: {code}")

    deadline = time.time() + 600  # 10 minutes, matches code expiry
    while time.time() < deadline:
        status = requests.get(
            f"{REGISTRY_API_URL}/registry/privileged-actions/{request_id}"
        ).json()["status"]
        if status == "confirmed":
            return True
        if status in ("denied", "expired"):
            return False
        time.sleep(2)
    return False
```

Any bash-tool wrapper invoked by a Lead's Hermes process checks its command against `config/privileged_patterns.yaml` before execution and calls `require_confirmation()` first if it matches.

### 12.5 Wolves: Exclusion, Not Interception

Wolves never receive privileged-capable tools in their `get_tool_schemas()` output at all — there's no confirmation flow to build for them because the capability simply isn't offered. As defense in depth, `TaskClaimer`/`WolfController` also validates every incoming task payload against the same pattern list before a Wolf claims it; a match is refused outright and logged to `privileged_action_log` with `status='denied'`, `wolf_id` populated — catching the case where a bug or a malformed directive tries to assign privileged work to a Wolf, rather than assuming that can never happen.

### 12.6 Acceptance Test

A test SQL statement containing `DROP TABLE` submitted through the PHP API must return HTTP 412 with a confirmation code and must NOT execute, until a matching confirmation is submitted to `/v1/registry/privileged-actions/{id}/confirm` — verified by checking the target table still exists between the two calls, not just by reading a log line.

---

## 13. ACCEPTANCE CRITERIA (BUILDER CHECKLIST)

- [ ] All DDL executes cleanly on MariaDB 11.8
- [ ] Vector indexes created and usable (`EXPLAIN SELECT ... ORDER BY VECTOR_DISTANCE(...)`)
- [ ] PHP API returns 200 on `/v1/healthz` and `/v1/readyz`
- [ ] Core migration script imports SOUL.md, user.md, `/memories/` without data loss, and is confirmed to run only ONCE as a seed — not as an ongoing sync path
- [ ] `on_memory_write` hook fires on every native SOUL.md/USER.md/MEMORY.md write and mirrors to the relevant Sanctum table within 2 seconds
- [ ] Quiddity indexer processes all `.md` files in `/hermes/profiles/zeon7/quiddity/`, creates searchable vectors
- [ ] Semantic search returns relevant chunks for test queries ("FTN handbook rules", "Quantum Lattice physics")
- [ ] Cognitive Router selects correct tier for test cases (chat → local, plan → heavy, private → local)
- [ ] Privacy-flagged prompts produce zero outbound network calls to Layer 2/3 endpoints under test (assert at the network layer, not the log layer)
- [ ] Wolf claiming uses atomic `UPDATE ... WHERE status='queued'` or `SELECT ... FOR UPDATE SKIP LOCKED`; load test of 50 concurrent Wolf polls against a shared queue produces zero double-claims
- [ ] Concurrent Wolf writes into `memory_lore` under a shared `task_id` never collide (test: 3 Wolves writing simultaneously, distinct `key_name`s, zero duplicate-key errors)
- [ ] Wolf dispatch: Director → queue → worker → result callback works end-to-end, including one forced `dead_letter` retry
- [ ] `sync_turn()` confirmed non-blocking (unit test forces a slow 5s mock API response; calling thread returns control in under 50ms)
- [ ] No Wolf and no Lead ever calls `/v1/commons/ingest/batch`; only the Ingestion Pipeline does
- [ ] `specialist_workers` (Registry) and `wolf_sessions` (Sanctum) never disagree on status for the same Wolf: forced status-transition test confirms both tables update inside one transaction (§3.5)
- [ ] A directive issued via `POST /v1/director/directives` with a non-empty `task_shards` array produces matching `task_queue` rows carrying the same `directive_id`; a Wolf polling that queue can find and claim them (§3.6)
- [ ] A directive with an empty `task_shards` array writes only to `directives`, produces zero `task_queue` rows, and this is confirmed as the sole legitimate no-dispatch case
- [ ] `GET /v1/registry/budget` returns correct `remaining` after seeding `token_budget_ledger`; `CognitiveRouter.select_model()` falls back one tier when a cloud tier's budget is exhausted, and fails open (assumes available) when the budget endpoint itself is unreachable
- [ ] A `DROP TABLE`-class request is blocked with HTTP 412 and a confirmation code, does not execute until confirmed, and the confirmation code expires after 10 minutes (§12.6)
- [ ] No privileged-capable tool ever appears in a Wolf's `get_tool_schemas()` output; a task payload matching a privileged pattern is refused and logged with `status='denied'` (§12.5)
- [ ] Docker Compose spins up full stack in under 3 minutes on fresh machine
- [ ] Backup/restore test passes on staging
- [ ] Load test: 50 concurrent vector searches under 200ms p95
- [ ] **Ingestion triggers (§4.5):** Agent-initiated sync with `paths: ["test.md"]` indexes exactly one file and routes it. Agent-initiated sync with empty `paths` returns 403. Cron-initiated full-root sync with `organise: true` processes all root `.md` files. Agent startup fires a background sync request (verify via API access log, not via user-visible output).
- [ ] **Folder router (§4.6):** A document about music production routes to `03_TheInitiative_Audio`; a document about API schemas routes to `06_QuiddityLtd_Dev_Specs`; an ambiguous document with no clear match (confidence < 0.3) routes to `_review/`. Physical file moves are confirmed on disk.
- [ ] **`ingest_file` tool:** Agent calls `ingest_file("new_doc.md")` → file is chunked, embedded, routed, and physically moved from root to subfolder.
- [ ] **Folder CRUD:** `PUT /v1/commons/folders` creates a new folder entry in `quiddity_folders.yaml` and creates the on-disk directory when `create_on_disk: true`. `DELETE /v1/commons/folders/{name}` removes the catalogue entry without deleting on-disk files. Both require Sudo confirmation.
- [ ] **Reclassify:** `POST /v1/commons/folders/reclassify` moves a file from `_review/` to a specified folder, updates `quiddity_files.relative_path`, and the file is physically relocated on disk.
- [ ] **Centroid rebuild:** `POST /v1/commons/folders/rebuild-centroids` regenerates all centroids and updates `quiddity_folder_centroids`. After adding a new folder and rebuilding, files route to the new folder when content matches.
- [ ] **`ingestion_dead_letter`:** A forced embedding failure writes the failed chunk to `ingestion_dead_letter` with error trace. The retry job re-processes from this table (not from the filesystem), and a chunk that exhausts `max_retries` stays in dead-letter with `retry_count = max_retries`.
- [ ] **`_is_healthy()`:** Returns cached result within 30s window. Ollama health check succeeds against a running local instance. Cloud health check succeeds against a reachable provider. Both return False on connection failure.
- [ ] **`on_memory_write` mirror:** Writing to built-in `memory` or `user` target fires the hook and PUTs to the correct Sanctum endpoint within 2 seconds.
- [ ] **`on_session_end`:** Session close triggers a background summary write to `memory_lore` under `session_summaries` namespace. Failure is logged, not surfaced.
- [ ] **All 10 tools:** Every tool in `get_tool_schemas()` routes to its correct PHP endpoint via `handle_tool_call()` and returns valid JSON. Unknown tool names return `{"error": "Unknown tool: ..."}`.
- [ ] **Method signatures:** `prefetch()` accepts `session_id` kwarg and returns `str`. `sync_turn()` accepts `session_id` and `messages` kwargs. `on_memory_write()` accepts `metadata` kwarg. `backup_paths()` is present and callable.



---

## 14. MIGRATION DIRECTIVE FOR QWEN (SEQUENCED EXECUTION)

1. **Stage 1: SQL Schema Initialisation.** Initialise MariaDB, mount HNSW indexes including `quiddity_folder_centroids` and `ingestion_dead_letter` (§2.1). Populate initial `soul` mirror rows from `/hermes/profiles/zeon7/SOUL.md` (§8.2).
2. **Stage 2: API Assembly.** Build the PHP API paths matching the headers and payload JSON requirements exactly (§3). Keep DB connections stateless and resource limits safe. Implement `TaskClaimer.php` (§7.2) as a single shared service, not per-controller logic. Implement `WolfController::updateStatus()` (§3.5) as the sole path by which either `specialist_workers` or `wolf_sessions` is ever written. Implement `DirectorController::issueDirective()` (§3.6) so every directive with non-empty `task_shards` produces linked `task_queue` rows in the same request. Implement `PrivilegedActionGate` middleware (§12.3) ahead of every controller that can issue DDL or destructive statements. Implement `FolderController.php` (§3.3) with reclassify, CRUD, and centroid-rebuild endpoints.
3. **Stage 3: Hermes Plugin Build.** Implement `ForeverBoxMemoryProvider` per §6 in full — all 10 tools, the threading contract (§6.5), the `on_memory_write` mirror hook (§6.6), `on_session_end`, `on_session_switch`, `on_delegation`, `backup_paths`, startup sync trigger (§4.5 Path C), and `on_pre_compress`. All method signatures must match the `MemoryProvider` ABC. Wire `require_confirmation()` (§12.4) into any bash-tool wrapper a Lead's Hermes profile uses for terminal commands.
4. **Stage 4: Python Client Routing Patch.** Hook the `CognitiveRouter` logic into `run_agent.py` so conversation payloads are intercepted, privacy-scanned (§5.1), budget-checked (§5.2 `_budget_available`), health-checked (§5.2 `_is_healthy`), and routed.
5. **Stage 5: Migration Scripts.** Execute `migrate_core_state.py` then `index_quiddity.py` against the real profile directory, once per Lead. Run `generate_folder_centroids.py` to seed `quiddity_folder_centroids` from existing indexed files (§4.6). Seed `token_budget_ledger` with today's row per tier from `router.yaml` defaults (§5.3). Register the 4-hour `quiddity-sync` cron job (§4.5 Path B).
6. **Stage 6: Verification Testing.** Execute all acceptance criteria in §13, including the new ingestion trigger, folder routing, tool dispatch, stub-fill, and method-signature tests. Confirm zero `...` or `pass` stubs remain. Confirm all 10 tools route correctly. Verify `ingestion_dead_letter` retry cycle. Verify folder reclassification and centroid rebuild. Verify `on_session_end` and `on_delegation` hooks fire.

---

**End of Technical Specification, Version 3.0.**
This document, read alongside `The_Council_Library_Master_Briefing_V6_Technical_Human.md`, is the single source of truth for the Builder (Qwen Coder). Every SQL statement, endpoint, class specification, concurrency pattern, migration step, tool schema, and method signature is defined. No `...` or `pass` stubs remain. Version 3.0 is the first complete draft — all gaps filled, all stubs implemented, all acceptance criteria updated.
