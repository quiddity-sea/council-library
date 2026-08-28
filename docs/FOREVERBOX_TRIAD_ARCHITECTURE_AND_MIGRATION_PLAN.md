# ForeverBox Triad Architecture and Migration Plan

**Status:** Architecture decision / working plan  
**Date:** 20 August 2026  
**Scope:** `foreverbox-data`, `council-library`, and `i-am-self` (currently `zeon7-self`)

---

## 1. Purpose

This document records the architectural direction agreed for the ForeverBox ecosystem and provides a staged plan for reaching it without unnecessarily rebuilding working systems.

The core decision is that ForeverBox is a **triad** rather than a two-part system:

1. **Data** - structure, persistent knowledge, RAG, project data and agent definitions.
2. **Council** - reasoning, action, agent identity/context, routing and semantic memory.
3. **Self** - authenticated human interaction, presentation, UI templates, themes and agent-specific experience.

The three layers are complementary and should remain distinct even where they are temporarily housed in the same repository.

---

# 2. The ForeverBox Triad

```text
                         FOREVERBOX
                             |
              +--------------+--------------+
              |              |              |
              v              v              v
       +-------------+ +-------------+ +-------------+
       |    DATA     | |   COUNCIL   | |    SELF     |
       |             | |             | |             |
       | Structure   | | Reasoning   | | Interface   |
       | Knowledge   | | Agency      | | Identity    |
       | RAG         | | Action      | | Experience  |
       | Projects    | | Memory      | | Access      |
       +-------------+ +-------------+ +-------------+
```

### Data

**Question answered:** What exists?

### Council

**Question answered:** What should happen, and how does the system think, remember and act?

### Self

**Question answered:** How does a human encounter and interact with the system and its agents?

---

# 3. Current Repositories

## 3.1 `foreverbox-data`

Current role: **Data / structure layer**

The repository contains the persistent definitions and resources that make up the ForeverBox agent ecosystem.

Expected responsibilities include:

- Agent profiles
- SOUL / identity definitions
- Skills
- Shared skills
- Lore / knowledge
- Documents and structured data
- RAG source material
- Project structures
- Project-management data
- Synchronisation and ingestion resources
- Agent-specific configuration

The existing `council-library` implementation currently lives inside this repository. That is acceptable as a transitional arrangement.

### Architectural rule

`foreverbox-data` should describe and supply the agents and their persistent resources. It should not become the long-term home of the human-facing UI.

---

## 3.2 `council-library`

Current role: **Cognition / agency layer**

Council is the system that allows the agents to think, remember, search, route work and take action.

Responsibilities include:

- Agent identity and `AgentContext`
- Reasoning orchestration
- Cognitive Router
- Model routing and selection
- Semantic memory
- Vector retrieval
- Conversation-memory retrieval
- Tool and action execution
- Hermes integration
- Hermes skills and adapters
- Wolf task/delegation infrastructure
- Permissions and privileged actions
- API layer
- Agent coordination
- Memory ingestion and retrieval

### Architectural rule

Council is infrastructure. It should provide services to agents and interfaces rather than becoming another agent-specific application.

### Long-term repository position

The current `foreverbox-data/council-library` arrangement can remain while the architecture stabilises. Once the Council API and boundaries are sufficiently stable, `council-library` should be extracted into its own independent repository.

Target repository structure:

```text
quiddity-sea/
├── foreverbox-data
├── council-library
└── i-am-self
```

This extraction is a later phase, not an immediate requirement.

---

## 3.3 `zeon7-self` -> `i-am-self`

Current role: **Self / human-facing interface layer**

The existing Zeon7 interface should evolve into a general-purpose authenticated interface for the ForeverBox ecosystem.

The repository should ultimately become `i-am-self`.

Its responsibilities should include:

- Authentication
- User identity/session handling
- Agent selection based on authorised assignments
- Agent-specific UI templates
- Shared UI component system
- Themes
- Public interface
- Admin interface
- Human-facing chat/session interface
- Agent presentation and visual identity
- Client-side interaction with Council APIs

