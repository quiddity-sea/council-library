# Stage 1 — Final Completion Plan

*Date: 2026-07-20*

---

## Overview

Three planning blueprints are complete (100%). This plan captures all remaining work discovered during the build-out that was not covered by those plans.

---

## Task 1: Souls Configuration Canvas V2 → V3 - DONE

**Problem:** The Souls Configuration Canvas V2 mirrors the live SOUL.md files for all 4 agents, but the live files have since been updated with:
- Wolf Protocol sections (WOLF PROTOCOL block, spawn and retrieval instructions, the `fbox-wolf-spawn` skill reference)
- Shell wrapper instructions in the MEMORY OPERATIONS section (`/foreverbox_data/bin/fbox-memory-*` instead of `skill_view`)
- DOCUMENTATION MAINTENANCE section (update-plans-progression and reference-doc-alteration-log skill usage)
- Layer 1 Guard with Merrill override authority

**Fix:**
1. Read all 4 live SOUL.md files from `/foreverbox_data/profiles/{zeon7,leon,gemma,otec}/SOUL.md`
2. Update the Canvas document with the exact current content
3. Rename file to `Souls Configuration Canvas - V3.md`
4. Move V2 to archives
5. Run `reference-doc-alteration-log` skill to log the change
6. Run `update-plans-progression` if we track reference docs as plans

---

## Task 2: Council Library Handbook V1 → V2 - DONE

**Problem:** The Handbook documents the system as it existed in V1 (July 2026). Since then:
- Section 6 (File Organisation): Lists only 6 top-level folders. We now have 8 (07_MerrillLeo_CreativeWorks and 08_VisualMedia were added).
- Section 5 (Agent Tools): Lists the now-broken plugin tools (wolf_dispatch, memory_search, memory_upsert) as available. Agents now use shell wrappers at `/foreverbox_data/bin/`.
- Section 7 (Wolves): Describes systemd worker units (council-wolves) that don't exist. Wolves are spawned ad-hoc via `hermes chat --profile wolf`.
- Section 4 (Model Routing): References a router.yaml and CognitiveRouter that is not fully implemented.
- Section 8 (Skills Reference): Lists deprecated skills (council-library-cli, quiddity-folder-manager, foreverbox-operations). The actual interface is the 19 Shared_Skills/foreverbox skills plus shell wrappers.
- Quick Reference Card: Lists curl commands that don't work with the current API + agent identity system.

**Fix:**
1. Update Section 6 to list all 8 top-level folders with subfolder structure
2. Update Section 5 to document shell wrappers (`/foreverbox_data/bin/fbox-*`) as the primary interface and note plugin tools as non-functional
3. Update Section 7 to describe the actual wolf spawn mechanism (`hermes chat --profile wolf`) and the `fbox-wolf-spawn` skill
4. Update Section 4 to note CognitiveRouter status (not implemented) and current routing reality
5. Update Section 8 to list Shared_Skills/foreverbox content
6. Update Quick Reference Card with correct shell wrapper commands
7. Rename to `COUNCIL_LIBRARY_HANDBOOK_V2.md`
8. Move V1 to archives
9. Run `reference-doc-alteration-log` skill

---

## Task 3: Architecture Blueprint V3 — Remaining Implementation Items

**Context:** The Architecture Blueprint V3 (2,320-line master spec) specifies several subsystems that are not yet built. These are implementation gaps, not documentation gaps.

### 3a: Cognitive Router (Section 5 of Blueprint)

**What's missing:** The router hook (`cognitive_router.on_turn_start`) is referenced in all agent configs but the actual scoring logic, budget gate, and privacy gate are not implemented.

**What the blueprint specifies:**
- **Scoring**: Prompt complexity scoring based on tool depth, task type, context size, retry loops, delegation depth
- **Privacy gate**: When private data (API keys, file paths) is detected, forces Layer 1 local model. Hard-stops if local unavailable.
- **Budget gate**: Checks `agent_registry.token_budget_ledger` before routing to cloud tiers. Prevents overspending.

**Fix:**
1. Implement `cognitive_router.py` hook that reads `router.yaml`
2. Add scoring logic per blueprint §5.1
3. Add privacy gate per blueprint §5.4
4. Add budget gate per blueprint §5.2
5. Write `router.yaml` config with tier definitions
6. Deploy to all agent profiles

### 3b: Sudo Protocol Enforcement (Section 12 of Blueprint)

**What's missing:** The `privileged_action_log` table and the enforcement gate. Currently the Sudo Protocol is prompt-level only (agents are told to ask Merrill). There is no technical gate.

**What the blueprint specifies:**
- `agent_registry.privileged_action_log` table with action types: sql_ddl, sudo_command, schema_alter, production_deploy, destructive_file_op
- Confirmation code mechanism: privileged action generates a code, Merrill reads it back to approve
- API endpoints: `GET /v1/registry/privileged-actions/{id}` and `POST /v1/registry/privileged-actions/{id}/confirm`

**Fix:**
1. Create the `privileged_action_log` table in `agent_registry` (DDL already in blueprint §2.3)
2. Implement PrivilegedAction middleware in PHP API
3. Add confirmation code generation (`bin2hex(random_bytes(4))`)
4. Wire into terminal command execution path
5. Update agent SOULs to reference the technical gate alongside the prompt-level convention

