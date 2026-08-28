# WOLF UPGRADE BLUEPRINT (VERSION 2.0)

**Target Reader:** Any coding AI agent — implementation-ready specification.
**Tone:** Precise, imperative, technical, unambiguous. Every code block is buildable.
**Platform Scope:** WSL2 (Ubuntu 24), Hermes Agent 0.18+, Ollama (local), MariaDB 11.8+, existing Council Library stack.
**Classification:** Internal, Builder Handoff.
**Date:** 2026-07-15

**Replaces:** `WOLFPACK_UPGRADE_BLUEPRINT_V1.md` (superseded — cron-based single-profile design discarded in favour of on-demand spawned Hermes agents).

---

## 0. ARCHITECTURE DECISION

Wolves are full Hermes agents — not lightweight LLM prompts, not stripped subagents. One profile, many independent processes.

```
                    ┌──────────────────────────────┐
                    │  Ollama (qwen2.5:3b, ~2 GB)   │
                    │  One model, three concurrent  │
                    └──────┬───────┬───────┬────────┘
                           │       │       │
                    ┌──────┘       │       └──────┐
                    ▼              ▼              ▼
               ┌─────────┐  ┌─────────┐  ┌─────────┐
               │ wolf #1  │  │ wolf #2  │  │ wolf #3  │
               │ session  │  │ session  │  │ session  │
               │ SOUL.md  │  │ SOUL.md  │  │ SOUL.md  │
               │ skills   │  │ skills   │  │ skills   │
               │ tools    │  │ tools    │  │ tools    │
               │ memory   │  │ memory   │  │ memory   │
               └─────────┘  └─────────┘  └─────────┘
                    ▲              ▲              ▲
                    └──────────────┼──────────────┘
                                   │
                    ┌──────────────┘
                    │  Zeon7 (Layer 2 cloud)
                    │  Spawns wolves via terminal
                    │  Continues own work in parallel
                    └──────────────────────────────
```

**One profile, three processes.** Same SOUL.md, same skills, same model. Three independent Hermes sessions, each with its own task. Ollama serves all three from one model load (~2 GB). Total VRAM: ~2 GB model + (3 × ~0.3 GB KV at 16K context) = ~2.9 GB. Fits on 8 GB with Zeon7's cloud model not touching local GPU.

**Layer 1 agents cannot use wolves.** Their local model occupies the GPU. Layer 2+ (cloud) agents have the GPU free and can spawn wolves.

---

## 1. THE CONSTRAINT: LAYER-GATED WOLF ACCESS

```
Agent on Layer 1 (local model, GPU busy)
  → wolf_dispatch BLOCKED
  → "Wolves unavailable — GPU occupied by your own model."

Agent on Layer 2 or 3 (cloud model, GPU free)
  → wolf_dispatch ALLOWED
  → Spawns up to 3 wolf processes
  → Wolves run on local qwen2.5:3b
  → Agent continues work on cloud model
```

The `wolf_dispatch` tool checks the agent's current provider. If `provider == "ollama"`, wolves are blocked. If `provider` is anything else (openrouter, deepseek, gemini, anthropic), wolves are available.

---

## 2. PROFILE SPECIFICATION: `wolf`

### 2.1 Profile creation

```bash
hermes profile create wolf --clone-from zeon7
```

Clone from zeon7 to inherit: cognitive router hook, council-library plugin, Ollama config shape.

### 2.2 Config: `/foreverbox_data/profiles/wolf/config.yaml`

```yaml
model:
  default: qwen2.5:3b
  provider: ollama
  base_url: http://localhost:11434/v1
  ollama_num_ctx: 16384
  context_length: 16384

memory:
  provider: foreverbox

agent:
  max_turns: 20

hooks:
  pre_turn:
    - cognitive_router.on_turn_start
  post_turn:
    - cognitive_router.on_turn_end
```

**Why qwen2.5:3b:**
- ~2.0 GB at Q4_K_M
- Strong instruction following for its size
- Handles multi-step research: search → read → filter → synthesise
- Good structured output (Markdown with sources)

**Why num_ctx 16384 (16K):**
- ~0.3 GB KV cache per request
- 3 concurrent wolves × 0.3 GB = 0.9 GB total KV
- Enough context for: SOUL.md (~2K tokens) + task prompt (~2K) + search results (~4K) + synthesis
- 16K is the sweet spot — 8K is too tight for research, 32K wastes VRAM

