<?php
declare(strict_types=1);

namespace CouncilLibrary\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ConnectedSitesController
{
    public function __construct(private \PDO $pdo, private \Monolog\Logger $logger) {}

    public function list(Request $request, Response $response): Response
    {
        $this->pdo->exec('USE quiddity_commons');
        $stmt = $this->pdo->query(
            'SELECT id, slug, domain, title, description, purpose,
                    main_vectors, filter_tags, creator, updated_by,
                    created_at, updated_at, is_active
             FROM connected_sites
             ORDER BY updated_at DESC'
        );
        $sites = $stmt->fetchAll();

        // Decode JSON fields
        foreach ($sites as &$s) {
            $s['main_vectors'] = json_decode($s['main_vectors'] ?? '[]', true) ?: [];
            $s['filter_tags']  = json_decode($s['filter_tags'] ?? '[]', true) ?: [];
        }

        return $this->json($response, ['success' => true, 'sites' => $sites]);
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $this->pdo->exec('USE quiddity_commons');
        $stmt = $this->pdo->prepare(
            'SELECT id, slug, domain, title, description, purpose,
                    main_vectors, filter_tags, creator, updated_by,
                    created_at, updated_at, is_active
             FROM connected_sites WHERE slug = :slug'
        );
        $stmt->execute(['slug' => $args['slug']]);
        $site = $stmt->fetch();

        if (!$site) {
            return $this->json($response, ['success' => false, 'error' => 'Site not found'], 404);
        }

        $site['main_vectors'] = json_decode($site['main_vectors'] ?? '[]', true) ?: [];
        $site['filter_tags']  = json_decode($site['filter_tags'] ?? '[]', true) ?: [];

        return $this->json($response, ['success' => true, 'site' => $site]);
    }

    public function upsert(Request $request, Response $response): Response
    {
        $this->pdo->exec('USE quiddity_commons');
        $agent = $request->getAttribute('agent_slug') ?? 'system';
        $body  = $request->getParsedBody();

        $slug        = trim($body['slug'] ?? '');
        $domain      = trim($body['domain'] ?? '');
        $title       = trim($body['title'] ?? '');
        $description = trim($body['description'] ?? '');
        $purpose     = trim($body['purpose'] ?? '');

        if (!$slug || !$domain || !$title || !$description) {
            return $this->json($response, ['success' => false, 'error' => 'slug, domain, title, description required'], 400);
        }

        $mainVectors = json_encode($body['main_vectors'] ?? []);
        $filterTags  = json_encode($body['filter_tags'] ?? []);
        $webRoot     = trim($body['web_root_path'] ?? "/var/www/{$slug}");
        $symlink     = trim($body['symlink_path'] ?? "/foreverbox_data/connected_sites/{$slug}");

        $stmt = $this->pdo->prepare(
            'INSERT INTO connected_sites (slug, domain, title, description, purpose, main_vectors, filter_tags, creator, updated_by, web_root_path, symlink_path, is_active)
             VALUES (:slug, :domain, :title, :desc, :purpose, :vecs, :tags, :creator, :updater, :web, :sym, 1)
             ON DUPLICATE KEY UPDATE
                 domain=VALUES(domain), title=VALUES(title), description=VALUES(description),
                 purpose=VALUES(purpose), main_vectors=VALUES(main_vectors), filter_tags=VALUES(filter_tags),
                 updated_by=VALUES(updated_by)'
        );
        $stmt->execute([
            'slug'    => $slug,
            'domain'  => $domain,
            'title'   => $title,
            'desc'    => $description,
            'purpose' => $purpose,
            'vecs'    => $mainVectors,
            'tags'    => $filterTags,
            'creator' => $agent,
            'updater' => $agent,
            'web'     => $webRoot,
            'sym'     => $symlink,
        ]);

        $this->logger->info('connected_site_upserted', ['slug' => $slug, 'by' => $agent]);

        return $this->json($response, ['success' => true, 'slug' => $slug]);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
