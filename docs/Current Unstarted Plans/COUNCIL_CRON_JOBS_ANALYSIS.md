# Council Cron Jobs & Scheduled Tasks — Analysis & Planning Document

## Section 1: Current State Inventory

### 1.1 Hermes Cron Jobs (Per-Agent Profile)

| Job ID | Name | Schedule | Mode | Workdir | Script | Deliver | Status | Verified Working? |
|--------|------|----------|------|---------|--------|---------|--------|-------------------|
| a974c83afeb2 | qwen-proxy-watchdog | every 2m | no-agent | /foreverbox_data/shared/services/qwen_proxy | qwen-proxy-watchdog.sh | local | **Active** | Yes |

**Details:**
- **Purpose:** Monitor qwen-proxy (port 11435) health; restart if unresponsive
- **How it works:**
  1. Runs `curl -sf http://localhost:11435/health`
  2. If fails: kills stale PID on port 11435, restarts proxy via `python proxy.py` in `.venv`
  3. Verifies restart with another health check
  4. Logs to `/foreverbox_data/shared/services/qwen_proxy/watchdog.log`
- **Shared across all 5 profiles** (zeon7, leon, otec, gemma, wolf) — single job ID visible in each
- **No files outside `/foreverbox_data/`** — workdir points to shared location

---

### 1.2 Systemd User Services (Council Library Daemons)

| Service Name | Description | Status | Port/Endpoint | Verified Working? |
|--------------|-------------|--------|---------------|-------------------|
| council-embedding.service | Embedding service (all-MiniLM-L6-v2) | **Active (running)** | http://127.0.0.1:8900/health | Yes |
| council-ingestion.service | Ingestion worker (PHP) | Inactive (dead) | — | No |
| council-wolves.service | Wolf workers (3 agents: curator, producer, director) | Inactive (dead) | — | No |

**Details:**

#### council-embedding.service
- **ExecStart:** `/usr/bin/python3.12 /foreverbox_data/council-library/scripts/embedding_service.py --port 8900`
- **Purpose:** Generates vector embeddings for Quiddity Lore Sea ingestion
- **Health check:** `curl -X POST http://127.0.0.1:8900/health` returns `{"status":"ok"}`
- **Auto-restart:** `Restart=on-failure`, `RestartSec=10`

#### council-ingestion.service
- **ExecStart:** `/usr/bin/php8.3 /foreverbox_data/council-library/scripts/ingestion_worker.php`
- **Purpose:** Processes files dropped in Quiddity Lore Sea folders, generates embeddings, stores in vector DB
- **Depends on:** council-embedding.service
- **Environment:** `EMBEDDING_URL=http://127.0.0.1:8900`, `DB_PASS`
- **Current state:** Stopped — exited cleanly (status 0) after 24ms, no journal output
- **Design intent:** Runs periodically (every 8 hours) OR on-demand when an agent requests it — NOT a continuous daemon

#### council-wolves.service
- **ExecStart:** Bash command launching 3 Python wolf workers in background
- **Purpose:** Background research workers (Research Wolf System — NOT the Forever Fit gamification protocol)
- **Workers:** curator (wolf_1), producer (wolf_2), director (wolf_3)
- **Wolf Profile:** Uses `Zeon7-Gemma:64k` on **local Ollama** (see `/foreverbox_data/profiles/wolf/config.yaml`)
- **Layer 1 Guard:** Blocks MAIN AGENTS (zeon7, leon, gemma, otec) from spawning wolves on local models because their GPU is occupied. The WOLF PROFILE ITSELF is a separate Hermes profile designed to run on local Ollama.
- **Current state:** Stopped — systemd service never started properly
- **Root cause analysis:** See Section 2.3

---

### 1.3 Other Scheduled Mechanisms

| Mechanism | Description | Status |
|-----------|-------------|--------|
| `hermes cron` daemon | Runs Hermes cron jobs (including qwen-proxy-watchdog) | Must be running for cron jobs to execute |
| `loginctl enable-linger zeon7` | Allows systemd user services to persist after logout | Configured |
| Apache2 on port 8080 | Council Library API (PHP) | Running |
| MariaDB | Databases: quiddity_commons, agent_*, agent_registry | Running |

