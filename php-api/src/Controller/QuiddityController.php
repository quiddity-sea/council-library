<?php
declare(strict_types=1);

namespace CouncilLibrary\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use CouncilLibrary\Service\VectorSearch;
use CouncilLibrary\Service\FolderRouter;

class QuiddityController
{
    public function __construct(
        private \PDO             $pdo,
        private \Monolog\Logger  $logger,
        private VectorSearch     $search,
    ) {}

    // ── GET /v1/commons/files ────────────────────────────────────
    public function listFiles(Request $request, Response $response): Response
    {
        $this->ensureCommons();
        $params = $request->getQueryParams();

        $sql = "SELECT id, relative_path, content_hash, mime_type, file_size_bytes,
                       last_modified, indexed_at, indexing_status, error_message
                FROM quiddity_files";
        $binds = [];
        $conds = [];

        if (!empty($params['status'])) {
            $conds[] = 'indexing_status = :status';
            $binds['status'] = $params['status'];
        }
        if (!empty($params['search'])) {
            $conds[] = 'relative_path LIKE :s';
            $binds['s'] = '%' . $params['search'] . '%';
        }

        if ($conds) {
            $sql .= ' WHERE ' . implode(' AND ', $conds);
        }
        $sql .= ' ORDER BY last_modified DESC LIMIT ' . (int) ($params['limit'] ?? 50);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($binds);

        return $this->json($response, [
            'success' => true,
            'files'   => $stmt->fetchAll(),
            'count'   => $stmt->rowCount(),
        ]);
    }

