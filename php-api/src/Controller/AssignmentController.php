<?php
declare(strict_types=1);

namespace CouncilLibrary\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class AssignmentController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * GET /v1/registry/assignments?user_id=N
     * Returns all active agent assignments for a user.
     */
    public function listByUser(Request $request, Response $response): Response
    {
        $userId = $request->getQueryParams()['user_id'] ?? null;
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'user_id required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $this->db->prepare(
            'SELECT agent_id, template_id, permissions, memory_scope, status
             FROM agent_registry.user_agent_assignments
             WHERE user_id = :uid AND status = "active"
             ORDER BY agent_id'
        );
        $stmt->execute([':uid' => (int)$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['permissions'] = json_decode($row['permissions'], true);
        }

        $response->getBody()->write(json_encode(['assignments' => $rows], JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * PUT /v1/registry/assignments
     * Create or update a user-agent assignment.
     */
    public function upsert(Request $request, Response $response): Response
    {
        $body = json_decode((string)$request->getBody(), true);

        $required = ['user_id', 'agent_id'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                $response->getBody()->write(json_encode(['error' => "{$field} required"]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        }

        $stmt = $this->db->prepare(
            'INSERT INTO agent_registry.user_agent_assignments
             (user_id, agent_id, template_id, permissions, memory_scope, status)
             VALUES (:uid, :aid, :tid, :perms, :scope, :status)
             ON DUPLICATE KEY UPDATE
             template_id = VALUES(template_id),
             permissions = VALUES(permissions),
             memory_scope = VALUES(memory_scope),
             status = VALUES(status),
             updated_at = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            ':uid'    => (int)$body['user_id'],
            ':aid'    => $body['agent_id'],
            ':tid'    => $body['template_id'] ?? 'default',
            ':perms'  => json_encode($body['permissions'] ?? ['chat']),
            ':scope'  => $body['memory_scope'] ?? 'public',
            ':status' => $body['status'] ?? 'active',
        ]);

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
