# Council Library

Sovereign memory architecture for the Foreverbox AI council. Self-hosted on physical hardware in Wales. Gives four AI agents durable, structured, privacy-gated memory with semantic vector search, parallel background workers, and a three-tier cognitive router.

**Status: Phase 1 complete.** All six stages built, tested, and running.

## Quick Start

```bash
cd /foreverbox_data/council-library
chmod +x bin/council-library
./bin/council-library install --all
./bin/council-library status
```

Full instructions: [Operational Handbook](docs/HANDBOOK.md)

## What's Built

| Component | Description | Status |
|-----------|-------------|--------|
| **Database** | 6 MariaDB databases, 30+ tables, VECTOR(384) + FULLTEXT indexes | Running |
| **PHP API** | Slim 4 REST API (28 endpoints), Apache vhost on :8080 | Running |
| **Hermes Plugin** | ForeverBox MemoryProvider — 10 tools, full ABC implementation | Installed |
| **Embedding Service** | all-MiniLM-L6-v2 on :8900, 384-dim vectors | Running |
| **Vector Search** | PHP-side cosine similarity with FULLTEXT fallback | Running |
| **Ingestion Worker** | PHP CLI daemon — chunks, embeds, indexes files | Running |
| **Wolf Workers** | 3 per agent, model-routed LLM task executors | Running |
| **Cognitive Router** | 3-tier model routing with privacy gate + budget gate | Configured |
| **Installer CLI** | `council-library install/uninstall/enable/disable/status/doctor` | Built |
| **Centroids** | 4 folder centroids from 1,470 embedded chunks | Built |

## Architecture

Three pillars: **Python** (Hermes Agent + plugin + router), **MariaDB** (vector + relational memory), **PHP** (guarded REST API layer). Four database Wings: Commons (shared knowledge), Sanctums (private agent memory), Director (strategic plans), Registry (control plane).

Full architecture diagram and component map: [Handbook §2](docs/HANDBOOK.md#2-architecture-overview)

## Agents & Model Routing

Every agent routes through three cognitive layers. Wolves cap at Layer 2.

| Agent | Layer 1 — Intuitive Reflex | Layer 2 — Analytical Engine | Layer 3 — Deep Architect |
|-------|------------------------------|-----------------------------|--------------------------|
| **Curator** (Zeon7) | `Zeon7-Gemma:latest` (Ollama) | `deepseek-v4-flash` | `deepseek-v4-pro` |
| **Producer** (Leon) | `deepseek-v4-flash` | `qwen3-coder:free` | `deepseek-v4-pro` |
| **Coach** (Gemma) | `Zeon7-Gemma:latest` (Ollama) | `deepseek-v4-flash` | `deepseek-v4-pro` |
| **Director** (Otec) | `deepseek-v4-flash` | `nemotron-3-super:free` | `deepseek-v4-pro` |
| **Wolves** (all) | `gemini-3.1-flash-lite` | `deepseek-v4-flash` | `deepseek-v4-flash` (capped) |

## Repository

```
council-library/
├── bin/                # council-library CLI installer
├── schema/             # MariaDB DDL — Commons, Sanctums, Registry, Director
├── php-api/            # PHP 8.3 Slim 4 REST API — 8 controllers, 3 middleware
│   └── config/         # quiddity_folders.yaml — folder catalogue
├── hermes-plugin/      # Python MemoryProvider — 10 tools, 4 hooks
├── router/             # CognitiveRouter — tiered model routing + hook
│   └── router.yaml     # Model profiles and per-agent overrides
├── scripts/            # Workers, migration, centroids, embedding service
├── docs/               # Blueprint V3, Master Briefing V6, Handbook
└── docker/             # Docker Compose + Apache vhost
```

## Docs

- [Operational Handbook](docs/HANDBOOK.md) — installation, API reference, model routing, tools, wolves, troubleshooting
- [Architecture Blueprint V3](docs/ARCHITECTURE_BLUEPRINT_V3.md) — builder-facing technical specification (2,320 lines)
- [Master Briefing V6](docs/MASTER_BRIEFING_V6.md) — human-facing design philosophy

## Environment

- Ubuntu 24.04 (WSL2)
- MariaDB 11.8.8
- Apache 2.4 + PHP 8.3
- Python 3.12 + sentence-transformers
- Ollama (local models)
- OpenRouter (cloud models)

## License

Private repository. License to be determined.
