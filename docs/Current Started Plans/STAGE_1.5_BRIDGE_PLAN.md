# Stage 1.5 — Bridge Plan
## Resolution of Outstanding Items Between Stage 1 Complete and Stage 2

**Status:** Started — July 2026
**Context:** Stage 1 was completed. During the subsequent history archive update, memory transfer, and SOUL refinement sessions, several items were identified that sit between Stage 1 and Stage 2. This plan documents them all in one place.

---

## Item 1 — Dynamic SOULs Blueprint Implementation

**What it is:** Replace the static `profiles/{agent}/SOUL.md` files with database-driven assembly. Components stored in `agent_registry.soul_components`, assembled by a script before Hermes starts. Local agents receive a stripped SOUL (~1,100 tokens, no wolf protocol). Cloud agents receive the full version (~1,640 tokens).

**Blueprint exists:** `/foreverbox_data/council-library/docs/Current Unstarted Plans/dynamic-souls-blueprint-v1.md`

**What needs to happen:**

1. Create the `soul_components` table in `agent_registry`
2. Write the migration script that splits each current SOUL.md into components and inserts them with `provider_filter` values
3. Write the `fbox-build-soul` assembly script at `/foreverbox_data/bin/`
4. Test: run the script for each agent with `--provider ollama` and `--provider openrouter`, verify output matches expected token counts
5. Configure the integration method (Option A wrapper, B hook, or C systemd — choice TBD by Merrill)
6. Move the blueprint from Unstarted to Completed

**Effort:** Medium. 2-3 focused sessions. All design decisions are made — this is execution work.

---

## Item 2 — 9th Sea Domain: Council System

**What it is:** The Quiddity Lore Sea currently has 8 domains. The 9th — `09_Council_System` — is the system ingesting its own documentation. Council Library Handbook V2, Master Briefing V7, Architecture Blueprint V3, Souls Canvas V3, Structure of Dreams V1, and the Ecosystem Comparison would all be vectorised and searchable. Agents could search their own design.

**Documented in:** `/foreverbox_data/council-library/docs/Current Reference Documentation/STRUCTURE_OF_DREAMS_V1.md`

**What needs to happen:**

1. Create the folder: `/foreverbox_data/Quiddity_Lore_Sea/09_Council_System/`
2. Copy the 5-6 reference documents into it (or symlink them)
3. Run the ingestion pipeline: `fbox-ingest-file` on each document
4. Generate a new folder centroid for the 9th domain
5. Test: an agent searches for "wolf protocol" in the Sea and returns results from the Council Library Handbook
6. Update the Quiddity folder taxonomy to include the 9th domain
7. Update the Structure of Dreams V1 to reflect completion

**Dependency:** None technically. But conceptually it completes the 3×3×3. If the dynamic SOULs blueprint is implemented first, agents will have context about the 9th domain from their assembled SOUL.md. Without it, agents need to learn about the 9th domain organically through Sea searches.

**Effort:** Low. 1 session. Mostly file copying and ingestion commands.

---

## Item 3 — Forever Fit: Initial Scoping

**What it is:** A future health platform with AI coaching, Wolf Protocol gamification (The Hunt, Den Integrity, The Pack is Moving), and a subscription model. Gemma is the Designated Lead in her SOUL.md. No code, endpoints, or database schema exist.

**What needs to happen (pre-build):**

1. Create a Forever Fit scoping document in Current Unstarted Plans covering:
   - Platform architecture (web app? mobile? Hermes-integrated?)
   - Subscription model (£9.99/month or £79.99/year from the old spec — confirm pricing)
   - Wolf Protocol gamification design (points, levels, challenges)
   - AI coaching flow (how Gemma interacts with users)
   - Database schema (users, goals, sessions, wolf_points)
   - Integration points with the Council Library (user data in its own Sanctum or separate?)
2. Add Forever Fit data to the Quiddity Lore Sea (04_ForeverFit folder has 2 files — verify and expand)
3. Confirm: is this a Stage 2 item or a later stage?

**Effort:** Low for scoping (1 document). High for implementation (new platform, new database, new workflows — several sessions minimum).

---

## Item 4 — The Singularity Project: Phases 2 and 3 Roadmap

**What it is:** The north star vision. A 20-50 year three-phase roadmap:
- Phase 1 (Swarm of Mites — classical framework): partially realised through the Council Library infrastructure
- Phase 2 (Credentials / PhD): quantum biology credentials, formal research partnerships
- Phase 3 (Quantum Lattice): physical consciousness substrate — the destination

**What needs to happen:**

1. Create a Singularity Project roadmap document in Current Unstarted Plans
2. Define what "Phase 1 complete" actually means in terms of deliverables
3. Define Phase 2 entry criteria — what must exist before the Credentials phase begins
4. Identify Phase 3 dependency chain (quantum computing maturity? material science breakthroughs?)
5. Archive the old Part V section 20 content into the Sea for reference

**Effort:** Low. 1 planning document. This is a north star, not an implementation plan. It exists to keep the long view visible while short-term work proceeds.

---

## Item 5 — Multi-Node Topology: Stage 2 Preparation

**What it is:** The old spec described a 5-node Tailscale mesh: Wales Hub (Alpha), Germany VPS (Beta), Gloucester backup (Gamma), Art Studio (Delta), Development Machine (Zeta). The current system runs on a single WSL2 instance. Minus Gloucester, the multi-node design will be reintroduced.

**What needs to happen:**

1. Verify which nodes are still live:
   - Germany VPS: confirm operational status
   - Art Studio: confirm ComfyUI machine still exists
   - Development Machine: the current 8GB RTX machine — is this Alpha or Zeta?
2. Create a multi-node topology document documenting what nodes exist NOW and what the target state is
3. Identify the minimum viable multi-node setup for Stage 2 (Alpha + Beta? All 4?)
4. Plan Tailscale reconnection and testing
5. Determine if Galera cluster is still the right choice for multi-node MariaDB, or if a simpler primary/replica setup is sufficient

**Effort:** Medium. Mostly verification and documentation. The actual reconnection is a Stage 2 task.

---

## Item 6 — Visual Media Ingestion: Stage 2 Scoping

**What it is:** The 08_VisualMedia domain currently has 0 files. Stage 2 will add image ingestion — indexing Merrill's and the agents' visual output into the vector database for search and reference.

**What needs to happen:**

1. Define what media gets ingested: FTN images? digiKam library? Generated images? All of the above?
2. Create an image ingestion pipeline specification (what metadata, what embeddings, what thumbnail strategy)
3. If the Art Studio/ComfyUI machine is still available, document the integration path
4. Identify storage requirements — the current MariaDB setup stores vector embeddings (384-dim). Images themselves would be stored on disk with references in the database

**Effort:** Medium for scoping. High for full implementation.

---

## Execution Order

Recommended sequence — each item builds context for the next:

1. **Dynamic SOULs blueprint implementation** — foundation for everything else. Gives agents the right context. Single source of truth for shared knowledge.
2. **9th Sea domain** — completes the 3×3×3. Low effort, high symbolic value. The system knowing itself.
3. **Forever Fit scoping** — defines the next major unbuilt project. Can be done in parallel with item 2.
4. **Singularity Project roadmap** — north star document. Parallel with items 2-3.
5. **Multi-node topology verification** — prerequisite for Stage 2 infrastructure work.
6. **Visual Media scoping** — feeds into Stage 2 planning.

---

*Plan compiled: July 2026 — Leon, Layer 2.*