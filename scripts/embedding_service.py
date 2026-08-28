#!/usr/bin/env python3
"""
Embedding Service — lightweight HTTP server for text-to-vector conversion.
Uses all-MiniLM-L6-v2 (384-dim) for fast CPU-based embeddings.

Blueprint §4.2. Called by VectorSearch service and IngestionWorker.

Usage: python3 embedding_service.py --port 8900
"""
import argparse
import json
import sys
from http.server import HTTPServer, BaseHTTPRequestHandler

import numpy as np
import struct
from sentence_transformers import SentenceTransformer


class EmbeddingHandler(BaseHTTPRequestHandler):
    model = None

    def do_POST(self):
        if self.path == "/embed":
            length = int(self.headers.get("Content-Length", 0))
            body = json.loads(self.rfile.read(length))
            texts = body.get("texts", [])

            if not texts:
                self.send_error(400, "Missing 'texts' array")
                return

            embeddings = self.model.encode(
                texts,
                normalize_embeddings=True,
                batch_size=32,
                show_progress_bar=False,
            )

            result = {
                "embeddings": [e.astype(np.float32).tobytes().hex() for e in embeddings],
                "dimensions": embeddings.shape[1],
                "count": len(embeddings),
            }
            self.send_response(200)
            self.send_header("Content-Type", "application/json")
            self.end_headers()
            self.wfile.write(json.dumps(result).encode())

        elif self.path == "/health":
            self.send_response(200)
            self.send_header("Content-Type", "application/json")
            self.end_headers()
            self.wfile.write(json.dumps({"status": "ok", "model": str(self.model)}).encode())

        else:
            self.send_error(404)

    def log_message(self, format, *args):
        pass  # quiet


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--port", type=int, default=8900)
    parser.add_argument("--model", default="all-MiniLM-L6-v2")
    args = parser.parse_args()

    print(f"Loading {args.model}...")
    EmbeddingHandler.model = SentenceTransformer(args.model)
    print(f"Model loaded. Dimensions: {EmbeddingHandler.model.get_sentence_embedding_dimension()}")

    server = HTTPServer(("127.0.0.1", args.port), EmbeddingHandler)
    print(f"Embedding service on http://127.0.0.1:{args.port}")
    server.serve_forever()


if __name__ == "__main__":
    main()
