# WOLF UPGRADE BLUEPRINT (VERSION 3.0)

**Target Reader:** Any coding AI agent — implementation-ready specification.
**Tone:** Precise, imperative, technical, unambiguous. Every code block is buildable.
**Platform Scope:** WSL2 (Ubuntu 24), Hermes Agent 0.18+, Ollama (local), MariaDB 11.8+, existing Council Library stack.
**Classification:** Internal, Builder Handoff.
**Date:** 2026-07-18

**Replaces:** `WOLF_UPGRADE_BLUEPRINT_V2.md` (superseded — qwen2.5:3b model and memory_upsert tool approach discarded due to Hermes exclusive-plugin detection bug preventing tool registration).
**See also:** `WOLF_FIX_PLAN_SKILLS_BASED.md` — detailed investigation and rationale for the V3 changes.

---

## 0. WHAT CHANGED IN V3 - DONE

V2 assumed the foreverbox plugin's `memory_upsert` tool would be available to wolf agents. Investigation revealed the plugin defines 10 tools but registers none — the exclusive-plugin detection in Hermes skips the plugin's `register()` function entirely. The standard `memory` tool works via the `on_memory_write` hook, but custom-namespace operations require a different approach.

**V3 replaces the broken plugin tools with 7 fbox API-wrapper skills** shared via `Shared_Skills/foreverbox/`. These skills call the council-library REST API directly via `curl`, bypassing the plugin. They survive Hermes updates and require zero core changes.

**Model changed** from `qwen2.5:3b` to `Zeon7-Gemma:64k` — the 3B model could not maintain the Wolf persona reliably. Zeon7-Gemma is already available locally and shares a model load across concurrent wolves.

Everything else from V2 not addressed above remains in force.

---

## 1. ARCHITECTURE DECISION - DONE

Wolves are full Hermes agents — not lightweight LLM prompts, not stripped subagents. One profile, many independent processes.

```
                    ┌────────────────────────────────────┐
                    │  Ollama (Zeon7-Gemma:64k, ~3.8 GB)  │
                    │  One model load, three concurrent   │
                    └──────┬───────┬───────┬──────────────┘
                           │       │       │
                    ┌──────┘       │       └──────┐
                    ▼              ▼              ▼
               ┌─────────┐  ┌─────────┐  ┌─────────┐
               │ wolf #1  │  │ wolf #2  │  │ wolf #3  │
               │ session  │  │ session  │  │ session  │
               │ SOUL.md  │  │ SOUL.md  │  │ SOUL.md  │
               │ skills   │  │ skills   │  │ skills   │
               │ tools    │  │ tools    │  │ tools    │
               └─────────┘  └─────────┘  └─────────┘
                    ▲              ▲              ▲
                    └──────────────┼──────────────┘
                                   │
                    ┌──────────────┘
                    │  Agent (Layer 2 cloud)
                    │  Spawns wolves via terminal
                    │  Continues own work in parallel
                    └──────────────────────────────
```

**One profile, three processes.** Same SOUL.md, same skills, same model. Three independent Hermes sessions, each with its own task. Ollama serves all three from one model load (~3.8 GB). Total VRAM: ~3.8 GB model + (3 × ~1.2 GB KV at 64K context) = ~7.4 GB. Fits on 8 GB with headroom.

**Layer 1 agents cannot use wolves.** Their local model occupies the GPU. Layer 2+ (cloud) agents have the GPU free and can spawn wolves.

---

## 2. THE CONSTRAINT: LAYER-GATED WOLF ACCESS - DONE

```
Agent on Layer 1 (local model, GPU busy)
  → wolf_dispatch BLOCKED
  → "Wolves unavailable — GPU occupied by your own model."

Agent on Layer 2 or 3 (cloud model, GPU free)
  → wolf_dispatch ALLOWED
  → Spawns up to 3 wolf processes
  → Wolves run on Zeon7-Gemma:64k
  → Agent continues work on cloud model
```

**Implementation:** The provider gate is Step 1 of the `fbox-wolf-spawn` skill. Local agents default to blocking. Merrill can explicitly override.

---

## 3. PROFILE SPECIFICATION: `wolf` - DONE

### 3.1 Profile creation

```bash
hermes profile create wolf --clone-from zeon7
```

### 3.2 Config: `/foreverbox_data/profiles/wolf/config.yaml`

```yaml
model:
  default: Zeon7-Gemma:64k
  provider: ollama
  base_url: http://localhost:11434/v1
  ollama_num_ctx: 65536
  context_length: 65536

memory:
  provider: foreverbox

agent:
  max_turns: 20

hooks:
  pre_turn:
    - cognitive_router.on_turn_start
  post_turn:
    - cognitive_router.on_turn_end
  tavily:
    command: npx
    args:
      - -y
      - '@tavily/mcp-server'
    env:
      TAVILY_API_KEY: tvly-dev-xxx
```

### 3.3 SOUL.md

The wolf SOUL.md instructs the model to use `terminal()` with shell scripts at `/foreverbox_data/bin/` to write to the Sanctum. The SOUL explicitly warns the wolf NOT to call fbox scripts as native tools — they are bash scripts, not registered Hermes tools. Full SOUL.md is maintained at `/foreverbox_data/profiles/wolf/SOUL.md`.

Key operational protocol:
1. Search the web first
2. Read sources, cross-reference
3. Synthesise structured output
4. Write to Sanctum via `terminal("/foreverbox_data/bin/fbox-memory-upsert wolf_tasks {task_id} \"{findings}\"")`
5. Signal completion via `terminal("/foreverbox_data/bin/fbox-memory-upsert wolf_tasks {task_id}:done \"{\"status\": \"completed\"}\"")`

### 3.4 Skills

