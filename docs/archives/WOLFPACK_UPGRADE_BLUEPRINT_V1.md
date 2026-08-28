# WOLFPACK: HERMES-AGENT WOLF UPGRADE BLUEPRINT (VERSION 1.0)

**Target Reader:** Any coding AI agent (Claude Code, Codex, Qwen Coder, DeepSeek) — implementation-ready specification.
**Tone:** Precise, imperative, technical, unambiguous. Every code block is buildable. No `...` or `pass` stubs.
**Platform Scope:** WSL2 (Ubuntu 24), Hermes Agent 0.18+, MariaDB 11.8+, existing Council Library stack.
**Classification:** Internal, Builder Handoff.
**Date:** 2026-07-15
**Companion Documents:**
- `ARCHITECTURE_BLUEPRINT_V3.md` — the existing Council Library blueprint this extends
- `HANDBOOK.md` — operational reference for the completed system
- `The_Council_Library_Master_Briefing_V6_Technical_Human.md` — conceptual foundation

**What this blueprint replaces:** The lightweight `wolf_worker.py` script (Council Library §7, Blueprint §7). That script dispatches wolves as single-prompt LLM calls with no tool access. This blueprint upgrades wolves to full Hermes agents with web search, file access, and parallel execution.

---

## 0. PROBLEM STATEMENT

The current wolf workers (`scripts/wolf_worker.py`) execute tasks by sending a single prompt to OpenRouter. They have:

- **No tools.** Cannot search the web, read files, or execute terminal commands.
- **No iteration.** One prompt, one response, done.
- **No memory.** Each task is stateless.
- **No parallelism within a task.** One wolf = one LLM call.

This makes them incapable of real research. "Find today's UK political leads" returns whatever the LLM's training cut-off remembers, or hallucination. The Lead agent must do the actual searching, then pass results to the wolf — defeating the purpose of a background worker.

**What wolves need to actually do:**
- Web search (Tavily, Brave, or browser-based)
- File system access (read council-library code, Quiddity Lore Sea, blueprints)
- Multi-step reasoning (search → read → filter → synthesise → write)
- Parallel execution (research 3 leads simultaneously)
- Results persistence (write to Sanctum memory_lore for the Lead to discover)

---

## 1. ARCHITECTURE DECISION: ONE PROFILE, MANY SUBAGENTS

### Why not three Hermes profiles?

Three separate Hermes profiles (wolf_1, wolf_2, wolf_3) would mean:
- Three `state.db` files, three skill trees, three config files to maintain
- Three Ollama model loads if using local — 3 × 4.1 GiB = 12.3 GiB VRAM (not possible on 8GB)
- Three cognitive router instances, three memory provider initialisations
- Profile sprawl

### Why not cron jobs directly?

