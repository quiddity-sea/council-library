<?php
declare(strict_types=1);

namespace CouncilLibrary\Service;

class FolderRouter
{
    private array $folders;
    private EmbeddingClient $embedder;
    private ?\PDO $pdo;

    public function __construct(
        ?string $configPath = null,
        ?EmbeddingClient $embedder = null,
        ?\PDO $pdo = null,
    ) {
        $configPath ??= dirname(__DIR__, 2) . '/config/quiddity_folders.yaml';
        $this->folders = self::parseYamlFolders($configPath);
        $this->embedder = $embedder ?? new EmbeddingClient();
        $this->pdo = $pdo;
    }

    // ── YAML parser (no ext-yaml dependency) ──────────────────

    private static function parseYamlFolders(string $path): array
    {
        $folders = [];
        $current = null;

        foreach (file($path) as $line) {
            $line = rtrim($line);
            if (preg_match('/^\s+"?(\w[\w_]*)"?\s*:\s*$/', $line, $m)) {
                $name = $m[1];
                if ($name === 'folders' || $name === 'keywords') continue;
                $current = $name;
                $folders[$current] = ['keywords' => [], 'purpose' => ''];
            } elseif ($current && preg_match('/^\s+-\s+"?(.+?)"?\s*$/', $line, $m)) {
                $folders[$current]['keywords'][] = trim($m[1]);
            } elseif ($current && preg_match('/^\s+purpose:\s*"(.+)"$/', $line, $m)) {
                $folders[$current]['purpose'] = $m[1];
            }
        }
        return $folders;
    }

    // ── Classification ────────────────────────────────────────

    public function classify(string $contentText, array $chunkEmbeddings = []): string
    {
        if ($this->embedder->isAvailable()) {
            $docVec = $this->embedder->embedOne(substr($contentText, 0, 2000));
            if ($docVec) {
                $result = $this->vectorClassify($docVec);
                if ($result !== null) {
                    return $result;
                }
            }
        }
        return $this->keywordClassify($contentText);
    }

    private function vectorClassify(string $docVec): ?string
    {
        if (!$this->pdo) return null;

        try {
            $stmt = $this->pdo->prepare(
                "SELECT folder_name,
                        VECTOR_DISTANCE(centroid, :vec) AS distance
                 FROM quiddity_folder_centroids
                 ORDER BY distance ASC LIMIT 1"
            );
            $stmt->execute(['vec' => $docVec]);
            $row = $stmt->fetch();

            if ($row) {
                $similarity = 1.0 - (float) $row['distance'];
                return $similarity > 0.3 ? $row['folder_name'] : '_review';
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    private function keywordClassify(string $contentText): string
    {
        $contentText = substr($contentText, 0, 4096);
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
