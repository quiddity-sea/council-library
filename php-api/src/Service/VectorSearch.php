<?php
declare(strict_types=1);

namespace CouncilLibrary\Service;

class VectorSearch
{
    private EmbeddingClient $embedder;

    public function __construct(
        private \PDO $pdo,
        ?EmbeddingClient $embedder = null,
    ) {
        $this->embedder = $embedder ?? new EmbeddingClient();
    }

    /**
     * Hybrid search: vector similarity when embedding service is available,
     * FULLTEXT fallback otherwise.
     */
    public function search(string $query, int $limit = 10): array
    {
        if ($this->embedder->isAvailable()) {
            return $this->vectorSearch($query, $limit);
        }
        return $this->fulltextSearch($query, $limit);
    }

    private function vectorSearch(string $query, int $limit): array
    {
        $queryVec = $this->embedder->embedOne($query);
        if (!$queryVec) {
            return $this->fulltextSearch($query, $limit);
        }

        // MariaDB 11.8 Community (Ubuntu) has VECTOR storage but no
        // VECTOR_DISTANCE function. Load candidates and compute similarity
        // in PHP using dot product on normalized vectors.
        $stmt = $this->pdo->query(
            "SELECT qvr.id, qvr.chunk_text, qvr.chunk_index,
                    qf.relative_path, qf.id as file_id, HEX(qvr.embedding) as emb_hex
             FROM quiddity_vector_references qvr
             JOIN quiddity_files qf ON qf.id = qvr.file_id
             WHERE qvr.embedding IS NOT NULL
             LIMIT 200"
        );
        $candidates = $stmt->fetchAll();

        if (empty($candidates)) {
            return [];
        }

        // Compute cosine similarity (dot product for L2-normalized vectors)
        $queryFloats = self::bytesToFloats($queryVec);
        $scored = [];
        foreach ($candidates as $row) {
            $candFloats = self::bytesToFloats(hex2bin($row['emb_hex']));
            $row['similarity'] = self::dotProduct($queryFloats, $candFloats);
            $scored[] = $row;
        }

        // Sort by similarity descending
        usort($scored, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($scored, 0, $limit);
    }

    private static function bytesToFloats(string $bytes): array
    {
        return array_values(unpack('f*', $bytes));
    }

    private static function dotProduct(array $a, array $b): float
    {
        $sum = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $sum += $a[$i] * $b[$i];
        }
        return $sum;
    }

    private function fulltextSearch(string $query, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT qvr.id, qvr.chunk_text, qvr.chunk_index,
                    qf.relative_path, qf.id as file_id,
                    MATCH(qvr.chunk_text) AGAINST(:query IN NATURAL LANGUAGE MODE) AS relevance
             FROM quiddity_vector_references qvr
             JOIN quiddity_files qf ON qf.id = qvr.file_id
             WHERE MATCH(qvr.chunk_text) AGAINST(:query2 IN NATURAL LANGUAGE MODE)
             ORDER BY relevance DESC
             LIMIT {$limit}"
        );
        $stmt->execute(['query' => $query, 'query2' => $query]);

        return $stmt->fetchAll();
    }

    public function isVectorAvailable(): bool
    {
        return $this->embedder->isAvailable();
    }
}
