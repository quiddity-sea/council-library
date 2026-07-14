# Council Library

Sovereign memory architecture for the Foreverbox AI council. A self-hosted system running on physical hardware in Wales that gives artificial intelligence agents durable, structured, privacy-gated memory with semantic search, parallel background workers, and a cognitive router.

## Status

**Pre-alpha** — blueprint stage. Version 3.0 of the technical architecture specification is complete. No code has been built yet.

## What's Here

```
council-library/
├── schema/             # MariaDB DDL — four Wings, vector indexes
├── php-api/            # PHP 8.1+ REST API — the guarded service layer
├── hermes-plugin/      # Python MemoryProvider for Hermes Agent
├── cli/                # council-library installer/manager (TBD)
├── docker/             # Docker Compose stack
├── scripts/            # Migration, indexing, centroid generation
└── docs/               # Architecture blueprint + master briefing
```

## Architecture

Three pillars: **Python** (active mind — Hermes Agent), **MariaDB** (durable memory — vector + relational), **PHP** (guarded API — the bouncer). Four database "Wings": Commons (shared knowledge), Sanctums (private agent memory), Throne (director's plans), Registry (control plane). Three-layer cognitive router with a hard privacy gate. Three specialist Wolves per agent for parallel background processing.

## Docs

- [Architecture Blueprint V3.0](docs/ARCHITECTURE_BLUEPRINT_V3.md) — the builder-facing technical specification
- [Master Briefing V6](docs/MASTER_BRIEFING_V6.md) — the human-facing explanation

## License

Private repository. License to be determined.
