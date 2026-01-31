<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MessageService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MessageController
{
    use JsonResponse;

    public function __construct(private MessageService $messageService)
    {
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $groupId = (int) $args['id'];

        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? (int) $queryParams['limit'] : null;
        $offset = isset($queryParams['offset']) ? (int) $queryParams['offset'] : null;
        $since = $queryParams['since'] ?? null;
        $afterId = isset($queryParams['after_id']) ? (int) $queryParams['after_id'] : null;

        $result = $this->messageService->getMessages(
            $groupId,
            $user['id'],
            $limit,
            $offset,
            $since,
            $afterId
        );

        return $this->success($response, $result);
    }

    public function create(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $groupId = (int) $args['id'];
        $data = $request->getParsedBody() ?? [];
        $content = $data['content'] ?? '';

        $message = $this->messageService->sendMessage($groupId, $user['id'], $content);

        return $this->created($response, [
            'message' => 'Message sent successfully',
            'data' => $message,
        ]);
    }
}
