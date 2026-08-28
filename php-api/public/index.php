<?php
declare(strict_types=1);

use CouncilLibrary\Core\Request;
use CouncilLibrary\Core\Response;
use CouncilLibrary\Controller\{
    SoulController, MemoryController, ConversationController,
    WolfController, QuiddityController, IngestionController,
    FolderController, DirectorController, ConnectedSitesController,
    AssignmentController, AgentCatalogueController
};

$app = require __DIR__ . '/../src/bootstrap.php';
$pdo = $app['pdo'];
$logger = $app['logger'];
$router = $app['router'];

// Attach Middleware pipeline
$router->addMiddleware(new \CouncilLibrary\Middleware\Auth($pdo));
$router->addMiddleware(new \CouncilLibrary\Middleware\AgentContext($pdo));
$router->addMiddleware(new \CouncilLibrary\Middleware\PrivilegedActionGate());

// Controller resolver helper
function c(string $class, string $method): array {
    return [$class, $method];
}

// ── Health ──────────────────────────────────────────────────
$router->get('/v1/healthz', function (Request $req, Response $res): Response {
    $res->getBody()->write(json_encode(['status' => 'ok']));
    return $res->withHeader('Content-Type', 'application/json');
});

$router->get('/v1/readyz', function (Request $req, Response $res) use ($pdo): Response {
    $pdo->query('SELECT 1');
    $res->getBody()->write(json_encode(['status' => 'ready', 'db' => 'connected']));
    return $res->withHeader('Content-Type', 'application/json');
});

// ── Sanctum ─────────────────────────────────────────────────
$router->group('/v1/sanctum', function ($s) {
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
    $s->post('/conversations/search', c(ConversationController::class, 'search'));
    $s->get('/conversations/{sid}', c(ConversationController::class, 'get'));
    $s->post('/conversations', c(ConversationController::class, 'create'));
    $s->post('/conversations/{sid}/messages', c(ConversationController::class, 'append'));

    $s->get('/wolves/status', c(WolfController::class, 'status'));
    $s->post('/wolves/{wid}/task', c(WolfController::class, 'dispatch'));
    $s->get('/wolves/{wid}/task/{tid}', c(WolfController::class, 'taskStatus'));
    $s->post('/wolves/{wid}/memory', c(WolfController::class, 'memoryUpsert'));
});

// ── Commons ─────────────────────────────────────────────────
$router->group('/v1/commons', function ($c) {
    $c->get('/files', c(QuiddityController::class, 'listFiles'));
    $c->post('/files/sync', c(QuiddityController::class, 'sync'));
    $c->get('/files/{id}/chunks', c(QuiddityController::class, 'chunks'));
    $c->get('/search', c(QuiddityController::class, 'search'));
    $c->get('/folders', c(FolderController::class, 'list'));
    $c->put('/folders', c(FolderController::class, 'upsert'));
    $c->delete('/folders/{name}', c(FolderController::class, 'delete'));
    $c->post('/folders/reclassify', c(FolderController::class, 'reclassify'));
    $c->post('/folders/rebuild-centroids', c(FolderController::class, 'rebuildCentroids'));
    $c->get('/sites', c(ConnectedSitesController::class, 'list'));
    $c->get('/sites/{slug}', c(ConnectedSitesController::class, 'get'));
    $c->post('/sites', c(ConnectedSitesController::class, 'upsert'));
    $c->post('/ingest/batch', c(IngestionController::class, 'batch'));
});

// ── Director ────────────────────────────────────────────────
$router->group('/v1/director', function ($d) {
    $d->post('/plans', c(DirectorController::class, 'createPlan'));
    $d->post('/directives', c(DirectorController::class, 'issueDirective'));
    $d->get('/status', c(DirectorController::class, 'globalStatus'));
});

// ── Registry ────────────────────────────────────────────────
$router->group('/v1/registry', function ($r) {
    $r->get('/budget', c(SoulController::class, 'getBudget'));
    $r->post('/privileged-actions', c(SoulController::class, 'requestPrivileged'));
    $r->get('/privileged-actions/{id}', c(SoulController::class, 'getPrivileged'));
    $r->post('/privileged-actions/{id}/confirm', c(SoulController::class, 'confirmPrivileged'));

    // User-Agent Assignments
    $r->get('/assignments', c(AssignmentController::class, 'listByUser'));
    $r->put('/assignments', c(AssignmentController::class, 'upsert'));

    // Agent Catalogue & Roster
    $r->get('/agents', c(AgentCatalogueController::class, 'listAgents'));
    $r->get('/agents/{slug}', c(AgentCatalogueController::class, 'getAgent'));
    $r->get('/catalogue', c(AgentCatalogueController::class, 'listAgents'));

    // Head / Dynamic SOUL Component CRUD
    $r->get('/heads', c(AgentCatalogueController::class, 'listHeads'));
    $r->get('/heads/{id}', c(AgentCatalogueController::class, 'getHead'));
    $r->post('/heads', c(AgentCatalogueController::class, 'createHead'));
    $r->put('/heads/{id}', c(AgentCatalogueController::class, 'updateHead'));
    $r->delete('/heads/{id}', c(AgentCatalogueController::class, 'deleteHead'));
    $r->get('/agents/{slug}/heads', c(AgentCatalogueController::class, 'listHeads'));

    // Model Profiles
    $r->get('/models', c(AgentCatalogueController::class, 'listModels'));
});

// Dispatch current request
$request = Request::fromGlobals();
$router->dispatch($request);