Skills symlinked from Shared_Skills:
```bash
rm -rf /foreverbox_data/profiles/wolf/skills/foreverbox
ln -sf /foreverbox_data/Shared_Skills/foreverbox /foreverbox_data/profiles/wolf/skills/foreverbox
```

All 19 Shared_Skills/foreverbox skills are available to the wolf via the symlink. The wolf primarily uses:

| Shell Script | Purpose |
|-------|---------|
| `/foreverbox_data/bin/fbox-memory-upsert` | Write research findings to Sanctum via `terminal()` |
| `/foreverbox_data/bin/fbox-memory-search` | Search past wolf results via `terminal()` |
| `/foreverbox_data/bin/fbox-memory-get` | Retrieve a specific wolf result via `terminal()` |
| `/foreverbox_data/bin/fbox-memory-list` | List all entries in a namespace via `terminal()` |

These are bash scripts called via `terminal()`, NOT native Hermes tools. The wolf SOUL explicitly warns against calling them as tools.

### 3.5 Toolsets

```bash
# Core tools for wolf operation
hermes tools enable web --profile wolf       # web search (essential)
hermes tools enable terminal --profile wolf  # shell script execution (required for Sanctum writes)
hermes tools enable file --profile wolf      # file read/write
hermes tools enable browser --profile wolf   # web browsing for source verification
```

Additional tools enabled on the live wolf: code_execution, vision, image_gen, tts, skills, todo, session_search, clarify, delegation, cronjob, computer_use.

**Note:** The `memory` tool is **disabled** on the wolf because the wolf uses shell scripts via `terminal()` for all Sanctum operations. Enabling `memory` would add a redundant tool that the wolf is explicitly trained not to use.

### 3.6 Plugin, foreverbox.json, .env

Plugin installed from Leon's profile. foreverbox.json sets `agent_slug: "wolf"`. .env contains FOREVERBOX_API_KEY and TAVILY_API_KEY.

---

## 4. SANCTUM — WHERE WOLVES WRITE - DONE

The wolf writes to `agent_wolf` Sanctum (created via the 02_sanctum_template.sql schema). The `/foreverbox_data/bin/fbox-memory-upsert` shell script sends `X-Agent-ID: wolf` to route writes to the correct database.

Retrieval: the spawning agent uses `/foreverbox_data/bin/fbox-memory-search` or `/foreverbox_data/bin/fbox-memory-get` to read wolf results from Sanctum.

---

## 5. HOW AGENTS SPAWN WOLVES - DONE

Agents use the `fbox-wolf-spawn` skill (in Shared_Skills/foreverbox). The skill handles provider checking, task ID generation, command construction, and background dispatch.

**Provider gate (Step 1 of the skill):**
- Cloud model: wolves allowed, GPU is free.
- Local model (ollama): wolves blocked by default. Report: "Wolves unavailable — GPU occupied by my local model. Switch me to Layer 2 or 3 to spawn wolves, or explicitly instruct me to proceed."
- Exception: Merrill can explicitly authorise a local agent to spawn wolves. This is rare.

**Short-form spawn command (for agents already familiar):**
```bash
hermes chat --profile wolf -q "Research task. Task ID: {id}. {query}. Write findings to Sanctum via terminal: /foreverbox_data/bin/fbox-memory-upsert wolf_tasks {id} \"{findings}\". Then signal completion via terminal: /foreverbox_data/bin/fbox-memory-upsert wolf_tasks {id}:done \"{\"status\": \"completed\"}\"." -m Zeon7-Gemma:64k --provider ollama --source wolf
```

Three wolves in parallel:
```python
terminal(command="hermes chat --profile wolf -q '...task A...' ...", background=True)
terminal(command="hermes chat --profile wolf -q '...task B...' ...", background=True)
terminal(command="hermes chat --profile wolf -q '...task C...' ...", background=True)
```

---

## 6. ACCEPTANCE CRITERIA - DONE

- [x] **AC-1:** Wolf profile loads and identifies as "a Wolf, a Council Library research worker" in first person
- [x] **AC-2:** Wolf has web search capability and returns current, verifiable information with source URLs
- [x] **AC-3:** Wolf writes structured results to Sanctum via shell wrappers at `/foreverbox_data/bin/`
- [x] **AC-4:** Three concurrent wolf processes run on one Ollama model load
- [x] **AC-5:** Agent on Layer 2+ (cloud) can spawn wolves via terminal background processes
- [x] **AC-6:** Agent on Layer 1 (local) defaults to blocking wolves, with Merrill override authority

**AC-6 note:** The Layer 1 guard is a procedural gate in the `fbox-wolf-spawn` skill (Step 1). Local agents refuse by default. Merrill can explicitly authorise an override. The guard is prompt-level rather than code-level; this is acceptable because it conserves the 8 GB GPU for the agent's own 64K context window and prevents accidental resource contention.

---

## 7. BUILD SEQUENCE (AFTER FIRST BUILD) - DONE

For a fresh installation — the wolf profile is already built and verified. Required steps:

1. Ensure `Zeon7-Gemma:64k` is available in Ollama
2. Clone or recreate the wolf profile (SOUL.md, config.yaml, toolsets, Shared_Skills symlink)
3. Create `agent_wolf` Sanctum in MariaDB
4. Create the `fbox-wolf-spawn` skill in Shared_Skills/foreverbox/
5. Verify with: `hermes chat --profile wolf -q "Who are you?" -m Zeon7-Gemma:64k --provider ollama --source wolf`
6. Test wolf spawn: `hermes chat --profile leon --provider openrouter -q "Load fbox-wolf-spawn and spawn one test wolf."`

The `--source wolf` flag tags wolf sessions for filtering in the Hermes session list. It is a valid Hermes CLI flag.
