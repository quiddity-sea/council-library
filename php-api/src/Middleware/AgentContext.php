<?php
declare(strict_types=1);

namespace CouncilLibrary\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use PDO;

class AgentContext implements MiddlewareInterface
{
    private static array $dbMap = [
        'zeon7' => 'agent_curator',
        'leon'  => 'agent_producer',
        'gemma' => 'agent_coach',
        'otec'  => 'agent_director',
        'wolf'  => 'agent_wolf',
    ];

    public function __construct(private ?PDO $pdo = null) {}

    public function process(Request $request, Handler $handler): Response
    {
        $agentSlug = $request->getHeaderLine('X-Agent-ID') ?: 'zeon7';
        $wolfId    = $request->getHeaderLine('X-Wolf-ID');
        $requestId = $request->getHeaderLine('X-Request-ID') ?: bin2hex(random_bytes(16));

        $sanctumDb = self::$dbMap[$agentSlug] ?? 'agent_curator';

        if ($this->pdo) {
            $path = $request->getUri()->getPath();
            if (str_starts_with($path, '/v1/sanctum')) {
                $this->pdo->exec("USE `{$sanctumDb}`");
            } elseif (str_starts_with($path, '/v1/commons')) {
                $this->pdo->exec("USE `quiddity_commons`");
            } else {
                $this->pdo->exec("USE `agent_registry`");
            }
        }

        $request = $request
            ->withAttribute('agent_slug', $agentSlug)
            ->withAttribute('sanctum_db', $sanctumDb)
            ->withAttribute('wolf_id', $wolfId ?: null)
            ->withAttribute('request_id', $requestId);

        $response = $handler->handle($request);
        return $response->withHeader('X-Request-ID', $requestId);
    }
}
