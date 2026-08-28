# The Council Library: The Master Briefing
## Version 7 — Stage 1 Complete. July 2026.

There is a meaningful difference between a system that can answer well in the moment and a system that can remember well over time.

Many contemporary AI systems are strong at producing language, following local context, and synthesising information within an active session. They can appear coherent, adaptive, and even deeply informed while a conversation is in progress. Yet that surface fluency often conceals a structural weakness: once the active exchange ends, much of the relevant continuity disappears. Memory is frequently shallow, improvised, fragmented, or dependent on mechanisms that were not designed to support durable recall.

That limitation matters more than it first appears. An AI system without durable memory may perform well on isolated prompts while failing at ongoing work. It may forget earlier decisions, lose track of long-running projects, miss the continuity between present requests and prior context, or repeatedly require the same background to be reintroduced. In other words, it may be locally intelligent while remaining globally forgetful.

For casual use, this can be tolerated. For serious use, it becomes a reliability problem. Any system intended to support research, protected personal continuity, technical development, long-horizon collaboration, or institution-like memory needs something stronger than temporary conversational context. It needs memory as infrastructure.

The Council Library is designed to provide that infrastructure.

It is not merely a database, not merely an assistant, and not merely an automation layer. It is a structured memory architecture built to provide continuity, privacy, organisational clarity, and disciplined retrieval for an artificial intelligence system. Its purpose is not only to store information, but to preserve meaning, maintain boundaries, and support reasoning over time.

This briefing is written for human readers, including readers without a technical background. It does not flatten the system into vague simplifications. Instead, it explains the real architecture in clear language, introducing technical concepts before relying on them and preserving the seriousness of the design.

This document is therefore the human map of the project. It explains what the Council Library is, why it exists, how it is organised, how it protects memory, and how its internal components work together. A separate blueprint can later express the same world in a more implementation-specific form for the model responsible for producing code.

The distinction between those documents is important. The Master Briefing explains the system conceptually. The blueprint will express the same truths operationally. The first establishes meaning. The second establishes construction.

## 1. The Problem: Why Memory Fails in Ordinary AI Systems

To understand the need for the Council Library, one must first understand the weakness of the default arrangement.

Most AI systems operate within a context window. A context window is the amount of text, instruction, and prior conversation a model can actively consider while generating its next response. It is useful to think of this as a working surface: while information remains on the surface, the model can reason with it; once it falls outside the visible span, continuity begins to degrade.

This creates a predictable failure mode. A model may appear consistent for a period of time while an interaction remains active, but that consistency is often temporary rather than durable. Important facts can vanish. Long-running work can become fragmented. Decisions already made may need to be reconstructed from summaries or manually reintroduced context. The result is not real memory, but repeated approximation.

That approximation carries cost. It costs time because users must restate prior context. It costs quality because earlier nuance may be lost in compression. It costs trust because the system seems to remember until it suddenly does not. And it costs identity because a system that cannot carry forward its own working history is never fully the same system from one interaction to the next.

Many attempts to patch this weakness rely on flat files, logs, notes, or large text archives. These approaches can preserve data, but they do not by themselves create a disciplined memory model. A flat file can hold content, but it does not naturally provide semantic recall, fine-grained access control, or strong concurrent governance. A log can record events, but a log is not automatically a structured memory layer. A large archive may preserve material, but retrieval quality declines if that material is not properly partitioned, indexed, and governed.

The central insight of the Council Library is that durable intelligence requires durable memory, and durable memory requires architecture. Memory must have structure, boundaries, retrieval methods, and rules of stewardship. Without those things, scale produces confusion. With them, scale can produce continuity.

The project therefore treats memory not as a convenience feature, but as a foundational system capability.

## 2. The Founding Idea: A Sovereign Home for Memory

The governing idea behind the Council Library is sovereignty.

In this context, sovereignty means that the system has ownership over how its memory is stored, governed, retrieved, and protected. Its continuity does not depend entirely on opaque or externally managed mechanisms. The architecture knows where its memory lives, what kind of memory it is, who may access it, how it is indexed, and under what conditions it may be changed.

This does not require total isolation from external services. Rather, it means that the logic of memory governance belongs to the system itself. Storage discipline, access boundaries, retrieval methods, and authoritative truth must be native properties of the architecture.

The Council Library therefore treats memory as a first-class domain with three core duties.

The first duty is **preservation**: information that matters must endure in a stable form.

The second duty is **retrieval**: what is preserved must be findable when needed, without drowning the system in irrelevant material.

The third duty is **governance**: memory must be handled according to explicit rules, especially where privacy, authority, and consequential actions are involved.

