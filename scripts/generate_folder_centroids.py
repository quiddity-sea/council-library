#!/usr/bin/env python3
"""
Generate folder centroids by averaging chunk embeddings per folder.
Queries quiddity_files joined with quiddity_chunks, groups by folder_name,
computes average embedding vectors, and upserts into quiddity_folder_centroids.
"""

import json, sys, os, re
import numpy as np
import pymysql
from collections import defaultdict

# Load password from .env (manual parse, no dotenv dependency)
env_path = '/foreverbox_data/.env'
DB_PASS = 'F0reverb0x#2o26sql'
if os.path.exists(env_path):
    with open(env_path) as f:
        for line in f:
            line = line.strip()
            if line.startswith('DB_PASSWORD='):
                DB_PASS = line.split('=', 1)[1].strip('"\'')
            elif line.startswith('FOREVERBOX_DB_PASS='):
                DB_PASS = line.split('=', 1)[1].strip('"\'')

def db():
    return pymysql.connect(
        host='localhost', port=3306, user='zeon7_user',
        password=DB_PASS, database='quiddity_commons',
        charset='utf8mb4', cursorclass=pymysql.cursors.DictCursor
    )

def hex_to_vector(val):
    """Convert MariaDB VECTOR (binary or hex string) to numpy array of floats."""
    if isinstance(val, str):
        raw = bytes.fromhex(val)
    elif isinstance(val, bytes):
        raw = val
    else:
        raise TypeError(f"unexpected embedding type: {type(val)}")
    return np.frombuffer(raw, dtype=np.float32)

def vector_to_hex(vec):
    """Convert numpy array back to MariaDB VECTOR hex format."""
    return vec.astype(np.float32).tobytes().hex()

def main():
    conn = db()
    try:
        with conn.cursor() as cur:
            # Get all chunk embeddings with their file's folder path
            cur.execute("""
                SELECT f.relative_path, c.embedding
                FROM quiddity_vector_references c
                JOIN quiddity_files f ON c.file_id = f.id
                WHERE c.embedding IS NOT NULL
            """)
            rows = cur.fetchall()
        
        if not rows:
            print("No chunk embeddings found.")
            return
        
        # Group by folder (extract top-level folder from relative_path)
        folder_vectors = defaultdict(list)
        for row in rows:
            path = row['relative_path']
            # Extract top-level folder: "01_TheForeverbox_Mythos/origin_story/file.md" -> "01_TheForeverbox_Mythos"
            m = re.match(r'^(\d{2}_[^/]+)', path)
            if not m:
                print(f"  WARNING: cannot extract folder from path: {path}")
                continue
            folder = m.group(1)
            try:
                vec = hex_to_vector(row['embedding'])
                folder_vectors[folder].append(vec)
            except Exception as e:
                print(f"  WARNING: bad embedding for {path}: {e}")
                continue
        
        if not folder_vectors:
            print("No folders with embeddings found.")
            return
        
        print(f"Found {len(folder_vectors)} folders with embeddings:")
        
        with conn.cursor() as cur:
            for folder, vecs in sorted(folder_vectors.items()):
                avg = np.mean(vecs, axis=0)  # average across all chunks in this folder
                hex_enc = vector_to_hex(avg)
                count = len(vecs)
                
                # Upsert via INSERT ... ON DUPLICATE KEY UPDATE
                cur.execute("""
                    INSERT INTO quiddity_folder_centroids (folder_name, centroid, sample_count, rebuilt_at)
                    VALUES (%s, UNHEX(%s), %s, NOW())
                    ON DUPLICATE KEY UPDATE
                        centroid = UNHEX(%s),
                        sample_count = %s,
                        rebuilt_at = NOW()
                """, (folder, hex_enc, count, hex_enc, count))
                
                print(f"  {folder}: {count} chunk embeddings averaged")
        
        conn.commit()
        print(f"\nDone. {len(folder_vectors)} centroids generated.")
        
    finally:
        conn.close()

if __name__ == '__main__':
    main()
