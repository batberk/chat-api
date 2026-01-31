<?php

declare(strict_types=1);

namespace App\Exceptions;

class ConflictException extends HttpException
{
    public function __construct(string $message = 'Resource already exists')
    {
        parent::__construct($message, 409);
    }
}
