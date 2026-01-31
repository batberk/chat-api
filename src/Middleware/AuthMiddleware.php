<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Models\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $request->getHeaderLine('X-User-Token');

        if (empty($token)) {
            return $this->unauthorizedResponse('Missing X-User-Token header');
        }

        $user = $this->userRepository->findByToken($token);

        if ($user === null) {
            return $this->unauthorizedResponse('Invalid token');
        }

        // Attach user to request so controllers can access via $request->getAttribute('user')
        $request = $request->withAttribute('user', $user);

        return $handler->handle($request);
    }

    private function unauthorizedResponse(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode(['error' => $message]));

        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }
}
