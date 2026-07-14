<?php
declare(strict_types=1);

namespace CouncilLibrary\Service;

class VectorSearch
{
    public function __construct(private \PDO $pdo) {}

    /**
     * Hybrid vector + fulltext search on quiddity_vector_references.
     * Falls back to FULLTEXT when no embedding is available for the query vector.
     */
    public function search(string $query, int $limit = 10): array
    {
        // For now: FULLTEXT fallback. Full vector search requires an embedding
        // client to convert the query string to a VECTOR(1024) for VECTOR_DISTANCE.
        $stmt = $this->pdo->prepare(
            "SELECT qvr.id, qvr.chunk_text, qvr.chunk_index, qf.relative_path,
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

    /**
     * Cosine similarity search against a query vector.
     * To be used once an embedding client is wired in.
     */
    public function vectorSearch(string $vectorBlob, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT qvr.id, qvr.chunk_text, qf.relative_path,
                    VECTOR_DISTANCE(qvr.embedding, :vec) AS distance
             FROM quiddity_vector_references qvr
             JOIN quiddity_files qf ON qf.id = qvr.file_id
             ORDER BY distance ASC
             LIMIT {$limit}"
        );
        $stmt->execute(['vec' => $vectorBlob]);

        return $stmt->fetchAll();
    }
}
