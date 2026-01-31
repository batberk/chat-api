<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface;

trait JsonResponse
{
    protected function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }

    protected function success(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        return $this->json($response, $data, $status);
    }

    protected function created(ResponseInterface $response, array $data): ResponseInterface
    {
        return $this->json($response, $data, 201);
    }
}
