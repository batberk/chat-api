<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\UserService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController
{
    use JsonResponse;

    public function __construct(private UserService $userService)
    {
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $username = $data['username'] ?? '';

        $user = $this->userService->createUser($username);

        return $this->created($response, [
            'message' => 'User created successfully',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'token' => $user['token'],
            ],
        ]);
    }

    public function me(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');

        return $this->success($response, [
            'user' => $this->userService->getUserInfo($user),
        ]);
    }
}
