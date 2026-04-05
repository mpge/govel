<?php

namespace Mpge\Govel\Contracts;

use Mpge\Govel\DTO\Result;

interface Driver
{
    /**
     * Execute a Go task synchronously and return the result.
     */
    public function run(Task $task, array $payload = []): Result;

    /**
     * Execute a Go task asynchronously (fire-and-forget).
     */
    public function dispatch(Task $task, array $payload = []): void;
}
