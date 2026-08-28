<?php
declare(strict_types=1);

namespace CouncilLibrary\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use CouncilLibrary\Core\Response as NativeResponse;
use PDO;

class Auth implements MiddlewareInterface
{
    private const PUBLIC_PATHS = ['/v1/healthz', '/v1/readyz'];
    private const PUBLIC_PREFIXES = ['/v1/commons/sites'];

    public function __construct(private PDO $pdo) {}

    public function process(Request $request, Handler $handler): Response
    {
        $path = $request->getUri()->getPath();

        // Skip auth for public health endpoints
        if (in_array($path, self::PUBLIC_PATHS, true)) {
            return $handler->handle($request);
        }

        // Skip auth for public path prefixes (e.g. /v1/commons/sites/*)
        foreach (self::PUBLIC_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $handler->handle($request);
            }
        }

        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorized('Missing or malformed Authorization header');
        }

        $token = trim(substr($authHeader, 7));
        if ($token === '') {
            return $this->unauthorized('Empty Bearer token provided');
        }

        $tokenHash = hash('sha256', $token);

        if ($this->pdo !== null) {
            try {
                $stmt = $this->pdo->prepare(
                    'SELECT id, key_prefix, owner_agent_slug, name, scopes, expires_at 
                     FROM agent_registry.api_keys 
                     WHERE key_hash = :hash AND (expires_at IS NULL OR expires_at > NOW())'
                );
                $stmt->execute(['hash' => $tokenHash]);
                $key = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$key) {
                    return $this->unauthorized('Invalid or expired API token');
                }

                // Update last_used_at timestamp
                $upd = $this->pdo->prepare('UPDATE agent_registry.api_keys SET last_used_at = NOW() WHERE id = :id');
                $upd->execute(['id' => $key['id']]);

                $request = $request
                    ->withAttribute('api_key_id', $key['id'])
                    ->withAttribute('api_key_owner', $key['owner_agent_slug'])
                    ->withAttribute('api_key_scopes', json_decode($key['scopes'] ?? '[]', true) ?: []);

            } catch (\Throwable $e) {
                return $this->unauthorized('Authentication database lookup failed');
            }
        }

        return $handler->handle($request);
    }

    private function unauthorized(string $message): Response
    {
        $res = new NativeResponse(401);
        $res->getBody()->write(json_encode([
            'success' => false,
            'error' => ['code' => 'UNAUTHORIZED', 'message' => $message]
        ]));
        return $res->withHeader('Content-Type', 'application/json');
    }
}
