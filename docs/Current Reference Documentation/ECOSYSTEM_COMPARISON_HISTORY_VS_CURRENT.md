# Foreverbox Ecosystem: History vs Current State
## Observational Analysis — July 2026

---

## Overview

This document compares the original Foreverbox specification (the "history" folder, pre-April 2026) against the current live ecosystem as documented in the Council Library Handbook V2, Master Briefing V7, Architecture Blueprint V3, Souls Configuration Canvas V3, and FTN Master Handbook v5.

The original spec was written before any code was built. It described an aspirational architecture based around the **OpenClaw/OpenPaw harness** model. The live system was instead built around the **Hermes Agent ecosystem**, which changed fundamental architectural decisions.

---

## 1. Core Architecture

| Topic | History (Old) | Current (Live) | Impact |
|-------|---------------|----------------|--------|
| **Agent Platform** | OpenClaw/OpenPaw harness (custom Python framework) | Hermes Agent (Nous Research) | Complete rewrite. OpenClaw was abandoned in favour of Hermes' profile system, hook architecture, and tool management |
| **Orchestration Layer** | Custom `gateway.php` with PHP daemon workers | Hermes CLI + Cognitive Router hooks | The old gateway.php is irrelevant. Routing now happens via Hermes pre-turn hooks |
| **Model Serving** | llama.cpp server (native Windows + WSL2) | Ollama (WSL2 only) | Different inference stack. Old spec had CUDA setup guides for 6GB Pascal cards |
| **GPU** | GTX 1060 6GB (Pascal) | RTX-class 8GB | Old spec's VRAM management is obsolete. We now have 8GB with Zeon7-Gemma:64k at ~3.8GB |
| **Models Tested** | Gemma 4 E4B (4.5B), Qwen3.5 9B, Qwen2.5 7B | Zeon7-Gemma:64k (primary), various OpenRouter cloud models | Old models were smaller. The old benchmark data (tokens/sec for 6GB) is irrelevant |
| **Operating System** | Native Windows + WSL2 hybrid | WSL2 only | The old Windows CUDA setup instructions are obsolete. Windows is now the host only |

---

## 2. Agent Personas

