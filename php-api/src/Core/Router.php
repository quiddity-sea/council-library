<?php
declare(strict_types=1);

namespace CouncilLibrary\Core;

use PDO;

class Router
{
    private array $routes = [];
    private array $middleware = [];
    private string $currentPrefix = '';
    private ?\Closure $containerResolver = null;

    public function __construct(private PDO $pdo, private Logger $logger) {}

    public function setContainerResolver(\Closure $resolver): void
    {
        $this->containerResolver = $resolver;
    }

    public function addMiddleware(callable|object $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function group(string $prefix, callable $callback): void
    {
        $previous = $this->currentPrefix;
        $this->currentPrefix = $previous . '/' . trim($prefix, '/');
        $callback($this);
        $this->currentPrefix = $previous;
    }

    public function get(string $path, array|callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, array|callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, array|callable $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, array|callable $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute(string $method, string $path, array|callable $handler): void
    {
        $fullPath = '/' . trim($this->currentPrefix . '/' . trim($path, '/'), '/');
        if ($fullPath === '') {
            $fullPath = '/';
        }

        // Convert {param} to regex
        $regex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $fullPath);
        $regex = '#^' . $regex . '$#';

        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $fullPath,
            'regex'   => $regex,
            'handler' => $handler
        ];
    }

    public function dispatch(Request $request): void
    {
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();
        $response = new Response();

        // 1. Match route
        $matchedRoute = null;
        $matchedArgs = [];
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                if ($route['method'] === $method) {
                    $matchedRoute = $route;
                    foreach ($matches as $k => $v) {
                        if (!is_int($k)) {
                            $matchedArgs[$k] = $v;
                        }
                    }
                    break;
                } else {
                    $allowedMethods[] = $route['method'];
                }
            }
        }

        if (!$matchedRoute) {
            if (!empty($allowedMethods)) {
                $response = $response->withStatus(405)
                    ->withHeader('Content-Type', 'application/json')
                    ->withHeader('Allow', implode(', ', array_unique($allowedMethods)));
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Method not allowed'
                ]));
                $response->send();
                return;
            }

            $response = $response->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Endpoint not found',
                'path' => $path
            ]));
            $response->send();
            return;
        }

        // 2. Execute Middleware Pipeline
        $coreHandler = function (Request $req) use ($matchedRoute, $matchedArgs, $response): Response {
            $handler = $matchedRoute['handler'];
            if (is_array($handler)) {
                [$class, $action] = $handler;
                $controller = ($this->containerResolver) ? ($this->containerResolver)($class) : new $class($this->pdo, $this->logger);
                $res = $controller->$action($req, $response, $matchedArgs);
            } else {
                $res = $handler($req, $response, $matchedArgs);
            }

            return ($res instanceof Response) ? $res : $response;
        };

        // Chain middleware in reverse order
        $pipeline = $coreHandler;
        $reversedMiddleware = array_reverse($this->middleware);

        foreach ($reversedMiddleware as $mw) {
            $next = $pipeline;
            $pipeline = function (Request $req) use ($mw, $next): Response {
                $handlerShim = new class($next) implements \Psr\Http\Server\RequestHandlerInterface {
                    public function __construct(private $callable) {}
                    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
                        return ($this->callable)($request);
                    }
                };

                if (is_object($mw) && method_exists($mw, 'process')) {
                    $res = $mw->process($req, $handlerShim);
                } else {
                    $res = $mw($req, $handlerShim);
                }

                if ($res instanceof \Slim\Psr7\Response) {
                    return new Response($res->getStatusCode(), $res->getHeaders(), (string)$res->getBody());
                }

                return ($res instanceof Response) ? $res : new Response(200, [], (string)$res);
            };
        }

        try {
            $finalResponse = $pipeline($request);
            $finalResponse->send();
        } catch (\Throwable $e) {
            $this->logger->error('unhandled_exception', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString()
            ]);

            $errResponse = new Response(500, ['Content-Type' => 'application/json'], json_encode([
                'success' => false,
                'error' => 'Internal server error: ' . $e->getMessage()
            ]));
            $errResponse->send();
        }
    }
}

