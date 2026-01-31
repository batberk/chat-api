<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class MessageRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function create(int $groupId, int $userId, string $content): array
    {
        $stmt = $this->db->prepare('
            INSERT INTO messages (group_id, user_id, content) VALUES (:group_id, :user_id, :content)
        ');
        $stmt->execute([
            'group_id' => $groupId,
            'user_id' => $userId,
            'content' => $content,
        ]);

        return $this->findById((int) $this->db->lastInsertId());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT m.id, m.group_id, m.user_id, m.content, m.created_at, u.username
            FROM messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $message = $stmt->fetch();

        if ($message === false) {
            return null;
        }

        return $this->formatMessage($message);
    }

    public function findByGroup(
        int $groupId,
        ?int $limit = null,
        ?int $offset = null,
        ?string $since = null,
        ?int $afterId = null
    ): array {
        $sql = '
            SELECT m.id, m.group_id, m.user_id, m.content, m.created_at, u.username
            FROM messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.group_id = :group_id
        ';
        $params = ['group_id' => $groupId];

        // Prefer after_id over since for polling: IDs are sequential and reliable,
        // while timestamps can have precision issues across different databases
        if ($afterId !== null) {
            $sql .= ' AND m.id > :after_id';
            $params['after_id'] = $afterId;
        } elseif ($since !== null) {
            $sql .= ' AND m.created_at > :since';
            $params['since'] = $since;
        }

        $sql .= ' ORDER BY m.id ASC';

        if ($limit !== null) {
            $sql .= ' LIMIT :limit';
            if ($offset !== null) {
                $sql .= ' OFFSET :offset';
            }
        }

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            if ($key === 'after_id') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        if ($limit !== null) {
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            if ($offset !== null) {
                $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
            }
        }

        $stmt->execute();
        $messages = $stmt->fetchAll();

        return array_map([$this, 'formatMessage'], $messages);
    }

    public function countByGroup(int $groupId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM messages WHERE group_id = :group_id');
        $stmt->execute(['group_id' => $groupId]);

        return (int) $stmt->fetchColumn();
    }

    private function formatMessage(array $message): array
    {
        return [
            'id' => (int) $message['id'],
            'group_id' => (int) $message['group_id'],
            'user_id' => (int) $message['user_id'],
            'username' => $message['username'],
            'content' => $message['content'],
            'created_at' => $message['created_at'],
        ];
    }
}
