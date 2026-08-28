<?php
declare(strict_types=1);

$baseUrl = 'http://127.0.0.1:8080';
$validToken = 'dev-key-change-in-production';
$invalidToken = 'fake-token-12345';

function req(string $method, string $path, ?array $body = null, ?string $token = null, array $headers = []): array {
    global $baseUrl;
    $ch = curl_init("{$baseUrl}{$path}");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $hdrs = [];
    if ($token !== null) {
        $hdrs[] = "Authorization: Bearer {$token}";
    }
    if ($body !== null) {
        $hdrs[] = "Content-Type: application/json";
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    foreach ($headers as $k => $v) {
        $hdrs[] = "{$k}: {$v}";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrs);
    
    $res = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $status,
        'body'   => json_decode((string)$res, true) ?: (string)$res
    ];
}

echo "=== COUNCIL API CONTRACT & SECURITY TEST SUITE ===\n\n";

$tests = 0;
$passed = 0;

function assertTest(string $name, bool $condition, string $detail = '') {
    global $tests, $passed;
    $tests++;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}\n";
    } else {
        echo "  [FAIL] {$name} - {$detail}\n";
    }
}

// 1. Health checks (public, no auth)
$r = req('GET', '/v1/healthz');
assertTest('GET /v1/healthz is public (200)', $r['status'] === 200, "got {$r['status']}");

$r = req('GET', '/v1/readyz');
assertTest('GET /v1/readyz is public (200)', $r['status'] === 200, "got {$r['status']}");

// 2. Auth protection
$r = req('GET', '/v1/sanctum/memory');
assertTest('Protected route rejects missing token (401)', $r['status'] === 401, "got {$r['status']}");

$r = req('GET', '/v1/sanctum/memory', null, $invalidToken);
assertTest('Protected route rejects invalid token (401)', $r['status'] === 401, "got {$r['status']}");

$r = req('GET', '/v1/sanctum/memory', null, $validToken, ['X-Agent-ID' => 'zeon7']);
assertTest('Protected route accepts valid token (200)', $r['status'] === 200, "got {$r['status']}");

// 3. Route Parameter Tests
$r = req('GET', '/v1/commons/files/1/chunks', null, $validToken);
assertTest('GET /v1/commons/files/{id}/chunks succeeds (200)', $r['status'] === 200, "got {$r['status']}");

$r = req('POST', '/v1/commons/ingest/batch', ['files' => ['test.md']], $validToken);
assertTest('POST /v1/commons/ingest/batch route is registered (200)', $r['status'] === 200, "got {$r['status']}");

$r = req('GET', '/v1/sanctum/conversations', null, $validToken, ['X-Agent-ID' => 'zeon7']);
assertTest('GET /v1/sanctum/conversations succeeds (200)', $r['status'] === 200, "got {$r['status']}");

$r = req('GET', '/v1/registry/budget', null, $validToken);
assertTest('GET /v1/registry/budget succeeds (200)', $r['status'] === 200, "got {$r['status']}");

echo "\nSummary: {$passed} / {$tests} tests passed.\n";
if ($passed === $tests) {
    echo ">>> ALL API CONTRACT TESTS PASSED! <<<\n";
}