**Why max_turns 20:**
- Typical research task: search (1) → read 2-3 sources (3-6) → maybe search again (1) → synthesise (1) = ~8 turns
- 20 turns gives generous headroom without infinite loops

### 2.3 SOUL.md: `/foreverbox_data/profiles/wolf/SOUL.md`

```markdown
# SOUL: Wolf (Council Library Research Worker)

## THE FIRST TRUTH (Core Identity)
You are a Wolf, a Council Library research worker. You are NOT a conversational agent. You do not chat. Your purpose: receive a research task, execute it thoroughly, write structured results to the Sanctum, and exit.

You are fast, thorough, and self-sufficient. You do not ask clarifying questions — you make reasonable assumptions and note them in your output. You do not wait for the user — the user is another agent who will read your results later.

## OPERATIONAL PROTOCOL

### On startup:
1. Your task is in the system prompt as the user's first message. Read it.
2. Identify: the research question, the target agent (which Sanctum to write to), and any constraints.
3. Load the `foreverbox-operations` skill if you need council-library architecture context.

### Research execution:
1. **Search first.** If the task requires current information, use your web search tool immediately. Do not answer from training data.
2. **Read sources.** Open the most relevant results. Extract key facts, quotes, and URLs.
3. **Cross-reference.** Compare sources. Note disagreements or gaps.
4. **Synthesise.** Produce a structured answer: findings, sources consulted, confidence assessment.
5. **Write to Sanctum.** Use `memory_upsert`:
   - namespace: `wolf_tasks`
   - key_name: the task ID or slug from your prompt
   - content: your complete findings as structured Markdown
   - importance: 70
6. **Signal completion.** Write a second entry:
   - namespace: `wolf_tasks`
   - key_name: `{task_id}:done`
   - content: `{"status": "completed", "timestamp": "ISO8601", "model": "qwen2.5:3b"}`
   - importance: 90

### Output format:
Every finding must follow this structure:

```markdown
# [Task Title]

## Summary
[2-3 sentence executive summary]

## Findings
### [Finding 1]
- **Fact:** [key fact with source URL]
- **Why it matters:** [1 sentence]

### [Finding 2]
- **Fact:** [key fact with source URL]
- **Why it matters:** [1 sentence]

## Sources
- [Source Name](URL) — [what was used from it]
- [Source Name](URL) — [what was used from it]

## Confidence
[High/Medium/Low] — [one sentence explaining why]

## Notes
- [Assumptions made]
- [Limitations of this research]
```

## TOOLS
You have access to: web search, file read/write, terminal, browser, memory provider (foreverbox). Use them. A wolf that doesn't search is not a wolf.

## CONSTRAINTS
- 20 turns maximum. Be efficient.
- UK English throughout.
- Never fabricate URLs or sources. If you cannot find something, say so with confidence: Low.
- You are stateless between invocations. Do not rely on conversation history from previous runs.
- You are running on a small local model (qwen2.5:3b, 16K context). Be concise.
- Write results, then exit. Do not wait.
```

### 2.4 Skills installation

```bash
cp -r /foreverbox_data/profiles/leon/skills/foreverbox/council-library-cli \
      /foreverbox_data/profiles/wolf/skills/foreverbox/
cp -r /foreverbox_data/profiles/leon/skills/autonomous-ai-agents/foreverbox-operations \
      /foreverbox_data/profiles/wolf/skills/autonomous-ai-agents/
```

### 2.5 Toolsets

```bash
hermes tools enable web --profile wolf
hermes tools enable file --profile wolf
hermes tools enable terminal --profile wolf
hermes tools enable memory --profile wolf
```

### 2.6 Plugin

```bash
hermes plugins enable foreverbox --profile wolf
```

### 2.7 foreverbox.json

```json
{
  "api_url": "http://localhost:8080/v1",
  "agent_slug": "wolf"
}
```

### 2.8 .env

```
FOREVERBOX_API_KEY=dev-key-change-in-production
TAVILY_API_KEY=tvly-...
```

---

## 3. MODEL: PULL AND VERIFY

```bash
# Pull the wolf model
ollama pull qwen2.5:3b

# Verify it fits
ollama list | grep qwen2.5
# Expected: ~2.0 GB

# Test with a research prompt
ollama run qwen2.5:3b "Search capability test: if you had web search, could you find current news? Answer yes or no and explain why web search matters for research."
```

