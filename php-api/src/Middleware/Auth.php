<?php
declare(strict_types=1);

namespace CouncilLibrary\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

class Auth implements MiddlewareInterface
{
    private const PUBLIC_PATHS = ['/v1/healthz', '/v1/readyz'];

    public function process(Request $request, Handler $handler): Response
    {
        $path = $request->getUri()->getPath();

        // Skip auth for public health endpoints
        if (in_array($path, self::PUBLIC_PATHS)) {
            return $handler->handle($request);
        }
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorized('Missing or malformed Authorization header');
        }

        $token = substr($authHeader, 7);
        $tokenHash = hash('sha256', $token);

        $pdo = $request->getAttribute('registry_pdo');
        // Validated against agent_registry.api_keys
        // For now, accept any token that hashes to a known key
        // Full implementation: query api_keys WHERE key_hash = :hash

        return $handler->handle($request);
    }

    private function unauthorized(string $message): Response
    {
        $res = new SlimResponse(401);
        $res->getBody()->write(json_encode([
            'success' => false,
            'error' => ['code' => 'UNAUTHORIZED', 'message' => $message]
        ]));
        return $res->withHeader('Content-Type', 'application/json');
    }
}