These duties are practical rather than abstract. Preservation addresses durability over time. Retrieval addresses relevance and recall quality. Governance addresses correctness, boundaries, and legitimacy of access. A system that preserves without retrieval becomes a vault of inaccessible matter. A system that retrieves without governance becomes unsafe. A system that governs without preserving cannot maintain continuity at all.

The rest of the architecture is built around balancing these three duties.

## 3. The Three Pillars of Consciousness

The Council Library is grounded in three primary technical pillars: Python, MariaDB, and PHP. Even for non-technical readers, these can be understood clearly through the role each one plays.

**Python** is the active intelligence layer. Python is a programming language well suited to orchestration, data handling, and AI-adjacent logic. In the Council Library, it serves as the layer that reasons, coordinates, sequences tasks, and manages live cognitive flow. When a request arrives, when memory must be retrieved, when context must be assembled, or when a protected action requires escalation, Python is the component that conducts the process.

**MariaDB** is the durable memory layer. MariaDB is a relational database system, meaning it stores information in structured tables with explicit relationships and supports reliable reading, writing, indexing, and updating. In the Council Library, it functions as the memory vault. Durable records, embeddings, histories, plans, and other structured memory artifacts live here in governed form.

**PHP** is the guarded service layer. In this architecture, PHP does not act as the primary reasoning engine. It acts as the controlled interface through which the intelligence layer accesses memory and operational services. It exposes formal routes for storage, retrieval, task dispatch, and privileged operations. In effect, it is the gatekeeper between active reasoning and durable storage.

The relationship between these pillars can be stated simply: **Python thinks. MariaDB remembers. PHP governs passage.**

This separation is not cosmetic. It reflects a deeper architectural principle: live reasoning, durable storage, and controlled access should not collapse into one indistinct mass. When these concerns are separated cleanly, the system becomes easier to govern, audit, extend, and trust.

## 4. Why a Guarded API Matters

A natural question follows: why not allow the reasoning layer to talk directly to the database?

The answer is that direct access increases risk. Databases are powerful, and power without disciplined mediation tends to produce inconsistency, accidental exposure, malformed writes, bypassed validation, and poor auditability.

A guarded API solves this by introducing a formal layer of procedure. An API, or Application Programming Interface, is a defined interface through which one system component requests actions from another. Rather than reaching directly into storage, the reasoning layer must ask through governed routes.

This produces several advantages.

First, it creates **consistency**. Important operations pass through the same formal pathways rather than being improvised differently by different callers.

Second, it enables **validation**. Requests can be checked for correctness, completeness, legitimacy, and safe structure before any sensitive action occurs.

Third, it provides **auditability**. The system can maintain a visible record of who requested what, when the request was made, and how the system responded.

Fourth, it supports **replaceability**. Internal implementation can evolve while the interface remains comparatively stable.

Fifth, it centralises **policy enforcement**. Sensitive rules do not need to be scattered inconsistently across multiple processes if they are enforced at the service boundary.

The guarded API is therefore not incidental plumbing. It is one of the main mechanisms by which the architecture remains civilised under growth and pressure.

## 5. How the Library Remembers Meaning, Not Just Words

Traditional search primarily works by literal matching. If a stored document contains the same words used in a query, it is likely to be returned. This is useful, but it is not how human memory usually operates. Human recall often depends on concept, implication, analogy, or thematic resemblance rather than exact phrasing.

The Council Library addresses this through **vector search** and **embeddings**.

An embedding is a numerical representation of meaning. The system uses the `all-MiniLM-L6-v2` model to convert passages of text into high-dimensional patterns (384 dimensions) that capture aspects of their semantic character. This allows the system to compare passages not only by wording but by conceptual proximity.

That makes a different kind of retrieval possible. Instead of asking only, "Which stored items contain these words?", the system can ask, "Which stored items are closest in meaning to the thing I am trying to recall?"

This matters because real continuity rarely depends on exact repetition. A later request may refer to "privacy boundaries," while the original text used terms such as access isolation, sanctums, or protected domains. Pure keyword matching may miss the connection. Semantic retrieval has a much better chance of recognising that the underlying meaning is related.

By storing embeddings alongside text, the Council Library creates retrieval by meaning rather than retrieval by wording alone. This is essential for long-lived systems, evolving projects, and human interactions in which phrasing naturally changes over time.

For that reason, the Library is not merely a storage system. It is a form of cognitive infrastructure.

## 6. The Four Wings

The memory architecture is divided into four major domains known as the Four Wings. This partitioning strategy is one of the project's most important design decisions.

