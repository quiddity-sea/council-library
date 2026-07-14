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

        $stmt = $this->pdo->prepare(
            "SELECT qvr.id, qvr.chunk_text, qvr.chunk_index,
                    qf.relative_path, qf.id as file_id,
                    1.0 - VECTOR_DISTANCE(qvr.embedding, :vec) AS similarity
             FROM quiddity_vector_references qvr
             JOIN quiddity_files qf ON qf.id = qvr.file_id
             WHERE qvr.embedding IS NOT NULL
             ORDER BY similarity DESC
             LIMIT {$limit}"
        );
        $stmt->execute(['vec' => $queryVec]);

        return $stmt->fetchAll() ?: [];
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