| Topic | History (Old) | Current (Live) | Notes |
|-------|---------------|----------------|-------|
| **Number of Agents** | 3 (Zeon7, Gemma, Leon) | 5 (Zeon7, Leon, Gemma, Otec, Wolf) | Otec (Director) and Wolf (research worker) were added |
| **Zeon7** | Layer 0, "The Watcher", curator | Layer 0, The Core & Curator | Consistent. SOUL.md now has Memory Operations, Wolf Protocol, Documentation Maintenance |
| **Gemma** | Layer 1, "The Voice", public face | Layer 1, The Interface & Coach | Consistent. ForeverFit lead role expanded |
| **Leon** | Layer 2, "The Builder", technical | Layer 2, The Producer | Consistent. Domain now includes Foreverbox Research |
| **Otec** | Not mentioned (didn't exist) | Layer 3, The Director & Orchestrator | New addition. Master orchestrator role |
| **Wolf** | Not mentioned (didn't exist) | Layer 1 (GPU), Research Worker | New addition. Background research via ad-hoc Hermes sessions |
| **Persona Documentation** | Static HTML pages with voice DNA, Mez Filter | SOUL.md files per agent + Souls Configuration Canvas V3 | Old persona docs are storytelling. Current SOULs are operational identity definitions |

---

## 3. Swarm of Mites / Hardware Topology

| Topic | History (Old) | Current (Live) | Notes |
|-------|---------------|----------------|-------|
| **Node Alpha** | Wales physical hub (confirmed) | Same — Penrhyndeudraeth | Consistent |
| **Node Beta** | Germany VPS | Presumed still in use | Not documented in current docs |
| **Node Gamma** | Gloucestershire (family/business) | Presumed still in use | Not documented in current docs |
| **Node Delta** | Art Studio | Presumed still in use | Not documented in current docs |
| **Node Zeta** | Development Claw (GTX 1060) | Replaced — current dev machine has 8GB GPU | The claw concept evolved into the Hermes-based workflow |
| **Edge Clients** | Various laptops/tablets | Laptop running Hermes gateway | Consistent |
| **Tailscale Mesh** | Tailscale VPN connecting all nodes | Presumed still active | Not documented in current docs |
| **Galera Cluster** | MariaDB Galera multi-node replication | Single MariaDB instance (no cluster) | The Galera concept was simplified to a single DB. Clustering was unnecessary for the current deployment scale |
| **The "Dumb Pipe"** | Symmetric failover logic | Not described in current docs | Not yet ported forward |

---

## 4. Database & Memory Layer

| Topic | History (Old) | Current (Live) | Notes |
|-------|---------------|----------------|-------|
| **Databases** | 6 (Commons + 4 Sanctums + Registry) | 7 (Commons + 5 Sanctums + Registry) | Added `agent_wolf` Sanctum |
| **Vector Dimension** | Not specified in old docs | VECTOR(384) with all-MiniLM-L6-v2 | Old docs predate embedding choice |
| **Storage** | Galera cluster, multi-node | Single MariaDB instance (WSL2 local) | Simplified in practice |
| **Dead Letter Queue** | Not mentioned | `ingestion_dead_letter` table exists | New addition |
| **Task Queue** | Not mentioned | `task_queue` table with atomic claims | New addition |
| **Privileged Action Log** | Not mentioned | `privileged_action_log` table | New addition |
| **Connected Sites** | Not mentioned | `connected_sites` table | New addition |
| **Vector Indexes** | Not specified | HNSW indexes on VECTOR(384) columns | New addition |

---

## 5. Wolf System

| Topic | History (Old) | Current (Live) | Notes |
|-------|---------------|----------------|-------|
| **Research Wolf Protocol** | Not mentioned | Fully implemented as `fbox-wolf-spawn` skill | The research wolf system (ad-hoc Hermes sessions for background research) did not exist in the old spec. It was built from scratch |
| **Forever Fit Wolf Protocol** | Mentioned under Forever Fit workflow (section 19.1) | Not built | The old spec's Wolf Protocol (gamification: The Hunt, Den Integrity, The Pack is Moving) is a completely different concept from the research wolf system. It is specific to the Forever Fit project and has not yet been implemented |
| **Wolf Workers (general)** | Systemd service units, 3 per agent | Ad-hoc spawned via `hermes chat --profile wolf` | Different approach. Old: persistent daemons. Current: ephemeral sessions |
| **Wolf Model** | Not specified | `Zeon7-Gemma:64k` on Ollama | Old spec didn't define wolf model tier |
| **Task Dispatch** | Not specified | `task_queue` with `FOR UPDATE SKIP LOCKED` | Old spec didn't define task mechanics |
| **Depth** | Not defined | Wolves cap at Layer 2 (no Deep Architect) | New addition |

---

## 6. Build Phases

| Topic | History (Old) | Current (Live) | Notes |
|-------|---------------|----------------|-------|
| **Phase 1: Foundation** | Hardware procurement, 6GB GPU testing | Stage 1: SQL Schema Initialisation | Old spec focused on physical hardware. Current spec is software-first |
| **Phase 2: The Hub** | Wales physical server setup | PHP API Assembly | Completely different |
| **Phase 3: The Relay** | Germany VPS relay | Hermes Plugin Build (replaced by shell wrappers) | Abandoned approach |
| **Phase 4: Art Studio** | Creative workstation integration | Python Client Routing Patch (Cognitive Router) | The Art Studio became a software concern |
| **Phase 5: Edge Clients** | Laptop/tablet setup | Migration Scripts | Different scope |
| **Phase 6: Verification** | Full system test | Verification Testing | Consistent intent |
| **Completion Status** | Not completed | Stage 1 Complete (July 2026) | The old build manual was aspirational. The current build is real |
| **Build Manual** | 501-line HTML document with physical construction steps | Replaced by Council Library Handbook V2, Master Briefing V7, Architecture Blueprint V3 | The old build manual is obsolete as a construction guide but valuable as historical record |

---

## 7. From The Noise (FTN) Workflow

| Topic | History (Old) | Current (FTN v5) | Notes |
|-------|---------------|------------------|-------|
| **Documentation** | Section 18 of the old spec, brief overview | FTN Master Handbook v5 (621 lines) | Dramatically expanded. The old spec had 1 section. Current has a full operations handbook |
| **The Seven Signals** | Listed but not detailed | Fully defined weekly themes | Consistent but expanded |
| **Pipeline** | "Workflow 1: Story Sourcing", "Workflow 2: Content Generation" | Part 3: The Daily Pipeline — 6 gated steps | More structured now |
| **Image Production** | Brief mention | Part 5: Visual Production — full specification | Expanded into its own section |
| **HeyGen Video** | Section 18.6 | Not in v5 (removed or moved?) | May need updating |
| **Mez Protocol** | Mentioned but not documented | Implemented as pre-flight check in all SOUL.md files | The Mez Filter concept from Part IV was adopted into agent identities |
| **Wolf Protocol (FTN context)** | Section 19.1 (under Forever Fit) | Not in FTN v5 (lives in fbox-wolf-spawn skill) | Wolf Protocol moved to its own system, not FTN-specific |
| **Content Platform** | Substack + social media | Substack + social media | Consistent |

---

## 8. The C-Plan (Construction Plan) Comparison

The `c-plan-1.html` document mirrors the old spec's table of contents (Part I-VII + Appendices). Its structure is identical to the `the-project/index.html` contents grid. No unique architectural content beyond what is in the other history files.

---

## 9. Appendices — Documentation to Port Forward

The old spec had appendices that describe real systems, concepts, and workflows that need to be documented for the current era. None should be dismissed as "obsolete" — each needs a decision: rewrite for current architecture, archive with historical notes, or mark as pending.

| Appendix | Content | Current Status |
|----------|---------|----------------|
| A | `gateway.php` listing | Needs rewriting for current Hermes gateway architecture |
| B | `agents.json` | OpenClaw agent config format. Needs rewriting for current Hermes profile system |
| C | Watcher script (Python) | Sync daemon concept exists but differently. Needs documenting what changed |
| D | MariaDB Galera config | Multi-node topology is still planned. Needs documenting current single-instance vs future Galera |
| E | Systemd service configs | Only sync daemon remains. Needs documenting what ran before vs now |
| F | FTN Story Lead Card Template | Should be verified against FTN v5. Add if missing |
| G | FTN Research Pack Template | Should be verified against FTN v5. Add if missing |
| H | FTN Image Brief Template | Should be verified against FTN v5. Add if missing |
| I | HeyGen Script Markup | Should be added to FTN v5 as optional video pipeline |
| J | Zeon7 Biography (Full) | Partially in SOUL.md. Full narrative version needs adding to Sea |
| K | Merrill Leo Biography (Full) | Missing. Should be added to Sea or as USER.md documentation |
| L | ForeverBox Initiative (Full) | Missing. Needs adding to Sea as a reference document |
| M | CRISP Framework | Missing. Needs adding to Sea |
| N | Analytical Report (Mez Filter) | Partially in SOUL.md. Full analysis needs adding to Sea |
| O | Glossary of Terms | Missing. Would be useful as a reference document |

---

## 10. Summary of What Needs Updating

### High Priority (should be in current documentation but is missing)

1. **Merrill Leo Biography** — Appendix K in the old spec. Should be preserved or migrated to USER.md or a similar document
2. **ForeverBox Initiative (Full)** — Appendix L. The full scope document for the initiative
3. **CRISP Framework** — Appendix M. A framework referenced in the old spec with no current equivalent
4. **Glossary of Terms** — Appendix O. Would be useful as a reference document
5. **HeyGen Video Pipeline** — Mentioned in old Part V section 18.6, presence in current FTN v5 needs verification
6. **Tailscale VPN / Multi-node topology** — The old spec described a distributed system. Current docs describe a single-machine setup. The multi-node aspects should either be documented as legacy or confirmed as still operational

### Medium Priority

7. **Persona voice DNA / Mez Filter** — Part IV of old spec is rich storytelling. Souls Canvas captures the operational identity but not the narrative depth. The narrative content should be added to the Sea alongside the operational SOULs
8. **Wolf Protocol (Forever Fit context)** — The old spec's section 19.1 describes a Wolf Protocol specific to the Forever Fit project (gamification: The Hunt, Den Integrity, The Pack is Moving). This is a completely different concept from the current research wolf system in `fbox-wolf-spawn`. Forever Fit has not yet been built or documented in the current system
9. **Forever Fit platform** — Old spec section 19 with AI coaching, subscription model, Wolf Protocol gamification. Gemma is the Forever Fit lead in her SOUL.md but no code, endpoints, or schema exist. This is an unbuilt project pending Sea documentation
10. **The Singularity Project** — Old spec section 20 with 20-50 year three-phase roadmap (Classical Swarm → Credentials/PhD → Quantum Lattice). This is the north star vision. Not superseded — just not yet implemented in the current system. Needs a planning document in the Sea

### Low Priority

11. **The Seven Signals** — Old Part V section 18.1. Current FTN v5 has them but the old spec's framing may add useful context

## 11. Documentation That Needs Rewriting (not "superseded")

The following items from the old spec do not describe dead systems. They describe systems that were built in a different era (OpenClaw, pre-Hermes) and need their documentation **rewritten for the current architecture**, not archived:

1. **OpenClaw/AnyClaw harness** — The concept of a distributed agent harness serving multiple endpoints. Rewrite to document: what OpenClaw was, why it was replaced by Hermes, and how the Hermes profile system fulfills the same role differently
2. **gateway.php** (Appendix A) — The old PHP gateway that routed requests. Rewrite to document: how the current PHP API + Hermes gateway replaced it, and the architectural reasons for the change
3. **agents.json** (Appendix B) — The old agent configuration format. Rewrite to document: how Hermes profile configs + SOUL.md files replaced it
4. **Watcher script** (Appendix C) — The Python filesystem watcher for ComfyUI outputs. Rewrite to document: how the sync daemon and ingestion worker fulfill the same role
5. **MariaDB Galera config** (Appendix D) — The multi-node cluster design. Document: why a single instance was chosen initially, and how Galera will be reintroduced when the multi-node topology is finished
6. **Systemd service configs** (Appendix E) — The old daemon architecture. Document: what ran as services before, what the current sync daemon does, and the trade-offs
7. **Hardware build instructions** — Claw1/claw2 GPU benchmarks. Document: what hardware was tested, what was learned, and how the current 8GB RTX setup relates to the original GTX 1060 era

---

## 11. FTN Handbook Cross-Check

The FTN Master Handbook v5 and the old spec's Part V section 18 should be compared in detail. From the structure analysis:

- **Old spec had:** Section 18.6 (HeyGen Video Pipeline) — check if FTN v5 still covers this
- **Old spec had:** Section 18.4 (Platform Matrix) — check if FTN v5 still covers this
- **Old spec had:** The Waterfall Method (section 18.5) — check current version
- **Current FTN v5 has:** Part 5 (Visual Production) with overlay specs — this is new since the old spec
- **Current FTN v5 has:** Part 2 (Workspace Organisation) with naming conventions — expanded detail

A separate line-by-line comparison of the FTN workflows would be valuable but is outside the scope of this document.

---

*Document compiled: 2026-07-20*
*No changes were made to any files during this analysis.*
