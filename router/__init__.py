"""
Cognitive Router — Three Layers of Thought for Hermes Agent.

Blueprint §5. Intercepts prompts before the LLM call, scores cognitive
load, scans for private data, checks budget, and routes to the appropriate
model tier: System1 (local), System2 Light (cloud, cheap), System2 Heavy
(cloud, deep reasoning).

Usage in run_agent.py:
    from router.cognitive_router import CognitiveRouter, scan_for_private_data
    router = CognitiveRouter()
    ...
    has_private = scan_for_private_data(messages)
    if has_private and not router.local_model_available():
        raise HardStop("Local unavailable; privacy-gated request cannot proceed.")
    profile = router.select_model(ctx)
"""

from __future__ import annotations

import logging
import os
import re
import time
from dataclasses import dataclass, field
from enum import Enum
from pathlib import Path
from typing import Dict, List, Optional

import requests
import yaml

logger = logging.getLogger("cognitive_router")

REGISTRY_API_URL = os.environ.get("FOREVERBOX_API_URL", "http://localhost:8080/v1")


class ModelTier(Enum):
    LAYER_1_INTUITIVE_REFLEX = "layer_1_intuitive_reflex"
    LAYER_2_ANALYTICAL_ENGINE = "layer_2_analytical_engine"
    LAYER_3_DEEP_ARCHITECT = "layer_3_deep_architect"


@dataclass
class ModelProfile:
    tier: ModelTier
    provider: str
    model: str
    base_url: Optional[str] = None
    max_tokens: int = 8192
    temperature: float = 0.3
    tags: list = field(default_factory=list)
    extra_params: dict = field(default_factory=dict)


@dataclass
class AgentRequestContext:
    messages: list
    enabled_toolsets: list
    context_tokens: int
    task_type: str = "chat"
    is_retry: bool = False
    delegation_depth: int = 0
    user_explicit_deep: bool = False
    has_private_data: bool = False


class CognitiveRouter:
    """Route prompts to the appropriate model tier based on complexity,
    privacy, budget, and health."""

    WEIGHTS = {
        "tool_depth_gt_2": 0.30,
        "planning_task_type": 0.40,
        "context_gt_40k": 0.20,
        "retry_loop": 0.25,
        "explicit_deep": 1.00,
        "delegation_depth_gt_1": 0.35,
        "private_data_present": -0.50,
    }

    THRESHOLDS = {
        ModelTier.LAYER_1_INTUITIVE_REFLEX: 0.0,
        ModelTier.LAYER_2_ANALYTICAL_ENGINE: 0.40,
        ModelTier.LAYER_3_DEEP_ARCHITECT: 0.70,
    }

    def __init__(self, config_path: str = None):
        if config_path is None:
            config_path = Path(__file__).parent / "router.yaml"
        with open(config_path) as f:
            cfg = yaml.safe_load(f)
        self.profiles = {
            ModelTier(k): ModelProfile(tier=ModelTier(k), **v)
            for k, v in cfg["model_profiles"].items()
        }
        self.agent_overrides = cfg.get("agent_overrides", {})
        self.health_cache: Dict[str, dict] = {}
        self._private_patterns = cfg.get("router", {}).get("private_patterns", [])

    def estimate_load(self, ctx: AgentRequestContext) -> float:
        score = 0.0
        if self._estimate_tool_depth(ctx) > 2:
            score += self.WEIGHTS["tool_depth_gt_2"]
        if ctx.task_type in {"plan", "architect", "debug", "refactor", "synthesize", "research"}:
            score += self.WEIGHTS["planning_task_type"]
        if ctx.context_tokens > 40000:
            score += self.WEIGHTS["context_gt_40k"]
        if ctx.is_retry:
            score += self.WEIGHTS["retry_loop"]
        if ctx.user_explicit_deep:
            score = 1.0
        if ctx.delegation_depth > 1:
            score += self.WEIGHTS["delegation_depth_gt_1"]
        if ctx.has_private_data:
            score += self.WEIGHTS["private_data_present"]
        return max(0.0, min(1.0, score))

    def select_model(self, ctx: AgentRequestContext, agent_slug: str = None) -> ModelProfile:
        load = self.estimate_load(ctx)

        # Privacy: force local if private data present
        if ctx.has_private_data and load >= self.THRESHOLDS[ModelTier.LAYER_2_ANALYTICAL_ENGINE]:
            return self.profiles[ModelTier.LAYER_1_INTUITIVE_REFLEX]

        for tier in [ModelTier.LAYER_3_DEEP_ARCHITECT, ModelTier.LAYER_2_ANALYTICAL_ENGINE, ModelTier.LAYER_1_INTUITIVE_REFLEX]:
            if load >= self.THRESHOLDS[tier] and self._is_healthy(tier):
                # Budget gate for cloud tiers
                if tier != ModelTier.LAYER_1_INTUITIVE_REFLEX and not self._budget_available(tier):
                    continue
                return self._apply_override(tier, agent_slug)

        return self.profiles[ModelTier.LAYER_1_INTUITIVE_REFLEX]

    def _apply_override(self, tier: ModelTier, agent_slug: str = None) -> ModelProfile:
        """Apply per-agent model overrides if configured."""
        profile = self.profiles[tier]
        if agent_slug and self.agent_overrides:
            overrides = self.agent_overrides.get(agent_slug, {}).get(tier.value, {})
            if overrides:
                # Merge override fields into a copy of the default profile
                return ModelProfile(
                    tier=profile.tier,
                    provider=overrides.get("provider", profile.provider),
                    model=overrides.get("model", profile.model),
                    base_url=overrides.get("base_url", profile.base_url),
                    max_tokens=overrides.get("max_tokens", profile.max_tokens),
                    temperature=overrides.get("temperature", profile.temperature),
                )
        return profile

    def local_model_available(self) -> bool:
        return self._is_healthy(ModelTier.LAYER_1_INTUITIVE_REFLEX)

    def scan_private(self, text: str) -> bool:
        for pattern in self._private_patterns:
            if re.search(pattern, text, re.IGNORECASE):
                return True
        return False

    # ── Internal ──────────────────────────────────────────────

    def _estimate_tool_depth(self, ctx: AgentRequestContext) -> int:
        depth = 0
        for msg in ctx.messages:
            if msg.get("role") == "tool":
                depth += 1
            if msg.get("tool_calls"):
                depth += len(msg["tool_calls"])
        return depth

    def _budget_available(self, tier: ModelTier) -> bool:
        cache_key = f"budget_{tier.value}"
        cached = self.health_cache.get(cache_key)
        if cached and (time.time() - cached["ts"]) < 60:
            return cached["available"]
        try:
            r = requests.get(
                f"{REGISTRY_API_URL}/registry/budget",
                params={"tier": tier.value},
                timeout=1.5,
            )
            r.raise_for_status()
            available = r.json()["remaining"] > 0
        except requests.RequestException as e:
            logger.warning("budget check failed for %s, failing open: %s", tier, e)
            available = True
        self.health_cache[cache_key] = {"available": available, "ts": time.time()}
        return available

    def _is_healthy(self, tier: ModelTier) -> bool:
        cache_key = f"health_{tier.value}"
        cached = self.health_cache.get(cache_key)
        if cached and (time.time() - cached["ts"]) < 30:
            return cached["healthy"]

        profile = self.profiles.get(tier)
        if not profile:
            return False

        healthy = False
        try:
            if tier == ModelTier.LAYER_1_INTUITIVE_REFLEX:
                r = requests.get(f"{profile.base_url}/api/tags", timeout=2)
                healthy = r.status_code == 200
            else:
                key_var = f"{profile.provider.upper()}_API_KEY"
                headers = {"Authorization": f"Bearer {os.environ.get(key_var, '')}"}
                r = requests.get(f"{profile.base_url}/models", headers=headers, timeout=3)
                healthy = r.status_code == 200
        except requests.RequestException:
            healthy = False

        self.health_cache[cache_key] = {"healthy": healthy, "ts": time.time()}
        return healthy