---

## Section 2: Root Cause Analysis

### 2.1 Why qwen-proxy-watchdog Works (Success Pattern)

| Factor | qwen-proxy-watchdog | council-ingestion | council-wolves |
|--------|---------------------|-------------------|----------------|
| **Runtime** | Bash script (no deps) | PHP 8.3 + vendor deps | Python 3.12 + mysql-connector + requests |
| **Dependencies** | curl, lsof, python3 | php8.3, composer vendor/autoload.php | mysql-connector-python, requests, council-library router module |
| **Environment** | `.venv` in workdir (self-contained) | `.env.production` (DB, embedding URL) | `.env.production` + `OPENROUTER_API_KEY` (MISSING) |
| **Restart policy** | Cron re-runs every 2m | `Restart=on-failure` (systemd) | `Restart=on-failure` (systemd) |
| **Health check** | Explicit HTTP `/health` | None (fire-and-forget) | None (fire-and-forget) |
| **Failure visibility** | Logs to shared `watchdog.log` | Journal (empty — exited 0) | Journal (empty — never started) |
| **Trigger** | Time-based (cron) | Dependency (embedding) | Manual/auto start |

**Key differentiators for qwen-proxy-watchdog:**
1. **Self-contained** — owns its venv, no system deps
2. **Explicit health endpoint** — proxy exposes `/health` for watchdog to query
3. **Cron-driven** — not dependent on systemd restart logic; cron re-runs every 2 minutes regardless
4. **Observable** — logs to file, stats exposed via health endpoint
5. **No external secrets** — no API keys needed

---

### 2.2 Why council-ingestion.service Is "Dead" (Corrected)

**Observed behavior:** Started, ran for 24ms, exited with status 0 (SUCCESS), no journal output.

**Correction:** This is **NOT a failure** — it's the intended design.

**Investigation findings:**
1. **Periodic/on-demand design** — The ingestion worker is meant to run once every 8 hours (or on agent request), process pending files, then exit
2. **Single-pass script** — `ingestion_worker.php` checks for pending files, processes them, exits
3. **No files to process** — Current DB shows 12 files all `indexed`, 0 `pending` → worker runs, finds nothing, exits cleanly
4. **systemd sees exit 0** → "success" → no restart (correct behavior)

**Evidence:**
```sql
-- Current indexing status
SELECT indexing_status, COUNT(*) FROM quiddity_commons.quiddity_files GROUP BY indexing_status;
-- indexed: 12, pending: 0, processing: 0, failed: 0
```

**Conclusion:** The service works as designed. It should be triggered by:
- A systemd **timer** (every 8 hours) — NOT currently configured
- An **on-demand trigger** from agents (e.g., via API or Hermes cron job) — NOT currently implemented

---

### 2.3 Why council-wolves.service Is Dead (Corrected)

**Observed behavior:** Never started (inactive dead since install).

**Root causes:**

1. **wolf_worker.py hardcodes OpenRouter (cloud)** — but the Wolf Profile uses **local Ollama (Zeon7-Gemma:64k)**
   ```python
   # wolf_worker.py lines 31, 131
   OPENROUTER_KEY = os.environ.get("OPENROUTER_API_KEY", "")  # Empty = auth failure
   OPENROUTER_URL = "https://openrouter.ai/api/v1/chat/completions"
   ...
   r = requests.post(OPENROUTER_URL, headers=headers, json=body, timeout=120)
   ```

2. **router.yaml wolf_overrides point to cloud models** — but wolves should use local model
   ```yaml
   wolf_overrides:
     layer_1_intuitive_reflex:
       model: "google/gemini-3.1-flash-lite"  # Cloud model
   ```

3. **Missing `OPENROUTER_API_KEY`** — `.env.production` has `FOREVERBOX_API_KEY` but not `OPENROUTER_API_KEY`

4. **Layer 1 Guard misunderstanding** — The Guard blocks MAIN AGENTS from spawning wolves on local models. The WOLF PROFILE ITSELF is a separate Hermes profile designed to run on local Ollama (Zeon7-Gemma:64k). Wolves ARE meant to run locally.

5. **No task queue population** — `task_queue` table likely empty; workers would poll but find nothing

