<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\UidProcessor;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Build DI container
$builder = new ContainerBuilder();
$builder->addDefinitions([
    // MariaDB PDO connections — one per database
    PDO::class => function (): PDO {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $user = $_ENV['DB_USER'] ?? 'zeon7_user';
        $pass = $_ENV['DB_PASS'] ?? '';
        return new PDO(
            "mysql:host={$host};charset=utf8mb4",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    },

    'quiddity_commons' => function (PDO $pdo): PDO {
        $pdo->exec('USE quiddity_commons');
        return $pdo;
    },

    'agent_registry' => function (PDO $pdo): PDO {
        $pdo->exec('USE agent_registry');
        return $pdo;
    },

    Logger::class => function (): Logger {
        $logger = new Logger('council-library');
        $logger->pushHandler(new StreamHandler(
            $_ENV['LOG_PATH'] ?? __DIR__ . '/../logs/api.log',
            ($_ENV['LOG_LEVEL'] ?? 'DEBUG') === 'DEBUG' ? Logger::DEBUG : Logger::INFO
        ));
        $logger->pushProcessor(new UidProcessor());
        return $logger;
    },

    \CouncilLibrary\Service\VectorSearch::class => function (PDO $pdo): \CouncilLibrary\Service\VectorSearch {
        $pdo->exec('USE quiddity_commons');
        return new \CouncilLibrary\Service\VectorSearch($pdo);
    },

    \CouncilLibrary\Service\EmbeddingClient::class => function (): \CouncilLibrary\Service\EmbeddingClient {
        $url = $_ENV['EMBEDDING_URL'] ?? 'http://127.0.0.1:8900';
        return new \CouncilLibrary\Service\EmbeddingClient($url);
    },
]);

$container = $builder->build();
AppFactory::setContainer($container);
return $container;
