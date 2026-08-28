# Council Library

Sovereign memory architecture for the Foreverbox AI council. Self-hosted on physical hardware in Wales. Gives five AI agents durable, structured, privacy-gated memory with semantic vector search, parallel background research workers, and a three-tier cognitive router.

**Status: Stage 1 complete.** All planned work across 4 plans (Classification, Wolf Fix, Wolf Blueprint V3, Stage 1 Final) is complete. 7 services running across MariaDB, Apache, and Ollama.

## Quick Start

```bash
cd /foreverbox_data/council-library
# See the Operational Handbook for full instructions
```

Full instructions: [Operational Handbook V2](docs/Current%20Reference%20Documentation/COUNCIL_LIBRARY_HANDBOOK_V2.md)

## What's Built

| Component | Description | Status |
|-----------|-------------|--------|
| **Databases** | 7 MariaDB databases (Commons + 5 Sanctums + Registry), VECTOR(384) + FULLTEXT | Running |
| **PHP API** | Slim 4 REST API (30+ endpoints), Apache vhost on :8080 | Running |
| **Embedding Service** | all-MiniLM-L6-v2 on :8900, 384-dim vectors | Running |
| **Vector Search** | PHP-side cosine similarity with FULLTEXT fallback, random candidate limit 5000 | Running |
| **Ingestion Worker** | PHP CLI daemon — chunks, embeds, indexes files. Dead letter retry (max 5) | Running |
| **Folder Centroids** | 6 centroids from 1,131 chunk embeddings — enables auto-classification | Running |
| **Cognitive Router** | 3-tier model routing with scoring, privacy gate, budget gate | Running |
| **Wolf Task Queue** | Atomic claim via `FOR UPDATE SKIP LOCKED`, retry logic, dead letter | Running |
| **Sudo Protocol** | Confirmation code gate via privileged_action_log table | Running |
| **Wolves** | Ad-hoc Hermes spawns via fbox-wolf-spawn skill, 3 concurrent on 8 GB GPU | Running |
| **Sync Daemon** | Systemd timer, every 30 min — syncs files + sessions to API | Running |
| **Plans Dashboard** | Auto-generated Plans Progression + Reference Docs Log | Running |

## Architecture

Three pillars: **Python** (Hermes Agent + Cognitive Router), **MariaDB** (vector + relational memory), **PHP** (guarded REST API layer). Four database Wings: Commons (shared knowledge, 594 vectors), Sanctums (5 private agent memory chambers), Director (strategic plans), Registry (control plane: budgets, task queue, privileged actions).

Full architecture: [Handbook §2](docs/Current%20Reference%20Documentation/COUNCIL_LIBRARY_HANDBOOK_V2.md#2-architecture-overview)

## Agents & Model Routing

Every agent routes through three cognitive layers via the CognitiveRouter hook. Wolves use the local GPU (Zeon7-Gemma:64k, ~3.8 GB).

| Agent | Layer 1 — Intuitive Reflex | Layer 2 — Analytical Engine | Layer 3 — Deep Architect |
|-------|------------------------------|-----------------------------|--------------------------|
| **Curator** (Zeon7) | `Zeon7-Gemma:64k` (Ollama) | `deepseek-v4-flash` | `deepseek-v4-pro` |
| **Producer** (Leon) | `deepseek-v4-flash` | `qwen3-coder:free` | `deepseek-v4-pro` |
| **Coach** (Gemma) | `Zeon7-Gemma:64k` (Ollama) | `deepseek-v4-flash` | `deepseek-v4-pro` |
| **Director** (Otec) | `deepseek-v4-flash` | `nemotron-3-super:free` | `deepseek-v4-pro` |
| **Wolves** (all) | `Zeon7-Gemma:64k` (Ollama) | `deepseek-v4-flash` | `deepseek-v4-flash` (capped) |

The Cognitive Router scores each prompt on tool depth, task type, context size, retry loops, and delegation depth. Privacy gate scans for API keys, tokens, passwords, and file paths. Budget gate checks daily token limits.

## Repository

```
council-library/
├── php-api/            # PHP 8.3 Slim 4 REST API — 8 controllers, 3 middleware
│   ├── config/         # quiddity_folders.yaml — 8-domain folder catalogue
│   └── src/
│       ├── Controller/ # Folder, Memory, Wolf, Soul, Conversation, Director
│       └── Middleware/  # Auth, AgentContext, PrivilegedActionGate
├── router/             # CognitiveRouter — tiered model routing + privacy/budget gates
│   ├── __init__.py     # 302-line CognitiveRouter class
│   └── router.yaml     # Model profiles and per-agent overrides
├── scripts/            # Workers, migration, centroids, embedding service
│   ├── ingestion_worker.php
│   └── generate_folder_centroids.py
├── docs/               # Documentation system
│   ├── Current Reference Documentation/
│   │   ├── COUNCIL_LIBRARY_HANDBOOK_V2.md
│   │   ├── MASTER_BRIEFING_V7.md
│   │   ├── ARCHITECTURE_BLUEPRINT_V3.md
│   │   ├── Souls Configuration Canvas - V3.md
│   │   ├── Plans Progression.md
│   │   └── Reference Docs Log.md
│   ├── Current Completed Plans/
│   ├── archives/
│   └── Current Unstarted Plans/
└── docker/             # Docker Compose + Apache vhost
```

## Key Design Decisions

- **Shell wrappers over plugin tools**: The ForeverBox MemoryProvider plugin was found non-functional (Hermes exclusive-plugin detection bug prevents tool registration). All agent operations use bash scripts at `/foreverbox_data/bin/` called via `terminal()`.
- **Ad-hoc wolf spawning over systemd workers**: Wolves are spawned on demand via `hermes chat --profile wolf`, not as persistent system services. This conserves GPU memory for the spawning agent.
- **Procedural Layer 1 Guard over code-level enforcement**: Local agents default to blocking wolves via the `fbox-wolf-spawn` skill (Step 1). Merrill can override. This conserves the 8 GB GPU for the agent's own 64K context window.
- **Prompt-level Sudo Protocol with technical confirmation gate**: Privileged actions require a confirmation code generated via `bin2hex(random_bytes(4))` and stored in `privileged_action_log`. Merrill reads the code back to authorise execution.

## Docs

- [Operational Handbook V2](docs/Current%20Reference%20Documentation/COUNCIL_LIBRARY_HANDBOOK_V2.md) — installation, API reference, model routing, tools, wolves, troubleshooting, documentation system, Sudo Protocol
- [Master Briefing V7](docs/Current%20Reference%20Documentation/MASTER_BRIEFING_V7.md) — human-facing design philosophy
- [Architecture Blueprint V3](docs/Current%20Reference%20Documentation/ARCHITECTURE_BLUEPRINT_V3.md) — builder-facing technical specification (2,320 lines)
- [Souls Configuration Canvas V3](docs/Current%20Reference%20Documentation/Souls%20Configuration%20Canvas%20-%20V3.md) — all 5 SOUL.md snapshots
- [Plans Progression](docs/Current%20Reference%20Documentation/Plans%20Progression.md) — auto-generated plan status dashboard

## Environment

- Ubuntu 24.04 (WSL2)
- MariaDB 11.8.8
- Apache 2.4 + PHP 8.3
- Python 3.12 + sentence-transformers (all-MiniLM-L6-v2)
- Ollama (Zeon7-Gemma:64k, local GPU)
- OpenRouter (cloud models)

## License

Private repository. License to be determined.