**Correction from earlier analysis:** 
- **Wolves ARE supposed to run on local Ollama (Zeon7-Gemma:64k)**
- The wolf_worker.py script is **architecturally wrong** — it should call local Ollama, not OpenRouter
- The router.yaml wolf_overrides are **wrong for the Research Wolf System** (they belong to a different routing context)

**Evidence from two-wolf-protocols.md:**
> **Research Wolf System (Live — Stage 1 Complete)**
> **Profile:** `wolf` (Zeon7-Gemma:64k on Ollama, 3.8 GB)
> **Layer 1 Guard:** Local agents block by default (GPU occupied). Cloud agents allowed.

---

### 2.4 Can the Dead Jobs Learn from the Working One?

**Yes — apply these patterns:**

| Pattern from qwen-proxy-watchdog | Apply to council-ingestion | Apply to council-wolves |
|----------------------------------|----------------------------|-------------------------|
| **Explicit health endpoint** | Add `/health` to ingestion API | Add health check to wolf worker |
| **Cron-driven (not systemd-only)** | Add Hermes cron job to trigger every 8h | Add cron to check wolf health / re-queue |
| **Self-contained runtime** | Ensure PHP deps in vendor/ are present | Fix wolf_worker.py to use local Ollama |
| **Observable logging** | Log to shared file + journal | Log to shared file + journal |
| **Graceful empty-queue handling** | Single-pass is correct for periodic job | Loop with sleep, don't exit on empty queue |
| **No missing secrets** | Uses only DB_PASS (present) | Remove OPENROUTER_API_KEY dependency; use local Ollama |

