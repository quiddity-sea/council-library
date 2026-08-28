<?php
declare(strict_types=1);

namespace CouncilLibrary\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use Monolog\Logger;

class AgentCatalogueController
{
    public function __construct(
        private PDO $pdo,
        private Logger $logger
    ) {}

    /**
     * GET /v1/registry/agents
     * Returns full agent catalogue with associated head summary.
     */
    public function listAgents(Request $request, Response $response): Response
    {
        $this->ensureRegistry();

        $stmt = $this->pdo->query(
            "SELECT slug, display_name, role, description, db_name, allowed_scopes, rate_limit_rpm, status, created_at
             FROM agents
             WHERE status != 'decommissioned'
             ORDER BY role DESC, slug ASC"
        );
        $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Attach head summary for each agent from soul_components
        $headStmt = $this->pdo->query(
            "SELECT id, component_key, agent_slug, provider_filter, section_order, section_description
             FROM soul_components
             ORDER BY section_order ASC"
        );
        $allHeads = $headStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($agents as &$agent) {
            $agent['allowed_scopes'] = json_decode($agent['allowed_scopes'] ?? '["*"]', true);
            $agent['heads'] = array_values(array_filter($allHeads, function ($h) use ($agent) {
                return $h['agent_slug'] === null || $h['agent_slug'] === $agent['slug'];
            }));
        }
        unset($agent);

        return $this->json($response, [
            'success' => true,
            'count'   => count($agents),
            'agents'  => $agents
        ]);
    }

    /**
     * GET /v1/registry/agents/{slug}
     * Returns detail for a specific agent.
     */
    public function getAgent(Request $request, Response $response, array $args): Response
    {
        $this->ensureRegistry();
        $slug = strtolower(trim($args['slug'] ?? ''));

        $stmt = $this->pdo->prepare(
            "SELECT slug, display_name, role, description, db_name, allowed_scopes, rate_limit_rpm, status, created_at
             FROM agents
             WHERE slug = :slug"
        );
        $stmt->execute(['slug' => $slug]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$agent) {
            return $this->json($response, ['success' => false, 'error' => 'Agent not found'], 404);
        }

        $agent['allowed_scopes'] = json_decode($agent['allowed_scopes'] ?? '["*"]', true);

        // Fetch component heads for this agent (including shared)
        $headStmt = $this->pdo->prepare(
            "SELECT id, component_key, agent_slug, provider_filter, section_order, section_description, created_at, updated_at
             FROM soul_components
             WHERE agent_slug IS NULL OR agent_slug = :slug
             ORDER BY section_order ASC"
        );
        $headStmt->execute(['slug' => $slug]);
        $agent['heads'] = $headStmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->json($response, ['success' => true, 'agent' => $agent]);
    }

    /**
     * GET /v1/registry/heads
     * Lists dynamic SOUL components / heads with optional agent filter.
     */
    public function listHeads(Request $request, Response $response, array $args = []): Response
    {
        $this->ensureRegistry();
        $params = $request->getQueryParams();
        $agentFilter = $args['slug'] ?? $params['agent_slug'] ?? null;

        $sql = "SELECT id, component_key, agent_slug, provider_filter, section_order, section_description, created_at, updated_at
                FROM soul_components";
        $binds = [];

        if ($agentFilter !== null) {
            $sql .= " WHERE agent_slug = :agent OR agent_slug IS NULL";
            $binds['agent'] = strtolower(trim($agentFilter));
        }

        $sql .= " ORDER BY agent_slug ASC, section_order ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($binds);
        $heads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->json($response, [
            'success' => true,
            'count'   => count($heads),
            'heads'   => $heads
        ]);
    }

    /**
     * GET /v1/registry/heads/{id}
     * Returns full head / SOUL component including section_content.
     */
    public function getHead(Request $request, Response $response, array $args): Response
    {
        $this->ensureRegistry();
        $id = (int)($args['id'] ?? 0);

        $stmt = $this->pdo->prepare("SELECT * FROM soul_components WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $head = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$head) {
            return $this->json($response, ['success' => false, 'error' => 'Head component not found'], 404);
        }

        return $this->json($response, ['success' => true, 'head' => $head]);
    }

