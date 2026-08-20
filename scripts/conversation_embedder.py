#!/usr/bin/env python3
"""
Conversation Embedding Worker

Processes new conversation messages from Sanctum, generates embeddings
via the local embedding service (port 8900), and stores vector references
in quiddity_commons.conversation_vectors.
"""
import os
import json
import sys
import time
import requests
import mysql.connector
from datetime import datetime

EMBEDDING_URL = os.environ.get("EMBEDDING_URL", "http://127.0.0.1:8900/embed")
DB_HOST = os.environ.get("DB_HOST", "127.0.0.1")
DB_USER = os.environ.get("DB_USER", "zeon7_user")
DB_PASS = os.environ.get("DB_PASS", "F0reverb0x#2o26sql")

AGENT_DATABASES = [
    "agent_curator",    # zeon7
    "agent_producer",   # leon
    "agent_coach",      # gemma
    "agent_director",   # otec
]

def get_embedding(text: str) -> list[float]:
    """Generate 384-dim embedding via local embedding service."""
    resp = requests.post(EMBEDDING_URL, json={"text": text}, timeout=10)
    resp.raise_for_status()
    return resp.json()["embedding"]

def process_agent(agent_db: str):
    """Find unembedded conversation messages and generate vectors."""
    try:
        conn = mysql.connector.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=agent_db, charset="utf8mb4")
    except Exception as e:
        print(f"  Cannot connect to {agent_db}: {e}")
        return

    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT ch.id, ch.session_id, ch.role, ch.content_text, ch.model_used, ch.created_at
        FROM conversation_history ch
        WHERE ch.content_text IS NOT NULL AND LENGTH(ch.content_text) > 10
        ORDER BY ch.id ASC
        LIMIT 50
    """)

    messages = cursor.fetchall()
    if not messages:
        print(f"  {agent_db}: no messages to embed")
        cursor.close()
        conn.close()
        return

    print(f"  {agent_db}: processing {len(messages)} messages...")

    commons = mysql.connector.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database="quiddity_commons", charset="utf8mb4")
    cv_cursor = commons.cursor()

    for msg in messages:
        try:
            text = msg["content_text"][:2000]
            embedding = get_embedding(text)

            cv_cursor.execute("""
                INSERT INTO conversation_vectors
                (source_table, source_id, session_id, agent_id, role,
                 content_preview, embedding, created_at)
                VALUES (%s, %s, %s, %s, %s, %s, VEC_FromText(%s), %s)
                ON DUPLICATE KEY UPDATE content_preview = VALUES(content_preview)
            """, (
                agent_db,
                msg["id"],
                msg["session_id"],
                agent_db.replace("agent_", ""),
                msg["role"],
                text[:200],
                json.dumps(embedding),
                msg["created_at"].isoformat() if msg["created_at"] else datetime.now().isoformat()
            ))
            commons.commit()
        except Exception as e:
            print(f"    Error embedding message {msg['id']}: {e}")
            continue

    cv_cursor.close()
    commons.close()
    cursor.close()
    conn.close()
    print(f"  {agent_db}: finished embedding")

def main():
    print(f"=== Conversation Embedder Worker at {datetime.now().isoformat()} ===")
    for db in AGENT_DATABASES:
        try:
            process_agent(db)
        except Exception as e:
            print(f"  Error processing {db}: {e}")
    print("=== Complete ===")

if __name__ == "__main__":
    main()
