#!/usr/bin/env python3
"""
Wolf Worker — background task processor for Council Library agents.
Blueprint §7. Polls agent_registry.task_queue, claims tasks atomically,
executes them, and writes results back to the Sanctum.

Usage: python3 wolf_worker.py --agent=curator --wolf-id=wolf_1
"""
import argparse
import json
import os
import sys
import time
import uuid
from datetime import datetime

import mysql.connector
import requests

DB_HOST = os.environ.get("DB_HOST", "localhost")
DB_USER = os.environ.get("DB_USER", "zeon7_user")
DB_PASS = os.environ.get("DB_PASS", "")
API_URL = os.environ.get("FOREVERBOX_API_URL", "http://localhost:8080/v1")
API_KEY = os.environ.get("FOREVERBOX_API_KEY", "dev-key-change-in-production")
POLL_INTERVAL = 5


def claim_task(cursor, worker_id: str, agent_slug: str) -> dict | None:
    """Atomic claim: SELECT FOR UPDATE SKIP LOCKED + conditional UPDATE."""
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
        return None  # Another worker got it

    return {
        "task_id": task_id,
        "action": action,
        "payload": json.loads(payload_json) if payload_json else {},
    }


def execute_task(task: dict, agent_slug: str) -> dict:
    """Execute a claimed task. For now, passes through to the API."""
    action = task["action"]
    payload = task["payload"]

    headers = {
        "Authorization": f"Bearer {API_KEY}",
        "X-Agent-ID": agent_slug,
        "Content-Type": "application/json",
        "X-Request-ID": str(uuid.uuid4()),
    }

    # Route common action types to API endpoints
    if action in ("research", "audit", "synthesise", "index", "analyse"):
        r = requests.post(
            f"{API_URL}/commons/search",
            params={"query": json.dumps(payload)},
            headers=headers,
            timeout=30,
        )
        return {"action": action, "api_result": r.json() if r.ok else {"error": r.text}}

    return {"action": action, "status": "executed", "payload": payload}


def main():
    parser = argparse.ArgumentParser(description="Council Library Wolf Worker")
    parser.add_argument("--agent", required=True, help="Agent slug (curator, producer, etc.)")
    parser.add_argument("--wolf-id", required=True, help="Unique wolf identifier")
    parser.add_argument("--once", action="store_true", help="Process one task and exit")
    args = parser.parse_args()

    worker_id = args.wolf_id
    agent_slug = args.agent

    conn = mysql.connector.connect(
        host=DB_HOST, user=DB_USER, password=DB_PASS,
        database="agent_registry", charset="utf8mb4",
        autocommit=False,
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
            result = execute_task(task, agent_slug)
            cursor.execute(
                "UPDATE task_queue SET status = 'completed', completed_at = NOW(), "
                "result_json = %s WHERE task_id = %s",
                (json.dumps(result), task["task_id"]),
            )
            conn.commit()
            print(f"  Completed {task['task_id']}")
        except Exception as e:
            conn.rollback()
            cursor.execute(
                "UPDATE task_queue SET status = 'failed', error_message = %s "
                "WHERE task_id = %s",
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
