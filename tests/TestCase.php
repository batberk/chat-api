<?php

declare(strict_types=1);

namespace Tests;

use App\Database\Database;
use App\Middleware\ErrorHandler;
use DI\ContainerBuilder;
use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

abstract class TestCase extends BaseTestCase
{
    protected App $app;
    protected ContainerInterface $container;
    protected PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        Database::resetInstance();

        $this->db = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->initializeSchema();

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions([
            PDO::class => $this->db,
            ResponseFactoryInterface::class => new ResponseFactory(),
        ]);
        (require __DIR__ . '/../config/container.php')($containerBuilder);
        $containerBuilder->addDefinitions([PDO::class => $this->db]);
        $this->container = $containerBuilder->build();

        AppFactory::setContainer($this->container);
        $this->app = AppFactory::create();
        $this->app->addBodyParsingMiddleware();

        $errorMiddleware = $this->app->addErrorMiddleware(false, false, false);
        $errorMiddleware->setDefaultErrorHandler(new ErrorHandler(new ResponseFactory(), true));

        (require __DIR__ . '/../config/routes.php')($this->app);
    }

    private function initializeSchema(): void
    {
        $this->db->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                token TEXT NOT NULL UNIQUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->db->exec('
            CREATE TABLE groups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                created_by INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id)
            )
        ');

        $this->db->exec('
            CREATE TABLE group_members (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                group_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (group_id) REFERENCES groups(id),
                FOREIGN KEY (user_id) REFERENCES users(id),
                UNIQUE(group_id, user_id)
            )
        ');

        $this->db->exec('
            CREATE TABLE messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                group_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                content TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (group_id) REFERENCES groups(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ');
    }

    protected function createRequest(
        string $method,
        string $path,
        array $body = [],
        array $headers = []
    ): \Psr\Http\Message\ServerRequestInterface {
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest($method, $path);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if (!empty($body)) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request = $request->withParsedBody($body);
        }

        return $request;
    }

    protected function createUser(string $username = 'testuser'): array
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->db->prepare('INSERT INTO users (username, token) VALUES (?, ?)');
        $stmt->execute([$username, $token]);

        return [
            'id' => (int) $this->db->lastInsertId(),
            'username' => $username,
            'token' => $token,
        ];
    }

    protected function createGroup(string $name, int $createdBy): array
    {
        $stmt = $this->db->prepare('INSERT INTO groups (name, created_by) VALUES (?, ?)');
        $stmt->execute([$name, $createdBy]);
        $groupId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare('INSERT INTO group_members (group_id, user_id) VALUES (?, ?)');
        $stmt->execute([$groupId, $createdBy]);

        return [
            'id' => $groupId,
            'name' => $name,
            'created_by' => $createdBy,
        ];
    }

    protected function addGroupMember(int $groupId, int $userId): void
    {
        $stmt = $this->db->prepare('INSERT OR IGNORE INTO group_members (group_id, user_id) VALUES (?, ?)');
        $stmt->execute([$groupId, $userId]);
    }

    protected function getResponseData(\Psr\Http\Message\ResponseInterface $response): array
    {
        $response->getBody()->rewind();
        return json_decode($response->getBody()->getContents(), true);
    }
}
