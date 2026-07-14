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
        $agent = $request->getAttribute('agent_slug');
        $params = $request->getQueryParams();
        $limit = (int) ($params['limit'] ?? 20);

        $stmt = $this->pdo->prepare(
            "SELECT session_id, MIN(created_at) as started, MAX(created_at) as last_active,
                    COUNT(*) as message_count
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
        $agent = $request->getAttribute('agent_slug');
        $stmt = $this->pdo->prepare(
            "SELECT message_seq, role, content_text, tool_calls, model_used, created_at
             FROM conversation_history
             WHERE agent_slug = :agent AND session_id = :sid
             ORDER BY message_seq"
        );
        $stmt->execute(['agent' => $agent, 'sid' => $args['session_id']]);

        return $this->json($response, ['success' => true, 'messages' => $stmt->fetchAll()]);
    }

    public function create(Request $request, Response $response): Response
    {
        $agent = $request->getAttribute('agent_slug');
        $sessionId = bin2hex(random_bytes(16));

        return $this->json($response, [
            'success' => true,
            'session_id' => $sessionId,
            'agent_slug' => $agent,
        ], 201);
    }

    public function append(Request $request, Response $response, array $args): Response
    {
        $agent = $request->getAttribute('agent_slug');
        $body = $request->getParsedBody();
        $sessionId = $args['session_id'];

        $seq = 1;
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(MAX(message_seq), 0) + 1 as next_seq
             FROM conversation_history
             WHERE agent_slug = :agent AND session_id = :sid"
        );
        $stmt->execute(['agent' => $agent, 'sid' => $sessionId]);
        $seq = (int) $stmt->fetch()['next_seq'];

        // Insert user message
        if (!empty($body['user'])) {
            $this->insertMessage($agent, $sessionId, $seq++, 'user', $body['user']);
        }
        // Insert assistant message
        if (!empty($body['assistant'])) {
            $this->insertMessage($agent, $sessionId, $seq++, 'assistant', $body['assistant']);
        }

        return $this->json($response, ['success' => true, 'appended' => $seq - 1]);
    }

    private function insertMessage(string $agent, string $sid, int $seq, string $role, string $content): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO conversation_history
             (agent_slug, session_id, message_seq, role, content_text)
             VALUES (:agent, :sid, :seq, :role, :content)"
        );
        $stmt->execute([
            'agent' => $agent, 'sid' => $sid, 'seq' => $seq,
            'role' => $role, 'content' => $content,
        ]);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
