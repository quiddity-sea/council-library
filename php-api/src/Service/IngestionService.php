<?php
declare(strict_types=1);

namespace CouncilLibrary\Service;

use PDO;
use Throwable;
use Exception;
use RuntimeException;

class IngestionService
{
    private PDO $pdo;
    private string $embeddingUrl;
    private FolderRouter $router;
    private string $rootDir;

    public function __construct(PDO $pdo, ?string $embeddingUrl = null, ?string $rootDir = null)
    {
        $this->pdo = $pdo;
        $this->embeddingUrl = $embeddingUrl ?: (getenv('EMBEDDING_URL') ?: 'http://127.0.0.1:8900');
        $this->rootDir = $rootDir ?: '/foreverbox_data/Quiddity_Lore_Sea';
        $this->router = new FolderRouter(null, null, $pdo);
    }

    private function ensureCommons(): void
    {
        $this->pdo->exec('USE quiddity_commons');
    }

    public function ensureEmbeddingService(): void
    {
        if ($this->isEmbeddingHealthy()) {
            return;
        }

        // Try starting the systemd service if available
        exec('systemctl start council-embedding.service 2>&1', $out, $ret);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            sleep(2);
            if ($this->isEmbeddingHealthy()) {
                return;
            }
        }

        throw new RuntimeException(
            'Embedding service at ' . $this->embeddingUrl .
            ' failed to start or is not responding after 20 seconds.'
        );
    }

    public function isEmbeddingHealthy(): bool
    {
        $ch = curl_init(rtrim($this->embeddingUrl, '/') . '/health');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);

        return is_string($resp) && str_contains($resp, '"ok"');
    }

    public function resetFile(int $fileId): void
    {
        $this->ensureCommons();
        $stmt = $this->pdo->prepare(
            "UPDATE quiddity_files 
             SET indexing_status = 'pending', indexed_at = NULL, error_message = NULL 
             WHERE id = ?"
        );
        $stmt->execute([$fileId]);
    }

    public function ingestFile(int $fileId): array
    {
        $this->ensureCommons();

        $selectStmt = $this->pdo->prepare(
            "SELECT id, relative_path, content_hash, mime_type, file_size_bytes 
             FROM quiddity_files 
             WHERE id = ?"
        );
        $selectStmt->execute([$fileId]);
        $file = $selectStmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            return ['status' => 'failed', 'chunks' => 0, 'error' => 'File record not found in quiddity_files'];
        }

        $relPath = $file['relative_path'];
        $fullPath = $this->rootDir . '/' . ltrim($relPath, '/');

        $statusStmt = $this->pdo->prepare(
            "UPDATE quiddity_files 
             SET indexing_status = ?, indexed_at = IF(? = 'indexed', NOW(), NULL), error_message = ?
             WHERE id = ?"
        );

        $pathUpdateStmt = $this->pdo->prepare(
            "UPDATE quiddity_files SET relative_path = ? WHERE id = ?"
        );

        $chunkStmt = $this->pdo->prepare(
            "INSERT INTO quiddity_vector_references
                (file_id, chunk_index, chunk_text, chunk_token_count, chunk_metadata, embedding)
             VALUES (?, ?, ?, ?, ?, UNHEX(?))"
        );

        if (!file_exists($fullPath)) {
            $statusStmt->execute(['failed', 'failed', 'File not found on disk', $fileId]);
            return ['status' => 'failed', 'chunks' => 0, 'error' => 'File not found on disk'];
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            $statusStmt->execute(['failed', 'failed', 'Could not read file', $fileId]);
            return ['status' => 'failed', 'chunks' => 0, 'error' => 'Could not read file'];
        }

        $mimeType = $file['mime_type'] ?? (function_exists('mime_content_type') ? mime_content_type($fullPath) : 'text/markdown');

        $text = '';
        if ($mimeType === 'application/pdf') {
            $tmpOut = tempnam(sys_get_temp_dir(), 'pdf_text_');
            exec("pdftotext " . escapeshellarg($fullPath) . " " . escapeshellarg($tmpOut) . " 2>&1", $out, $ret);
            if ($ret === 0 && file_exists($tmpOut)) {
                $text = file_get_contents($tmpOut);
                unlink($tmpOut);
            } else {
                $text = '';
            }
        } else {
            $text = $content;
        }

        if (empty(trim((string)$text))) {
            $statusStmt->execute(['failed', 'failed', 'No text extracted', $fileId]);
            return ['status' => 'failed', 'chunks' => 0, 'error' => 'No text extracted'];
        }

        // Paragraph-based chunking with 1000 char boundary
        $paragraphs = preg_split('/\n\s*\n/', (string)$text);
        $chunks = [];
        $currentChunk = '';
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if (empty($p)) continue;
            if (strlen($currentChunk) + strlen($p) > 1000) {
                if (!empty($currentChunk)) {
                    $chunks[] = $currentChunk;
                    $currentChunk = '';
                }
            }
            $currentChunk .= ($currentChunk ? "\n\n" : '') . $p;
        }
        if (!empty($currentChunk)) {
            $chunks[] = $currentChunk;
        }

        if (empty($chunks)) {
            $statusStmt->execute(['failed', 'failed', 'No chunks generated', $fileId]);
            return ['status' => 'failed', 'chunks' => 0, 'error' => 'No chunks generated'];
        }

        $statusStmt->execute(['processing', 'processing', null, $fileId]);

        try {
            $this->ensureEmbeddingService();
            $embeddingsHex = $this->getEmbeddings($chunks);

            if (count($embeddingsHex) !== count($chunks)) {
                throw new Exception("Embedding count mismatch: " . count($embeddingsHex) . " vs " . count($chunks));
            }

            // Folder classification
            $filename = basename($relPath);
            $folder = $this->router->classify((string)$text, [], $filename);

            if ($folder !== '_review' && str_contains($folder, '/')) {
                $targetDir = $this->rootDir . '/' . dirname($folder);
                $targetPath = $this->rootDir . '/' . $folder . '/' . basename($relPath);
                if ($relPath !== $folder . '/' . basename($relPath)) {
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }
                    if (rename($fullPath, $targetPath)) {
                        $newRelPath = $folder . '/' . basename($relPath);
                        $pathUpdateStmt->execute([$newRelPath, $fileId]);
                        $relPath = $newRelPath;
                    }
                }
            }

            // Remove existing chunks
            $deleteStmt = $this->pdo->prepare("DELETE FROM quiddity_vector_references WHERE file_id = ?");
            $deleteStmt->execute([$fileId]);

            foreach ($chunks as $i => $chunk) {
                $tokenCount = str_word_count($chunk);
                $sanitised = mb_convert_encoding($chunk, 'UTF-8', 'UTF-8');
                $embHex = $embeddingsHex[$i] ?? null;
                if ($embHex === null) continue;
                $chunkStmt->execute([
                    $fileId, $i, $sanitised, $tokenCount,
                    json_encode(['source' => $relPath]), $embHex
                ]);
            }

            $statusStmt->execute(['indexed', 'indexed', null, $fileId]);

            return [
                'status'        => 'indexed',
                'chunks'        => count($chunks),
                'relative_path' => $relPath,
                'error'         => null
            ];

        } catch (Throwable $e) {
            $statusStmt->execute(['failed', 'failed', $e->getMessage(), $fileId]);

            if (!empty($chunks)) {
                $dlStmt = $this->pdo->prepare(
                    'INSERT INTO ingestion_dead_letter (file_id, chunk_index, chunk_text, error_message, error_trace, retry_count, max_retries, last_attempted_at, created_at)
                     VALUES (?, ?, ?, ?, ?, 0, 5, NOW(), NOW())'
                );
                foreach ($chunks as $ci => $ct) {
                    $dlStmt->execute([$fileId, $ci, mb_substr($ct, 0, 64000), $e->getMessage(), $e->getTraceAsString()]);
                }
            }

            return [
                'status' => 'failed',
                'chunks' => 0,
                'error'  => $e->getMessage()
            ];
        }
    }

    public function ingestPending(): array
    {
        $this->ensureCommons();
        $selectStmt = $this->pdo->prepare(
            "SELECT id FROM quiddity_files 
             WHERE indexing_status IN ('pending', 'processing')
             ORDER BY id ASC"
        );
        $selectStmt->execute();
        $files = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

        $processed = 0;
        $indexed = 0;
        $failed = 0;

        foreach ($files as $f) {
            $fileId = (int)$f['id'];
            $res = $this->ingestFile($fileId);
            $processed++;
            if (($res['status'] ?? '') === 'indexed') {
                $indexed++;
            } else {
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'indexed'   => $indexed,
            'failed'    => $failed
        ];
    }

    private function getEmbeddings(array $texts): array
    {
        if (empty($texts)) return [];
        $ch = curl_init(rtrim($this->embeddingUrl, '/') . '/embed');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['texts' => $texts]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        if (!$response) return [];
        $data = json_decode($response, true);
        return $data['embeddings'] ?? [];
    }
}
