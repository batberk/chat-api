<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\GroupService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class GroupController
{
    use JsonResponse;

    public function __construct(private GroupService $groupService)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $groups = $this->groupService->getAllGroups();

        return $this->success($response, ['groups' => $groups]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $groupId = (int) $args['id'];
        $group = $this->groupService->getGroupWithMembers($groupId);

        return $this->success($response, ['group' => $group]);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];
        $name = $data['name'] ?? '';

        $group = $this->groupService->createGroup($name, $user['id']);

        return $this->created($response, [
            'message' => 'Group created successfully',
            'group' => $group,
        ]);
    }

    public function join(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $groupId = (int) $args['id'];

        $result = $this->groupService->joinGroup($groupId, $user['id']);

        $message = $result['already_member']
            ? 'Already a member of this group'
            : 'Successfully joined the group';

        return $this->success($response, [
            'message' => $message,
            'group' => $result['group'],
        ]);
    }

    public function members(Request $request, Response $response, array $args): Response
    {
        $groupId = (int) $args['id'];
        $members = $this->groupService->getMembers($groupId);

        return $this->success($response, ['members' => $members]);
    }
}
