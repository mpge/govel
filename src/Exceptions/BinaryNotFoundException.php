<?php

namespace Govel\Govel\Exceptions;

use RuntimeException;

class BinaryNotFoundException extends RuntimeException
{
    public static function forTask(string $name, string $path): self
    {
        return new self("Go binary not found for task [{$name}] at path: {$path}");
    }
}
