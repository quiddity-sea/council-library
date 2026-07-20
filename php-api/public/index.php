<?php
declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use CouncilLibrary\Controller\{
    SoulController, MemoryController, ConversationController,
    WolfController, QuiddityController, IngestionController,
    FolderController, DirectorController, ConnectedSitesController
};

$container = require __DIR__ . '/../src/bootstrap.php';
AppFactory::setContainer($container);
$app = AppFactory::create();

$app->add(\CouncilLibrary\Middleware\Auth::class);
$app->add(\CouncilLibrary\Middleware\AgentContext::class);
$app->add(\CouncilLibrary\Middleware\PrivilegedActionGate::class);
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Shortcut: resolve controller from container, switch to agent's Sanctum
function c(string $class, string $method): callable {
    return function (Request $req, Response $res, array $args) use ($class, $method) {
        $agent = $req->getAttribute('agent_slug') ?? 'curator';
        $pdo = $this->get(PDO::class);
        $sanctum = 'agent_' . $agent;
        try { $pdo->exec("USE `{$sanctum}`"); } catch (\PDOException $e) {}
        $ctrl = $this->get($class);
        return $ctrl->$method($req, $res, $args);
    };
}

// ── Health ──────────────────────────────────────────────────
$app->get('/v1/healthz', function (Request $req, Response $res): Response {
    $res->getBody()->write(json_encode(['status' => 'ok']));
    return $res->withHeader('Content-Type', 'application/json');
});

$app->get('/v1/readyz', function (Request $req, Response $res): Response {
    $this->get(PDO::class)->query('SELECT 1');
    $res->getBody()->write(json_encode(['status' => 'ready', 'db' => 'connected']));
    return $res->withHeader('Content-Type', 'application/json');
});

// ── Sanctum ─────────────────────────────────────────────────
$app->group('/v1/sanctum', function (RouteCollectorProxy $s) {
    $s->get('/soul', c(SoulController::class, 'get'));
    $s->put('/soul', c(SoulController::class, 'upsert'));
    $s->get('/user-context', c(SoulController::class, 'getUserContext'));
    $s->put('/user-context', c(SoulController::class, 'upsertUserContext'));

    $s->get('/memory', c(MemoryController::class, 'list'));
    $s->post('/memory/search', c(MemoryController::class, 'search'));
    $s->get('/memory/{ns}/{key}', c(MemoryController::class, 'get'));
    $s->put('/memory/{ns}/{key}', c(MemoryController::class, 'upsert'));
    $s->delete('/memory/{ns}/{key}', c(MemoryController::class, 'delete'));

    // Dynamic plugin paths
    $s->put('/memory/session_summaries/{key}', c(MemoryController::class, 'putDynamic'));
    $s->put('/memory/delegation_log/{key}', c(MemoryController::class, 'putDynamic'));
    $s->put('/memory/compression_snapshots/{key}', c(MemoryController::class, 'putDynamic'));
    $s->put('/memory/hermes_builtin/{action}', c(MemoryController::class, 'putDynamic'));

    $s->get('/conversations', c(ConversationController::class, 'list'));
    $s->get('/conversations/{sid}', c(ConversationController::class, 'get'));
    $s->post('/conversations', c(ConversationController::class, 'create'));
    $s->post('/conversations/{sid}/messages', c(ConversationController::class, 'append'));

    $s->get('/wolves/status', c(WolfController::class, 'status'));
    $s->post('/wolves/{wid}/task', c(WolfController::class, 'dispatch'));
    $s->get('/wolves/{wid}/task/{tid}', c(WolfController::class, 'taskStatus'));
    $s->post('/wolves/{wid}/memory', c(WolfController::class, 'memoryUpsert'));
});

// ── Commons ─────────────────────────────────────────────────
$app->group('/v1/commons', function (RouteCollectorProxy $c) {
    $c->get('/files', c(CommonsController::class, 'listFiles'));
    $c->post('/files/sync', c(CommonsController::class, 'syncFiles'));
    $c->get('/files/{id}/chunks', c(CommonsController::class, 'getChunks'));
    $c->get('/search', c(CommonsController::class, 'search'));
    $c->get('/folders', c(FolderController::class, 'list'));
    $c->put('/folders', c(FolderController::class, 'upsert'));
    $c->delete('/folders/{name}', c(FolderController::class, 'delete'));
    $c->post('/folders/reclassify', c(FolderController::class, 'reclassify'));
    $c->post('/folders/rebuild-centroids', c(FolderController::class, 'rebuildCentroids'));
    $c->get('/sites', c(ConnectedSitesController::class, 'list'));
    $c->get('/sites/{slug}', c(ConnectedSitesController::class, 'get'));
    $c->post('/sites', c(ConnectedSitesController::class, 'upsert'));
});

// ── Director ────────────────────────────────────────────────
$app->group('/v1/director', function (RouteCollectorProxy $d) {
    $d->post('/plans', c(DirectorController::class, 'createPlan'));
    $d->post('/directives', c(DirectorController::class, 'issueDirective'));
    $d->get('/status', c(DirectorController::class, 'globalStatus'));
});

// ── Registry ────────────────────────────────────────────────
$app->group('/v1/registry', function (RouteCollectorProxy $r) {
    $r->get('/budget', c(SoulController::class, 'getBudget'));
    $r->post('/privileged-actions', c(SoulController::class, 'requestPrivileged'));
    $r->get('/privileged-actions/{id}', c(SoulController::class, 'getPrivileged'));
    $r->post('/privileged-actions/{id}/confirm', c(SoulController::class, 'confirmPrivileged'));
});

$app->run();
