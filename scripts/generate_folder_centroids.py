#!/usr/bin/env python3
"""
Generate folder centroids for content-based routing.
Blueprint §4.6 — Rebuilds quiddity_folder_centroids from indexed files.

Run after: index_quiddity.py
Re-run when: quiddity_folders.yaml changes or new folders added
"""
import os
import sys
import struct
import numpy as np

import mysql.connector

DB_HOST = os.environ.get("DB_HOST", "localhost")
DB_USER = os.environ.get("DB_USER", "zeon7_user")
DB_PASS = os.environ.get("DB_PASS", "")
QUIDDITY_ROOT = os.environ.get("QUIDDITY_ROOT", "/foreverbox_data/Quiddity_Lore_Sea")

FOLDERS = [
    "01_TheForeverbox_Mythos",
    "02_ReInvigor_Texts",
    "03_TheInitiative_Audio",
    "04_FromTheNoise_Archives",
    "05_Agent_Profiles",
    "06_QuiddityLtd_Dev_Specs",
]


def vector_from_blob(blob: bytes) -> np.ndarray:
    """Decode a MariaDB VECTOR(1024) BLOB to numpy float32 array."""
    return np.frombuffer(blob, dtype=np.float32)


def vector_to_blob(vec: np.ndarray) -> bytes:
    """Encode numpy float32 array to MariaDB VECTOR BLOB."""
    return vec.astype(np.float32).tobytes()


def main():
    conn = mysql.connector.connect(
        host=DB_HOST, user=DB_USER, password=DB_PASS,
        database="quiddity_commons", charset="utf8mb4",
    )
    cursor = conn.cursor()

    for folder in FOLDERS:
        print(f"Building centroid for {folder}...")

        # Get all chunk embeddings for files in this folder
        cursor.execute(
            "SELECT qvr.embedding FROM quiddity_vector_references qvr "
            "JOIN quiddity_files qf ON qf.id = qvr.file_id "
            "WHERE qf.relative_path LIKE %s AND qvr.embedding IS NOT NULL",
            (f"{folder}/%",),
        )
        rows = cursor.fetchall()

        if not rows:
            print(f"  {folder}: no embedded files — skipping")
            continue

        # Mean-pool embeddings
        vectors = [vector_from_blob(r[0]) for r in rows]
        centroid = np.mean(vectors, axis=0)
        centroid_hex = centroid.astype(np.float32).tobytes().hex()

        cursor.execute(
            "INSERT INTO quiddity_folder_centroids "
            "(folder_name, centroid, sample_count) "
            "VALUES (%s, UNHEX(%s), %s) "
            "ON DUPLICATE KEY UPDATE centroid=UNHEX(VALUES(centroid)), "
            "sample_count=VALUES(sample_count), rebuilt_at=NOW()",
            (folder, centroid_hex, len(rows)),
        )
        conn.commit()
        print(f"  {folder}: centroid built from {len(rows)} chunks")

    cursor.close()
    conn.close()
    print("\nCentroid generation complete.")


if __name__ == "__main__":
    main()