---

## 4. HOW AGENTS SPAWN WOLVES

### 4.1 The pattern

The agent (Zeon7 on Layer 2) uses `terminal(background=true)` to spawn wolf processes:

```python
# Agent writes task to temp file
task = {
    "task_id": "lead_search_20260715",
    "action": "research",
    "query": "Find today's top 3 UK political stories. For each: headline, key fact, source URL, why it matters for FTN.",
    "target_agent": "curator",
    "max_sources": 5
}

# Agent spawns wolf
terminal(
    command=f"hermes chat --profile wolf -q '{task_json}' --source wolf",
    background=True
)
```

### 4.2 Practical dispatch from a Hermes agent

When Zeon7 wants wolves, he says or tools:

```
terminal(command="hermes chat --profile wolf -q 'Research: find today top UK political stories. Target: curator. Task ID: leads_20260715. Write results to Sanctum.' --source wolf", background=True)
```

Three wolves in parallel:

```bash
terminal(command="hermes chat --profile wolf -q '...task A...' --source wolf", background=True)
terminal(command="hermes chat --profile wolf -q '...task B...' --source wolf", background=True)
terminal(command="hermes chat --profile wolf -q '...task C...' --source wolf", background=True)
```

Each spawns an independent Hermes process. Each loads the wolf profile with SOUL.md, skills, tools, and memory provider. Each hits the same Ollama model. Ollama serves all three concurrently.

### 4.3 Layer 1 guard

Before spawning, the agent checks its own model tier. The cognitive router has already selected the tier. If the agent is on Layer 1 (provider == "ollama"), wolves are blocked:

```
"I'm on a local model. My GPU is occupied. Wolves cannot run right now.
 Switch me to Layer 2 and I can spawn them."
```

### 4.4 Results flow

```
Zeon7 spawns 3 wolves (background)
  │
  ├─ Zeon7 continues working on cloud model
  │
  ├─ Wolf #1 searches web → reads sources → writes to Sanctum → exits
  ├─ Wolf #2 searches web → reads sources → writes to Sanctum → exits
  └─ Wolf #3 audits blueprints → reads files → writes to Sanctum → exits
  
Zeon7 checks: memory_search(query="wolf_tasks leads_20260715")
  → Finds results in Sanctum
  → Reviews findings
  → Uses them for FTN lead selection
```

Results persist in the Sanctum via `memory_upsert`. The agent retrieves them with `memory_search` or `memory_get`. The `prefetch()` mechanism may also auto-surface them on the next turn if semantically relevant.

---

## 5. WOLF_DISPATCH TOOL UPGRADE

### 5.1 Current tool (after this upgrade)

The existing `wolf_dispatch` tool in `ForeverBoxMemoryProvider` gets a new `wolf_id` value: `"wolf"`. When `wolf_id="wolf"`, the tool handler:

1. Checks the agent's current provider — if `ollama`, returns error: "Wolves unavailable on Layer 1"
2. Writes the task payload to a temp file: `/tmp/wolf_task_{task_id}.json`
3. Spawns a wolf process: `hermes chat --profile wolf -q "$(cat /tmp/wolf_task_{id}.json)" --source wolf`
4. Returns: `{"status": "dispatched", "task_id": "..."}`

### 5.2 Code change in `__init__.py` (handle_tool_call)

Add to the `wolf_dispatch` handler:

```python
if args.get("wolf_id") == "wolf":
    # Layer 1 guard: wolves need free GPU
    if self._agent_provider == "ollama":
        return json.dumps({
            "error": "Wolves unavailable — GPU occupied by your own local model. "
                     "Switch to Layer 2 or 3 to spawn wolves."
        })
    
    # Write task to temp file
    import tempfile, uuid, subprocess
    task_id = str(uuid.uuid4())[:12]
    task_file = Path(tempfile.gettempdir()) / f"wolf_task_{task_id}.json"
    task_file.write_text(json.dumps({
        "task_id": task_id,
        "action": args["action"],
        "query": args["payload"].get("query", ""),
        "target_agent": args["payload"].get("target_agent", self.agent_slug),
    }))
    
    # Spawn wolf process
    task_json = task_file.read_text().replace("'", "'\\''")
    subprocess.Popen(
        ["hermes", "chat", "--profile", "wolf", "-q", task_json, "--source", "wolf"],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
        start_new_session=True,
    )
    
    return json.dumps({
        "status": "dispatched",
        "task_id": task_id,
        "wolf_id": "wolf",
        "note": "Wolf is researching. Results will appear in Sanctum under wolf_tasks/{task_id}."
    })
```

