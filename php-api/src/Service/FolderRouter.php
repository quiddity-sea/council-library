<?php
declare(strict_types=1);

namespace CouncilLibrary\Service;

class FolderRouter
{
    private array $folders;
    private EmbeddingClient $embedder;

    public function __construct(
        ?string $configPath = null,
        ?EmbeddingClient $embedder = null,
    ) {
        $configPath ??= dirname(__DIR__, 2) . '/config/quiddity_folders.yaml';
        $this->folders = yaml_parse_file($configPath)['folders'] ?? [];
        $this->embedder = $embedder ?? new EmbeddingClient();
    }

    /**
     * Classify a document into the best-matching folder.
     * Uses vector similarity against folder centroids when embeddings available;
     * falls back to keyword matching.
     */
    public function classify(string $contentText, array $chunkEmbeddings = []): string
    {
        // Try vector-based classification
        if ($this->embedder->isAvailable()) {
            $docVec = $this->embedder->embedOne(substr($contentText, 0, 2000));
            if ($docVec) {
                return $this->vectorClassify($docVec);
            }
        }

        // Fallback: keyword matching
        $scores = [];
        foreach ($this->folders as $folder => $info) {
            $keywords = $info['keywords'] ?? [];
            $scores[$folder] = $this->keywordScore($contentText, $keywords);
        }

        arsort($scores);
        $best = array_key_first($scores);
        $bestScore = $scores[$best];

        return $bestScore < 2 ? '_review' : $best;
    }

    private function vectorClassify(string $docVec): string
    {
        // Brute-force cosine similarity against stored centroids.
        // Uses VECTOR_DISTANCE — smaller distance = more similar.
        // Cosine distance = 1 - similarity, so we ORDER BY distance ASC.
        global $pdo_commons;
        $pdo = $pdo_commons ?? null;
        if (!$pdo) return '_review';

        try {
            $stmt = $pdo->prepare(
                "SELECT folder_name,
                        VECTOR_DISTANCE(centroid, :vec) AS distance
                 FROM quiddity_folder_centroids
                 ORDER BY distance ASC LIMIT 1"
            );
            $stmt->execute(['vec' => $docVec]);
            $row = $stmt->fetch();

            if ($row) {
                $similarity = 1.0 - (float) $row['distance'];
                // Minimum confidence threshold
                return $similarity > 0.3 ? $row['folder_name'] : '_review';
            }
        } catch (\Throwable $e) {
            // Fall through to keyword matching
        }

        return '_review';
    }

    private function keywordScore(string $text, array $keywords): int
    {
        $textLower = strtolower($text);
        $score = 0;
        foreach ($keywords as $kw) {
            $score += substr_count($textLower, strtolower($kw));
        }
        return $score;
    }

    public function getFolders(): array
    {
        return $this->folders;
    }
}
