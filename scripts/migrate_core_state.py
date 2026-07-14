#!/usr/bin/env python3
"""
Seed the Sanctum soul/user_context mirror from Hermes profile files.
Blueprint §8.2 — Run once per Lead after schema deployment.
"""
import json
import os
import sys
from pathlib import Path

import mysql.connector

DB_HOST = os.environ.get("DB_HOST", "localhost")
DB_USER = os.environ.get("DB_USER", "zeon7_user")
DB_PASS = os.environ.get("DB_PASS", "")

PROFILES = {
    "curator": "/foreverbox_data/profiles/zeon7",
    "producer": "/foreverbox_data/profiles/leon",
    "coach": "/foreverbox_data/profiles/gemma",
    "director": "/foreverbox_data/profiles/otec",
}


def migrate_agent(conn, slug: str, profile_root: str):
    root = Path(profile_root)
    soul_md = root / "SOUL.md"
    user_md = root / "USER.md"
    memories_dir = root / "memories"

    db = f"agent_{slug}"
    conn.database = db

    cursor = conn.cursor()

    # Seed SOUL mirror
    if soul_md.exists():
        content = soul_md.read_text()
        cursor.execute(
            "INSERT INTO soul (agent_slug, version, identity_yaml, protocols_yaml, updated_by) "
            "VALUES (%s, 1, %s, %s, %s) "
            "ON DUPLICATE KEY UPDATE identity_yaml=VALUES(identity_yaml), "
            "protocols_yaml=VALUES(protocols_yaml), version=version+1",
            (slug, content, "", "migrate_core_state"),
        )
        print(f"  {slug}: SOUL.md mirrored")

    # Seed USER mirror
    if user_md.exists():
        content = user_md.read_text()
        cursor.execute(
            "INSERT INTO user_context (agent_slug, version, profile_yaml, relationship_notes) "
            "VALUES (%s, 1, %s, %s) "
            "ON DUPLICATE KEY UPDATE profile_yaml=VALUES(profile_yaml), version=version+1",
            (slug, content, ""),
        )
        print(f"  {slug}: USER.md mirrored")

    # Seed memory_lore from memories/ folder
    if memories_dir.exists():
        count = 0
        for md_file in memories_dir.rglob("*.md"):
            if md_file.name.endswith(".lock"):
                continue
            rel = md_file.relative_to(memories_dir)
            ns = str(rel.parent) if rel.parent != Path(".") else "general"
            key = rel.stem
            content = md_file.read_text()
            cursor.execute(
                "INSERT INTO memory_lore "
                "(agent_slug, namespace, key_name, content_json, content_text, source_type) "
                "VALUES (%s, %s, %s, %s, %s, 'document_ingestion') "
                "ON DUPLICATE KEY UPDATE content_text=VALUES(content_text), updated_at=NOW()",
                (slug, ns, key, json.dumps({"raw": content}), content),
            )
            count += 1
        if count:
            print(f"  {slug}: {count} memory files migrated")

    conn.commit()
    cursor.close()


def main():
    conn = mysql.connector.connect(
        host=DB_HOST, user=DB_USER, password=DB_PASS, charset="utf8mb4"
    )

    print("Council Library — Core State Migration")
    print("=" * 40)

    for slug, path in PROFILES.items():
        if Path(path).exists():
            migrate_agent(conn, slug, path)
        else:
            print(f"  {slug}: SKIPPED — profile directory not found at {path}")

    conn.close()
    print("\nMigration complete.")


if __name__ == "__main__":
    main()
