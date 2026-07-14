<?php
declare(strict_types=1);

namespace CouncilLibrary\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class WolfController
{
    public function __construct(private \PDO $pdo, private \Monolog\Logger $logger) {}

    public function status(Request $request, Response $response): Response
    {
        $agent = $request->getAttribute('agent_slug');
        $stmt = $this->pdo->prepare(
            "SELECT wolf_id, status, current_task_id, started_at, heartbeats_missed
             FROM wolf_sessions WHERE parent_lead_slug = :agent"
        );
        $stmt->execute(['agent' => $agent]);
        return $this->json($response, ['success' => true, 'wolves' => $stmt->fetchAll()]);
    }

    public function dispatch(Request $request, Response $response, array $args): Response
    {
        $agent = $request->getAttribute('agent_slug');
        $body = $request->getParsedBody();
        $wolfId = $args['wolf_id'];
        $taskId = bin2hex(random_bytes(12));

        // Insert into Registry task_queue for Wolf polling
        $regPdo = $this->getRegistryPdo();
        $stmt = $regPdo->prepare(
            "INSERT INTO task_queue
             (task_id, source_agent_slug, target_agent_slug, target_worker_id,
              action, payload_json, priority)
             VALUES (:tid, :src, :tgt, :wid, :action, :payload, :pri)"
        );
        $stmt->execute([
            'tid'     => $taskId,
            'src'     => $agent,
            'tgt'     => $agent,
            'wid'     => $wolfId,
            'action'  => $body['action'] ?? 'execute',
            'payload' => json_encode($body['payload'] ?? []),
            'pri'     => $body['priority'] ?? 'normal',
        ]);

        return $this->json($response, [
            'success' => true,
            'task_id' => $taskId,
            'wolf_id' => $wolfId,
            'status'  => 'queued',
        ]);
    }

    public function taskStatus(Request $request, Response $response, array $args): Response
    {
        $regPdo = $this->getRegistryPdo();
        $stmt = $regPdo->prepare(
            "SELECT task_id, status, claimed_by_worker_id, started_at, completed_at,
                    result_json, error_message
             FROM task_queue WHERE task_id = :tid"
        );
        $stmt->execute(['tid' => $args['task_id']]);
        $task = $stmt->fetch();

        if (!$task) {
            return $this->json($response, ['success' => false, 'error' => 'Task not found'], 404);
        }

        return $this->json($response, ['success' => true, 'task' => $task]);
    }

    public function memoryUpsert(Request $request, Response $response, array $args): Response
    {
        $body = $request->getParsedBody();
        $stmt = $this->pdo->prepare(
            "INSERT INTO wolf_working_memory
             (wolf_id, namespace, key_name, value_json)
             VALUES (:wid, :ns, :key, :val)
             ON DUPLICATE KEY UPDATE value_json = VALUES(value_json), updated_at = NOW()"
        );
        $stmt->execute([
            'wid' => $args['wolf_id'],
            'ns'  => $body['namespace'] ?? 'general',
            'key' => $body['key_name'] ?? 'scratch',
            'val' => json_encode($body['value'] ?? []),
        ]);

        return $this->json($response, ['success' => true]);
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
