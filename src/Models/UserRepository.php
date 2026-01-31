<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class UserRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function create(string $username): array
    {
        $token = bin2hex(random_bytes(32)); // 256-bit cryptographically secure token

        $stmt = $this->db->prepare('
            INSERT INTO users (username, token) VALUES (:username, :token)
        ');
        $stmt->execute(['username' => $username, 'token' => $token]);

        return [
            'id' => (int) $this->db->lastInsertId(),
            'username' => $username,
            'token' => $token,
        ];
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->db->prepare('SELECT id, username, token, created_at FROM users WHERE token = :token');
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch();

        if ($user === false) {
            return null;
        }

        $user['id'] = (int) $user['id'];
        return $user;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, username, token, created_at FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        if ($user === false) {
            return null;
        }

        $user['id'] = (int) $user['id'];
        return $user;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT id, username, token, created_at FROM users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user === false) {
            return null;
        }

        $user['id'] = (int) $user['id'];
        return $user;
    }
}