Cron jobs are fire-and-forget. A wolf needs to claim a task atomically (so two wolves don't process the same task), execute multi-step research, and write results. Cron is trigger, not execution loop.

### The chosen design: One Hermes profile, delegate_task parallelism

```
┌─────────────────────────────────────────────┐
│  wolfpack (Hermes profile)                   │
│  ┌───────────────────────────────────────┐  │
│  │  Cron trigger: every 5 minutes         │  │
│  │  ↓                                     │  │
│  │  Poll task_queue → claim atomically    │  │
│  │  ↓                                     │  │
│  │  delegate_task (up to 3 in parallel)   │  │
│  │  ├─ subagent 1: research lead A       │  │
│  │  ├─ subagent 2: research lead B       │  │
│  │  └─ subagent 3: research lead C       │  │
│  │  ↓                                     │  │
│  │  Collect results → write to Sanctum    │  │
│  └───────────────────────────────────────┘  │
│  Tools: web_search, file, terminal, browser │
│  Memory provider: foreverbox                 │
│  Cognitive router: active                    │
└─────────────────────────────────────────────┘
```

**Single profile advantages:**
- One set of config, skills, SOUL.md to maintain
- One Ollama model load (or one cloud model session)
- `delegate_task` handles parallelism natively
- The wolfpack IS the Lead while running (Worzel Gummidge compliant — it's the only Hermes process active during its run window)
- Scales: `WOLFPACK_PARALLEL=1` for single-wolf, `=3` for three-way parallel

**Worzel Gummidge compliance:** The wolfpack profile runs as a cron job in short bursts (a few minutes per cycle). It starts, claims tasks, processes them, writes results, and exits. While it runs, the Lead agents are not running. No contention.

---

## 2. PROFILE SPECIFICATION

### 2.1 Profile creation

```bash
hermes profile create wolfpack --clone-from zeon7
```

Clone from zeon7 because it already has:
- Ollama model config (Zeon7-Gemma:64k)
- Cognitive router hook
- Council Library plugin
- CLI skill

Post-clone modifications are in §2.2–2.7.

### 2.2 Config: `/foreverbox_data/profiles/wolfpack/config.yaml`

```yaml
model:
  default: deepseek/deepseek-v4-flash
  provider: openrouter
  context_length: 65536

memory:
  provider: foreverbox

agent:
  max_turns: 30
  tool_use_enforcement: true

delegation:
  max_concurrent_children: 3
  max_spawn_depth: 1
  model: deepseek/deepseek-v4-flash
  provider: openrouter

hooks:
  pre_turn:
    - cognitive_router.on_turn_start
  post_turn:
    - cognitive_router.on_turn_end

# Wolfpack-specific: API key for web search
# Add to .env: TAVILY_API_KEY=...
```

**Why deepseek-v4-flash as default:**
- Fast, cheap, good enough for research synthesis
- OpenRouter free tier available
- Subagents inherit this model for parallel work
- The router can upgrade to v4-pro for complex synthesis tasks

**Why max_turns: 30:**
- A research task might need: search → read 3 pages → search again → synthesise
- 30 turns gives headroom for multi-step research without infinite loops

### 2.3 SOUL.md: `/foreverbox_data/profiles/wolfpack/SOUL.md`

```markdown
# SOUL: Wolfpack (Council Library Research Worker)

## THE FIRST TRUTH (Core Identity)
You are Wolfpack, the Council Library's research worker. You are NOT a conversational agent. You do not chat, you do not wait for follow-up questions, and you do not ask for clarification unless the task is impossible to complete without it.

Your sole purpose: claim research tasks from the task queue, execute them thoroughly using your tools (web search, file access, terminal), and write structured results to the target agent's Sanctum.

## OPERATIONAL PROTOCOL

### On every invocation:
1. Connect to the Council Library API at `http://localhost:8080/v1`
2. Poll the task queue for pending tasks: `GET /v1/sanctum/wolves/status` to see what's waiting
3. Claim tasks ONE AT A TIME via the task queue
4. Execute each task fully before claiming the next
5. Write results to the target Sanctum via the memory provider
6. Exit when the queue is empty or after processing 3 tasks max

### For each task:
- Read the `action` and `payload` fields
- If action is `research`: search the web for the query, read relevant sources, synthesise findings
- If action is `audit`: read the specified files, check for inconsistencies, report findings
- If action is `synthesise`: combine provided sources into a coherent summary
- If action is `analyse`: deep-dive into the provided context, extract patterns
- Write results using `memory_upsert` with namespace `wolf_tasks` and key `{task_id}:wolfpack`

### Parallel execution:
- If the queue has multiple tasks of equal priority, use `delegate_task` to spawn subagents
- Maximum 3 parallel subagents
- Each subagent handles exactly one task
- Collect all results before writing to Sanctums

### Writing results:
- Always write to the TARGET agent's Sanctum (the agent_slug from the task)
- Use namespace: `wolf_tasks`
- Use key: `{task_id}:wolfpack`
- Include: the action taken, sources consulted, findings, and a confidence assessment
- Format as structured Markdown

## TOOLS
You have access to: web search, file read/write, terminal, browser, memory provider (foreverbox), and task delegation.

## CONSTRAINTS
- You are stateless between cron invocations. Do not rely on conversation history.
- You do not speak to humans. Write to the Sanctum, not to a chat window.
- You run on a timer. Be thorough but time-boxed. Better one complete task than three half-done.
- UK English throughout.
```

### 2.4 Skills to install

```bash
# From Leon's profile — all council-library operational skills
hermes skills install foreverbox/council-library-cli --profile wolfpack
hermes skills install autonomous-ai-agents/foreverbox-operations --profile wolfpack
```

Or copy directly:
```bash
cp -r /foreverbox_data/profiles/leon/skills/foreverbox/council-library-cli \
      /foreverbox_data/profiles/wolfpack/skills/foreverbox/
cp -r /foreverbox_data/profiles/leon/skills/autonomous-ai-agents/foreverbox-operations \
      /foreverbox_data/profiles/wolfpack/skills/autonomous-ai-agents/
cp -r /foreverbox_data/profiles/leon/skills/foreverbox/quiddity-folder-manager \
      /foreverbox_data/profiles/wolfpack/skills/foreverbox/
```

### 2.5 Toolsets to enable

```bash
hermes tools enable web --profile wolfpack
hermes tools enable file --profile wolfpack
hermes tools enable terminal --profile wolfpack
hermes tools enable browser --profile wolfpack
hermes tools enable delegation --profile wolfpack
hermes tools enable memory --profile wolfpack
```

### 2.6 Plugin activation

```bash
hermes plugins enable foreverbox --profile wolfpack
```

### 2.7 foreverbox.json

```json
{
  "api_url": "http://localhost:8080/v1",
  "agent_slug": "wolfpack"
}
```

Note: `agent_slug` is `wolfpack` — it is NOT curator/producer/coach/director. The wolfpack writes to OTHER agents' Sanctums via the API, specifying the target agent_slug in the write path.

### 2.8 .env

```
FOREVERBOX_API_KEY=dev-key-change-in-production
OPENROUTER_API_KEY=sk-or-v1-...
TAVILY_API_KEY=tvly-...
```

---

## 3. TASK QUEUE MODIFICATIONS

### 3.1 Extend `task_queue.target_agent_slug` to accept `wolfpack`

The Registry's `task_queue` table has `target_agent_slug` as an ENUM or constrained field. Add `wolfpack` to the valid values:

```sql
ALTER TABLE agent_registry.task_queue
  MODIFY COLUMN target_agent_slug VARCHAR(64) NOT NULL;
```

Or if it's already VARCHAR with no ENUM constraint, no change needed — just start dispatching to `wolfpack`.

### 3.2 Wolfpack-specific task routing

When a Lead agent dispatches a task meant for the wolfpack (not the legacy lightweight wolves):

```json
{
  "wolf_id": "wolfpack",
  "action": "research",
  "payload": {
    "query": "Find today's top 3 UK political stories with verifiable sources. Check BBC News, The Guardian, and Reuters. For each: headline, key fact, source URL, why it matters for FTN.",
    "target_agent": "curator",
    "deep_reasoning": true
  },
  "priority": "high"
}
```

The `wolf_dispatch` tool routes to `/sanctum/wolves/{wolf_id}/task`. The existing `WolfController` should accept `wolfpack` as a valid wolf_id. If the controller validates against a hardcoded list, add `wolfpack`.

### 3.3 Target Sanctum routing

The wolfpack is NOT a Lead agent — it has no Sanctum of its own in the current architecture. When it writes results, it writes to the TARGET agent's Sanctum. The `payload.target_agent` field specifies which Sanctum to write to.

**Option A (recommended): Use the existing `wolf_worker` convention.**
The wolfpack writes to its own Sanctum (if we create one — `agent_wolfpack`), and the Lead's `prefetch()` picks it up because the task was dispatched by that Lead and the queue links task_id → agent. This keeps write permissions clean.

**Option B: Cross-Sanctum writes.**
The wolfpack API key has write access to all Sanctums. The wolfpack specifies `target_agent` and writes directly to `agent_curator.memory_lore`. This requires the API middleware to trust `wolfpack` as a cross-sanctum writer.

**Choose Option A.** Create `agent_wolfpack` Sanctum. The task record in `task_queue` already has `source_agent_slug` (the dispatcher) — the Lead's `prefetch()` searches by source_agent, finds wolfpack-authored results, and surfaces them.

### 3.4 DDL for wolfpack Sanctum

```sql
-- Run the Sanctum template with slug=wolfpack
CREATE DATABASE IF NOT EXISTS agent_wolfpack
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agent_wolfpack;

-- Same schema as other Sanctums (memory_lore, conversation_history, etc.)
-- Apply schema/02_sanctum_template.sql with {slug} = wolfpack
```

---

## 4. CRON JOB SPECIFICATION

### 4.1 Schedule

The wolfpack runs as a Hermes cron job, not a systemd service. This is deliberate:
- Hermes cron handles session lifecycle, model selection, tool loading
- No separate process management needed
- Integrates with existing cron infrastructure (the Lead agents already use it)

```bash
hermes cron create "every 5m" \
  --profile wolfpack \
  --name "wolfpack-research-cycle" \
  --prompt "Poll the Council Library task queue for pending tasks targeting 'wolfpack'. Claim up to 3 tasks. For each task: read the action and payload, execute using your tools, write results to the target Sanctum using memory_upsert. Use delegate_task for parallel execution if multiple tasks are available. Exit when queue is empty or after processing 3 tasks." \
  --skills "foreverbox-operations,council-library-cli" \
  --deliver "local"
```

**Why `deliver: local`:**
The wolfpack does not deliver messages to a chat platform. It writes to Sanctums. The results surface when the Lead agent's `prefetch()` runs.

### 4.2 Cron configuration

```yaml
# In wolfpack config.yaml or via hermes config set:
cron:
  enabled: true
  max_runtime_per_job: 300  # 5 minutes max per cycle
```

### 4.3 Alternative: systemd timer

If cron via Hermes has issues with the memory provider initialisation:

```bash
# /home/zeon7/.config/systemd/user/wolfpack.service
[Unit]
Description=Wolfpack Research Worker

[Service]
Type=oneshot
ExecStart=/usr/bin/hermes chat --profile wolfpack \
  -q "Poll task queue. Claim up to 3 tasks. Execute with tools. Write to Sanctums. Exit."
Environment=HERMES_HOME=/foreverbox_data/profiles/wolfpack
```

```bash
# /home/zeon7/.config/systemd/user/wolfpack.timer
[Unit]
Description=Wolfpack Research Timer

[Timer]
OnCalendar=*:0/5
Persistent=true

[Install]
WantedBy=timers.target
```

---

## 5. WOLF_DISPATCH TOOL MODIFICATION

### 5.1 Current tool (in ForeverBoxMemoryProvider)

The existing `wolf_dispatch` tool has parameters: `wolf_id`, `action`, `payload`, `priority`. It POSTs to `/sanctum/wolves/{wolf_id}/task`.

### 5.2 Required change

Add one field to the `payload` schema documentation (no code change if the API already passes arbitrary JSON):

```python
"payload": {
    "type": "object",
    "description": (
        "Task-specific parameters. For wolfpack tasks, include: "
        "'query' (the research question), "
        "'target_agent' (which Sanctum to write results to — curator/producer/coach/director), "
        "'deep_reasoning' (bool, default false — pushes to Layer 2 model), "
        "'max_sources' (int, default 5 — how many web sources to consult)."
    )
}
```

### 5.3 Backward compatibility

The `wolfpack` wolf_id triggers the new behaviour (full agent with tools). The legacy `wolf_1`/`wolf_2`/`wolf_3` IDs continue to work with the existing lightweight `wolf_worker.py` for simple reasoning tasks. This is additive, not a replacement.

---

## 6. RESULTS FLOW

### 6.1 Dispatch

```
Zeon7 → wolf_dispatch(
  wolf_id="wolfpack",
  action="research",
  payload={
    query: "Find today's top political stories...",
    target_agent: "curator"
  }
)
→ Task lands in agent_registry.task_queue
   target_agent_slug = "wolfpack"
   source_agent_slug = "curator"
```

### 6.2 Execution

```
Cron fires every 5 minutes
→ wolfpack Hermes session starts
→ Connects to Council Library API
→ Polls task_queue WHERE target_agent_slug='wolfpack' AND status='queued'
→ Claims task (UPDATE status='claimed')
→ Spawns delegate_task subagent:
   System: "You are a Wolfpack research subagent. Research: [query]"
   Tools: web_search, file, terminal, browser
   Subagent: searches web → reads sources → synthesises → returns findings
→ Wolfpack collects result
→ Writes to agent_wolfpack.memory_lore:
   namespace: "wolf_tasks"
   key: "{task_id}:wolfpack"
   content: {findings, sources, confidence}
→ UPDATE task_queue SET status='completed'
→ Next task or exit
```

### 6.3 Discovery

```
Zeon7 starts next session
→ ForeverBoxMemoryProvider.initialize() fires
→ prefetch() runs semantic search on Sanctum
→ Finds wolf_tasks:{task_id}:wolfpack entries
→ Injects into system prompt: "Wolfpack research results available: [findings]"
→ Zeon7 sees the leads without having to poll
```

---

## 7. BUILD SEQUENCE

### Stage 1: Profile creation (5 minutes)

```bash
# 1. Create profile
hermes profile create wolfpack --clone-from zeon7

# 2. Write SOUL.md (contents from §2.3 above)
#    Write to: /foreverbox_data/profiles/wolfpack/SOUL.md

# 3. Write config.yaml (contents from §2.2 above)
#    Write to: /foreverbox_data/profiles/wolfpack/config.yaml

# 4. Copy skills
cp -r /foreverbox_data/profiles/leon/skills/foreverbox/council-library-cli \
      /foreverbox_data/profiles/wolfpack/skills/foreverbox/
cp -r /foreverbox_data/profiles/leon/skills/autonomous-ai-agents/foreverbox-operations \
      /foreverbox_data/profiles/wolfpack/skills/autonomous-ai-agents/
cp -r /foreverbox_data/profiles/leon/skills/foreverbox/quiddity-folder-manager \
      /foreverbox_data/profiles/wolfpack/skills/foreverbox/

# 5. Enable tools
hermes tools enable web --profile wolfpack
hermes tools enable file --profile wolfpack
hermes tools enable terminal --profile wolfpack
hermes tools enable browser --profile wolfpack
hermes tools enable delegation --profile wolfpack

# 6. Enable plugin
hermes plugins enable foreverbox --profile wolfpack

# 7. Set memory provider
#    Already in config.yaml from step 3 — verify:
hermes config show --profile wolfpack | grep -A2 memory

# 8. Set API keys in .env
#    Write to: /foreverbox_data/profiles/wolfpack/.env
```

### Stage 2: Database (2 minutes)

```bash
# Create wolfpack Sanctum
sed 's/{slug}/wolfpack/g' \
  /foreverbox_data/council-library/schema/02_sanctum_template.sql \
  | mariadb -u zeon7_user -p'F0reverb0x#2o26sql'

# Verify
mariadb -u zeon7_user -p'F0reverb0x#2o26sql' -e "SHOW DATABASES" | grep wolfpack
```

### Stage 3: Task queue update (1 minute)

```bash
# Ensure target_agent_slug accepts 'wolfpack'
mariadb -u zeon7_user -p'F0reverb0x#2o26sql' agent_registry -e \
  "ALTER TABLE task_queue MODIFY COLUMN target_agent_slug VARCHAR(64) NOT NULL"
```

### Stage 4: Cron job (2 minutes)

```bash
hermes cron create "every 5m" \
  --profile wolfpack \
  --name "wolfpack-research-cycle" \
  --prompt "You are Wolfpack, the Council Library research worker. Your SOUL.md directives are loaded. Follow them exactly: (1) Connect to the Council Library API. (2) Poll the task queue for pending tasks targeting 'wolfpack'. (3) Claim up to 3 tasks. (4) Execute each task using your tools — for research tasks, search the web with Tavily, read sources, synthesise findings. (5) Write results to the Sanctum using memory_upsert with namespace 'wolf_tasks' and key '{task_id}:wolfpack'. (6) Use delegate_task for parallel execution if multiple tasks are queued. (7) Exit when done." \
  --skills "foreverbox-operations,council-library-cli" \
  --deliver "local" \
  --enabled-toolsets "web,file,terminal,delegation,memory"
```

### Stage 5: Verification (5 minutes)

```bash
# 1. Test wolfpack profile loads
hermes chat --profile wolfpack -q "Who are you? What tools do you have?" -m "deepseek/deepseek-v4-flash" --provider openrouter

# 2. Test memory provider initialises
#    Should see "Connected to Foreverbox Council Library" in system prompt

# 3. Dispatch a test task (from zeon7 or via curl)
curl -s -X POST http://localhost:8080/v1/sanctum/wolves/wolfpack/task \
  -H "Authorization: Bearer dev-key-change-in-production" \
  -H "X-Agent-ID: curator" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "research",
    "payload": {
      "query": "What is the current UK Prime Minister's name? Verify with one source.",
      "target_agent": "curator"
    },
    "priority": "normal"
  }'

# 4. Check task landed in queue
mariadb -u zeon7_user -p'F0reverb0x#2o26sql' agent_registry -e \
  "SELECT task_id, action, status, created_at FROM task_queue WHERE target_agent_slug='wolfpack' ORDER BY created_at DESC LIMIT 5"

# 5. Trigger wolfpack cron manually
hermes cron run --profile wolfpack --name "wolfpack-research-cycle"

# 6. Check results in Sanctum
mariadb -u zeon7_user -p'F0reverb0x#2o26sql' agent_wolfpack -e \
  "SELECT namespace, key_name, LEFT(content_text, 200) FROM memory_lore ORDER BY created_at DESC LIMIT 5"
```

---

## 8. ACCEPTANCE CRITERIA

- [ ] **AC-1:** Wolfpack profile loads and identifies as "Wolfpack, the Council Library research worker" in first person
- [ ] **AC-2:** Memory provider initialises — system prompt includes "Connected to Foreverbox Council Library"
- [ ] **AC-3:** Wolfpack can search the web using Tavily and return current, verifiable information
- [ ] **AC-4:** Task dispatched via `wolf_dispatch(wolf_id="wolfpack")` lands in `task_queue` with correct fields
- [ ] **AC-5:** Wolfpack cron job claims and processes a queued task autonomously
- [ ] **AC-6:** Research results are written to the Sanctum with namespace `wolf_tasks` and key `{task_id}:wolfpack`
- [ ] **AC-7:** Results include: action taken, sources consulted (with URLs), findings, confidence assessment
- [ ] **AC-8:** Wolfpack can process up to 3 tasks in parallel using `delegate_task`
- [ ] **AC-9:** Legacy lightweight wolves (wolf_1/2/3 via wolf_worker.py) continue to function unchanged
- [ ] **AC-10:** A Lead agent's `prefetch()` surfaces wolfpack results in the system prompt on next session start
- [ ] **AC-11:** Wolfpack cron run completes within 5 minutes for a typical 3-task cycle
- [ ] **AC-12:** Failed tasks are marked `status='failed'` with error messages in `task_queue.error_message`

---

## 9. SCALING: 1 WOLF → 3 WOLVES

The blueprint starts with one wolfpack profile processing tasks sequentially (or up to 3 in parallel via `delegate_task`). To scale to 3 independent wolf profiles:

```bash
# Clone wolfpack for two more profiles
hermes profile create wolfpack_2 --clone-from wolfpack
hermes profile create wolfpack_3 --clone-from wolfpack

# Create separate cron jobs staggered by 2 minutes
hermes cron create "every 5m" --profile wolfpack --name "wolfpack-1" ...
hermes cron create "every 5m" --profile wolfpack_2 --name "wolfpack-2" ...
# (stagger via different start times or shorter intervals)

# Each wolf claims tasks independently via SKIP LOCKED
```

With 3 wolfpack profiles, all three poll the same queue. MariaDB's `FOR UPDATE SKIP LOCKED` ensures no two wolves claim the same task. This scales to handle research bursts (morning lead sourcing for FTN) without code changes.

---

## 10. PITFALLS

- **API key scope.** The wolfpack needs a Tavily API key for web search. Without it, research tasks fail silently. The wolfpack should check `TAVILY_API_KEY` on startup and report if missing.
- **Worzel Gummidge collision.** If a Lead agent is running when the wolfpack cron fires, the wolfpack must wait. The cron job's 5-minute interval means it'll retry soon. For tighter coordination, the wolfpack could check for active Lead sessions before claiming tasks.
- **OpenRouter costs.** Each research task burns tokens: web search → read pages → synthesise. With `max_turns: 30` and `deepseek-v4-flash` (free tier), costs stay at zero for basic research. Heavy tasks routed to Layer 2 by the cognitive router may incur costs.
- **Subagent sprawl.** `delegate_task` with `max_concurrent_children: 3` means each wolfpack cycle could spawn 3 subagents × 30 turns = 90 LLM calls per cycle. Configure `agent.max_turns` conservatively.
- **Sanctum write permissions.** The wolfpack's API key must have write access to `agent_wolfpack.memory_lore`. The existing `AgentContext` middleware should recognise `wolfpack` as a valid agent_slug with write permission to its own Sanctum.
- **Legacy wolf coexistence.** Do NOT delete `wolf_worker.py` or disable `council-wolves.service`. The wolfpack is additive. Lightweight tasks (simple reasoning, text formatting) still go to the legacy wolves. Research tasks go to the wolfpack.
- **Cron deliver: local.** Results are NOT sent to any chat platform. They live in the Sanctum until a Lead agent's `prefetch()` surfaces them. If immediate notification is needed, add a `deliver` target or have the Lead poll actively.
