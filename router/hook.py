"""
Cognitive Router hook for Hermes Agent.

Drop this in a profile's hooks/ directory. It fires before each LLM turn,
scores the context, and overrides agent.model/provider/base_url to route
to the appropriate tier.

Configuration: profiles/<name>/router.yaml
"""
import logging
import sys
from pathlib import Path
from typing import Any

logger = logging.getLogger("cognitive_router.hook")

# Ensure the council-library router is importable
_router_path = Path("/foreverbox_data/council-library")
if str(_router_path) not in sys.path:
    sys.path.insert(0, str(_router_path))

try:
    from router import CognitiveRouter, scan_for_private_data, AgentRequestContext, ModelTier
    _ROUTER_AVAILABLE = True
except ImportError as e:
    _ROUTER_AVAILABLE = False
    logger.warning("CognitiveRouter not available: %s", e)

_router = None


def _get_router():
    global _router
    if _router is None and _ROUTER_AVAILABLE:
        _router = CognitiveRouter()
    return _router


def pre_llm_call(agent: Any, messages: list, enabled_toolsets: list) -> dict | None:
    """
    Fire before the LLM call. Returns a dict with model/provider/base_url
    overrides, or None to use the agent's defaults.
    """
    router = _get_router()
    if not router:
        return None

    # Estimate context tokens (rough: ~4 chars per token)
    context_text = " ".join([m.get("content", "") for m in messages if m.get("content")])
    context_tokens = len(context_text) // 4

    has_private = scan_for_private_data(messages)

    # Privacy gate: block cloud if local is unavailable
    if has_private and not router.local_model_available():
        logger.warning("Privacy-gated request: local model unavailable, blocking cloud routing")
        return {"model": None, "error": "local_unavailable_privacy_gate"}

    ctx = AgentRequestContext(
        messages=messages,
        enabled_toolsets=enabled_toolsets,
        context_tokens=context_tokens,
        task_type=getattr(agent, '_task_type', 'chat'),
        is_retry=getattr(agent, '_is_retry', False),
        delegation_depth=getattr(agent, '_delegation_depth', 0),
        has_private_data=has_private,
    )

    profile = router.select_model(ctx)

    logger.info(
        "Router: %s → %s/%s (load=%.2f, private=%s)",
        getattr(agent, 'model', 'unknown'),
        profile.provider,
        profile.model,
        router.estimate_load(ctx),
        has_private,
    )

    return {
        "model": profile.model,
        "provider": profile.provider,
        "base_url": profile.base_url or getattr(agent, 'base_url', None),
    }


def on_turn_start(agent: Any, messages: list, enabled_toolsets: list, **kwargs):
    """
    Hermes hook: called at the start of each conversation turn.
    Overrides agent.model/provider for this turn based on routing decision.
    """
    override = pre_llm_call(agent, messages, enabled_toolsets)
    if override:
        if override.get("error"):
            raise RuntimeError(override["error"])
        agent._router_original_model = agent.model
        agent._router_original_provider = agent.provider
        agent._router_original_base_url = agent.base_url
        agent.model = override["model"]
        agent.provider = override["provider"]
        agent.base_url = override.get("base_url", agent.base_url)


def on_turn_end(agent: Any, **kwargs):
    """Restore original model settings after the turn completes."""
    if hasattr(agent, '_router_original_model'):
        agent.model = agent._router_original_model
        agent.provider = agent._router_original_provider
        agent.base_url = agent._router_original_base_url
        del agent._router_original_model
        del agent._router_original_provider
        del agent._router_original_base_url
