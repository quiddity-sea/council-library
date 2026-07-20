<?php
/**
 * Ingestion Worker for the Quiddity Lore Sea
 * Processes pending files: chunks, embeds, indexes, and classifies them.
 */

require_once __DIR__ . '/../php-api/vendor/autoload.php';

use CouncilLibrary\Service\FolderRouter;

$pdo = new PDO(
    "mysql:host=127.0.0.1;dbname=quiddity_commons;charset=utf8mb4",
    "zeon7_user",
    getenv('DB_PASS') ?: 'F0reverb0x#2o26sql',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Check for --reclassify flag
$reclassify = in_array('--reclassify', $argv);

if ($reclassify) {
    echo "Reclassify flag detected. Resetting all files to pending...\n";
    $pdo->exec("UPDATE quiddity_files SET indexing_status = 'pending'");
    echo "All files reset to pending.\n";
}

$router = new FolderRouter(null, null, $pdo);
$embeddingUrl = getenv('EMBEDDING_URL') ?: 'http://127.0.0.1:8900';

$selectStmt = $pdo->prepare(
    "SELECT id, relative_path, content_hash, mime_type, file_size_bytes 
     FROM quiddity_files 
     WHERE indexing_status IN ('pending', 'processing')
     ORDER BY id ASC"
);
$selectStmt->execute();
$files = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($files)) {
    echo "No pending files to process.\n";
    exit(0);
}

// Use UNHEX(?) for the vector column - pass hex strings directly
$chunkStmt = $pdo->prepare(
    "INSERT INTO quiddity_vector_references
        (file_id, chunk_index, chunk_text, chunk_token_count, chunk_metadata, embedding)
     VALUES (?, ?, ?, ?, ?, UNHEX(?))"
);

$statusStmt = $pdo->prepare(
    "UPDATE quiddity_files 
     SET indexing_status = ?, indexed_at = IF(? = 'indexed', NOW(), NULL), error_message = ?
     WHERE id = ?"
);

$pathUpdateStmt = $pdo->prepare(
    "UPDATE quiddity_files SET relative_path = ? WHERE id = ?"
);

echo "Ingestion Worker started (pid " . getmypid() . ")\n";

foreach ($files as $file) {
    $fileId = $file['id'];
    $relPath = $file['relative_path'];
    $fullPath = "/foreverbox_data/Quiddity_Lore_Sea/" . $relPath;

    if (!file_exists($fullPath)) {
        echo "  $relPath: $fileId — FILE NOT FOUND\n";
        $statusStmt->execute(['failed', 'failed', 'File not found on disk', $fileId]);
        continue;
    }

    $content = file_get_contents($fullPath);
    if ($content === false) {
        echo "  $relPath: $fileId — READ ERROR\n";
        $statusStmt->execute(['failed', 'failed', 'Could not read file', $fileId]);
        continue;
    }

    // Determine MIME type
    $mimeType = $file['mime_type'] ?? mime_content_type($fullPath);

    // Extract text based on MIME type
    $text = '';
    if ($mimeType === 'application/pdf') {
        // PDF extraction via pdftotext
        $tmpOut = tempnam(sys_get_temp_dir(), 'pdf_text_');
        exec("pdftotext " . escapeshellarg($fullPath) . " " . escapeshellarg($tmpOut) . " 2>&1", $out, $ret);
        if ($ret === 0 && file_exists($tmpOut)) {
            $text = file_get_contents($tmpOut);
            unlink($tmpOut);
        } else {
            $text = '';
        }
    } else {
        // Text/markdown files
        $text = $content;
    }

    if (empty(trim($text))) {
        echo "  $relPath: $fileId — NO TEXT EXTRACTED\n";
        $statusStmt->execute(['failed', 'failed', 'No text extracted', $fileId]);
        continue;
    }

    // Chunk the text (simple paragraph-based chunking)
    $paragraphs = preg_split('/\n\s*\n/', $text);
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
        echo "  $relPath: $fileId — NO CHUNKS\n";
        $statusStmt->execute(['failed', 'failed', 'No chunks generated', $fileId]);
        continue;
    }

    echo "  $relPath: $fileId — " . count($chunks) . " chunks\n";

    $statusStmt->execute(['processing', 'processing', null, $fileId]);

    try {
        // Get embeddings as hex strings directly from the service
        $embeddingsHex = getEmbeddings($chunks, $embeddingUrl);
        if (count($embeddingsHex) !== count($chunks)) {
            throw new Exception("Embedding count mismatch: " . count($embeddingsHex) . " vs " . count($chunks));
        }

        // Classify the document (uses filename + content)
        $filename = basename($relPath);
        $folder = $router->classify($text, [], $filename);

        // Move file if classified to a real folder (not _review)
        // Only move if the target is different from current location
        if ($folder !== '_review' && str_contains($folder, '/')) {
            $targetDir = "/foreverbox_data/Quiddity_Lore_Sea/" . dirname($folder);
            $targetPath = "/foreverbox_data/Quiddity_Lore_Sea/" . $folder . "/" . basename($relPath);
            // Don't move if already in the correct location
            if ($relPath !== $folder . "/" . basename($relPath)) {
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                if (rename($fullPath, $targetPath)) {
                    $newRelPath = $folder . "/" . basename($relPath);
                    $pathUpdateStmt->execute([$newRelPath, $fileId]);
                    echo "    -> Moved to: $newRelPath\n";
                }
            }
        }

        // Delete old chunks before inserting new ones (for re-indexing)
        $deleteStmt = $pdo->prepare("DELETE FROM quiddity_vector_references WHERE file_id = ?");
        $deleteStmt->execute([$fileId]);

        // Insert chunks with embeddings (hex strings via UNHEX)
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

    } catch (Throwable $e) {
        echo "    ERROR: " . $e->getMessage() . "\n";
        $statusStmt->execute(['failed', 'failed', $e->getMessage(), $fileId]);
    }
}

echo "Ingestion Worker finished.\n";

// ── Helper: fetch embeddings as hex strings ───────────────────
function getEmbeddings(array $texts, string $url): array {
    if (empty($texts)) return [];
    $ch = curl_init($url . '/embed');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['texts' => $texts]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    if (!$response) return [];
    $data = json_decode($response, true);
    return $data['embeddings'] ?? [];
}
