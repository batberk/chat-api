<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\GroupRepository;

class GroupService
{
    private const MIN_NAME_LENGTH = 3;
    private const MAX_NAME_LENGTH = 100;

    public function __construct(private GroupRepository $groupRepository)
    {
    }

    public function getAllGroups(): array
    {
        return $this->groupRepository->findAll();
    }

    public function getGroup(int $groupId): array
    {
        $group = $this->groupRepository->findById($groupId);
        if ($group === null) {
            throw new NotFoundException('Group not found');
        }
        return $group;
    }

    public function getGroupWithMembers(int $groupId): array
    {
        $group = $this->getGroup($groupId);
        $group['members'] = $this->groupRepository->getMembers($groupId);
        return $group;
    }

    public function createGroup(string $name, int $userId): array
    {
        $name = trim($name);
        $this->validateGroupName($name);

        $existing = $this->groupRepository->findByName($name);
        if ($existing !== null) {
            throw new ConflictException('Group name already exists');
        }

        return $this->groupRepository->create($name, $userId);
    }

    public function joinGroup(int $groupId, int $userId): array
    {
        $group = $this->getGroup($groupId);

        if ($this->groupRepository->isMember($groupId, $userId)) {
            return ['already_member' => true, 'group' => $group];
        }

        $this->groupRepository->addMember($groupId, $userId);
        return ['already_member' => false, 'group' => $this->getGroup($groupId)];
    }

    public function getMembers(int $groupId): array
    {
        $this->getGroup($groupId);
        return $this->groupRepository->getMembers($groupId);
    }

    public function isMember(int $groupId, int $userId): bool
    {
        return $this->groupRepository->isMember($groupId, $userId);
    }

    private function validateGroupName(string $name): void
    {
        if (empty($name)) {
            throw new ValidationException('Group name is required');
        }

        $length = strlen($name);
        if ($length < self::MIN_NAME_LENGTH || $length > self::MAX_NAME_LENGTH) {
            throw new ValidationException(
                sprintf('Group name must be between %d and %d characters', self::MIN_NAME_LENGTH, self::MAX_NAME_LENGTH)
            );
        }
    }
}