def scan_for_private_data(messages: list, patterns: list = None) -> bool:
    """Pure synchronous scan — zero network calls. Returns True if
    private/sensitive patterns detected in message content."""
    if patterns is None:
        patterns = [
            r"api[_-]?key",
            r"secret",
            r"password",
            r"token",
            r"/home/",
            r"/Users/",
            r"C:\\Users\\",
        ]

    text = " ".join(
        [m.get("content", "") for m in messages if m.get("content")]
    )

    for pattern in patterns:
        if re.search(pattern, text, re.IGNORECASE):
            return True

    # Deep scan tool call arguments
    for msg in messages:
        for call in msg.get("tool_calls", []):
            args = call.get("arguments", {})
            for val in args.values():
                if isinstance(val, str) and (
                    val.startswith("/home")
                    or val.startswith("/Users")
                    or "C:\\Users" in val
                ):
                    return True

    return False


# ── Integration Patch for run_agent.py ────────────────────
#
# In run_agent.py, inside AIAgent.run_conversation(), BEFORE
# client.chat.completions.create():
#
#   from router.cognitive_router import CognitiveRouter, scan_for_private_data
#
#   def run_conversation(self, user_message, ...):
#       router = CognitiveRouter()
#       while iteration < max_iterations:
#           has_private = scan_for_private_data(messages)
#
#           if has_private and not router.local_model_available():
#               raise HardStop("Local unavailable; privacy-gated request cannot proceed.")
#
#           ctx = AgentRequestContext(
#               messages=messages,
#               enabled_toolsets=self.enabled_toolsets,
#               context_tokens=self._estimate_tokens(messages),
#               task_type=self._infer_task_type(messages),
#               has_private_data=has_private,
#           )
#
#           profile = router.select_model(ctx)
#           response = self.client.chat.completions.create(
#               model=profile.model,
#               messages=messages,
#               tools=self._get_tool_schemas(profile),
#               **profile.extra_params,
#           )
