<?php
declare(strict_types=1);

namespace CouncilLibrary\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FolderController
{
    private \PDO $pdo;
    private \Monolog\Logger $logger;
    private string $configPath;
    private string $quiddityRoot;

    public function __construct(\PDO $pdo, \Monolog\Logger $logger)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->configPath = __DIR__ . '/../../config/quiddity_folders.yaml';
        $this->quiddityRoot = rtrim($_ENV['QUIDDITY_ROOT'] ?? '/foreverbox_data/Quiddity_Lore_Sea', '/');
    }

    // ── GET /v1/commons/folders ──────────────────────────────
    public function list(Request $request, Response $response): Response
    {
        $catalogue = $this->readConfig();

        try {
            $this->useCommons();
            $stmt = $this->pdo->query(
                'SELECT folder_name, sample_count, rebuilt_at FROM quiddity_folder_centroids'
            );
            $centroidMap = [];
            foreach ($stmt->fetchAll() as $row) {
                $centroidMap[$row['folder_name']] = [
                    'sample_count' => (int) $row['sample_count'],
                    'rebuilt_at'   => $row['rebuilt_at'],
                ];
            }
            foreach ($catalogue['folders'] as $name => &$entry) {
                $entry['centroid'] = $centroidMap[$name] ?? null;
            }
            unset($entry);
        } catch (\Throwable $e) {
            $this->logger->warning('folder_list_centroid_fetch_failed', ['error' => $e->getMessage()]);
            foreach ($catalogue['folders'] as $name => &$entry) {
                $entry['centroid'] = null;
            }
            unset($entry);
        }

        return $this->json($response, [
            'success' => true,
            'folders' => $catalogue['folders'],
            'count'   => count($catalogue['folders']),
        ]);
    }

    // ── PUT /v1/commons/folders ──────────────────────────────
    public function upsert(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        $folderName   = trim($body['folder_name'] ?? '');
        $purpose      = trim($body['purpose'] ?? '');
        $createOnDisk = (bool) ($body['create_on_disk'] ?? false);

        if ($folderName === '') {
            return $this->json($response, ['success' => false, 'error' => 'folder_name is required'], 400);
        }
        if ($purpose === '') {
            return $this->json($response, ['success' => false, 'error' => 'purpose is required'], 400);
        }
        if (preg_match('#[/\\\\]|^\.|\.\.#', $folderName)) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'folder_name must not contain path separators or relative components',
            ], 400);
        }

        $catalogue = $this->readConfig();
        $isNew = !isset($catalogue['folders'][$folderName]);

        $catalogue['folders'][$folderName] = [
            'purpose'    => $purpose,
            'created_at' => $isNew
                ? gmdate('Y-m-d\TH:i:s\Z')
                : ($catalogue['folders'][$folderName]['created_at'] ?? gmdate('Y-m-d\TH:i:s\Z')),
        ];

        $this->writeConfig($catalogue);

        $dirCreated = false;
        if ($createOnDisk) {
            $dirPath = $this->quiddityRoot . '/' . $folderName;
            if (!is_dir($dirPath)) {
                $dirCreated = @mkdir($dirPath, 0755, true);
                if (!$dirCreated) {
                    $this->logger->warning('folder_upsert_mkdir_failed', [
                        'folder' => $folderName, 'path' => $dirPath,
                    ]);
                }
            }
        }

        $this->logger->info($isNew ? 'folder_created' : 'folder_updated', [
            'folder' => $folderName, 'purpose' => $purpose, 'dir_created' => $dirCreated,
        ]);

        return $this->json($response, [
            'success'     => true,
            'folder'      => $folderName,
            'action'      => $isNew ? 'created' : 'updated',
            'dir_created' => $dirCreated,
        ]);
    }

    // ── DELETE /v1/commons/folders/{folder_name} ─────────────
    public function delete(Request $request, Response $response, array $args): Response
    {
        $folderName = trim($args['folder_name'] ?? '');

        if ($folderName === '') {
            return $this->json($response, ['success' => false, 'error' => 'folder_name is required'], 400);
        }

        $catalogue = $this->readConfig();
        if (!isset($catalogue['folders'][$folderName])) {
            return $this->json($response, [
                'success' => false, 'error' => "Folder '{$folderName}' not found in catalogue",
            ], 404);
        }

        $purpose = $catalogue['folders'][$folderName]['purpose'] ?? '(unknown)';
        unset($catalogue['folders'][$folderName]);
        $this->writeConfig($catalogue);

        try {
            $this->useCommons();
            $this->pdo->prepare('DELETE FROM quiddity_folder_centroids WHERE folder_name = :name')
                ->execute(['name' => $folderName]);
        } catch (\Throwable $e) {
            $this->logger->warning('folder_delete_centroid_cleanup_failed', [
                'folder' => $folderName, 'error' => $e->getMessage(),
            ]);
        }

        $this->logger->info('folder_deleted', ['folder' => $folderName, 'purpose' => $purpose]);

        return $this->json($response, ['success' => true, 'folder' => $folderName, 'action' => 'deleted']);
    }

    // ── POST /v1/commons/folders/reclassify ──────────────────
    public function reclassify(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        $filename     = trim($body['filename'] ?? '');
        $targetFolder = trim($body['target_folder'] ?? '');

        if ($filename === '') {
            return $this->json($response, ['success' => false, 'error' => 'filename is required'], 400);
        }
        if ($targetFolder === '') {
            return $this->json($response, ['success' => false, 'error' => 'target_folder is required'], 400);
        }

        $filename = basename($filename);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return $this->json($response, ['success' => false, 'error' => 'Invalid filename'], 400);
        }

        $catalogue = $this->readConfig();
        if (!isset($catalogue['folders'][$targetFolder])) {
            return $this->json($response, [
                'success' => false, 'error' => "Target folder '{$targetFolder}' not found in catalogue",
            ], 404);
        }

        $sourcePath = $this->quiddityRoot . '/_review/' . $filename;
        if (!file_exists($sourcePath)) {
            return $this->json($response, [
                'success' => false, 'error' => "File '{$filename}' not found in _review/",
            ], 404);
        }

        $targetDir  = $this->quiddityRoot . '/' . $targetFolder;
        $targetPath = $targetDir . '/' . $filename;

        if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
            return $this->json($response, [
                'success' => false, 'error' => "Failed to create target directory '{$targetFolder}'",
            ], 500);
        }

        if (file_exists($targetPath)) {
            return $this->json($response, [
                'success' => false, 'error' => "File '{$filename}' already exists in '{$targetFolder}'",
            ], 409);
        }

        if (!@rename($sourcePath, $targetPath)) {
            $this->logger->error('reclassify_rename_failed', [
                'source' => $sourcePath, 'target' => $targetPath,
                'filename' => $filename, 'folder' => $targetFolder,
            ]);
            return $this->json($response, ['success' => false, 'error' => 'Failed to move file on disk'], 500);
        }

        $newRelativePath = $targetFolder . '/' . $filename;
        $oldPath = '_review/' . $filename;

        try {
            $this->useCommons();
            $stmt = $this->pdo->prepare(
                'UPDATE quiddity_files SET relative_path = :new WHERE relative_path = :old'
            );
            $stmt->execute(['new' => $newRelativePath, 'old' => $oldPath]);
            $updated = $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            $this->logger->error('reclassify_db_update_failed', [
                'old_path' => $oldPath, 'new_path' => $newRelativePath, 'error' => $e->getMessage(),
            ]);
            @rename($targetPath, $sourcePath); // rollback filesystem
            return $this->json($response, [
                'success' => false, 'error' => 'Database update failed; file left in _review/',
            ], 500);
        }

        $this->logger->info('file_reclassified', [
            'filename' => $filename, 'from' => '_review', 'to' => $targetFolder,
            'new_path' => $newRelativePath, 'db_updated' => $updated,
        ]);

        return $this->json($response, [
            'success'       => true,
            'filename'      => $filename,
            'target_folder' => $targetFolder,
            'new_path'      => $newRelativePath,
            'db_updated'    => $updated,
        ]);
    }

    // ── POST /v1/commons/folders/rebuild-centroids ───────────
    public function rebuildCentroids(Request $request, Response $response): Response
    {
        $jobId = bin2hex(random_bytes(16));

        $this->logger->info('centroid_rebuild_queued', ['job_id' => $jobId]);

        return $this->json($response, [
            'success' => true,
            'job_id'  => $jobId,
            'status'  => 'queued',
        ], 202);
    }

    // ── Private helpers ─────────────────────────────────────

    private function useCommons(): void
    {
        $this->pdo->exec('USE quiddity_commons');
    }

    /**
     * Parse quiddity_folders.yaml — no yaml extension dependency.
     */
    private function readConfig(): array
    {
        if (!file_exists($this->configPath)) {
            return ['folders' => []];
        }

        $lines = file($this->configPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            $this->logger->error('folder_config_read_failed', ['path' => $this->configPath]);
            return ['folders' => []];
        }

        $folders = [];
        $current = null;
        $inFolders = false;

        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === 'folders:') { $inFolders = true; continue; }
            if (!$inFolders) continue;
            if (preg_match('/^"([^"]+)":$/', $t, $m)) {
                $current = $m[1];
                $folders[$current] = ['purpose' => '', 'created_at' => ''];
                continue;
            }
            if ($current !== null && preg_match('/^(\w+):\s*"(.*)"$/', $t, $m)) {
                $folders[$current][$m[1]] = $m[2];
            }
        }

        return ['folders' => $folders];
    }

    private function writeConfig(array $catalogue): void
    {
        $dir = dirname($this->configPath);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $yaml = "# Quiddity Folder Catalogue\n"
              . "# Council Library V3.0 — maintained via FolderController\n"
              . "# Source of truth for the content-based router (§4.6)\n"
              . "# Adding a folder here requires rebuilding centroids afterward.\n\n"
              . "folders:\n";

        foreach ($catalogue['folders'] as $name => $entry) {
            $yaml .= "  \"{$name}\":\n";
            $yaml .= '    purpose: ' . json_encode($entry['purpose'] ?? '', JSON_UNESCAPED_SLASHES) . "\n";
            $yaml .= '    created_at: ' . json_encode($entry['created_at'] ?? '', JSON_UNESCAPED_SLASHES) . "\n";
        }

        if (@file_put_contents($this->configPath, $yaml, LOCK_EX) === false) {
            $this->logger->error('folder_config_write_failed', ['path' => $this->configPath]);
            throw new \RuntimeException('Failed to write folder catalogue');
        }
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
