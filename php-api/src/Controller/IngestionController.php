<?php
declare(strict_types=1);

namespace CouncilLibrary\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use CouncilLibrary\Service\IngestionService;
use PDO;

class IngestionController
{
    private string $rootDir;

    public function __construct(
        private PDO $pdo,
        private \CouncilLibrary\Core\Logger $logger,
        ?string $rootDir = null
    ) {
        $this->rootDir = $rootDir ?: '/foreverbox_data/Quiddity_Lore_Sea';
    }

    public function upload(Request $request, Response $response): Response
    {
        set_time_limit(300);
        $this->ensureCommons();

        $files = $request->getUploadedFiles();
        if (empty($files['file'])) {
            // Check superglobal fallback
            $files = $_FILES;
        }

        $file = $files['file'] ?? null;
        if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'No valid file uploaded'
            ], 400);
        }

        $originalName = basename($file['name'] ?? '');
        $tmpPath = $file['tmp_name'] ?? '';

        if (empty($originalName) || !file_exists($tmpPath)) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Uploaded temporary file missing'
            ], 400);
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['md', 'txt', 'pdf'], true)) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Only .md, .txt, and .pdf files are allowed'
            ], 400);
        }

        // Limit size to 10MB
        $fileSize = (int)($file['size'] ?? filesize($tmpPath));
        if ($fileSize > 10 * 1024 * 1024) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'File size exceeds maximum limit of 10MB'
            ], 400);
        }

        $body = $request->getParsedBody() ?: $_POST;
        $subfolder = trim((string)($body['subfolder'] ?? ''));

        // Prevent path traversal in subfolder
        $subfolder = str_replace(['..', '\\'], '', $subfolder);
        $subfolder = trim($subfolder, '/');

        $targetDir = $this->rootDir;
        if (!empty($subfolder)) {
            $targetDir .= '/' . $subfolder;
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . '/' . $originalName;
        $relativePath = !empty($subfolder) ? ($subfolder . '/' . $originalName) : $originalName;

        $content = file_get_contents($tmpPath);
        if ($content === false || file_put_contents($targetPath, $content) === false) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Failed to write file to Quiddity Lore Sea filesystem'
            ], 500);
        }

        $contentHash = hash_file('sha256', $targetPath);
        $lastModified = date('Y-m-d H:i:s', filemtime($targetPath));
        $mimeType = $ext === 'pdf' ? 'application/pdf' : 'text/markdown';

        // Check if file already exists in quiddity_files
        $ck = $this->pdo->prepare('SELECT id FROM quiddity_files WHERE relative_path = :p');
        $ck->execute(['p' => $relativePath]);
        $existing = $ck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $fileId = (int)$existing['id'];
            $this->pdo->prepare(
                "UPDATE quiddity_files
                 SET content_hash = :h, file_size_bytes = :s, last_modified = :m,
                     indexing_status = 'pending', indexed_at = NULL, error_message = NULL
                 WHERE id = :id"
            )->execute([
                'h'  => $contentHash,
                's'  => $fileSize,
                'm'  => $lastModified,
                'id' => $fileId
            ]);
        } else {
            $this->pdo->prepare(
                "INSERT INTO quiddity_files
                    (relative_path, content_hash, mime_type, file_size_bytes, last_modified, indexing_status)
                 VALUES (:p, :h, :mime, :s, :m, 'pending')"
            )->execute([
                'p'    => $relativePath,
                'h'    => $contentHash,
                'mime' => $mimeType,
                's'    => $fileSize,
                'm'    => $lastModified
            ]);
            $fileId = (int)$this->pdo->lastInsertId();
        }

        // Inline Ingestion
        $ingestionService = new IngestionService($this->pdo, null, $this->rootDir);
        $ingestRes = $ingestionService->ingestFile($fileId);

        $this->logger->info('quiddity_file_uploaded_and_ingested', [
            'file_id'       => $fileId,
            'relative_path' => $ingestRes['relative_path'] ?? $relativePath,
            'status'        => $ingestRes['status'] ?? 'pending',
            'chunks'        => $ingestRes['chunks'] ?? 0
        ]);

        return $this->json($response, [
            'success'         => true,
            'file_id'         => $fileId,
            'relative_path'   => $ingestRes['relative_path'] ?? $relativePath,
            'indexing_status' => $ingestRes['status'] ?? 'pending',
            'chunk_count'     => $ingestRes['chunks'] ?? 0,
            'error'           => $ingestRes['error'] ?? null
        ]);
    }

    public function batch(Request $request, Response $response): Response
    {
        set_time_limit(300);
        $this->ensureCommons();

        $agent = $request->getAttribute('agent_slug');
        $body = $request->getParsedBody() ?: [];
        $fileIds = $body['files'] ?? $body['file_ids'] ?? [];

        $ingestionService = new IngestionService($this->pdo, null, $this->rootDir);

        if (empty($fileIds)) {
            $summary = $ingestionService->ingestPending();
            return $this->json($response, [
                'success'   => true,
                'mode'      => 'pending',
                'summary'   => $summary
            ]);
        }

        $results = [];
        foreach ($fileIds as $fid) {
            $fileId = (int)$fid;
            $ingestionService->resetFile($fileId);
            $res = $ingestionService->ingestFile($fileId);
            $results[] = [
                'file_id' => $fileId,
                'result'  => $res
            ];
        }

        return $this->json($response, [
            'success' => true,
            'mode'    => 'explicit',
            'results' => $results
        ]);
    }

    private function ensureCommons(): void
    {
        $this->pdo->exec('USE quiddity_commons');
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
