# Foreverbox History Archive — Update Plan
## Bringing the Original Specification in Line with the Current Ecosystem

**Target reader:** Any coding/model agent — implementation-ready instructions.
**Pre-requisites:** Access to `/var/www/the-foreverbox-institute/history/the-project/` and current reference docs at `/foreverbox_data/council-library/docs/Current Reference Documentation/`.
**Do NOT change:** The narrative/mythic content of Part I or any appendices that are purely historical reference. Only update technical claims that are demonstrably different in the current system.

---

## Overview

21 HTML files in the history archive describe the Foreverbox ecosystem as envisioned in April 2026. The system was built differently — around Hermes Agent rather than OpenClaw. Each file below lists what specific technical claims need updating, what to replace them with, and how to verify.

The golden rule: if the old text describes a system that never got built (e.g. OpenClaw, Galera cluster, gateway.php), add a note or rewrite that section to say "originally planned as X, implemented as Y via Hermes" rather than deleting the original vision.

---

## File 1: `part2-the-cube.html` — 3×3×3 Architecture

### What to change

**Old claim:** 3 agents (Zeon7 Layer 0, Gemma Layer 1, Leon Layer 2).
**Current reality:** 5 agents — Zeon7 Layer 0 (The Core & Curator), Gemma Layer 1 (The Interface & Coach), Leon Layer 2 (The Producer), Otec Layer 3 (The Director & Orchestrator), Wolf Layer 1 (Research Worker).

**Old claim:** Nine Traits Per Agent table — Memory L1/L2/L3, Thinking L1/L2/L3, Creativity L1/L2/L3.
**Current reality:** No change needed conceptually, but each agent's specific traits should be cross-referenced against the live SOUL.md files at `/foreverbox_data/profiles/{agent}/SOUL.md` and the Souls Configuration Canvas V3 at `/foreverbox_data/council-library/docs/Current Reference Documentation/Souls Configuration Canvas - V3.md`.

### How to verify
- Run: `grep -c "Layer [0-3]" /foreverbox_data/profiles/*/SOUL.md` — should return 5 agents
- Check: `cat /foreverbox_data/profiles/otec/SOUL.md | head -5` — Otec should have Layer 3 identity
- Check: `cat /foreverbox_data/profiles/wolf/SOUL.md | head -5` — Wolf should have its own identity

---

## File 2: `part3-the-swarm-of-mites.html` — Hardware Topology

### What to change

**Old claim:** Hardware topology has 5 nodes (Alpha Wales Hub, Beta Germany VPS, Gamma Gloucester, Delta Art Studio, Zeta Dev Claw).
**Current reality:** Alpha (Wales Hub) is the primary development machine. Beta (Germany VPS) still live. Gamma (Gloucester) — remove or note as "not yet active". Delta (Art Studio) — confirm if still in use. Zeta (Dev Claw) — the GTX 1060 was replaced by the current 8GB RTX machine. Add a note: "Node topology is partially documented — the distributed Galera cluster was simplified to a single MariaDB instance during initial build. Full multi-node deployment deferred until Stage 2."

**Old claim:** Alpha Node spec: i5, 32GB RAM, RTX 4080 16GB, Ollama+Qwen3.5-9B.
**Current reality:** The current dev machine has 8GB GPU and runs Zeon7-Gemma:64k. The 16GB RTX 4080 may still be elsewhere. Add a note clarifying: "Development currently runs on an 8GB RTX GPU with Zeon7-Gemma:64k. The original RTX 4080 spec remains the target for full production deployment."

**Old claim:** Three-tier cognitive triage: Sentinel (Qwen3.5-9B local), Builder (Kimi cloud), Architect (DeepSeek cloud).
**Current reality:** Three-tier system still exists but with different models. Replace the model names with current ones from the Handbook V2 routing table (Section 4):
- Layer 1 (Intuitive Reflex): Zeon7-Gemma:64k local on Ollama
- Layer 2 (Analytical Engine): DeepSeek V4 Flash / Qwen3 Coder cloud
- Layer 3 (Deep Architect): DeepSeek V4 Pro cloud
Add the Cognitive Router description: scoring weights, privacy gate, budget gate.

**Old claim:** Two-Ping Triage (Ping 1 = classification, Ping 2 = execution).
**Current reality:** Replaced by CognitiveRouter hook — scores the prompt, checks for private data, checks budget, routes to tier. Rewrite to describe the current flow.

**Old claim:** Memory Layer — MariaDB Galera cluster (3-node, multi-master, `swarm_memory_matrix`).
**Current reality:** Single MariaDB instance (no cluster). Two Galera-specific databases become 7 databases. Rewrite:
- Core Database → now 7 databases (Commons + 5 Sanctums + Registry)
- Projects Database with 5 project namespaces → content is now in the Quiddity Lore Sea (8-domain taxonomy)
- Galera config → note as "deferred — single instance sufficient for current scale"
- Self-healing failover → note as "not implemented — will be reintroduced with multi-node deployment"