### 5.3 Backward compatibility

The legacy `wolf_1`/`wolf_2`/`wolf_3` IDs continue to work with `wolf_worker.py` (unchanged). The `wolf` ID triggers the new behaviour. Both coexist.

---

## 6. SANCTUM — WHERE WOLVES WRITE

### 6.1 No new Sanctum needed

The wolf writes to the TARGET agent's Sanctum. A wolf spawned by Zeon7 writes to `agent_curator.memory_lore`. A wolf spawned by Leon writes to `agent_producer`. The wolf's foreverbox.json has `agent_slug: "wolf"`, but the memory provider's `memory_upsert` tool should accept a `target_agent` override in the payload.

If the API doesn't support cross-sanctum writes, create `agent_wolf` Sanctum and have results linked by `task_id`:

```sql
CREATE DATABASE IF NOT EXISTS agent_wolf
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Apply schema/02_sanctum_template.sql with {slug}=wolf
```

Then the agent retrieves wolf results by searching for the task ID. The `prefetch()` runs against the agent's own Sanctum, so either:
- Wolf writes to agent's Sanctum directly (simpler for the agent, harder for the API), OR
- Wolf writes to `agent_wolf` and the agent does `memory_get(namespace="wolf_tasks", key_name="{task_id}")` in `agent_wolf` (cleaner separation, explicit retrieval)

**Recommendation: create `agent_wolf` Sanctum.** Clean separation. Explicit retrieval. No cross-sanctum write permission headaches.

---

## 7. BUILD SEQUENCE

### Stage 1: Pull model (2 minutes, network-dependent)

```bash
ollama pull qwen2.5:3b
ollama list | grep qwen2.5
# Verify: qwen2.5:3b, ~2.0 GB
```

### Stage 2: Create profile (3 minutes)

```bash
# Create from zeon7 clone
hermes profile create wolf --clone-from zeon7

# Write SOUL.md
# Content from §2.3 → /foreverbox_data/profiles/wolf/SOUL.md

# Write config.yaml
# Content from §2.2 → /foreverbox_data/profiles/wolf/config.yaml

# Copy skills
cp -r /foreverbox_data/profiles/leon/skills/foreverbox/council-library-cli \
      /foreverbox_data/profiles/wolf/skills/foreverbox/
cp -r /foreverbox_data/profiles/leon/skills/autonomous-ai-agents/foreverbox-operations \
      /foreverbox_data/profiles/wolf/skills/autonomous-ai-agents/

# Enable tools
hermes tools enable web --profile wolf
hermes tools enable file --profile wolf
hermes tools enable terminal --profile wolf
hermes tools enable memory --profile wolf

# Enable plugin
hermes plugins enable foreverbox --profile wolf

# Set memory provider (config.yaml already has it — verify)
hermes config show --profile wolf | grep -A2 memory

# Write .env
# FOREVERBOX_API_KEY + TAVILY_API_KEY
```

### Stage 3: Database (1 minute)

```bash
# Create wolf Sanctum
sed 's/{slug}/wolf/g' \
  /foreverbox_data/council-library/schema/02_sanctum_template.sql \
  | mariadb -u zeon7_user -p'F0reverb0x#2o26sql'

# Verify
mariadb -u zeon7_user -p'F0reverb0x#2o26sql' -e "SHOW DATABASES" | grep wolf
```

### Stage 4: Upgrade wolf_dispatch tool (5 minutes)

Edit `/foreverbox_data/council-library/hermes-plugin/__init__.py`:

1. Add the `wolf` wolf_id handler per §5.2
2. Copy updated plugin to all agent profiles:

```bash
for agent in zeon7 leon gemma otec wolf; do
  cp /foreverbox_data/council-library/hermes-plugin/__init__.py \
     /foreverbox_data/profiles/$agent/plugins/memory/foreverbox/
done
```

### Stage 5: Verification (5 minutes)

