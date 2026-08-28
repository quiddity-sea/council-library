<?php
declare(strict_types=1);

namespace CouncilLibrary\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ConversationController
{
    public function __construct(private \PDO $pdo, private \CouncilLibrary\Core\Logger $logger) {}

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
            "SELECT message_seq, role, content_text, tool_calls, model_used, operator_id,
                    ip_address, source_interface, head_used, request_id, tokens_input, tokens_output, created_at
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

        $ipAddress       = $body['ip_address'] ?? null;
        $operatorId      = isset($body['operator_id']) ? (int)$body['operator_id'] : null;
        $model           = $body['metadata']['model'] ?? $body['model'] ?? null;
        $sourceInterface = $body['source_interface'] ?? $body['metadata']['source_interface'] ?? 'self_public';
        $headUsed        = $body['head_used'] ?? $body['metadata']['head'] ?? null;
        $requestId       = $body['request_id'] ?? $request->getAttribute('request_id');
        $tokensIn        = isset($body['tokens_input']) ? (int)$body['tokens_input'] : (isset($body['metadata']['tokens_input']) ? (int)$body['metadata']['tokens_input'] : null);
        $tokensOut       = isset($body['tokens_output']) ? (int)$body['tokens_output'] : (isset($body['metadata']['tokens']) ? (int)$body['metadata']['tokens'] : null);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(MAX(message_seq), 0) + 1 as next_seq
                 FROM conversation_history
                 WHERE agent_slug = :agent AND session_id = :sid
                 FOR UPDATE"
            );
            $stmt->execute(['agent' => $agent, 'sid' => $sessionId]);
            $seq = (int) $stmt->fetch()['next_seq'];

            // Single turn format: {role, content, ...}
            if (!empty($body['role']) && isset($body['content'])) {
                $this->insertMessage(
                    $agent, $sessionId, $seq++, (string)$body['role'],
                    (string)$body['content'], $model, $ipAddress, $operatorId,
                    $sourceInterface, $headUsed, $requestId, $tokensIn, $tokensOut
                );
            }

            // Dual turn format: {user, assistant}
            if (!empty($body['user'])) {
                $this->insertMessage(
                    $agent, $sessionId, $seq++, 'user',
                    (string)$body['user'], $model, $ipAddress, $operatorId,
                    $sourceInterface, $headUsed, $requestId, $tokensIn, null
                );
            }
            if (!empty($body['assistant'])) {
                $this->insertMessage(
                    $agent, $sessionId, $seq++, 'assistant',
                    (string)$body['assistant'], $model, $ipAddress, $operatorId,
                    $sourceInterface, $headUsed, $requestId, null, $tokensOut
                );
            }

            $this->pdo->commit();

            return $this->json($response, [
                'success'          => true,
                'session_id'       => $sessionId,
                'appended'         => $seq - 1,
                'source_interface' => $sourceInterface,
            ]);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger->error('conversation_append_failed', ['error' => $e->getMessage()]);
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
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
            "SELECT session_id, message_seq, role, content_text, model_used, source_interface, created_at
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
        string $content, ?string $model, ?string $ip, ?int $operatorId,
        string $sourceInterface = 'self_public', ?string $headUsed = null,
        ?string $requestId = null, ?int $tokensIn = null, ?int $tokensOut = null
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO conversation_history
             (agent_slug, session_id, message_seq, role, content_text, model_used, ip_address, operator_id,
              source_interface, head_used, request_id, tokens_input, tokens_output)
             VALUES (:agent, :sid, :seq, :role, :content, :model, :ip, :op_id,
                     :source_interface, :head_used, :request_id, :tokens_in, :tokens_out)"
        );
        $stmt->execute([
            'agent'            => $agent,
            'sid'              => $sid,
            'seq'              => $seq,
            'role'             => $role,
            'content'          => $content,
            'model'            => $model,
            'ip'               => $ip,
            'op_id'            => $operatorId,
            'source_interface' => $sourceInterface,
            'head_used'        => $headUsed,
            'request_id'       => $requestId,
            'tokens_in'        => $tokensIn,
            'tokens_out'       => $tokensOut,
        ]);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