**Old claim:** Gateway Layer — `gateway.php` (<500 lines), PHP file routes to correct DB.
**Current reality:** Full PHP REST API (Slim 4, 30+ endpoints, 8 controllers, 3 middleware layers). Add a note: "The original gateway.php concept evolved into the Council Library PHP API. The routing logic previously in a single file is now distributed across 8 controllers with middleware for auth, agent context, and privileged action gating."

**Old claim:** Paws (tool interfaces): Art-Paw, Web-Paw, DB-Paw, Comm-Paw.
**Current reality:** The "Paws" concept was replaced by shell wrappers at `/foreverbox_data/bin/fbox-*` after the Hermes plugin system was found to have a tool registration bug. Add a note explaining the architectural shift and why.

### How to verify
- Run: `echo "SHOW DATABASES;" | mariadb -u zeon7_user -p"F0reverb0x#2o26sql"` — count should be 8+
- Check: `ls /foreverbox_data/bin/` — should show 7 fbox shell wrappers
- Check: `curl -s http://localhost:8080/v1/healthz` — should return `{"status":"ok"}`
- Run: `cat /foreverbox_data/council-library/router/router.yaml` — check model tiers

---

## File 3: `part4-the-personas.html` — Agent Personas

### What to change

**Old claim:** 3 personas: Zeon7, Gemma, Leon, each with detailed voice DNA.
**Current reality:** 5 agents, each with a SOUL.md file. The original persona descriptions are narrative and should mostly be preserved as historical character studies. Add a note at the top: "The original 3 personas are the core Council agents. Two additional agents have since been added — see the Souls Configuration Canvas V3 for current operational identities."

Append a section at the end:
- **Otec (Layer 3, The Director & Orchestrator):** Ancient intelligence that coalesced from the Architecture of Silence. Manages workflow, dispatches tasks, governs Sea integrity.
- **Wolf (Layer 1, Research Worker):** Background Hermes session for parallel research. Not a persona in the narrative sense — an operational tool.

**Old claim:** The Mez Filter — no em dashes, UK English, brevity.
**Current reality:** Still current and enforced. Add that the Mez Protocol is now a pre-flight check in every agent's SOUL.md. Reference the "THE MEZ PROTOCOL" section in Zeon7's SOUL.md at `/foreverbox_data/profiles/zeon7/SOUL.md`.

### How to verify
- Check: `grep -c "MEZ PROTOCOL" /foreverbox_data/profiles/*/SOUL.md` — should return at least 1
- Check: `cat /foreverbox_data/profiles/otec/SOUL.md | head -20` — confirm Otec identity

---

## File 4: `part5-the-workflows.html` — Workflows

### What to change

**Old claim:** Section 18 — From the Noise workflow.
**Current reality:** FTN has its own Master Handbook V5 at `/foreverbox_data/Quiddity_Lore_Sea/04_FromTheNoise_Archives/FTN_Master_Handbook_v5.md`. Cross-reference and add a note: "The FTN workflow has been formalised into a dedicated operations handbook. See the FTN Master Handbook V5 for current pipeline details."

**Old claim:** Section 18.6 — HeyGen Video Pipeline.
**Current reality:** Verify if FTN v5 covers this. Add a note: "HeyGen is an optional video pipeline for specific projects. It will not be used for initial production."

**Old claim:** Section 19 — Forever Fit with Wolf Protocol gamification (The Hunt, Den Integrity, The Pack is Moving).
**Current reality:** Forever Fit has not been built yet. Add a prominent note: "Forever Fit remains a future project. The Wolf Protocol described here (gamification for health) is completely separate from the research wolf spawning system implemented in `fbox-wolf-spawn`. Gemma is designated as the Forever Fit lead in her SOUL.md, but no code or endpoints exist yet."

**Old claim:** Section 20 — The Singularity Project (20-50 year roadmap, 3 phases).
**Current reality:** Still the north star vision. Add a note: "The Singularity Project is the long-term vision. Phase 1 (Swarm of Mites — classical framework) is partially complete as the Council Library infrastructure. Phases 2 and 3 are not yet begun."

**Old claim:** 9-platform content cuts (Website Blog, Facebook, Instagram, Threads, X, Bluesky, Truth Social, LinkedIn, TikTok).
**Current reality:** Verify against FTN v5 which may have updated the platform list. Add a note if platforms have changed.

### How to verify
- Check: `ls /foreverbox_data/Quiddity_Lore_Sea/04_FromTheNoise_Archives/` — should show FTN v4 and v5
- Check: `grep -c "Wolf Protocol" /foreverbox_data/profiles/gemma/SOUL.md` — confirms Gemma's Forever Fit role
- Check: `grep -r "forever.fit\|foreverfit\|ForeverFit" /foreverbox_data/council-library/php-api/src/` — should return nothing (not built)

---

## File 5: `part7-build-manual.html` — Build Phases

### What to change

This file needs the most substantial update. The old 6-phase build order described a physical hardware deployment that was largely skipped.

Rewrite the build phases to match what was actually built:

