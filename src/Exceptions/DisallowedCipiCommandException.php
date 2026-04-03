<?php

namespace CipiApi\Exceptions;

use RuntimeException;

/**
 * Thrown when code queues a Cipi CLI command that is not listed in {@see \CipiApi\Services\CipiCliService::ALLOWED_COMMANDS}.
 * Prevents jobs that would always fail at execution time.
 */
class DisallowedCipiCommandException extends RuntimeException
{
    public function __construct(string $command)
    {
        parent::__construct(
            'Cipi CLI command is not permitted (add its prefix to CipiCliService::ALLOWED_COMMANDS): ' . $command
        );
    }
}
