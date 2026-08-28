<?php
declare(strict_types=1);

require_once __DIR__ . '/Core/Autoloader.php';
\CouncilLibrary\Core\Autoloader::register();

// Load .env if present
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim(trim($v), "\"'");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
            putenv("{$k}={$v}");
        }
    }
}

// Database Connection
$host = $_ENV['DB_HOST'] ?? 'localhost';
$user = $_ENV['DB_USER'] ?? 'zeon7';
$pass = $_ENV['DB_PASS'] ?? '';
$pdo = new PDO(
    "mysql:host={$host};dbname=agent_registry;charset=utf8mb4",
    $user, $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

// Native Logger
$logger = new \CouncilLibrary\Core\Logger('council-library', $_ENV['LOG_PATH'] ?? dirname(__DIR__) . '/logs/api.log');

// Router Setup
$router = new \CouncilLibrary\Core\Router($pdo, $logger);

return [
    'pdo'    => $pdo,
    'logger' => $logger,
    'router' => $router
];
