<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ConflictException;
use App\Exceptions\ValidationException;
use App\Models\UserRepository;

class UserService
{
    private const MIN_USERNAME_LENGTH = 3;
    private const MAX_USERNAME_LENGTH = 50;
    private const USERNAME_PATTERN = '/^[a-zA-Z0-9_-]+$/';

    public function __construct(private UserRepository $userRepository)
    {
    }

    public function createUser(string $username): array
    {
        $username = trim($username);
        $this->validateUsername($username);

        $existing = $this->userRepository->findByUsername($username);
        if ($existing !== null) {
            throw new ConflictException('Username already exists');
        }

        return $this->userRepository->create($username);
    }

    public function getUserInfo(array $user): array
    {
        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'created_at' => $user['created_at'],
        ];
    }

    private function validateUsername(string $username): void
    {
        if (empty($username)) {
            throw new ValidationException('Username is required');
        }

        $length = strlen($username);
        if ($length < self::MIN_USERNAME_LENGTH || $length > self::MAX_USERNAME_LENGTH) {
            throw new ValidationException(
                sprintf('Username must be between %d and %d characters', self::MIN_USERNAME_LENGTH, self::MAX_USERNAME_LENGTH)
            );
        }

        if (!preg_match(self::USERNAME_PATTERN, $username)) {
            throw new ValidationException('Username can only contain letters, numbers, underscores and hyphens');
        }
    }
}