It should **not** duplicate Council's reasoning, memory, vector search, Hermes skills or agent runtime.

---

# 4. Agent UI Architecture

The interface should use a shared component library with agent-specific compositions.

This is not:

```text
Zeon7 UI
Leon UI
Gemma UI
Otec UI
Wolf UI
```

It is:

```text
                    I-AM-SELF
                        |
                Shared Components
                        |
             Agent UI Template
                        |
        +---------------+---------------+
        |               |               |
      Zeon7            Leon            Gemma
     template        template        template
```

Shared components may include:

- Chat
- Agent status
- Memory
- Conversation history
- Tasks
- Projects
- Activity
- Context
- Tools
- Navigation
- HUD elements
- Notifications
- Search
- Knowledge views

Each agent can define:

- Layout/composition
- Theme
- Panels shown
- Component ordering
- Available capabilities
- Visual identity
- Agent-specific UI components

### Example

```yaml
agent: zeon7

theme:
  base: cybernetic
  accent: cyan

layout:
  type: cockpit

panels:
  left:
    - agent_status
    - memory
    - lore
  centre:
    - conversation
  right:
    - context
    - activity
    - tools
```

The same component system could render a completely different workspace for Leon or another agent.

---

# 5. User -> Agent -> Template Model

Authenticated users should not simply receive access to the entire agent ecosystem.

The access model should be:

```text
User
 |
 +-- Authentication
 |
 +-- Agent assignment
 |      |
 |      +-- Agent
 |      +-- Permissions
 |      +-- Capabilities
 |      +-- Memory scope
 |      +-- UI template
 |
 +-- Session
```

An assignment may conceptually contain:

```text
user_id
agent_id
template_id
permissions
capabilities
memory_scope
status
```

This allows the same agent to be presented differently to different users.

Example:

```text
User A -> Zeon7 -> full permissions -> zeon7-cockpit
User B -> Zeon7 -> restricted permissions -> zeon7-standard
User C -> Leon  -> project permissions -> leon-workspace
```

### Security rule

The UI must never be the authority for access control.

`i-am-self` should render what Council says the authenticated user is allowed to access. Council must enforce the permission boundary on the API itself.

---

# 6. Conversation Logs and Semantic Memory

Conversation logs remain the **authoritative source of truth**.

Vectors are not replacements for chat logs.

The architecture should be:

```text
                 CHAT LOGS
              Source of Truth
                     |
              Embedding Worker
                     |
                     v
          Conversation Vector Index
                     |
             references back to
                     |
                     v
              Original Logs
```

A vector record should contain enough metadata to locate the source conversation/message range, for example:

```text
vector_id
conversation_id
message_start / message_end
agent_id
operator_id
session_id
timestamp
embedding
```

The vector answers:

> Where is the semantically relevant conversation?

The log answers:

> What was actually said?

This avoids reconstructing history from embeddings and permits the embedding/index to be regenerated if the embedding model changes.

---

# 7. Conversation Memory Should Be First-Class

Conversation memory should be available alongside other forms of memory.

The target retrieval model is:

```text
                         QUERY
                           |
             +-------------+-------------+
             |             |             |
             v             v             v
         Knowledge       Agent       Episodic
          Memory         Memory      Conversation
             |             |             |
             +-------------+-------------+
                           |
                           v
                    Context Builder
                           |
                           v
                    Cognitive Router
                           |
                           v
                         Hermes
                           |
                           v
                         Agent
```

### Two useful levels of conversation indexing

1. **Message/chunk vectors** for precise retrieval.
2. **Conversation summary/topic vectors** for coarse retrieval and discovery.

Both point back to the authoritative chat logs.

---

# 8. Hermes and the Council Boundary

Hermes remains part of the Council execution layer.

The relationship should be:

```text
Human
  |
  v
I-Am-Self
  |
  | authenticated API
  v
Council
  |
  +-- Memory
  +-- Search
  +-- Agent Context
  +-- Cognitive Router
  +-- Hermes
        |
        +-- Skills
        +-- Tools
        +-- Models
        +-- Actions
  |
  v
ForeverBox Data
```