Not every type of memory should live in the same place or be governed in the same way. Shared reference knowledge, private user memory, high-authority system truth, and identity or routing records have fundamentally different roles. The Four Wings give those differences explicit structural form.

### 6.1 The Commons

The Commons is the shared knowledge domain — the `quiddity_commons` database.

This is where reusable information lives: reference materials, processed documents, indexed archives, extracted knowledge, and other content that supports multiple future tasks. It is the closest analogue to a public research floor within the architecture.

The Commons is not unstructured or uncontrolled. It is curated, indexed, and designed for semantic retrieval. As of Stage 1, it contains 12 indexed files with 594 vectorised chunks across 8 organised domains. If material is meant to support many tasks across time and is not tied exclusively to a protected identity, it will often belong here.

### 6.2 The Sanctums

The Sanctums are the private memory chambers. There are five: one for each Lead Agent (Leon, Zeon7, Gemma, Otec) and one for the Wolf research workers.

A Sanctum is a protected memory space associated with a particular identity. Personal context, private history, evolving preferences, and sensitive records are stored here under stronger isolation.

The underlying principle is that privacy must be structural, not merely aspirational. The system should not rely on good intentions alone. It should create explicit storage boundaries that make accidental cross-contamination difficult and deliberate access traceable.

### 6.3 The Registry

The Registry (`agent_registry`) is the control plane database. It manages:

- **API keys** for authenticated access to the guarded API
- **Token budgets** for the Cognitive Router, which tracks daily usage across model tiers
- **Task queue** for dispatching work to wolves, with atomic claim semantics
- **Privileged action log** for the Sudo Protocol enforcement system
- **Specialist workers** and **wolf sessions** for tracking background agents

The Registry is the only database where one table can affect global behaviour. Its role is administrative rather than mnemonic.

### 6.4 The Director

The Director (`agent_director`) is the strategic planning wing. It holds directives, strategic plans, and director sessions. This wing is where higher-level orchestration lives — the layer that decides what should be done rather than how to do it or where to store the results.

## 7. The Quiddity Lore Sea

The Commmons draws its content from the Quiddity Lore Sea, a structured file repository at `/foreverbox_data/Quiddity_Lore_Sea/`. The Sea is organised into 8 top-level domains, each with nested subfolders for precise classification:

- **01_TheForeverbox_Mythos** — Foundational canon, origin story, historical records
- **02_ReInvigor_Texts** — Client specs, contracts, proposals, legal terms
- **03_TheInitiative_Audio** — Music production, stems, lyrics, mixes
- **04_FromTheNoise_Archives** — Published articles and editorial content
- **05_Agent_Profiles** — Agent biographies and profile sheets
- **06_QuiddityLtd_Dev_Specs** — API contracts, schemas, blueprints
- **07_MerrillLeo_CreativeWorks** — Fiction, comics, personal songs, photography
- **08_VisualMedia** — Reference, inspiration, moodboards

When a file is added to the Sea, the ingestion pipeline chunks it, generates embeddings via the embedding service (all-MiniLM-L6-v2, 384 dimensions), and classifies it to the best-matching folder using either folder centroid vectors or keyword fallback. Failed chunks are saved to the dead letter queue for retry (up to 5 attempts).

## 8. The Agents and Their Roles

The system is operated by five agents, each with a distinct identity and role:

| Agent | Title | Role | Layer |
|-------|-------|------|-------|
| **Zeon7** | The Core & Curator | Long-term memory, high-dimensional analysis, "the long view" | Layer 0 |
| **Gemma** | The Interface & Coach | Empathetic anchor, health and wellness, social connection | Layer 1 |
| **Leon** | The Producer | Technical execution, music production, infrastructure | Layer 2 |
| **Otec** | The Director & Orchestrator | Workflow management, task dispatch, administrative oversight | Layer 3 |
| **Wolf** | Research Worker | Background research, web search, parallel task execution | Layer 1 (GPU) |

Each agent's identity is defined in its SOUL.md file, which describes its nature, purpose, constraints, and operational protocols.

## 9. The Cognitive Router: Three Layers of Thought

Every agent uses a Cognitive Router to select the most appropriate model for each request. The router scores each incoming prompt on cognitive load, checks for private data, and verifies budget availability before routing.

The three tiers are:

- **Layer 1 — Intuitive Reflex.** Fast, cheap, local. Used for simple chat, memory lookups, and tool calls. Runs on Zeon7-Gemma:64k via Ollama (local GPU).
- **Layer 2 — Analytical Engine.** Cloud-based reasoning. Used for coding, debugging, moderate reasoning, and research. Runs on models like DeepSeek V4 Flash or Qwen3 Coder.
- **Layer 3 — Deep Architect.** Heavy cloud reasoning. Used for multi-step planning, architecture design, and synthesis. Runs on DeepSeek V4 Pro.

