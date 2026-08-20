<?php
declare(strict_types=1);

namespace CouncilLibrary\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ConversationController
{
    public function __construct(private \PDO $pdo, private \Monolog\Logger $logger) {}

    public function list(Request $request, Response $response): Response
    {
        $agent = $request->getAttribute('agent_slug') ?? 'curator';
        $params = $request->getQueryParams();
        $limit = (int) ($params['limit'] ?? 20);

        $stmt = $this->pdo->prepare(
            "SELECT session_id, MIN(created_at) as started, MAX(created_at) as last_active,
                    COUNT(*) as message_count, MAX(operator_id) as operator_id
             FROM conversation_history
             WHERE agent_slug = :agent
             GROUP BY session_id
             ORDER BY last_active DESC
             LIMIT {$limit}"
        );
        $stmt->execute(['agent' => $agent]);

        return $this->json($response, ['success' => true, 'sessions' => $stmt->fetchAll()]);
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $agent = $request->getAttribute('agent_slug') ?? 'curator';
        $sid = $args['sid'] ?? $args['session_id'] ?? '';
        $stmt = $this->pdo->prepare(
            "SELECT message_seq, role, content_text, tool_calls, model_used, operator_id, ip_address, created_at
             FROM conversation_history
             WHERE agent_slug = :agent AND session_id = :sid
             ORDER BY message_seq"
        );
        $stmt->execute(['agent' => $agent, 'sid' => $sid]);

        return $this->json($response, ['success' => true, 'messages' => $stmt->fetchAll()]);
    }

    public function create(Request $request, Response $response): Response
    {
        $agent = $request->getAttribute('agent_slug') ?? 'curator';
        $sessionId = bin2hex(random_bytes(16));

        return $this->json($response, [
            'success' => true,
            'session_id' => $sessionId,
            'agent_slug' => $agent,
        ], 201);
    }

    public function append(Request $request, Response $response, array $args = []): Response
    {
        $agent = $request->getAttribute('agent_slug') ?? 'curator';
        $body = $request->getParsedBody() ?? json_decode((string)$request->getBody(), true) ?? [];
        $sessionId = $args['sid'] ?? $args['session_id'] ?? $body['session_id'] ?? bin2hex(random_bytes(16));

        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(MAX(message_seq), 0) + 1 as next_seq
             FROM conversation_history
             WHERE agent_slug = :agent AND session_id = :sid"
        );
        $stmt->execute(['agent' => $agent, 'sid' => $sessionId]);
        $seq = (int) $stmt->fetch()['next_seq'];

        $ipAddress  = $body['ip_address'] ?? null;
        $operatorId = isset($body['operator_id']) ? (int)$body['operator_id'] : null;
        $model      = $body['metadata']['model'] ?? $body['model'] ?? null;

        // Single turn format: {role, content, ...}
        if (!empty($body['role']) && isset($body['content'])) {
            $this->insertMessage(
                $agent, $sessionId, $seq++, (string)$body['role'],
                (string)$body['content'], $model, $ipAddress, $operatorId
            );
        }

        // Dual turn format: {user, assistant}
        if (!empty($body['user'])) {
            $this->insertMessage(
                $agent, $sessionId, $seq++, 'user',
                (string)$body['user'], $model, $ipAddress, $operatorId
            );
        }
        if (!empty($body['assistant'])) {
            $this->insertMessage(
                $agent, $sessionId, $seq++, 'assistant',
                (string)$body['assistant'], $model, $ipAddress, $operatorId
            );
        }

        return $this->json($response, [
            'success'    => true,
            'session_id' => $sessionId,
            'appended'   => $seq - 1
        ]);
    }

    public function search(Request $request, Response $response): Response
    {
        $agent = $request->getAttribute('agent_slug') ?? 'curator';
        $body = $request->getParsedBody() ?? json_decode((string)$request->getBody(), true) ?? [];
        $query = trim($body['query'] ?? '');
        $limit = min((int)($body['limit'] ?? 10), 50);

        if (empty($query)) {
            return $this->json($response, ['error' => 'query required'], 400);
        }

        $stmt = $this->pdo->prepare(
            "SELECT session_id, message_seq, role, content_text, model_used, created_at
             FROM conversation_history
             WHERE agent_slug = :agent AND content_text LIKE :query
             ORDER BY created_at DESC
             LIMIT {$limit}"
        );
        $stmt->execute(['agent' => $agent, 'query' => '%' . $query . '%']);
        $results = $stmt->fetchAll();

        return $this->json($response, ['success' => true, 'results' => $results]);
    }

    private function insertMessage(
        string $agent, string $sid, int $seq, string $role,
        string $content, ?string $model, ?string $ip, ?int $operatorId
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO conversation_history
             (agent_slug, session_id, message_seq, role, content_text, model_used, ip_address, operator_id)
             VALUES (:agent, :sid, :seq, :role, :content, :model, :ip, :op_id)"
        );
        $stmt->execute([
            'agent'   => $agent,
            'sid'     => $sid,
            'seq'     => $seq,
            'role'    => $role,
            'content' => $content,
            'model'   => $model,
            'ip'      => $ip,
            'op_id'   => $operatorId,
        ]);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
