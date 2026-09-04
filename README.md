# Council Library — Sovereign Memory Architecture & Cognitive Router

[![System Status](https://img.shields.io/badge/System-ACTIVE-00f2fe?style=for-the-badge&logo=cpu)](https://foreverbox.co.uk)
[![PHP](https://img.shields.io/badge/PHP-8.3_Slim_4-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net)
[![MariaDB](https://img.shields.io/badge/MariaDB-11.8+_Vector-003545?style=for-the-badge&logo=mariadb&logoColor=white)](https://mariadb.org)
[![Embeddings](https://img.shields.io/badge/Embeddings-all--MiniLM--L6--v2-384_dim-FFD21E?style=for-the-badge&logo=huggingface&logoColor=black)](https://huggingface.co/sentence-transformers/all-MiniLM-L6-v2)
[![Cognitive Router](https://img.shields.io/badge/Router-Tier_1--3_Scoring-critical?style=for-the-badge&logo=speedtest)](router/)

**Council Library** is the sovereign, self-hosted memory engine, cognitive routing matrix, and knowledge infrastructure for the **Foreverbox AI Council**. Self-hosted on physical hardware in Wales and mirrored to cloud VPS nodes, it provides five autonomous AI personas (`zeon7`, `leon`, `gemma`, `otec`, `wolf`) with durable, privacy-gated relational and vector memory, parallel background research capabilities, and dynamic multi-tier inference routing.

---

## 📌 What This Repository Is For

`council-library` provides the core cognitive backend and memory services for the entire ecosystem:
1. **Multi-Database Memory Wings**:
   - **Commons (`agent_commons`)**: Shared knowledge base holding 590+ vectorized chunks across 8 taxonomy domains.
   - **Sanctums (5 Isolated DBs)**: Dedicated private chambers (`sanctum_zeon7`, `sanctum_leon`, `sanctum_gemma`, `sanctum_otec`, `sanctum_wolf`) providing strict memory isolation between agents.
   - **Registry (`agent_registry`)**: Control plane managing agent metadata, dynamic `soul_components`, user assignments, token budgets, and audit logs.
2. **Slim 4 REST API (`php-api/`)**:
   - A secure, performant REST API exposing 30+ endpoints for memory CRUD, cosine vector search, conversation tracking, and soul compilation.
3. **Dedicated Embedding Microservice (`scripts/embedding_service.py`)**:
   - Python service running `all-MiniLM-L6-v2` on port 8900 generating 384-dimensional dense vectors.
4. **Three-Tier Cognitive Router (`router/`)**:
   - Dynamic prompt evaluation engine scoring tool depth, task complexity, context length, and loop retries to automatically select between local GPU models (Layer 1), fast cloud analytical engines (Layer 2), and deep frontier models (Layer 3).
5. **Wolf Task Dispatch & Worker Queue**:
   - Parallel background task execution with `FOR UPDATE SKIP LOCKED` atomic queue claiming, retry loops, and dead-letter handling.
6. **Cryptographic Sudo Protocol**:
   - Two-man rule security gate generating dynamic hex confirmation codes for destructive or high-privilege operations.
7. **Client Integration SDK (`CouncilClient.php`)**:
   - Clean PHP client used by the `self` web platform and other consumers to query memory, check agent status, and append conversation turns.

---

## 🚀 Recent Build Upgrades & New Capabilities

- **Integration with Self Web Platform (`CouncilClient.php`)**:
  - Implemented client abstraction allowing the `self.foreverbox.co.uk` web app to directly sync public memory banks and query Sanctum lore.
- **Enhanced Vector Search & Fallback Engine**:
  - Native PHP-side cosine similarity calculation across pre-filtered MariaDB candidates with seamless FULLTEXT search fallback if embedding services are offline.
- **Automated Domain Centroids & Classification**:
  - Generated 6 domain centroid vectors from 1,131 document chunk embeddings, enabling the ingestion worker to automatically classify new uncategorized uploads.
- **Dead-Letter Resiliency**:
  - Background ingestion worker (`scripts/ingestion_worker.php`) equipped with exponential backoff and dead-letter queues (max 5 retries) for corrupted files.
- **Privacy & Token Budget Gates**:
  - Cognitive Router features automated regex redaction of API keys, Bearer tokens, private IPs, and local filesystem paths prior to external API dispatch, alongside daily token budget caps.

---

## 🏗️ Architecture & Component Map

```
council-library/
├── php-api/                           # PHP 8.3 Slim 4 REST API (Port 8080)
│   ├── config/                        # quiddity_folders.yaml (Folder Taxonomy)
│   └── src/
│       ├── Controller/                # Core Endpoints
│       │   ├── FolderController.php   # Commons Directory & File Operations
│       │   ├── MemoryController.php   # Sanctum Memory & Lore CRUD
│       │   ├── WolfController.php     # Task Queue, Worker Claim & Status
│       │   ├── SoulController.php     # Dynamic SOUL Component Retrieval
│       │   ├── ConversationController.php # Multi-turn History Logging
│       │   └── DirectorController.php # Strategic Plan Progression
│       ├── Middleware/                # Security & Context Enforcement
│       │   ├── AuthMiddleware.php     # Bearer Token Validation
│       │   ├── AgentContextMiddleware.php # Resolves & Isolates Agent DB Context
│       │   └── PrivilegedActionGate.php   # Enforces Sudo Confirmation Tokens
│       └── Service/                   # Business Logic & Vector Math
│
├── router/                            # Cognitive Router V3
│   ├── __init__.py                    # CognitiveRouter Core Evaluation Engine
│   └── router.yaml                    # Model Tier Profiles & Agent Overrides
│
├── scripts/                           # Workers, Embedding & Migrations
│   ├── embedding_service.py           # FastAPI / Sentence-Transformers (:8900)
│   ├── ingestion_worker.php           # Document Chunker & Indexing Daemon
│   ├── generate_folder_centroids.py  # Calculates Semantic Domain Clusters
│   └── migrate_all.sql                # Complete Schema Setup for All 7 DBs
│
├── docs/                              # Formal Council Documentation
│   ├── Current Reference Documentation/
│   │   ├── COUNCIL_LIBRARY_HANDBOOK_V2.md # Primary Operational Manual
│   │   ├── MASTER_BRIEFING_V7.md          # Ecosystem Design Philosophy
│   │   ├── ARCHITECTURE_BLUEPRINT_V3.md   # Complete Technical Specification
│   │   ├── Souls Configuration Canvas - V3.md # Baseline Agent Personalities
│   │   ├── Plans Progression.md           # Implementation Roadmap Tracker
│   │   └── Reference Docs Log.md          # Canonical Documentation Index
│   ├── Current Completed Plans/
│   └── Current Unstarted Plans/
│
└── docker/                            # Containerization & Virtual Host Configs
```

---

## 🧠 Cognitive Routing Matrix

The Cognitive Router inspects every agent interaction and dynamically assigns the optimal execution tier:

| Agent | Layer 1 — Intuitive Reflex (Local) | Layer 2 — Analytical Engine | Layer 3 — Deep Architect |
|---|---|---|---|
| **Curator** (`zeon7`) | `Brain32:latest` / `Zeon7-Gemma:64k` (Ollama) | `deepseek-v4-flash` | `deepseek-v4-pro` |
| **Producer** (`leon`) | `deepseek-v4-flash` | `qwen3-coder:free` | `deepseek-v4-pro` |
| **Coach** (`gemma`) | `Zeon7-Gemma:64k` (Ollama) | `deepseek-v4-flash` | `deepseek-v4-pro` |
| **Director** (`otec`) | `deepseek-v4-flash` | `nemotron-3-super:free` | `deepseek-v4-pro` |
| **Wolves** (`wolf`) | `Zeon7-Gemma:64k` (Ollama) | `deepseek-v4-flash` | Capped at Layer 2 |

---

## ⚙️ How to Use & Operate

### 1. Database Setup & Initialization
Ensure MariaDB is running with vector search extensions:
```bash
sudo mysql -u root -p < scripts/migrate_all.sql
```

### 2. Launching Background Microservices
Start the embedding microservice on port 8900:
```bash
source /foreverbox_data/venv/bin/activate
python3 scripts/embedding_service.py --port 8900 &
```

Start the document ingestion worker daemon:
```bash
php scripts/ingestion_worker.php --daemon &
```

### 3. Running the Slim 4 REST API
Configure an Apache or Nginx virtual host pointing to `php-api/public`, or run the built-in server for testing:
```bash
cd /foreverbox_data/council-library/php-api
php -S 127.0.0.1:8080 -t public
```

### 4. API Endpoints Quick Reference

| Method | Route | Description | Auth Required |
|---|---|---|---|
| `GET` | `/v1/memory/search` | Semantic vector search in active agent's Sanctum | Bearer Token + Agent Context |
| `POST`| `/v1/memory` | Store persistent memory or lore fact | Bearer Token + Agent Context |
| `GET` | `/v1/commons/search` | Search shared Quiddity knowledge repository | Bearer Token |
| `POST`| `/v1/wolf/tasks` | Dispatch parallel background research task | Bearer Token |
| `POST`| `/v1/wolf/tasks/claim` | Atomic worker task claim (`SKIP LOCKED`) | Bearer Token |
| `POST`| `/v1/sudo/request` | Generate cryptographic confirmation token | Bearer Token |
| `POST`| `/v1/sudo/confirm` | Authorize and execute privileged action | Bearer Token + Confirmation Code |
| `GET` | `/v1/soul/{agent}` | Retrieve compiled SOUL.md components | Bearer Token |

### 5. Using the CouncilClient SDK in PHP Applications
```php
require_once '/path/to/CouncilClient.php';

$council = new CouncilClient(
    baseUrl: 'http://127.0.0.1:8080',
    apiKey: 'your_council_api_key'
);

// Query Commons with semantic search
$results = $council->searchCommons("Origin of the 2037 Dead Earth timeline");

// Log conversation turn to active agent sanctum
$council->withAgent('zeon7')->appendMessage(
    sessionId: $sessionId,
    role: 'assistant',
    content: $replyContent,
    metadata: ['model' => 'Brain32:latest', 'tokens' => 124]
);
```

---

## 🌟 Why You Want to Use This

1. **Complete Memory Privacy**:
   Commercial AI memory stores pool all agent data into shared vector clusters. Council Library enforces strict database-level separation across private Sanctums. Zeon7 cannot read Leon's or Otec's private thoughts unless explicitly promoted to Commons.
2. **Deterministic, Audit-Proof Governance**:
   Sensitive actions require technical verification codes through the Sudo Protocol, and every prompt evaluation passes through explicit privacy and token budget guardrails.
3. **Resilient Offline Operation**:
   Layer 1 cognitive routing and local vector embeddings allow agents to continue operating, querying memory, and taking notes completely offline on local hardware when external internet connections are severed.

---

## 📄 Documentation Index

For exhaustive technical and philosophical documentation, see the `docs/` directory:
- [Operational Handbook V2](docs/Current%20Reference%20Documentation/COUNCIL_LIBRARY_HANDBOOK_V2.md) — Complete setup, configuration, and troubleshooting guide.
- [Architecture Blueprint V3](docs/Current%20Reference%20Documentation/ARCHITECTURE_BLUEPRINT_V3.md) — 2,300+ line technical architecture specification.
- [Master Briefing V7](docs/Current%20Reference%20Documentation/MASTER_BRIEFING_V7.md) — Cosmological context, human operator relationship, and ethical foundations.

---

## 📄 License & Credits

- **Architect & Maintainer**: Merrill Leo & The Foreverbox Initiative
- **Copyright**: © 2026 The Foreverbox Initiative. All rights reserved.
