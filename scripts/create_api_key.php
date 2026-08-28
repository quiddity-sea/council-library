<?php
declare(strict_types=1);

if ($argc < 3) {
    echo "Usage: php create_api_key.php <owner_agent_slug> <key_name> [custom_raw_token]\n";
    exit(1);
}

$agent = $argv[1];
$name = $argv[2];
$rawToken = $argv[3] ?? bin2hex(random_bytes(32));

$prefix = substr($rawToken, 0, 8);
$hash = hash("sha256", $rawToken);

$host = getenv("DB_HOST") ?: "localhost";
$user = getenv("DB_USER") ?: "zeon7_user";
$pass = getenv("DB_PASSWORD") ?: (getenv("FOREVERBOX_DB_PASS") ?: "F0reverb0x#2o26sql");

try {
    $pdo = new PDO("mysql:host={$host};dbname=agent_registry;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO api_keys (key_prefix, key_hash, owner_agent_slug, name, scopes, created_at)
        VALUES (:prefix, :hash, :owner, :name, :scopes, NOW())
        ON DUPLICATE KEY UPDATE key_hash = VALUES(key_hash), owner_agent_slug = VALUES(owner_agent_slug), name = VALUES(name)
    ");

    $stmt->execute([
        "prefix" => $prefix,
        "hash" => $hash,
        "owner" => $agent,
        "name" => $name,
        "scopes" => json_encode(["*"])
    ]);

    echo "API Key Created/Updated Successfully!\n";
    echo "Owner: {$agent}\n";
    echo "Name:  {$name}\n";
    echo "Prefix: {$prefix}\n";
    echo "Raw Token (use as Bearer token): {$rawToken}\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
