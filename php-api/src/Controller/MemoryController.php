<?php
declare(strict_types=1);

namespace CouncilLibrary\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MemoryController
{
    private \PDO $pdo;
    private \Monolog\Logger $logger;

    public function __construct(\PDO $pdo, \Monolog\Logger $logger)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $agent = $request->getAttribute('agent_slug');
        $ns = $args['ns'] ?? $args['namespace'] ?? '';
        $key = $args['key'] ?? $args['key_name'] ?? '';

        $stmt = $this->pdo->prepare(
            "SELECT namespace, key_name, content_json, content_text, source_type,
                    importance, tags, created_at, updated_at
             FROM memory_lore
             WHERE agent_slug = :agent AND namespace = :ns AND key_name = :key"
        );
        $stmt->execute(['agent' => $agent, 'ns' => $ns, 'key' => $key]);
        $row = $stmt->fetch();

        if (!$row) {
            return $this->json($response, ['success' => false, 'error' => 'Not found'], 404);
        }

        return $this->json($response, ['success' => true, 'data' => $row]);
    }

    public function list(Request $request, Response $response): Response
    {
        $agent = $request->getAttribute('agent_slug');
        $params = $request->getQueryParams();

        $sql = "SELECT namespace, key_name, content_text, source_type, importance, tags, created_at
                FROM memory_lore WHERE agent_slug = :agent";
        $binds = ['agent' => $agent];

        if (!empty($params['namespace'])) {
            $sql .= " AND namespace = :ns";
            $binds['ns'] = $params['namespace'];
        }
        if (!empty($params['importance_min'])) {
            $sql .= " AND importance >= :imp";
            $binds['imp'] = (int) $params['importance_min'];
        }
        $sql .= " ORDER BY importance DESC, updated_at DESC LIMIT " .
                 (int) ($params['limit'] ?? 20);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($binds);

        return $this->json($response, ['success' => true, 'results' => $stmt->fetchAll()]);
    }

    public function search(Request $request, Response $response): Response
    {
        $agent = $request->getAttribute('agent_slug');
        $body = $request->getParsedBody();
        $query = $body['query'] ?? '';
        $ns = $body['namespace'] ?? null;
        $limit = (int) ($body['limit'] ?? 10);

        // Hybrid: FULLTEXT on content_text for keyword + relevance ordering
        $sql = "SELECT namespace, key_name, content_text, source_type, importance,
                       MATCH(content_text) AGAINST(:query IN NATURAL LANGUAGE MODE) AS relevance
                FROM memory_lore
                WHERE agent_slug = :agent AND MATCH(content_text) AGAINST(:query2 IN NATURAL LANGUAGE MODE)";
        $binds = ['agent' => $agent, 'query' => $query, 'query2' => $query];

        if ($ns) {
            $sql .= " AND namespace = :ns";
            $binds['ns'] = $ns;
        }
        $sql .= " ORDER BY relevance DESC LIMIT {$limit}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($binds);

        $this->logger->info('memory_search', [
            'agent' => $agent, 'query_hash' => hash('sha256', $query),
            'results' => $stmt->rowCount(),
        ]);

        return $this->json($response, ['success' => true, 'results' => $stmt->fetchAll()]);
    }

    public function upsert(Request $request, Response $response, array $args): Response
    {
        $agent = $request->getAttribute('agent_slug');
        $body = $request->getParsedBody();
        $ns = $args['ns'] ?? $args['namespace'] ?? '';
        $key = $args['key'] ?? $args['key_name'] ?? '';

        $stmt = $this->pdo->prepare(
            "INSERT INTO memory_lore
             (agent_slug, namespace, key_name, content_json, content_text,
              source_type, importance, tags)
             VALUES (:agent, :ns, :key, :json, :text, :source, :imp, :tags)
             ON DUPLICATE KEY UPDATE
               content_json = VALUES(content_json),
               content_text = VALUES(content_text),
               importance = VALUES(importance),
               tags = VALUES(tags),
               updated_at = NOW()"
        );
        $stmt->execute([
            'agent' => $agent,
            'ns'    => $ns,
            'key'   => $key,
            'json'  => json_encode(['raw' => $body['content'] ?? '']),
            'text'  => $body['content'] ?? '',
            'source'=> $body['source_type'] ?? 'user_directive',
            'imp'   => $body['importance'] ?? 50,
            'tags'  => json_encode($body['tags'] ?? []),
        ]);

        return $this->json($response, ['success' => true, 'upserted' => true]);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $agent = $request->getAttribute('agent_slug');
        $ns = $args['ns'] ?? $args['namespace'] ?? '';
        $key = $args['key'] ?? $args['key_name'] ?? '';
        $stmt = $this->pdo->prepare(
            "DELETE FROM memory_lore
             WHERE agent_slug = :agent AND namespace = :ns AND key_name = :key"
        );
        $stmt->execute([
            'agent' => $agent,
            'ns' => $ns,
            'key' => $key,
        ]);

        return $this->json($response, ['success' => true, 'deleted' => $stmt->rowCount() > 0]);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