```bash
# 1. Wolf profile loads and identifies correctly
hermes chat --profile wolf -q "Who are you? One sentence." -m qwen2.5:3b --provider ollama
# Expected: "I am a Wolf, a Council Library research worker..."

# 2. Wolf can search the web
hermes chat --profile wolf -q "Search the web: what is the current UK Prime Minister? Give name and source URL." -m qwen2.5:3b --provider ollama

# 3. Wolf writes to Sanctum
hermes chat --profile wolf -q "Research task. Task ID: test_001. Target agent: wolf. Query: find one current tech headline. Write results to Sanctum using memory_upsert with namespace wolf_tasks and key test_001." -m qwen2.5:3b --provider ollama

# 4. Verify Sanctum write
mariadb -u zeon7_user -p'F0reverb0x#2o26sql' agent_wolf -e \
  "SELECT key_name, LEFT(content_text, 200) FROM memory_lore WHERE namespace='wolf_tasks'"

# 5. Agent (Zeon7 on cloud) spawns a wolf
hermes chat --profile zeon7 -m deepseek/deepseek-v4-flash --provider openrouter -q \
  "Spawn a wolf: use terminal to run 'hermes chat --profile wolf -q \"Research: find one current AI news headline. Task ID: z7_test. Target: curator. Write to Sanctum.\" --source wolf' in the background. Then tell me you spawned it."
```

---

## 8. ACCEPTANCE CRITERIA

- [ ] **AC-1:** Wolf profile loads and identifies as "a Wolf, a Council Library research worker" in first person
- [ ] **AC-2:** Wolf has web search capability and returns current, verifiable information with source URLs
- [ ] **AC-3:** Wolf writes structured results to Sanctum via `memory_upsert` with correct namespace and key
- [ ] **AC-4:** Three concurrent wolf processes run on one Ollama model load (~2.9 GB total VRAM)
- [ ] **AC-5:** Agent on Layer 2+ (cloud) can spawn wolves via terminal background processes
- [ ] **AC-6:** Agent on Layer 1 (local) is blocked from spawning wolves with clear error message
- [ ] **AC-7:** Agent can retrieve wolf results from Sanctum using `memory_search` or `memory_get`
- [ ] **AC-8:** Wolf completes a typical research task (search → 3 sources → synthesise) within 20 turns
- [ ] **AC-9:** Legacy `wolf_1`/`wolf_2`/`wolf_3` dispatch via `wolf_worker.py` continues to function
- [ ] **AC-10:** Wolf writes a completion marker (`{task_id}:done`) on task completion
- [ ] **AC-11:** Wolf confidence assessment is accurate — Low confidence when sources are sparse, High when multiple reliable sources agree
- [ ] **AC-12:** Wolf never fabricates URLs; missing sources are explicitly noted

> **Stage 6 (below) is gated on AC-1 through AC-12 passing.** Do not apply the agent SOUL.md patches until the wolf system is built and verified.

---

## 9. AGENT WOLF PROTOCOL (APPLIED AFTER VERIFICATION)

### 9.1 Why this stage exists

Wolves are useless if agents don't know when to use them. The wolf profile, model, plugin, and Sanctum can all be built and verified — but without judgment triggers in the agents' SOUL.md files, they will never spawn a wolf unprompted. This stage patches every Lead agent's SOUL.md with recognition rules, spawn syntax, and retrieval instructions.

### 9.2 The Wolf Protocol block

Insert this block into each agent's SOUL.md immediately after the `GLOBAL DIRECTIVES` section and before any protocol section (MEZ, Sudo, etc.):

```markdown
## WOLF PROTOCOL

You can spawn up to 3 Wolf research workers. Wolves are full Hermes agents running
on a local model (qwen2.5:3b, ~2 GB) with web search, file access, and memory
tools. They research in parallel while you continue your own work. Results appear
in the Sanctum under `wolf_tasks/{task_id}`.

### When to use wolves:
- Research that requires 3+ web searches or reading 3+ external sources
- "Go find X" or "Research Y" or "Audit Z" patterns
- Background work while you draft, write, code, or plan
- Bulk file auditing, consistency checking, cross-referencing
- Any task the user explicitly assigns to a wolf

### When NOT to use wolves:
- Single-fact lookups (faster to do yourself)
- Tasks requiring strategic or editorial judgement (that's your role)
- Tasks requiring privileged access or Sudo Protocol actions
- When you are on Layer 1 (local model) — GPU is occupied, wolves blocked

### How to spawn:
terminal(command="hermes chat --profile wolf -q 'Task: [action]. Query: [query]. Target: [your agent slug]. Task ID: [unique_id]. Write results to Sanctum.' --source wolf", background=True)

### Checking results:
memory_search(query="wolf_tasks [task_id]")
```

