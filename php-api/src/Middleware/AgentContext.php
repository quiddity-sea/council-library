<?php
declare(strict_types=1);

namespace CouncilLibrary\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class AgentContext implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        $agentSlug = $request->getHeaderLine('X-Agent-ID');
        $wolfId = $request->getHeaderLine('X-Wolf-ID');
        $requestId = $request->getHeaderLine('X-Request-ID') ?: bin2hex(random_bytes(16));

        if (!$agentSlug) {
            $agentSlug = 'unknown';
        }

        // Switch PDO to the agent's Sanctum
        // Container is accessed via the request attribute set by Slim
        $sanctumDb = 'agent_' . $agentSlug;
        try {
            // PDO switch is best-effort; controllers manage their own DB context
        } catch (\PDOException $e) {
            // Sanctum may not exist yet
        }

        $request = $request
            ->withAttribute('agent_slug', $agentSlug)
            ->withAttribute('wolf_id', $wolfId ?: null)
            ->withAttribute('request_id', $requestId)
            ->withAttribute('sanctum_pdo', $pdo);

        $response = $handler->handle($request);
        return $response->withHeader('X-Request-ID', $requestId);
    }
}