    // ── POST /v1/commons/files/sync ──────────────────────────────
    public function sync(Request $request, Response $response): Response
    {
        $this->ensureCommons();
        $body     = $request->getParsedBody();
        $paths    = $body['paths']    ?? null;
        $organise = $body['organise'] ?? false;
        $context  = $body['context']  ?? 'agent';
        $agent    = $request->getAttribute('agent_slug');

        // Agent-initiated with no paths → 403
        if ($context === 'agent' && ($paths === null || $paths === [])) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Agent-initiated sync requires explicit paths. '
                           . 'Full-root scan is restricted to cron/startup contexts.',
            ], 403);
        }

        $rootDir = '/foreverbox_data/Quiddity_Lore_Sea';

        // Resolve file list
        if ($paths === null || $paths === []) {
            // Full-root scan (cron / startup)
            $found = glob($rootDir . '/*.md');
            if ($found === false) {
                return $this->json($response, [
                    'success' => false,
                    'error'   => 'Failed to scan root directory',
                ], 500);
            }
            $files = $found;
        } else {
            // Resolve relative → absolute; skip Zone.Identifier junk
            $files = [];
            foreach ($paths as $p) {
                if (str_contains((string) $p, ':Zone.Identifier')) continue;
                $files[] = str_starts_with((string) $p, '/')
                    ? (string) $p
                    : $rootDir . '/' . ltrim((string) $p, '/');
            }
        }

        $newCount       = 0;
        $changedCount   = 0;
        $unchangedCount = 0;
        $organisedCount = 0;
        $errorCount     = 0;
        $synced         = [];
        $errors         = [];
        $router         = $organise ? new FolderRouter(pdo: $this->pdo) : null;

        foreach ($files as $fullPath) {
            if (!file_exists($fullPath) || !is_file($fullPath)) {
                $errorCount++;
                $errors[] = ['path' => $fullPath, 'error' => 'Not found or not readable'];
                continue;
            }

            $relativePath = str_replace($rootDir . '/', '', $fullPath);
            $contentHash  = hash_file('sha256', $fullPath);
            $fileSize     = filesize($fullPath);
            $lastModified = date('Y-m-d H:i:s', filemtime($fullPath));

            // Check existing record
            $ck = $this->pdo->prepare(
                'SELECT id, content_hash FROM quiddity_files WHERE relative_path = :p'
            );
            $ck->execute(['p' => $relativePath]);
            $existing = $ck->fetch();

            if ($existing) {
                $fileId = (int) $existing['id'];
                if ($existing['content_hash'] === $contentHash) {
                    $status = 'unchanged';
                    $unchangedCount++;
                } else {
                    // Content changed — reset to pending for re-ingestion
                    $this->pdo->prepare(
                        "UPDATE quiddity_files
                         SET content_hash = :h, file_size_bytes = :s, last_modified = :m,
                             indexing_status = 'pending', indexed_at = NULL, error_message = NULL
                         WHERE id = :id"
                    )->execute(['h' => $contentHash, 's' => $fileSize, 'm' => $lastModified, 'id' => $fileId]);
                    $status = 'changed';
                    $changedCount++;
                }
                $synced[] = ['path' => $relativePath, 'status' => $status, 'file_id' => $fileId];
            } else {
                // New file
                $this->pdo->prepare(
                    "INSERT INTO quiddity_files
                        (relative_path, content_hash, mime_type, file_size_bytes, last_modified, indexing_status)
                     VALUES (:p, :h, 'text/markdown', :s, :m, 'pending')"
                )->execute(['p' => $relativePath, 'h' => $contentHash, 's' => $fileSize, 'm' => $lastModified]);
                $fileId = (int) $this->pdo->lastInsertId();
                $newCount++;
                $synced[] = ['path' => $relativePath, 'status' => 'new', 'file_id' => $fileId];
            }

            // Optional: organise root-level files into subfolders
            if ($router && !str_contains($relativePath, '/')) {
                $content = file_get_contents($fullPath);
                $folder  = $router->classify($content);
                if ($folder !== '_review') {
                    $targetDir = $rootDir . '/' . $folder;
                    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
                    $targetPath = $targetDir . '/' . basename($fullPath);
                    $newRelPath = $folder . '/' . basename($fullPath);
                    if (rename($fullPath, $targetPath)) {
                        $this->pdo->prepare(
                            'UPDATE quiddity_files SET relative_path = :np WHERE id = :id'
                        )->execute(['np' => $newRelPath, 'id' => $fileId]);
                        $organisedCount++;
                        $synced[count($synced) - 1]['organised_to'] = $newRelPath;
                    }
                }
            }
        }

        $this->logger->info('quiddity_sync', [
            'agent'     => $agent,
            'context'   => $context,
            'new'       => $newCount,
            'changed'   => $changedCount,
            'unchanged' => $unchangedCount,
            'organised' => $organisedCount,
            'errors'    => $errorCount,
        ]);

        return $this->json($response, [
            'success' => true,
            'summary' => [
                'new'       => $newCount,
                'changed'   => $changedCount,
                'unchanged' => $unchangedCount,
                'organised' => $organisedCount,
                'errors'    => $errorCount,
            ],
            'synced' => $synced,
            'errors' => $errors,
        ]);
    }

    // ── GET /v1/commons/search ───────────────────────────────────
    public function search(Request $request, Response $response): Response
    {
        $this->ensureCommons();
        $params = $request->getQueryParams();
        $query  = $params['q']     ?? $params['query'] ?? '';
        $limit  = (int) ($params['limit'] ?? 10);

        if ($query === '') {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Query parameter "q" is required',
            ], 400);
        }

        $results = $this->search->search($query, $limit);

        $this->logger->info('quiddity_search', [
            'query_hash' => hash('sha256', $query),
            'results'    => count($results),
        ]);

        return $this->json($response, [
            'success' => true,
            'query'   => $query,
            'results' => $results,
        ]);
    }

    // ── GET /v1/commons/files/{file_id}/chunks ───────────────────
    public function chunks(Request $request, Response $response, array $args): Response
    {
        $this->ensureCommons();
        $fileId = (int) $args['fid'];

        // Verify file exists
        $ck = $this->pdo->prepare(
            'SELECT id, relative_path, mime_type, indexing_status FROM quiddity_files WHERE id = :id'
        );
        $ck->execute(['id' => $fileId]);
        $file = $ck->fetch();

        if (!$file) {
            return $this->json($response, ['success' => false, 'error' => 'File not found'], 404);
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, chunk_index, chunk_text, chunk_token_count, chunk_metadata, created_at
             FROM quiddity_vector_references
             WHERE file_id = :fid
             ORDER BY chunk_index'
        );
        $stmt->execute(['fid' => $fileId]);

        return $this->json($response, [
            'success' => true,
            'file'    => $file,
            'chunks'  => $stmt->fetchAll(),
            'count'   => $stmt->rowCount(),
        ]);
    }

    // ── Private ──────────────────────────────────────────────────

    /**
     * Switch PDO back to quiddity_commons.
     *
     * The global AgentContext middleware switches PDO to the calling agent's
     * Sanctum (agent_{slug}). Commons endpoints must re-select the shared
     * database before every query.
     */
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
