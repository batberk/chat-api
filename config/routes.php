<?php

declare(strict_types=1);

use App\Controllers\GroupController;
use App\Controllers\MessageController;
use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app): void {
    $app->post('/users', [UserController::class, 'create']);

    $app->group('', function (RouteCollectorProxy $group) {
        $group->get('/users/me', [UserController::class, 'me']);

        $group->get('/groups', [GroupController::class, 'index']);
        $group->post('/groups', [GroupController::class, 'create']);
        $group->get('/groups/{id}', [GroupController::class, 'show']);
        $group->post('/groups/{id}/join', [GroupController::class, 'join']);
        $group->get('/groups/{id}/members', [GroupController::class, 'members']);

        $group->get('/groups/{id}/messages', [MessageController::class, 'index']);
        $group->post('/groups/{id}/messages', [MessageController::class, 'create']);
    })->add(AuthMiddleware::class);
};