    /**
     * POST /v1/registry/heads
     * Create a new dynamic SOUL component / head.
     */
    public function createHead(Request $request, Response $response): Response
    {
        $this->ensureRegistry();
        $body = $request->getParsedBody() ?? json_decode((string)$request->getBody(), true) ?? [];

        $key         = trim($body['component_key'] ?? '');
        $agentSlug   = !empty($body['agent_slug']) ? strtolower(trim($body['agent_slug'])) : null;
        $provider    = !empty($body['provider_filter']) ? trim($body['provider_filter']) : null;
        $order       = (int)($body['section_order'] ?? 50);
        $description = trim($body['section_description'] ?? '');
        $content     = trim($body['section_content'] ?? '');

        if ($key === '' || $content === '') {
            return $this->json($response, ['success' => false, 'error' => 'component_key and section_content are required'], 400);
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO soul_components 
             (component_key, agent_slug, provider_filter, section_order, section_description, section_content)
             VALUES (:key, :agent, :provider, :order, :desc, :content)"
        );

        try {
            $stmt->execute([
                'key'      => $key,
                'agent'    => $agentSlug,
                'provider' => $provider,
                'order'    => $order,
                'desc'     => $description,
                'content'  => $content
            ]);
            $id = (int)$this->pdo->lastInsertId();

            $this->logger->info('head_component_created', ['id' => $id, 'key' => $key, 'agent' => $agentSlug]);

            return $this->json($response, [
                'success' => true,
                'id'      => $id,
                'message' => 'Head component created successfully'
            ], 201);
        } catch (\PDOException $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 409);
        }
    }

    /**
     * PUT /v1/registry/heads/{id}
     * Update an existing SOUL component / head.
     */
    public function updateHead(Request $request, Response $response, array $args): Response
    {
        $this->ensureRegistry();
        $id = (int)($args['id'] ?? 0);
        $body = $request->getParsedBody() ?? json_decode((string)$request->getBody(), true) ?? [];

        $stmt = $this->pdo->prepare("SELECT id FROM soul_components WHERE id = :id");
        $stmt->execute(['id' => $id]);
        if (!$stmt->fetch()) {
            return $this->json($response, ['success' => false, 'error' => 'Head component not found'], 404);
        }

        $fields = [];
        $binds = ['id' => $id];

        if (isset($body['component_key'])) {
            $fields[] = "component_key = :key";
            $binds['key'] = trim($body['component_key']);
        }
        if (array_key_exists('agent_slug', $body)) {
            $fields[] = "agent_slug = :agent";
            $binds['agent'] = !empty($body['agent_slug']) ? strtolower(trim($body['agent_slug'])) : null;
        }
        if (array_key_exists('provider_filter', $body)) {
            $fields[] = "provider_filter = :provider";
            $binds['provider'] = !empty($body['provider_filter']) ? trim($body['provider_filter']) : null;
        }
        if (isset($body['section_order'])) {
            $fields[] = "section_order = :order";
            $binds['order'] = (int)$body['section_order'];
        }
        if (isset($body['section_description'])) {
            $fields[] = "section_description = :desc";
            $binds['desc'] = trim($body['section_description']);
        }
        if (isset($body['section_content'])) {
            $fields[] = "section_content = :content";
            $binds['content'] = trim($body['section_content']);
        }

        if (empty($fields)) {
            return $this->json($response, ['success' => false, 'error' => 'No fields provided for update'], 400);
        }

        $sql = "UPDATE soul_components SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :id";
        $this->pdo->prepare($sql)->execute($binds);

        $this->logger->info('head_component_updated', ['id' => $id]);

        return $this->json($response, ['success' => true, 'message' => 'Head component updated successfully']);
    }

    /**
     * DELETE /v1/registry/heads/{id}
     * Remove a SOUL component / head.
     */
    public function deleteHead(Request $request, Response $response, array $args): Response
    {
        $this->ensureRegistry();
        $id = (int)($args['id'] ?? 0);

        $stmt = $this->pdo->prepare("DELETE FROM soul_components WHERE id = :id");
        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() === 0) {
            return $this->json($response, ['success' => false, 'error' => 'Head component not found'], 404);
        }

        $this->logger->info('head_component_deleted', ['id' => $id]);

        return $this->json($response, ['success' => true, 'message' => 'Head component deleted successfully']);
    }

    /**
     * GET /v1/registry/models
     * Lists cognitive router model profiles and tier assignments.
     */
    public function listModels(Request $request, Response $response): Response
    {
        $routerYamlPath = __DIR__ . '/../../router/router.yaml';
        $profiles = [
            'layer_1_intuitive_reflex' => [
                'name'        => 'Layer 1: Intuitive Reflex',
                'provider'    => 'ollama',
                'model'       => 'Zeon7-Gemma:64k',
                'description' => 'Fast local edge model for immediate conversational response',
                'local'       => true,
            ],
            'layer_2_analytical_engine' => [
                'name'        => 'Layer 2: Analytical Engine',
                'provider'    => 'openrouter',
                'model'       => 'qwen/qwen3-32b:free',
                'description' => 'Cloud reasoning and coding engine',
                'local'       => false,
            ],
            'layer_3_deep_architect' => [
                'name'        => 'Layer 3: Deep Architect',
                'provider'    => 'openrouter',
                'model'       => 'deepseek/deepseek-v4-pro',
                'description' => 'Deep planning and architecture model',
                'local'       => false,
            ],
        ];

        return $this->json($response, [
            'success'  => true,
            'profiles' => $profiles,
            'local_endpoint' => $_ENV['LOCAL_MODEL_URL'] ?? 'http://localhost:11434'
        ]);
    }

    private function ensureRegistry(): void
    {
        $this->pdo->exec('USE agent_registry');
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}

