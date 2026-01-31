<?php

declare(strict_types=1);

use App\Controllers\GroupController;
use App\Controllers\MessageController;
use App\Controllers\UserController;
use App\Database\Database;
use App\Middleware\AuthMiddleware;
use App\Middleware\ErrorHandler;
use App\Models\GroupRepository;
use App\Models\MessageRepository;
use App\Models\UserRepository;
use App\Services\GroupService;
use App\Services\MessageService;
use App\Services\UserService;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Psr7\Factory\ResponseFactory;

return function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        PDO::class => function (ContainerInterface $c): PDO {
            $database = new Database();
            return $database->getConnection();
        },

        UserRepository::class => fn(ContainerInterface $c) => new UserRepository($c->get(PDO::class)),
        GroupRepository::class => fn(ContainerInterface $c) => new GroupRepository($c->get(PDO::class)),
        MessageRepository::class => fn(ContainerInterface $c) => new MessageRepository($c->get(PDO::class)),

        UserService::class => fn(ContainerInterface $c) => new UserService($c->get(UserRepository::class)),
        GroupService::class => fn(ContainerInterface $c) => new GroupService($c->get(GroupRepository::class)),
        MessageService::class => fn(ContainerInterface $c) => new MessageService(
            $c->get(MessageRepository::class),
            $c->get(GroupService::class)
        ),

        UserController::class => fn(ContainerInterface $c) => new UserController($c->get(UserService::class)),
        GroupController::class => fn(ContainerInterface $c) => new GroupController($c->get(GroupService::class)),
        MessageController::class => fn(ContainerInterface $c) => new MessageController($c->get(MessageService::class)),

        AuthMiddleware::class => fn(ContainerInterface $c) => new AuthMiddleware($c->get(UserRepository::class)),
        ResponseFactoryInterface::class => fn() => new ResponseFactory(),
        ErrorHandler::class => fn(ContainerInterface $c) => new ErrorHandler(
            $c->get(ResponseFactoryInterface::class),
            (bool) ($_ENV['APP_DEBUG'] ?? false)
        ),
    ]);
};
