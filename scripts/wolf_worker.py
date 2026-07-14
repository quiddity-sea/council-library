#!/usr/bin/env python3
"""
Wolf Worker — background task processor with model routing.
Blueprint §7. Polls task_queue, claims atomically, routes to LLM tier,
executes with tools, writes results to Sanctum memory_lore.

Usage: python3 wolf_worker.py --agent=curator --wolf-id=wolf_1
"""
import argparse
import json
import os
import sys
import time
from pathlib import Path

import mysql.connector
import requests

# Ensure the router is importable
ROUTER_PATH = "/foreverbox_data/council-library"
if ROUTER_PATH not in sys.path:
    sys.path.insert(0, ROUTER_PATH)

from router import CognitiveRouter

DB_HOST = os.environ.get("DB_HOST", "localhost")
DB_USER = os.environ.get("DB_USER", "zeon7_user")
DB_PASS = os.environ.get("DB_PASS", "")
API_URL = os.environ.get("FOREVERBOX_API_URL", "http://localhost:8080/v1")
API_KEY = os.environ.get("FOREVERBOX_API_KEY", "dev-key-change-in-production")
OPENROUTER_KEY = os.environ.get("OPENROUTER_API_KEY", "")
OPENROUTER_URL = "https://openrouter.ai/api/v1/chat/completions"
POLL_INTERVAL = 5

_router: CognitiveRouter | None = None


def get_router() -> CognitiveRouter:
    global _router
    if _router is None:
        _router = CognitiveRouter()
    return _router


def claim_task(cursor, worker_id: str, agent_slug: str) -> dict | None:
    cursor.execute(
        "SELECT task_id, action, payload_json FROM task_queue "
        "WHERE target_agent_slug = %s AND status = 'queued' "
        "ORDER BY priority DESC, created_at ASC "
        "LIMIT 1 FOR UPDATE SKIP LOCKED",
        (agent_slug,),
    )
    row = cursor.fetchone()
    if not row:
        return None

    task_id, action, payload_json = row
    cursor.execute(
        "UPDATE task_queue SET status = 'claimed', claimed_by_worker_id = %s, "
        "claimed_at = NOW() WHERE task_id = %s AND status = 'queued' LIMIT 1",
        (worker_id, task_id),
    )
    if cursor.rowcount == 0:
        return None

    return {
        "task_id": task_id,
        "action": action,
        "payload": json.loads(payload_json) if payload_json else {},
    }


def estimate_task_load(action: str, payload: dict) -> float:
    """Score task complexity for Wolf tier selection."""
    load = 0.0

    if action in ("research", "synthesise", "analyse", "audit"):
        load += 0.50
    if action == "synthesise":
        load += 0.25
    if len(json.dumps(payload)) > 2000:
        load += 0.20
    if payload.get("deep_reasoning"):
        load += 0.30

    return min(1.0, load)


def execute_with_llm(task: dict, agent_slug: str, wolf_id: str) -> dict:
    """Execute a Wolf task by calling the appropriate model tier."""
    router = get_router()
    load = estimate_task_load(task["action"], task["payload"])
    profile = router.wolf_select_model(load)

    action = task["action"]
    payload = task["payload"]
    query = payload.get("query", json.dumps(payload))

    # Build the system + user messages
    messages = [
        {
            "role": "system",
            "content": (
                f"You are {wolf_id}, a Wolf background worker for the {agent_slug} agent "
                f"in the Council Library ecosystem. You execute {action} tasks autonomously. "
                "You never speak to humans directly. You write results into memory_lore. "
                "Be thorough, precise, and structured. Use Markdown for output."
            ),
        },
        {
            "role": "user",
            "content": f"Task: {action}\n\nContext:\n{query}",
        },
    ]

    headers = {
        "Authorization": f"Bearer {OPENROUTER_KEY}",
        "Content-Type": "application/json",
    }

    body = {
        "model": profile.model,
        "messages": messages,
        "temperature": 0.3,
        "max_tokens": 4096,
    }

    print(f"  Wolf {wolf_id} → {profile.model} (load={load:.2f})")

    try:
        r = requests.post(OPENROUTER_URL, headers=headers, json=body, timeout=120)
        r.raise_for_status()
        data = r.json()
        content = data["choices"][0]["message"]["content"]
        return {
            "action": action,
            "model_used": profile.model,
            "load_score": load,
            "result": content,
            "usage": data.get("usage", {}),
        }
    except requests.RequestException as e:
        return {"action": action, "model_used": profile.model, "error": str(e)}


def write_result_to_sanctum(conn, task_id: str, wolf_id: str, agent_slug: str, result: dict):
    """Write Wolf results into memory_lore under {task_id}:{wolf_id} namespace."""
    cursor = conn.cursor()
    try:
        cursor.execute("USE agent_%s", (agent_slug,))
        content_text = result.get("result", json.dumps(result))
        cursor.execute(
            "INSERT INTO memory_lore "
            "(agent_slug, namespace, key_name, content_json, content_text, "
            " source_type, importance, tags) "
            "VALUES (%s, 'wolf_tasks', %s, %s, %s, 'wolf_synthesis', 60, %s) "
            "ON DUPLICATE KEY UPDATE content_text = VALUES(content_text), updated_at = NOW()",
            (
                agent_slug,
                f"{task_id}:{wolf_id}",
                json.dumps(result),
                content_text,
                json.dumps(["wolf", f"task:{task_id}"]),
            ),
        )
        conn.commit()
        cursor.close()
        return True
    except Exception as e:
        conn.rollback()
        cursor.close()
        print(f"  Memory write failed: {e}")
        return False


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--agent", required=True)
    parser.add_argument("--wolf-id", required=True)
    parser.add_argument("--once", action="store_true")
    args = parser.parse_args()

    worker_id = args.wolf_id
    agent_slug = args.agent

    conn = mysql.connector.connect(
        host=DB_HOST, user=DB_USER, password=DB_PASS,
        database="agent_registry", charset="utf8mb4", autocommit=False,
    )
    cursor = conn.cursor()

    print(f"Wolf {worker_id} started for {agent_slug} (pid {os.getpid()})")

    while True:
        try:
            task = claim_task(cursor, worker_id, agent_slug)
        except Exception as e:
            conn.rollback()
            print(f"Claim error: {e}")
            time.sleep(POLL_INTERVAL)
            continue

        if not task:
            conn.commit()
            if args.once:
                break
            time.sleep(POLL_INTERVAL)
            continue

        print(f"  Claimed {task['task_id']}: {task['action']}")

        try:
            result = execute_with_llm(task, agent_slug, worker_id)
            write_result_to_sanctum(conn, task["task_id"], worker_id, agent_slug, result)

            cursor.execute(
                "UPDATE task_queue SET status = 'completed', completed_at = NOW(), "
                "result_json = %s WHERE task_id = %s",
                (json.dumps(result), task["task_id"]),
            )
            conn.commit()
            print(f"  Completed {task['task_id']} → memory_lore")
        except Exception as e:
            conn.rollback()
            cursor.execute(
                "UPDATE task_queue SET status = 'failed', error_message = %s WHERE task_id = %s",
                (str(e)[:500], task["task_id"]),
            )
            conn.commit()
            print(f"  Failed {task['task_id']}: {e}")

        if args.once:
            break

    cursor.close()
    conn.close()
    print(f"Wolf {worker_id} shutting down.")


if __name__ == "__main__":
    main()