**Critical fixes needed for wolves:**
1. Rewrite `wolf_worker.py` to call local Ollama (port 11434) with model `Zeon7-Gemma:64k`
2. Remove `OPENROUTER_API_KEY` dependency
3. Update `router.yaml` wolf_overrides to point to local model (or remove — wolf profile already defines model)
4. Add daemon loop with `POLL_INTERVAL` sleep (don't exit on empty queue)
5. Add Hermes cron job to monitor/restart wolf workers (like qwen-proxy-watchdog)
6. Remove dead Architecture B code (`wolf_worker.py` systemd service) — it's the old pre-V1 system

---

## Section 3: Verification Commands

```bash
# Check Hermes cron jobs (any profile)
cd /foreverbox_data/profiles/leon && hermes cron list

# Check systemd services
systemctl --user status council-embedding council-ingestion council-wolves

# Check embedding health
curl -X POST http://127.0.0.1:8900/health

# Check qwen-proxy health
curl -sf http://localhost:11435/health

# Check watchdog log
tail -20 /foreverbox_data/shared/services/qwen_proxy/watchdog.log

# Check Apache API
curl -sf http://localhost:8080/v1/healthz

# Check MariaDB
systemctl status mariadb

# Check ingestion queue status
mariadb -h localhost -u zeon7_user -p'F0reverb0x#2o26sql' -e "SELECT indexing_status, COUNT(*) FROM quiddity_commons.quiddity_files GROUP BY indexing_status;"

# Debug ingestion worker manually
cd /foreverbox_data/council-library/scripts && php ingestion_worker.php

# Debug wolf worker manually (requires local Ollama running)
python3 wolf_worker.py --agent=curator --wolf-id=wolf_1 --once
```

---

## Section 4: Future Plans

### 4.1 Planned Improvements

| # | Improvement | Priority | Description |
|---|-------------|----------|-------------|
| 1 | **Remove dead Architecture B wolf system** | High | Delete `wolf_worker.py`, remove `council-wolves.service` from `cli.py` installer, disable systemd unit. This is the pre-V1 system replaced by V1→V2→V3. |
| 2 | **Fix council-ingestion.service trigger** | High | Add systemd timer (`council-ingestion.timer`) for every 8 hours + Hermes cron job for on-demand trigger via API. |
| 3 | **Add ingestion health endpoint** | Medium | Expose `/health` on ingestion API (port 8080 or new) for monitoring. |
| 4 | **Add ingestion Hermes cron job** | Medium | Cron job `ingestion-trigger` (schedule: every 8h + manual trigger) that calls ingestion API or runs PHP worker directly. |
| 5 | **Add qwen-proxy health to council-library status check** | Low | Extend `council-library status` command to verify qwen-proxy health. |

### 4.2 New Cron Jobs / Timers Needed

| Job/Timer | Type | Schedule | Purpose | Status |
|-----------|------|----------|---------|--------|
| `council-ingestion.timer` | systemd timer | Every 8 hours (0 */8 * * *) | Trigger ingestion worker periodically | **Not created** |
| `ingestion-trigger` | Hermes cron | Every 8h + manual | On-demand ingestion trigger via API | **Not created** |
| `wolf-health-watchdog` | Hermes cron | Every 5m | Monitor/restart wolf workers (if Architecture A daemonised) | **Not needed** — Architecture A uses ad-hoc spawn, not daemons |

### 4.3 Service Recovery Actions

| Service | Current State | Recovery Action | Verification |
|---------|---------------|-----------------|--------------|
| council-embedding | Running | None needed | `curl -X POST http://127.0.0.1:8900/health` |
| council-ingestion | Dead (by design) | Add timer + cron trigger | Timer fires → worker runs → files indexed |
| council-wolves | Dead (wrong arch) | **Remove Architecture B**; Architecture A is live | `fbox-wolf-spawn` skill works from cloud agents |

### 4.4 Detailed Implementation Plan: Remove Architecture B (Dead Wolf System)

**Objective:** Clean up the pre-V1 `wolf_worker.py` systemd daemon system that was superseded by V1→V2→V3.

**Steps:**

| Step | Action | Command/Location |
|------|--------|------------------|
| 1 | Delete `wolf_worker.py` script | `rm /foreverbox_data/council-library/scripts/wolf_worker.py` |
| 2 | Remove systemd unit from installer | Edit `/foreverbox_data/council-library/bin/council-library` (cli.py): remove `council-wolves.service` from `_install_systemd()` and `_uninstall_systemd()` |
| 3 | Disable/remove systemd service | `systemctl --user disable council-wolves.service && systemctl --user stop council-wolves.service` |
| 4 | Remove systemd unit file | `rm ~/.config/systemd/user/council-wolves.service && systemctl --user daemon-reload` |
| 5 | Verify no references remain | `grep -r "wolf_worker" /foreverbox_data/council-library/` — should return nothing |

**Acceptance Criteria:**
- [ ] `wolf_worker.py` deleted
- [ ] `council-wolves.service` no longer installed by `council-library install`
- [ ] `systemctl --user status council-wolves` returns "not found"
- [ ] No references to `wolf_worker` in council-library codebase

---

### 4.5 Detailed Implementation Plan: Fix Ingestion Triggering

**Objective:** Ensure ingestion worker runs every 8 hours + on-demand.

**Steps:**

| Step | Action | Details |
|------|--------|---------|
| 1 | Create systemd timer | `/home/zeon7/.config/systemd/user/council-ingestion.timer` with `OnCalendar=*-*-* 0/8:00:00` (every 8 hours) |
| 2 | Enable timer | `systemctl --user enable council-ingestion.timer && systemctl --user start council-ingestion.timer` |
| 3 | Add Hermes cron for on-demand trigger | `hermes cron create "every 8h" --name "ingestion-trigger" --script "ingestion_trigger.sh" --no-agent --workdir "/foreverbox_data/council-library/scripts"` |
| 4 | Create `ingestion_trigger.sh` | Bash script that calls `php ingestion_worker.php` and logs to shared location |
| 5 | Add `/health` endpoint to ingestion API | PHP endpoint at `/v1/ingestion/health` returning status + last run timestamp |
| 6 | Update `council-library status` | Add ingestion health check to `cli.py` `_check_ingestion()` |

**Acceptance Criteria:**
- [ ] Ingestion runs automatically every 8 hours (timer fires)
- [ ] Agent can trigger ingestion on-demand via Hermes cron
- [ ] Health endpoint returns last run time + status
- [ ] `council-library status` shows ingestion health

---

*Document created: 23 July 2026*  
*Author: Leon (Layer 2 — The Producer)*  
*Location: `/foreverbox_data/council-library/docs/Current Unstarted Plans/COUNCIL_CRON_JOBS_ANALYSIS.md`*