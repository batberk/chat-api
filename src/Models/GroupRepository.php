<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class GroupRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function create(string $name, int $createdBy): array
    {
        $stmt = $this->db->prepare('
            INSERT INTO groups (name, created_by) VALUES (:name, :created_by)
        ');
        $stmt->execute(['name' => $name, 'created_by' => $createdBy]);

        $groupId = (int) $this->db->lastInsertId();
        $this->addMember($groupId, $createdBy);

        return $this->findById($groupId);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT g.id, g.name, g.created_by, g.created_at, u.username as creator_name
            FROM groups g
            JOIN users u ON g.created_by = u.id
            WHERE g.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $group = $stmt->fetch();

        if ($group === false) {
            return null;
        }

        $group['id'] = (int) $group['id'];
        $group['created_by'] = (int) $group['created_by'];
        $group['member_count'] = $this->getMemberCount($group['id']);
        return $group;
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, created_by, created_at FROM groups WHERE name = :name');
        $stmt->execute(['name' => $name]);
        $group = $stmt->fetch();

        if ($group === false) {
            return null;
        }

        $group['id'] = (int) $group['id'];
        $group['created_by'] = (int) $group['created_by'];
        return $group;
    }

    public function findAll(): array
    {
        $stmt = $this->db->query('
            SELECT g.id, g.name, g.created_by, g.created_at, u.username as creator_name
            FROM groups g
            JOIN users u ON g.created_by = u.id
            ORDER BY g.created_at DESC
        ');
        $groups = $stmt->fetchAll();

        return array_map(function ($group) {
            $group['id'] = (int) $group['id'];
            $group['created_by'] = (int) $group['created_by'];
            $group['member_count'] = $this->getMemberCount($group['id']);
            return $group;
        }, $groups);
    }

    public function addMember(int $groupId, int $userId): bool
    {
        // INSERT OR IGNORE prevents duplicate membership errors due to UNIQUE constraint
        $stmt = $this->db->prepare('
            INSERT OR IGNORE INTO group_members (group_id, user_id) VALUES (:group_id, :user_id)
        ');
        $stmt->execute(['group_id' => $groupId, 'user_id' => $userId]);

        return $stmt->rowCount() > 0;
    }

    public function isMember(int $groupId, int $userId): bool
    {
        $stmt = $this->db->prepare('
            SELECT 1 FROM group_members WHERE group_id = :group_id AND user_id = :user_id
        ');
        $stmt->execute(['group_id' => $groupId, 'user_id' => $userId]);

        return $stmt->fetch() !== false;
    }

    public function getMemberCount(int $groupId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM group_members WHERE group_id = :group_id');
        $stmt->execute(['group_id' => $groupId]);

        return (int) $stmt->fetchColumn();
    }

    public function getMembers(int $groupId): array
    {
        $stmt = $this->db->prepare('
            SELECT u.id, u.username, gm.joined_at
            FROM group_members gm
            JOIN users u ON gm.user_id = u.id
            WHERE gm.group_id = :group_id
            ORDER BY gm.joined_at ASC
        ');
        $stmt->execute(['group_id' => $groupId]);
        $members = $stmt->fetchAll();

        return array_map(function ($member) {
            $member['id'] = (int) $member['id'];
            return $member;
        }, $members);
    }
}
