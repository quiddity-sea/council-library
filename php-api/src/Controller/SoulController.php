<?php
declare(strict_types=1);

namespace CouncilLibrary\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SoulController
{
    private \PDO $pdo;
    private \Monolog\Logger $logger;

    public function __construct(\PDO $pdo, \Monolog\Logger $logger)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
    }

    // ──────────────────────────────────────────────
    //  Soul (sanctum DB)
    // ──────────────────────────────────────────────

    public function get(Request $request, Response $response, array $args): Response
    {
        $agent = $request->getAttribute('agent_slug');

        $stmt = $this->pdo->prepare(
            'SELECT agent_slug, version, identity_yaml, protocols_yaml, updated_at, updated_by
             FROM soul
             WHERE agent_slug = :agent'
        );
        $stmt->execute(['agent' => $agent]);
        $row = $stmt->fetch();

        if (!$row) {
            return $this->json($response, ['success' => false, 'error' => 'Soul not found'], 404);
        }

        return $this->json($response, ['success' => true, 'data' => $row]);
    }

    public function upsert(Request $request, Response $response, array $args): Response
    {
        $agent = $request->getAttribute('agent_slug');
        $body = $request->getParsedBody();

        $version        = $body['version'] ?? 1;
        $identityYaml   = $body['identity_yaml'] ?? '';
        $protocolsYaml  = $body['protocols_yaml'] ?? '';
        $updatedBy      = $body['updated_by'] ?? $agent;

        $stmt = $this->pdo->prepare(
            'INSERT INTO soul (agent_slug, version, identity_yaml, protocols_yaml, updated_at, updated_by)
             VALUES (:agent, :version, :identity, :protocols, NOW(), :updated_by)
             ON DUPLICATE KEY UPDATE
               version       = VALUES(version),
               identity_yaml = VALUES(identity_yaml),
               protocols_yaml = VALUES(protocols_yaml),
               updated_at    = NOW(),
               updated_by    = VALUES(updated_by)'
        );
        $stmt->execute([
            'agent'      => $agent,
            'version'    => $version,
            'identity'   => $identityYaml,
            'protocols'  => $protocolsYaml,
            'updated_by' => $updatedBy,
        ]);

        $this->logger->info('soul_upserted', ['agent' => $agent, 'version' => $version]);

        return $this->json($response, ['success' => true, 'upserted' => true]);
    }

    // ──────────────────────────────────────────────
    //  User Context (sanctum DB)
    // ──────────────────────────────────────────────

    public function getUserContext(Request $request, Response $response, array $args): Response
    {
        $agent = $request->getAttribute('agent_slug');

        $stmt = $this->pdo->prepare(
            'SELECT agent_slug, version, profile_yaml, relationship_notes, updated_at
             FROM user_context
             WHERE agent_slug = :agent'
        );
        $stmt->execute(['agent' => $agent]);
        $row = $stmt->fetch();

        if (!$row) {
            return $this->json($response, ['success' => false, 'error' => 'User context not found'], 404);
        }

        return $this->json($response, ['success' => true, 'data' => $row]);
    }

    public function upsertUserContext(Request $request, Response $response, array $args): Response
    {
        $agent = $request->getAttribute('agent_slug');
        $body = $request->getParsedBody();

        $version          = $body['version'] ?? 1;
        $profileYaml      = $body['profile_yaml'] ?? '';
        $relationshipNotes = $body['relationship_notes'] ?? '';

        $stmt = $this->pdo->prepare(
            'INSERT INTO user_context (agent_slug, version, profile_yaml, relationship_notes, updated_at)
             VALUES (:agent, :version, :profile, :notes, NOW())
             ON DUPLICATE KEY UPDATE
               version           = VALUES(version),
               profile_yaml      = VALUES(profile_yaml),
               relationship_notes = VALUES(relationship_notes),
               updated_at        = NOW()'
        );
        $stmt->execute([
            'agent'    => $agent,
            'version'  => $version,
            'profile'  => $profileYaml,
            'notes'    => $relationshipNotes,
        ]);

        $this->logger->info('user_context_upserted', ['agent' => $agent]);

        return $this->json($response, ['success' => true, 'upserted' => true]);
    }

    // ──────────────────────────────────────────────
    //  Budget (registry DB)
    // ──────────────────────────────────────────────

    public function getBudget(Request $request, Response $response, array $args): Response
    {
        $this->switchToRegistry();

        $params = $request->getQueryParams();
        $tier   = $params['tier'] ?? 'default';

        $stmt = $this->pdo->prepare(
            'SELECT tier, usage_date, tokens_used, daily_limit
             FROM token_budget_ledger
             WHERE tier = :tier AND usage_date = CURDATE()'
        );
        $stmt->execute(['tier' => $tier]);
        $row = $stmt->fetch();

        if (!$row) {
            // No entry yet today — return zero usage
            return $this->json($response, [
                'success'   => true,
                'data'      => [
                    'tier'         => $tier,
                    'usage_date'   => date('Y-m-d'),
                    'tokens_used'  => 0,
                    'daily_limit'  => 0,
                    'remaining'    => 0,
                ],
            ]);
        }

        $remaining = max(0, (int) $row['daily_limit'] - (int) $row['tokens_used']);

        return $this->json($response, [
            'success' => true,
            'data'    => [
                'tier'         => $row['tier'],
                'usage_date'   => $row['usage_date'],
                'tokens_used'  => (int) $row['tokens_used'],
                'daily_limit'  => (int) $row['daily_limit'],
                'remaining'    => $remaining,
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  Privileged Actions (registry DB)
    // ──────────────────────────────────────────────

    public function requestPrivileged(Request $request, Response $response, array $args): Response
    {
        $this->switchToRegistry();

        $agent  = $request->getAttribute('agent_slug');
        $wolfId = $request->getAttribute('wolf_id');
        $body   = $request->getParsedBody();

        $actionType  = $body['action_type'] ?? 'generic';
        $commandText = $body['command_text'] ?? '';
        $confirmationCode = strtoupper(bin2hex(random_bytes(4)));

        $stmt = $this->pdo->prepare(
            'INSERT INTO privileged_action_log
             (agent_slug, wolf_id, action_type, command_text, confirmation_code, status, created_at)
             VALUES (:agent, :wolf, :action_type, :cmd, :code, :status, NOW())'
        );
        $stmt->execute([
            'agent'       => $agent,
            'wolf'        => $wolfId,
            'action_type' => $actionType,
            'cmd'         => $commandText,
            'code'        => $confirmationCode,
            'status'      => 'pending',
        ]);

        $id = (int) $this->pdo->lastInsertId();

        $this->logger->info('privileged_action_requested', [
            'agent'       => $agent,
            'action_type' => $actionType,
            'id'          => $id,
        ]);

        return $this->json($response, [
            'success' => true,
            'data'    => [
                'id'                => $id,
                'confirmation_code' => $confirmationCode,
                'status'            => 'pending',
            ],
        ], 201);
    }

    public function getPrivileged(Request $request, Response $response, array $args): Response
    {
        $this->switchToRegistry();

        $id = (int) $args['id'];

        $stmt = $this->pdo->prepare(
            'SELECT id, agent_slug, wolf_id, action_type, command_text,
                    status, confirmation_code, confirmed_at, confirmed_by,
                    executed_at, result_json, created_at
             FROM privileged_action_log
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return $this->json($response, ['success' => false, 'error' => 'Privileged action not found'], 404);
        }

        return $this->json($response, ['success' => true, 'data' => $row]);
    }

    public function confirmPrivileged(Request $request, Response $response, array $args): Response
    {
        $this->switchToRegistry();

        $agent = $request->getAttribute('agent_slug');
        $id    = (int) $args['id'];
        $body  = $request->getParsedBody();
        $code  = $body['confirmation_code'] ?? '';

        // Fetch the pending action
        $stmt = $this->pdo->prepare(
            'SELECT id, confirmation_code, status
             FROM privileged_action_log
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return $this->json($response, ['success' => false, 'error' => 'Privileged action not found'], 404);
        }

        if ($row['status'] !== 'pending') {
            return $this->json($response, [
                'success' => false,
                'error'   => "Action is already {$row['status']}",
            ], 409);
        }

        if ($row['confirmation_code'] !== $code) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Invalid confirmation code',
            ], 403);
        }

        // Confirm the action
        $stmt = $this->pdo->prepare(
            'UPDATE privileged_action_log
             SET status = :status, confirmed_at = NOW(), confirmed_by = :confirmed_by
             WHERE id = :id'
        );
        $stmt->execute([
            'status'       => 'confirmed',
            'confirmed_by' => $agent,
            'id'           => $id,
        ]);

        $this->logger->info('privileged_action_confirmed', [
            'id'           => $id,
            'confirmed_by' => $agent,
        ]);

        return $this->json($response, [
            'success' => true,
            'data'    => [
                'id'     => $id,
                'status' => 'confirmed',
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    /**
     * Switch the PDO connection to the agent_registry database.
     * The middleware switches to the agent's sanctum before controller
     * invocation; registry-facing methods call this to override.
     */
    private function switchToRegistry(): void
    {
        $this->pdo->exec('USE agent_registry');
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
