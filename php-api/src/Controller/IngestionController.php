<?php
declare(strict_types=1);

namespace CouncilLibrary\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class IngestionController
{
    public function __construct(private \PDO $pdo, private \Monolog\Logger $logger) {}

    public function batch(Request $request, Response $response): Response
    {
        $agent = $request->getAttribute('agent_slug');
        $body = $request->getParsedBody();
        $jobId = bin2hex(random_bytes(12));

        $this->logger->info('ingestion_batch_requested', [
            'agent' => $agent,
            'job_id' => $jobId,
            'file_count' => count($body['files'] ?? []),
        ]);

        // Ingestion pipeline-only: full implementation would spawn background
        // workers to chunk, embed, and store. For now, accepted and queued.
        return $this->json($response, [
            'success' => true,
            'job_id' => $jobId,
            'status' => 'accepted',
            'estimated_chunks' => 0,  // computed by the worker
        ]);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