### 3c: Dead Letter Queue (Section 2.1 of Blueprint)

**What's missing:** The `ingestion_dead_letter` table. Failed ingestion chunks are lost. The worker retries from the filesystem, re-chunking from scratch on transient failures.

**What the blueprint specifies:**
- `quiddity_commons.ingestion_dead_letter` table with retry_count, max_retries, error_trace
- Worker should retry from this table, not re-chunk from filesystem

**Fix:**
1. Create the table (DDL already in blueprint §2.1)
2. Modify `ingestion_worker.php` to write failed chunks to dead_letter instead of just logging errors
3. Add retry logic: on worker startup, check dead_letter for pending retries before scanning filesystem
4. Cap retries at `max_retries` (default 5)

### 3d: Folder Centroids (Section 3.2 of Blueprint)

**What's missing:** The `quiddity_folder_centroids` table and the `generate_folder_centroids.py` script. The FolderRouter's `vectorClassify()` method queries this table but it does not exist, so classification always falls back to keyword matching. This is why some files don't auto-classify.

**What the blueprint specifies:**
- `quiddity_commons.quiddity_folder_centroids` table with folder_name, centroid (VECTOR(384)), sample_count
- Endpoint: `POST /v1/commons/folders/rebuild-centroids`
- Script: `generate_folder_centroids.py` that computes average vectors for sampled files in each folder

**Fix:**
1. Create the `quiddity_folder_centroids` table
2. Write `generate_folder_centroids.py` that samples indexed files per folder, averages their chunk embeddings, and stores centroids
3. Register the rebuild-centroids endpoint in FolderController.php
4. Run centroids generation for all 8 current folders
5. Verify that `vectorClassify()` now returns non-null results for files with sufficient content

### 3e: Wolf Task Queue (Section 7 of Blueprint)

**What's missing:** The `agent_registry.task_queue` table and the atomic claim pattern. Wolves are currently spawned ad-hoc via `hermes chat --profile wolf` commands. The blueprint specifies a formal queuing system with Director-dispatched tasks, atomic claiming, and status tracking.

**What the blueprint specifies:**
- `agent_registry.task_queue` table with atomic claim using `SELECT ... FOR UPDATE`
- `WolfController.php` with `updateStatus()` dual-write to registry + Sanctum
- Director dispatch flow: `directives` → `task_queue` → Wolf polls and claims
- Heartbeat monitoring against `specialist_workers.last_heartbeat`

**Fix:**
1. Create the `task_queue` table (DDL already in blueprint §2.3)
2. Implement `TaskClaimer.php` with atomic claim SQL
3. Wire wolf spawn to task queue (wolf claims task before executing)
4. Implement heartbeat from wolf to `specialist_workers`
5. Update Director controller for directive → task_queue dispatch

### 3f: Vector Type Alignment

**Problem:** The blueprint DDL specifies `VECTOR(1024)` with `BAAI/bge-m3` model (1024 dims). The live system uses `VECTOR(384)` with `all-MiniLM-L6-v2` (384 dims). The blueprint §4.2 also specifies bge-m3 as default with nomic-embed as alternative.

**Fix:**
1. Either update the blueprint DDL to match the live system (VECTOR(384), all-MiniLM-L6-v2), OR
2. Migrate the live system to bge-m3 (requires pulling the model, regenerating all embeddings, and updating the embedding_service.py)
3. **Recommendation:** Update blueprint to match live system. BGE-M3 is 2.2 GB and would require significant GPU resources. All-MiniLM-L6-V2 (384-dim) is proven functional and lightweight.

---

## Task 5: Visual Media Ingestion (Future Phase)

**Not in this plan.** The 08_VisualMedia folder structure exists and is ready. Actual image ingestion (binary detection, EXIF extraction, OCR, perceptual hashing) will be a separate Stage 2 plan. This is noted here so it's not forgotten.

---

## Task 9: Master Briefing V6 → V7 - DONE

**Problem:** The Master Briefing V6 is the human-facing explanation of the Council Library system. Since V6 was written:
- 8-domain taxonomy with subfolders is live (V6 references 6 flat folders)
- Wolf Protocol is fully implemented (V6 describes systemd workers that don't exist)
- Shell wrappers at `/foreverbox_data/bin/` replaced the broken plugin tools
- Sudo Protocol enforcement table + endpoints exist
- Dead letter queue exists
- Folder centroids are live
- Souls Canvas is now V3

**Fix:**
1. Read the current `MASTER_BRIEFING_V6.md`
2. Update all sections to reflect the current live state of all systems
3. Rename to `MASTER_BRIEFING_V7.md`
4. Move V6 to archives
5. Run `reference-doc-alteration-log` skill

---

## Updated Implementation Order

| # | Task | Effort | Depends On |
|---|------|--------|------------|
| 1 | Souls Canvas V3 | Small | Done |
| 2 | Vector Type Align | Small | Done |
| 3 | Folder Centroids | Medium | Done |
| 4 | Dead Letter Queue | Small | Done |
| 5 | Sudo Protocol | Medium | Done |
| 6 | Cognitive Router | Large | Done |
| 7 | Wolf Task Queue | Large | Done |
| 8 | Handbook V2 | Medium | Done |
| 9 | Master Briefing V7 | Medium | Done |

**Recommended next pass:** Code tasks (6, 7), then doc tasks (8, 9) last to capture the final state of all systems.