`i-am-self` should not implement its own parallel Hermes skill system merely to obtain search, memory or agent capabilities that Council already provides.

The UI exposes capabilities; Council/Hermes executes them.

---

# 9. Target Repository Architecture

Eventually:

```text
quiddity-sea/
│
├── foreverbox-data/
│   ├── profiles/
│   ├── Shared_Skills/
│   ├── Quiddity_Lore_Sea/
│   ├── project data/
│   └── agent resources/
│
├── council-library/
│   ├── API/
│   ├── memory/
│   ├── vector search/
│   ├── conversation memory/
│   ├── router/
│   ├── Hermes integration/
│   ├── Wolves/
│   └── permissions/
│
└── i-am-self/
    ├── components/
    ├── templates/
    │   ├── zeon7/
    │   ├── leon/
    │   ├── gemma/
    │   ├── otec/
    │   └── wolf/
    ├── themes/
    ├── public/
    ├── admin/
    ├── authentication/
    └── council-client/
```

---

# 10. Migration Plan

## Phase 0 - Freeze the architectural boundary

Before substantial implementation:

- Record the three-layer model.
- Identify which existing `zeon7-self` functionality belongs in Self versus Council.
- Do not add new duplicate RAG/search/memory infrastructure to `zeon7-self`.
- Identify existing Council APIs that `i-am-self` can consume.

**Exit condition:** We can describe every major subsystem as Data, Council or Self.

---

## Phase 1 - Make Council the backend authority

From `zeon7-self`:

- Stop treating its local AI/RAG implementation as the long-term source of intelligence.
- Add a Council API client.
- Route agent interaction through Council.
- Reuse Hermes skills and adapters through Council.
- Reuse Council vector search and semantic retrieval.
- Preserve the existing Zeon7 UI while replacing its backend dependencies incrementally.

**Goal:** Zeon7 UI can operate through Council without duplicating Council functionality.

---

## Phase 2 - Extract reusable UI components

Refactor the current Zeon7 interface into reusable components.

Separate:

```text
Component
Theme
Template
Agent identity
```

from one another.

For example:

```text
components/Chat
components/Memory
components/Activity
components/AgentStatus
components/Tasks
components/Tools
```

Then create:

```text
templates/zeon7
```

using those components.

**Goal:** Zeon7 remains visually faithful while the UI becomes agent-agnostic underneath.

---

## Phase 3 - Introduce agent UI manifests

Create a declarative configuration describing each agent's interface.

For example:

```yaml
agent: zeon7
template: zeon7-cockpit
theme: cybernetic
capabilities:
  - chat
  - search
  - memory
  - tools
panels:
  - agent_status
  - conversation
  - memory
  - activity
```

Build the template loader/renderer.

**Goal:** Adding an agent does not require cloning the entire frontend.

---

## Phase 4 - Introduce authenticated user assignments

Implement the relationship:

```text
User -> Agent Assignment -> Permissions -> Template
```

Council becomes the authority for permissions.

`i-am-self` requests the authenticated user's available agents and their UI configuration.

**Goal:** Different authenticated users can see different agents and different versions of those agents' interfaces.

---

## Phase 5 - Move conversation storage into the Council model

Establish authoritative conversation logs in Council.

Implement:

- Conversation IDs
- Session IDs
- Agent IDs
- Operator/user IDs
- Message records
- Retention/privacy metadata

Then build an asynchronous embedding/indexing worker.

**Goal:** Every conversation can become semantic episodic memory without replacing the original log.

---

## Phase 6 - Add semantic conversation retrieval

Implement:

1. Conversation summary/topic embeddings.
2. Message/chunk embeddings.
3. Vector metadata pointing back to exact log records.
4. Permission filtering before retrieval.
5. Retrieval of original messages after semantic matching.

**Goal:** An agent can retrieve relevant past conversations naturally without requiring exact keywords or dates.

---

## Phase 7 - Introduce additional agent templates

After the Zeon7 template is stable:

- Leon template
- Gemma template
- Otec template
- Wolf/worker template

Each should reuse the common component system while having its own composition and identity.

**Goal:** Demonstrate that `i-am-self` is genuinely an agent interface rather than a renamed Zeon7 application.

---

## Phase 8 - Extract Council into its own repository

Only after Council has a stable API boundary:

```text
foreverbox-data/council-library
```

becomes:

```text
quiddity-sea/council-library
```

Update deployment, imports, environment configuration and CI/CD accordingly.

**Goal:** Council becomes an independently deployable infrastructure service.

---

## Phase 9 - Rename `zeon7-self` to `i-am-self`

Once the frontend is genuinely agent-agnostic:

- Rename repository.
- Update package/application identity.
- Replace Zeon7-specific naming where it is architectural rather than presentational.
- Preserve Zeon7 as the first/default agent template.
- Update documentation and deployment configuration.

**Goal:** The repository name accurately describes its responsibility.

---

# 11. What Should NOT Be Duplicated

Avoid creating parallel implementations of:

- Vector databases
- Semantic search
- Agent memory
- Conversation retrieval
- Hermes skills
- Model routing
- Cognitive routing
- Agent permission enforcement
- Wolf orchestration
- Agent identity/context

If Council can provide the capability, `i-am-self` should consume it.

---

# 12. Dependency Direction

The preferred dependency direction is:

```text
I-Am-Self
     |
     v
Council Library
     |
     v
ForeverBox Data
```

With Council using Hermes and the resources/agent definitions supplied by ForeverBox Data.

Avoid making the dependency graph circular.

In particular:

```text
BAD:
Data -> Council -> Self -> Data
```

Prefer:

```text
Data
  ^
  |
Council
  ^
  |
Self
```

where the arrows represent consumption/dependency rather than ownership.

---

# 13. Final Architectural Model

The completed system should be understandable as three layers:

```text
┌────────────────────────────────────────────────────────┐
│                       FOREVERBOX                       │
│                                                        │
│   DATA              COUNCIL               SELF         │
│                                                        │
│   What exists       What thinks           What humans  │
│   and persists      and acts              experience   │
│                                                        │
│   Knowledge         Reasoning             Interface    │
│   Structure         Agency                Identity     │
│   RAG               Memory                Access       │
│   Projects          Search                Templates    │
│   Agent data        Hermes                Themes       │
│                     Wolves                             │
└────────────────────────────────────────────────────────┘
```

The resulting loop is:

```text
Human
  |
  v
I-Am-Self
  |
  v
Council
  |
  +--> retrieve memory / knowledge
  +--> reason
  +--> invoke Hermes skills
  +--> take action
  +--> update data
  |
  v
ForeverBox Data
  |
  v
new state / knowledge / experience
  |
  v
Council
  |
  v
I-Am-Self
  |
  v
Human
```

This preserves the original ForeverBox vision while giving it the missing third axis: **a dedicated Self layer through which different authenticated humans can encounter different agents, with each agent able to have its own UI composition while sharing the same underlying component system and Council infrastructure.**

---

# 14. Immediate Next Steps

The next practical work should be architectural rather than a wholesale rewrite.

1. Map every major feature currently in `zeon7-self` to **Self**, **Council**, or **Data**.
2. Identify which Zeon7 backend features duplicate Council functionality.
3. Define the first stable Council API required by `i-am-self`.
4. Refactor the existing Zeon7 UI into shared components without changing its visual identity.
5. Define the first `zeon7` UI template/manifest.
6. Define the user -> agent -> permissions -> template data model.
7. Design the authoritative conversation-log schema and vector-reference schema.
8. Implement semantic conversation indexing through Council.
9. Only then begin introducing additional agent templates.
10. Extract Council into its own repository once the boundary is stable.
11. Rename `zeon7-self` to `i-am-self` once the frontend is demonstrably agent-agnostic.

The objective is **not to rewrite the three existing systems**. It is to progressively move each existing capability to the layer where it belongs, while preserving the working pieces and allowing the architecture to converge naturally.
