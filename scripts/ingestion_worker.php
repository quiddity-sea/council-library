<?php
/**
 * Ingestion Worker for the Quiddity Lore Sea
 * Processes pending files: chunks, embeds, indexes, and classifies them.
 */

require_once __DIR__ . '/../php-api/src/bootstrap.php';

use CouncilLibrary\Service\IngestionService;

$pdo = new PDO(
    "mysql:host=127.0.0.1;dbname=quiddity_commons;charset=utf8mb4",
    "zeon7_user",
    getenv('DB_PASS') ?: 'F0reverb0x#2o26sql',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$service = new IngestionService($pdo);

// Check for --reclassify flag
$reclassify = in_array('--reclassify', $argv, true);

if ($reclassify) {
    echo "Reclassify flag detected. Resetting all files to pending...\n";
    $pdo->exec("UPDATE quiddity_files SET indexing_status = 'pending'");
    echo "All files reset to pending.\n";
}

echo "Ingestion Worker started (pid " . getmypid() . ")\n";

$summary = $service->ingestPending();

echo "Ingestion Worker finished. Processed: {$summary['processed']}, Indexed: {$summary['indexed']}, Failed: {$summary['failed']}\n";
