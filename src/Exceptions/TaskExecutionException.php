<?php

namespace Govel\Govel\Exceptions;

use RuntimeException;

class TaskExecutionException extends RuntimeException
{
    public static function processError(string $name, string $stderr, int $exitCode): self
    {
        return new self(
            "Go task [{$name}] failed with exit code {$exitCode}: {$stderr}"
        );
    }

    public static function timeout(string $name, int $timeout): self
    {
        return new self(
            "Go task [{$name}] exceeded the timeout of {$timeout} seconds."
        );
    }
}
