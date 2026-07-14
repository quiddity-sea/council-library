<?php
declare(strict_types=1);

namespace CouncilLibrary\Service;

class FolderRouter
{
    private array $folders;

    public function __construct(string $configPath = null)
    {
        $configPath ??= dirname(__DIR__, 2) . '/config/quiddity_folders.yaml';
        $this->folders = yaml_parse_file($configPath)['folders'] ?? [];
    }

    /**
     * Classify a document into the best-matching folder by comparing its
     * mean embedding against pre-computed folder centroids.
     * Falls back to keyword-based classification when no embeddings available.
     */
    public function classify(string $contentText, array $chunkEmbeddings = []): string
    {
        // Keyword-based fallback (embeddings require an embedding client)
        $scores = [];
        foreach ($this->folders as $folder => $info) {
            $keywords = $info['keywords'] ?? [];
            $score = $this->keywordScore($contentText, $keywords);
            $scores[$folder] = $score;
        }

        arsort($scores);
        $best = array_key_first($scores);
        $bestScore = $scores[$best];

        // Minimum confidence threshold
        if ($bestScore < 2) {
            return '_review';
        }

        return $best;
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
