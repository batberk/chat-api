<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ForbiddenException;
use App\Exceptions\ValidationException;
use App\Models\MessageRepository;

class MessageService
{
    private const MAX_CONTENT_LENGTH = 5000;
    private const MAX_LIMIT = 100;

    public function __construct(
        private MessageRepository $messageRepository,
        private GroupService $groupService
    ) {
    }

    public function getMessages(
        int $groupId,
        int $userId,
        ?int $limit = null,
        ?int $offset = null,
        ?string $since = null,
        ?int $afterId = null
    ): array {
        $this->groupService->getGroup($groupId);
        $this->verifyMembership($groupId, $userId);

        $limit = $limit !== null ? min($limit, self::MAX_LIMIT) : null;
        $messages = $this->messageRepository->findByGroup($groupId, $limit, $offset, $since, $afterId);
        $total = $this->messageRepository->countByGroup($groupId);

        return [
            'messages' => $messages,
            'total' => $total,
            'group_id' => $groupId,
        ];
    }

    public function sendMessage(int $groupId, int $userId, string $content): array
    {
        $this->groupService->getGroup($groupId);
        $this->verifyMembership($groupId, $userId);

        $content = trim($content);
        $this->validateContent($content);

        return $this->messageRepository->create($groupId, $userId, $content);
    }

    private function verifyMembership(int $groupId, int $userId): void
    {
        if (!$this->groupService->isMember($groupId, $userId)) {
            throw new ForbiddenException('You must be a member of this group');
        }
    }

    private function validateContent(string $content): void
    {
        if (empty($content)) {
            throw new ValidationException('Message content is required');
        }

        if (strlen($content) > self::MAX_CONTENT_LENGTH) {
            throw new ValidationException(
                sprintf('Message content must be less than %d characters', self::MAX_CONTENT_LENGTH)
            );
        }
    }
}