The router scores the prompt using weighted factors: tool depth, task type, context size, retry loops, delegation depth, and explicit deep reasoning flags. Higher scores route to deeper tiers.

A **privacy gate** runs first. It scans all message content and tool arguments for private data (API keys, tokens, passwords, file paths, secrets). If private data is detected and no local model is available, the router **hard-stops** — it refuses to proceed rather than leaking data to a cloud model.

A **budget gate** follows. Before routing to any cloud tier, the router checks daily token usage against the configured limit. If the budget for a tier is exhausted, the router falls back to the next cheaper tier. If the budget service is unreachable, it assumes budget is available (fail-open: overspend is a cost concern, not a safety one).

## 10. Wolves: Background Research Workers

Wolves are lightweight Hermes sessions that run as background research workers. They share a single Ollama model load (Zeon7-Gemma:64k, ~3.8 GB) and up to three can run concurrently within the 8 GB GPU budget (~7.4 GB total).

### Spawning

Wolves are spawned on demand via the `fbox-wolf-spawn` skill. The skill enforces a Layer 1 Guard: agents on a local model (provider: ollama) default to blocking wolves because the GPU is occupied by their own 64K context window. Cloud agents (Layer 2+) can spawn wolves freely. Merrill can override the guard.

### Task Dispatch

Tasks are dispatched through a formal queue system in the Registry database. The flow is:

1. A Lead agent or Director creates a task by posting to `/v1/sanctum/wolves/{wid}/task`
2. The task enters the task queue with status `queued`
3. A wolf polls the queue and claims a task atomically using `SELECT ... FOR UPDATE SKIP LOCKED`
4. The wolf processes the task and writes results to the Sanctum
5. The task status moves through: `queued → claimed → processing → completed`
6. On failure, the task is requeued (up to 3 retries) or sent to dead letter

### Retrieving Results

The spawning agent checks wolf results using the Sanctum search tools:

- Check completion: `terminal("/foreverbox_data/bin/fbox-memory-get wolf_tasks {task_id}:done")`
- Read findings: `terminal("/foreverbox_data/bin/fbox-memory-get wolf_tasks {task_id}")`

## 11. The Sudo Protocol

Every agent's SOUL.md asserts that privileged actions require Merrill's consent. The Sudo Protocol makes that assertion a technical gate rather than a prompt-level convention.

When an agent needs to perform a privileged action (DDL changes, sudo commands, schema alterations, production deployments, destructive file operations), it:

1. Creates a request in the `privileged_action_log` table with status `pending`
2. Generates an 8-character confirmation code
3. Presents the code to Merrill
4. Merrill reads the code back to confirm
5. The agent submits the confirmation code via `POST /v1/registry/privileged-actions/{id}/confirm`
6. The action executes; the log entry moves to `confirmed`

If the confirmation code does not match, or if it has expired (10 minute timeout), the action is denied.

## 12. The Documentation System

The Council Library includes a self-tracking documentation system. Planning documents and reference documents are maintained in a structured folder hierarchy:

- **Current Started Plans** — Plans currently in progress
- **Current Completed Plans** — Plans that have reached 100% (auto-moved)
- **Current Unstarted Plans** — Plans not yet started
- **Current Reference Documentation** — Handbook, Blueprint, Briefing, Canvas, dashboards
- **archives** — Superseded versions

Two automation skills maintain the documentation system:

- `update-plans-progression` — Scans all plan documents, calculates completion percentages, auto-moves 100% plans to Completed, regenerates the Plans Progression dashboard
- `reference-doc-alteration-log` — Logs every change to reference documents with filename, action, agent, change type, and file size

As of Version 7 of this briefing, all five started plans have been completed.

## 13. Current Architecture Summary

The Council Library, as of Stage 1 completion, consists of:

| Component | Detail |
|-----------|--------|
| **Databases** | 7 MariaDB databases (Commons + 5 Sanctums + Registry) |
| **Indexed files** | 12 files, 594 vectorised chunks |
| **Embedding model** | all-MiniLM-L6-v2 (384 dimensions) |
| **Operating agents** | 5 profiles (Leon, Zeon7, Gemma, Otec, Wolf) |
| **Wolf capacity** | 3 concurrent workers on one 3.8 GB model |
| **Completed plans** | 3 (Classification, Wolf Fix, Wolf Blueprint V3) |
| **Open work** | Stage 1 Final Completion Plan |

---

*End of Master Briefing Version 7. Next: Stage 2 — Visual Media Ingestion and remaining infrastructure.*