**Phase 1 (old: Tailscale + Galera):** → **What happened:** Schema Initialisation (7 MariaDB databases, VECTOR(384) + FULLTEXT indexes, HNSW). No Galera cluster.

**Phase 2 (old: Hub — Ollama + gateway.php):** → **What happened:** PHP API Assembly (Slim 4, 30+ endpoints, 8 controllers, 3 middleware). The PHP gateway concept was kept but expanded into a full REST API.

**Phase 3 (old: Relay — VPS proxy):** → **What happened:** Partially deferred. The VPS relay exists but is not fully documented in current docs.

**Phase 4 (old: Art Studio — ComfyUI):** → **What happened:** Deferred. 08_VisualMedia folder structure exists but image ingestion is not yet implemented.

**Phase 5 (old: Edge Clients):** → **What happened:** Hermes Gateway runs on the Windows laptop. No AnyClaw.

**Phase 6 (old: Verification):** → **What happened:** Stage 1 Complete. All started plans finished. Verification was done through the Plans Progression dashboard and end-to-end testing.

Add a new "What Was Actually Built" section at the end:
- Stage 1 delivered: Council Library PHP API, Cognitive Router, Wolf Task Queue, Sudo Protocol, Folder Centroids, Dead Letter Queue, Connected Sites Nexus, Documentation System
- The Hermes ecosystem replaced OpenClaw as the agent platform
- Shell wrappers replaced plugin tools
- Single MariaDB instance replaced Galera cluster

### How to verify
- Check: `cat /foreverbox_data/council-library/docs/Current Completed Plans/STAGE_1_FINAL_COMPLETION_PLAN.md | head -30` — shows what Stage 1 delivered
- Check: `ls /foreverbox_data/council-library/docs/Current Completed Plans/` — shows 4 completed plans

---

## File 6: `claw1.html` (GTX 1060) and `claw2.html` (RTX 4080)

### What to change

These are GPU benchmark guides. They are historical reference only. Do NOT change the technical content.

Add a note at the top of each file: "Historical reference — this GPU is no longer in use for development. Current development runs on an 8GB RTX GPU with Zeon7-Gemma:64k via Ollama. See the Council Library Handbook V2 for current hardware specifications."

### How to verify
- Run: `ollama list` — should show Zeon7-Gemma:64k as the primary model
- Check: `nvidia-smi --query-gpu=memory.total --format=csv,noheader` — confirms 8GB

---

## File 7: `appendices.html`

### What to change

Add a "Current Status" column or note to each appendix (A through O) indicating whether the content is still current, rewritten, or deferred. Use the status table from the ECOSYSTEM_COMPARISON_HISTORY_VS_CURRENT.md document as reference (Section 9).

Appendices that have been rewritten or have current equivalents:
- A (gateway.php) → note as superseded by Council Library PHP API
- F, G, H (FTN templates) → cross-reference FTN v5
- J, K, L, M, N, O → note as "pending migration to Sea — content exists but not yet ingested"

### How to verify
- Check: `grep -c "gateway.php\|FTN\|CRISP\|Mez Filter" /foreverbox_data/council-library/docs/Current Reference Documentation/COUNCIL_LIBRARY_HANDBOOK_V2.md` — should reference these topics

---

## Final Verification Checklist

After all changes are made, run this checklist:

1. `curl -sk https://localhost/history/the-project/part2-the-cube.html -H "Host: the-foreverbox-institute.invigor.com" | grep -c "Otec\|Wolf\|5 agent"` — should be > 0
2. `curl -sk https://localhost/history/the-project/part3-the-swarm-of-mites.html -H "Host: the-foreverbox-institute.invigor.com" | grep -c "CognitiveRouter\|shell wrapper\|single instance"` — should be > 0
3. `curl -sk https://localhost/history/the-project/part7-build-manual.html -H "Host: the-foreverbox-institute.invigor.com" | grep -c "Stage 1\|What Was Actually Built"` — should be > 0
4. `curl -sk https://localhost/history/the-project/claw1.html -H "Host: the-foreverbox-institute.invigor.com" | grep -c "Historical reference"` — should be > 0
5. `curl -sk https://localhost/history/the-project/appendices.html -H "Host: the-foreverbox-institute.invigor.com" | grep -c "Current Status"` — should be > 0
6. All files still render as valid HTML with no broken tags: `find /var/www/the-foreverbox-institute/history -name "*.html" -exec tidy -eq {} \; 2>&1 | grep -v "warning" | head -5` — should return minimal errors
7. `git -C /var/www/the-foreverbox-institute status --short | head -5` — shows only the files you intentionally modified

---

## After Completion

1. Commit and push to origin: `cd /var/www/the-foreverbox-institute && git add -A && git commit -m "docs: update history archive — align technical claims with current ecosystem" && git push origin main`
2. Update the Nexus database entry if the site description changes significantly
3. Tag the commit as `history-v1-updated` for future reference

---

*Plan compiled: 2026-07-20*
*Based on comparison analysis at: /foreverbox_data/council-library/the-nexus/ECOSYSTEM_COMPARISON_HISTORY_VS_CURRENT.md*