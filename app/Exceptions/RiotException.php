<?php

namespace App\Exceptions;

use RuntimeException;

final class RiotException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false,
        public readonly int $retryAfter = 30, public readonly bool $throttled = false)
    {
        parent::__construct($message);
    }
}