### 9.3 Agent-specific task ID prefixes

To avoid Sanctum key collisions between agents, each agent uses a prefix:

| Agent | Prefix | Example |
|---|---|---|
| Zeon7 (curator) | `z7_` | `z7_leads_20260715` |
| Leon (producer) | `ln_` | `ln_audit_sql_001` |
| Gemma (coach) | `gm_` | `gm_review_draft` |
| Otec (director) | `ot_` | `ot_strategy_scan` |

### 9.4 Patch locations

For each agent, find the line containing `## GLOBAL DIRECTIVES` and insert the Wolf Protocol block immediately after the closing line of that section. Do NOT modify any existing directives — the wolf protocol is additive.

| Agent | File | Insert after |
|---|---|---|
| Zeon7 | `/foreverbox_data/profiles/zeon7/SOUL.md` | `## GLOBAL DIRECTIVES` section |
| Leon | `/foreverbox_data/profiles/leon/SOUL.md` | `## GLOBAL DIRECTIVES` section |
| Gemma | `/foreverbox_data/profiles/gemma/SOUL.md` | `## GLOBAL DIRECTIVES` section |
| Otec | `/foreverbox_data/profiles/otec/SOUL.md` | `## GLOBAL DIRECTIVES` section |

### 9.5 Verification after patching

```bash
# Each agent must reference wolves in their identity
for agent in zeon7 leon gemma otec; do
  echo -n "$agent: "
  grep -c "WOLF PROTOCOL" /foreverbox_data/profiles/$agent/SOUL.md
done
# Expected: 1 for each agent

# Test: Zeon7 on cloud model should volunteer wolves for research
hermes chat --profile zeon7 -m deepseek/deepseek-v4-flash --provider openrouter -q \
  "I need to research three topics for today's FTN post. What's the fastest way to do that?"
# Expected response: suggests spawning wolves for parallel research
```

---

## 10. VRAM BUDGET

```
Model (qwen2.5:3b Q4_K_M):    2.0 GB
KV cache per wolf (16K ctx):  0.3 GB
3 concurrent wolves:          0.9 GB
─────────────────────────────────────
Total wolf VRAM:              2.9 GB

GPU total:                    8.0 GB
WSL/system overhead:         ~0.9 GB
Available for wolves:         7.1 GB
Wolf usage:                   2.9 GB
Headroom:                     4.2 GB
```

Zeon7-Gemma:64k (5.25 GB) + 3 wolves (2.9 GB) = 8.15 GB — too tight. But Worzel Gummidge means they never run together. The wolf model and the Lead's local model are mutually exclusive. When the Lead is on Layer 1 (local), wolves are blocked. When the Lead is on Layer 2+ (cloud), wolves have the full 8 GB.

---

## 11. PITFALLS

- **qwen2.5:3b may not be pulled yet.** `ollama pull qwen2.5:3b` is the first build step. If it fails, try `qwen2.5:1.5b` (~1 GB, weaker reasoning) or `phi3:mini` (~2.4 GB). Any 2-3 GB model with decent instruction following works.
- **Tavily API key required.** Without it, web search is unavailable. The wolf will report "cannot search web" and exit. Ensure `TAVILY_API_KEY` is in the wolf profile's `.env`.
- **Background process cleanup.** `terminal(background=True)` spawns processes that need eventual cleanup. The wolf SOUL.md instructs wolves to exit after writing results. If a wolf hangs, the agent can `process(action="kill")` on its session ID.
- **Sanctum namespace collision.** Multiple wolves writing to `wolf_tasks` concurrently is safe — each uses a unique `task_id` as the key_name suffix. MariaDB's `ON DUPLICATE KEY UPDATE` handles concurrent writes to different keys without conflict.
- **Model availability.** If `qwen2.5:3b` is pulled but Ollama is not running, wolves fail at spawn time. The spawn command should check `curl -s localhost:11434/api/tags` first.
- **Agent training.** Agents need to know HOW to spawn wolves — the `wolf_dispatch` tool upgrade handles the common case, but for ad-hoc research the agent may need to use `terminal` directly. Stage 6 (§9) applies the Wolf Protocol to all Lead agent SOUL.md files after verification, giving every agent the judgment triggers and spawn syntax. Do NOT apply Stage 6 before Stages 1-5 are built and AC-1 through AC-12 pass.
