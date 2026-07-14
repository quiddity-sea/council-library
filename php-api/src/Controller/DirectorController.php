<?php
declare(strict_types=1);

namespace CouncilLibrary\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DirectorController
{
    public function __construct(private \PDO $pdo, private \Monolog\Logger $logger) {}

    public function createPlan(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $planId = bin2hex(random_bytes(12));

        $stmt = $this->pdo->prepare(
            "INSERT INTO strategic_plans (plan_id, title, description, priority, dependencies, assigned_agents)
             VALUES (:pid, :title, :desc, :pri, :deps, :agents)"
        );
        $stmt->execute([
            'pid'    => $planId,
            'title'  => $body['title'] ?? 'Untitled Plan',
            'desc'   => $body['description'] ?? null,
            'pri'    => $body['priority'] ?? 50,
            'deps'   => json_encode($body['dependencies'] ?? []),
            'agents' => json_encode($body['assigned_agents'] ?? []),
        ]);

        return $this->json($response, ['success' => true, 'plan_id' => $planId]);
    }

    public function issueDirective(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $directiveId = bin2hex(random_bytes(12));

        // Write to director's private ledger
        $stmt = $this->pdo->prepare(
            "INSERT INTO directives
             (directive_id, plan_id, target_agent_slug, action, payload_json, priority, status)
             VALUES (:did, :pid, :tgt, :action, :payload, :pri, 'dispatched')"
        );
        $stmt->execute([
            'did'     => $directiveId,
            'pid'     => $body['plan_id'] ?? null,
            'tgt'     => $body['target_agent_slug'],
            'action'  => $body['action'],
            'payload' => json_encode($body['payload'] ?? []),
            'pri'     => $body['priority'] ?? 'normal',
        ]);

        // Dispatch to Registry task_queue for Wolf visibility
        $shards = $body['task_shards'] ?? [];
        $regPdo = $this->getRegistryPdo();
        foreach ($shards as $shard) {
            $taskStmt = $regPdo->prepare(
                "INSERT INTO task_queue
                 (task_id, directive_id, source_agent_slug, target_agent_slug,
                  action, payload_json, priority, status)
                 VALUES (:tid, :did, 'director', :tgt, :action, :payload, :pri, 'queued')"
            );
            $taskStmt->execute([
                'tid'     => bin2hex(random_bytes(12)),
                'did'     => $directiveId,
                'tgt'     => $body['target_agent_slug'],
                'action'  => $shard['action'],
                'payload' => json_encode($shard['payload'] ?? []),
                'pri'     => $body['priority'] ?? 'normal',
            ]);
        }

        return $this->json($response, [
            'success' => true,
            'directive_id' => $directiveId,
            'shards_queued' => count($shards),
        ]);
    }

    public function globalStatus(Request $request, Response $response): Response
    {
        $regPdo = $this->getRegistryPdo();
        $agents = $regPdo->query("SELECT slug, display_name, role, status FROM agents")->fetchAll();
        $queueDepth = $regPdo->query("SELECT COUNT(*) as c FROM task_queue WHERE status='queued'")->fetch()['c'];

        return $this->json($response, [
            'success' => true,
            'agents' => $agents,
            'queue_depth' => $queueDepth,
        ]);
    }

    private function getRegistryPdo(): \PDO
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $user = $_ENV['DB_USER'] ?? 'zeon7_user';
        $pass = $_ENV['DB_PASS'] ?? '';
        return new \PDO(
            "mysql:host={$host};dbname=agent_registry;charset=utf8mb4",
            $user, $pass,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
