<?php
declare(strict_types=1);

namespace CouncilLibrary\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

class PrivilegedActionGate implements MiddlewareInterface
{
    private const PRIVILEGED_SQL = ['DROP', 'ALTER TABLE', 'TRUNCATE', 'CREATE DATABASE',
        'DROP DATABASE', 'GRANT', 'REVOKE'];
    private const PRIVILEGED_ENDPOINTS = [
        'PUT /v1/commons/folders',
        'DELETE /v1/commons/folders',
    ];

    public function process(Request $request, Handler $handler): Response
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        $routeKey = "$method $path";

        if (!in_array($routeKey, self::PRIVILEGED_ENDPOINTS)) {
            return $handler->handle($request);
        }

        // Check for pre-confirmed action
        $body = (string) $request->getBody();
        $data = json_decode($body, true) ?? [];
        $request->getBody()->rewind();

        if (!empty($data['confirmation_code'])) {
            $code = $data['confirmation_code'];
            $pdo = $this->getRegistryPdo();
            $stmt = $pdo->prepare(
                "SELECT id, status FROM privileged_action_log
                 WHERE confirmation_code = :code AND status = 'confirmed'
                 AND confirmed_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
            );
            $stmt->execute(['code' => $code]);
            if ($stmt->fetch()) {
                return $handler->handle($request);
            }
        }

        // Generate confirmation code and block
        $code = strtoupper(bin2hex(random_bytes(4)));
        $agentSlug = $request->getAttribute('agent_slug') ?? 'unknown';
        $wolfId = $request->getAttribute('wolf_id');

        $pdo = $this->getRegistryPdo();
        $stmt = $pdo->prepare(
            "INSERT INTO privileged_action_log
             (agent_slug, wolf_id, action_type, command_text, confirmation_code, status)
             VALUES (:agent, :wolf, 'schema_alter', :cmd, :code, 'pending')"
        );
        $stmt->execute([
            'agent' => $agentSlug,
            'wolf' => $wolfId,
            'cmd' => "$method $path: " . json_encode($data),
            'code' => $code,
        ]);

        $res = new SlimResponse(412);
        $res->getBody()->write(json_encode([
            'success' => false,
            'error' => [
                'code' => 'PRIVILEGED_ACTION_REQUIRES_CONFIRMATION',
                'message' => "This action requires confirmation. Relay this code: {$code}",
                'confirmation_code' => $code,
            ],
        ]));
        return $res->withHeader('Content-Type', 'application/json');
    }

    private function getRegistryPdo(): \PDO
    {
        // Controllers manage their own DB context — the middleware
        // creates a lightweight PDO clone for registry access.
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $user = $_ENV['DB_USER'] ?? 'zeon7_user';
        $pass = $_ENV['DB_PASS'] ?? '';
        $pdo = new \PDO(
            "mysql:host={$host};dbname=agent_registry;charset=utf8mb4",
            $user, $pass,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    }
}
