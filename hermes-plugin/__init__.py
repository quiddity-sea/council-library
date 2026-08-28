"""
ForeverBox MemoryProvider — Council Library integration for Hermes Agent.

Implements the Hermes MemoryProvider ABC to connect a Hermes Agent profile
to the Council Library's PHP REST API, providing durable Sanctum memory,
Commons vector search, Wolf orchestration, and ingestion triggering.

Blueprint reference: §6 (ARCHITECTURE_BLUEPRINT_V3.md)
"""

from __future__ import annotations

import json
import logging
import os
import threading
import time
import uuid
from pathlib import Path
from typing import Any, Dict, List, Optional

import requests
from agent.memory_provider import MemoryProvider
from hermes_constants import get_hermes_home

logger = logging.getLogger("foreverbox_memory")


class ForeverBoxMemoryProvider(MemoryProvider):
    """Council Library memory backend for Hermes Agent profiles."""

    # ── Core lifecycle ────────────────────────────────────────

    @property
    def name(self) -> str:
        return "foreverbox"

    def is_available(self) -> bool:
        cfg_path = get_hermes_home() / "foreverbox.json"
        return cfg_path.exists() and bool(os.environ.get("FOREVERBOX_API_KEY"))

    def initialize(self, session_id: str, **kwargs) -> None:
        hermes_home = kwargs["hermes_home"]
        cfg = json.loads((Path(hermes_home) / "foreverbox.json").read_text())
        self.api_url = cfg["api_url"]
        self.agent_slug = cfg["agent_slug"]
        self.api_key = os.environ["FOREVERBOX_API_KEY"]
        self.session_id = session_id
        self._sync_thread = None
        self._prefetch_cache = []
        self._prefetch_thread = None
        self._trigger_startup_sync()  # Path C (§4.5)

    def shutdown(self) -> None:
        if self._sync_thread and self._sync_thread.is_alive():
            self._sync_thread.join(timeout=5.0)
        if self._prefetch_thread and self._prefetch_thread.is_alive():
            self._prefetch_thread.join(timeout=3.0)

    # ── Config ──────────────────────────────────────────────────

    def get_config_schema(self):
        return [
            {"key": "api_key", "secret": True, "required": True,
             "env_var": "FOREVERBOX_API_KEY"},
            {"key": "api_url", "required": True,
             "default": "http://localhost:8080/v1"},
            {"key": "agent_slug", "required": True,
             "choices": ["curator", "coach", "producer", "director"]},
        ]

    def save_config(self, values: dict, hermes_home: str) -> None:
        (Path(hermes_home) / "foreverbox.json").write_text(
            json.dumps(values, indent=2))

    # ── System prompt ──────────────────────────────────────────

    def system_prompt_block(self) -> str:
        return (
            "You are connected to the Foreverbox Council Library via the "
            "ForeverBox memory provider. Your Sanctum holds durable memory, "
            "conversation history, and Wolf worker state. Use memory_search "
            "to recall facts, commons_search to query shared knowledge, "
            "wolf_dispatch to parallelise heavy work, and ingest_file to "
            "process new files dropped into the Quiddity Lore Sea. "
            "The Sudo Protocol gates privileged actions — request "
            "confirmation before destructive ops."
        )

    # ── Recall ──────────────────────────────────────────────────

    def prefetch(self, query: str, *, session_id: str = "") -> str:
        try:
            r = requests.post(
                f"{self.api_url}/sanctum/memory/search",
                json={"query": query}, timeout=2,
                headers=self._headers())
            results = r.json().get("results", [])
            if not results:
                return ""
            lines = ["[Foreverbox Sanctum — relevant context:]"]
            for item in results[:5]:
                ns = item.get("namespace", "")
                key = item.get("key_name", "")
                text = (item.get("content_text") or "")[:400]
                if text:
                    lines.append(f"  [{ns}/{key}] {text}")
            return "\n".join(lines)
        except requests.RequestException as e:
            logger.warning("prefetch failed: %s", e)
            return ""

    def queue_prefetch(self, query: str, *, session_id: str = "") -> None:
        def _fetch():
            try:
                r = requests.post(
                    f"{self.api_url}/sanctum/memory/search",
                    json={"query": query}, timeout=3,
                    headers=self._headers())
                self._prefetch_cache = r.json().get("results", [])
            except requests.RequestException:
                self._prefetch_cache = []
        self._prefetch_thread = threading.Thread(target=_fetch, daemon=True)
        self._prefetch_thread.start()

    # ── Sync ────────────────────────────────────────────────────

    def sync_turn(self, user_content, assistant_content, *,
                  session_id: str = "", messages: Optional[List[Dict[str, Any]]] = None):
        def _sync():
            try:
                requests.post(
                    f"{self.api_url}/sanctum/conversations/{self.session_id}/messages",
                    json={"user": user_content, "assistant": assistant_content},
                    headers=self._headers(), timeout=10)
            except requests.RequestException as e:
                logger.warning("sync_turn failed: %s", e)

        if self._sync_thread and self._sync_thread.is_alive():
            self._sync_thread.join(timeout=5.0)
        self._sync_thread = threading.Thread(target=_sync, daemon=True)
        self._sync_thread.start()

    # ── Tools ───────────────────────────────────────────────────

    def get_tool_schemas(self):
        return [
            {
                "name": "memory_search",
                "description": (
                    "Hybrid full-text + vector semantic search over your "
                    "Sanctum memory_lore. Returns memories ranked by relevance."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "query": {"type": "string", "description": "Natural-language search query."},
                        "namespace": {"type": "string", "description": "Optional filter."},
                        "limit": {"type": "integer", "description": "Max results (default 10).", "default": 10}
                    },
                    "required": ["query"]
                }
            },
            {
                "name": "memory_get",
                "description": "Retrieve a single memory by namespace and key.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "namespace": {"type": "string"},
                        "key_name": {"type": "string"}
                    },
                    "required": ["namespace", "key_name"]
                }
            },
            {
                "name": "memory_list",
                "description": "List memories, optionally filtered.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "namespace": {"type": "string"},
                        "tags": {"type": "array", "items": {"type": "string"}},
                        "importance_min": {"type": "integer"},
                        "limit": {"type": "integer", "default": 20}
                    },
                    "required": []
                }
            },
            {
                "name": "memory_upsert",
                "description": "Create or update a memory entry.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "namespace": {"type": "string"},
                        "key_name": {"type": "string"},
                        "content": {"type": "string"},
                        "importance": {"type": "integer", "default": 50},
                        "tags": {"type": "array", "items": {"type": "string"}},
                        "source_type": {"type": "string", "enum": ["user_directive", "session_extraction", "document_ingestion", "wolf_synthesis"], "default": "user_directive"}
                    },
                    "required": ["namespace", "key_name", "content"]
                }
            },
            {
                "name": "memory_delete",
                "description": "Delete a memory entry. Irreversible.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "namespace": {"type": "string"},
                        "key_name": {"type": "string"}
                    },
                    "required": ["namespace", "key_name"]
                }
            },
            {
                "name": "commons_search",
                "description": "Semantic vector search over the Quiddity Commons shared knowledge.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "query": {"type": "string"},
                        "limit": {"type": "integer", "default": 10}
                    },
                    "required": ["query"]
                }
            },
            {
                "name": "ingest_file",
                "description": "Trigger ingestion and vectorisation of a file in the Quiddity_Lore_Sea root.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "filename": {"type": "string"},
                        "organise": {"type": "boolean", "default": True}
                    },
                    "required": ["filename"]
                }
            },
            {
                "name": "wolf_status",
                "description": "List all Wolves and their current status.",
                "parameters": {"type": "object", "properties": {}, "required": []}
            },
            {
                "name": "wolf_dispatch",
                "description": "Dispatch a task to a Wolf for parallel background processing.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "wolf_id": {"type": "string"},
                        "action": {"type": "string"},
                        "payload": {"type": "object"},
                        "priority": {"type": "string", "enum": ["low", "normal", "high", "critical"], "default": "normal"}
                    },
                    "required": ["wolf_id", "action", "payload"]
                }
            },
            {
                "name": "wolf_task_status",
                "description": "Poll the status of a Wolf task.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "wolf_id": {"type": "string"},
                        "task_id": {"type": "string"}
                    },
                    "required": ["wolf_id", "task_id"]
                }
            }
        ]

    def handle_tool_call(self, name, args, **kwargs):
        headers = {**self._headers(), "X-Request-ID": str(uuid.uuid4())}

        route_map = {
            "memory_search":   ("POST", "/sanctum/memory/search"),
            "memory_get":      ("GET",  lambda a: f"/sanctum/memory/{a['namespace']}/{a['key_name']}"),
            "memory_list":     ("GET",  "/sanctum/memory"),
            "memory_upsert":   ("PUT",  lambda a: f"/sanctum/memory/{a['namespace']}/{a['key_name']}"),
            "memory_delete":   ("DELETE", lambda a: f"/sanctum/memory/{a['namespace']}/{a['key_name']}"),
            "commons_search":  ("GET",  "/commons/search"),
            "ingest_file":     ("POST", "/commons/files/sync"),
            "wolf_status":     ("GET",  "/sanctum/wolves/status"),
            "wolf_dispatch":   ("POST", lambda a: f"/sanctum/wolves/{a['wolf_id']}/task"),
            "wolf_task_status": ("GET", lambda a: f"/sanctum/wolves/{a['wolf_id']}/task/{a['task_id']}"),
        }

        if name not in route_map:
            return json.dumps({"error": f"Unknown tool: {name}"})

        method, path_or_fn = route_map[name]
        url_path = path_or_fn(args) if callable(path_or_fn) else path_or_fn
        url = f"{self.api_url}{url_path}"

        try:
            if method == "GET":
                r = requests.get(url, params=args, headers=headers, timeout=10)
            elif method == "DELETE":
                r = requests.delete(url, headers=headers, timeout=10)
            else:
                r = requests.request(method, url, json=args, headers=headers, timeout=10)
            r.raise_for_status()
            return json.dumps(r.json())
        except requests.RequestException as e:
            logger.warning("Tool %s failed: %s", name, e)
            return json.dumps({"error": str(e)})

    # ── Hooks ───────────────────────────────────────────────────

    def on_pre_compress(self, messages):
        snapshot = {
            "compressed_at": time.time(),
            "message_count": len(messages),
            "preview": str(messages[-3:])[:2000]
        }
        try:
            requests.put(
                f"{self.api_url}/sanctum/memory/compression_snapshots/{uuid.uuid4().hex[:12]}",
                json={
                    "namespace": "compression_snapshots",
                    "key_name": f"snapshot_{int(time.time())}",
                    "content": json.dumps(snapshot),
                    "source_type": "session_extraction",
                    "importance": 30
                },
                headers=self._headers(), timeout=5)
        except requests.RequestException as e:
            logger.warning("on_pre_compress failed: %s", e)
        return ""

    def on_memory_write(self, action, target, content, metadata=None):
        if target == "user":
            endpoint = "/sanctum/user-context"
            payload = {"profile_yaml": content, "agent_slug": self.agent_slug}
        elif target == "memory":
            endpoint = f"/sanctum/memory/hermes_builtin/{action}"
            payload = {"content": content, "source_type": "user_directive",
                       "importance": 60, "agent_slug": self.agent_slug}
        else:
            return

        try:
            requests.put(f"{self.api_url}{endpoint}",
                         json=payload, headers=self._headers(), timeout=5)
        except requests.RequestException as e:
            logger.warning("on_memory_write mirror failed: %s", e)

    def on_session_end(self, messages):
        def _extract():
            try:
                summary = {
                    "session_id": self.session_id,
                    "ended_at": time.time(),
                    "message_count": len(messages),
                    "final_exchange": str(messages[-2:])[:1000]
                }
                requests.put(
                    f"{self.api_url}/sanctum/memory/session_summaries/{self.session_id}",
                    json={
                        "namespace": "session_summaries",
                        "key_name": self.session_id,
                        "content": json.dumps(summary),
                        "source_type": "session_extraction",
                        "importance": 40
                    },
                    headers=self._headers(), timeout=5)
            except requests.RequestException as e:
                logger.warning("on_session_end failed: %s", e)
        threading.Thread(target=_extract, daemon=True).start()

    def on_session_switch(self, new_session_id: str, *,
                          parent_session_id: str = "",
                          reset: bool = False, rewound: bool = False, **kwargs):
        self.session_id = new_session_id

    def on_delegation(self, task: str, result: str, *,
                      child_session_id: str = "", **kwargs):
        try:
            requests.put(
                f"{self.api_url}/sanctum/memory/delegation_log/{child_session_id or uuid.uuid4().hex[:12]}",
                json={
                    "namespace": "delegation_log",
                    "key_name": child_session_id or uuid.uuid4().hex[:12],
                    "content": json.dumps({"task": task[:500], "result": result[:500]}),
                    "source_type": "session_extraction",
                    "importance": 20
                },
                headers=self._headers(), timeout=5)
        except requests.RequestException as e:
            logger.warning("on_delegation failed: %s", e)

    def backup_paths(self) -> list:
        return []

    # ── Internal ────────────────────────────────────────────────

    def _headers(self):
        return {
            "Authorization": f"Bearer {self.api_key}",
            "X-Agent-ID": self.agent_slug,
            "Content-Type": "application/json",
        }

    def _trigger_startup_sync(self):
        """Path C: fire-and-forget Commons sync on agent startup (§4.5)."""
        def _sync():
            try:
                requests.post(
                    f"{self.api_url}/commons/files/sync",
                    json={"organise": True},
                    headers=self._headers(), timeout=30)
            except requests.RequestException:
                pass
        threading.Thread(target=_sync, daemon=True).start()


# ── Registration ───────────────────────────────────────────────

def register(ctx) -> None:
    ctx.register_memory_provider(ForeverBoxMemoryProvider())
