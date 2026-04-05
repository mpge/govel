<?php

namespace Mpge\Govel\Drivers;

use Mpge\Govel\Contracts\Driver;
use Mpge\Govel\Contracts\Task;
use Mpge\Govel\DTO\Result;
use Mpge\Govel\Exceptions\TaskExecutionException;

/**
 * Communicates with a persistent Go gRPC server over HTTP/JSON.
 *
 * The Go server exposes an HTTP bridge alongside its gRPC service,
 * so PHP can communicate without requiring the grpc PECL extension.
 * This gives the same benefits as gRPC (persistent process, zero
 * startup overhead, connection pooling) with no PHP extensions.
 */
class GrpcDriver implements Driver
{
    public function __construct(
        protected string $host,
        protected int $port,
        protected int $timeout,
        protected bool $tls = false,
    ) {}

    public function run(Task $task, array $payload = []): Result
    {
        $start = hrtime(true);

        try {
            $response = $this->request($task->name(), $payload);
            $duration = (hrtime(true) - $start) / 1e6;

            return Result::fromOutput($response, $duration);
        } catch (\Throwable $e) {
            $duration = (hrtime(true) - $start) / 1e6;

            return Result::failure($e->getMessage(), $duration);
        }
    }

    public function dispatch(Task $task, array $payload = []): void
    {
        try {
            $this->request($task->name(), $payload, async: true);
        } catch (\Throwable $e) {
            $this->log("Govel gRPC async task [{$task->name()}] failed: {$e->getMessage()}");
        }
    }

    protected function request(string $taskName, array $payload, bool $async = false): string
    {
        $scheme = $this->tls ? 'https' : 'http';
        $url = "{$scheme}://{$this->host}:{$this->port}/govel/execute";

        $body = json_encode([
            'task' => $taskName,
            'payload' => $payload,
            'async' => $async,
        ], JSON_THROW_ON_ERROR);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $body,
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => $this->tls,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw TaskExecutionException::processError(
                $taskName,
                "Failed to connect to Govel gRPC server at {$this->host}:{$this->port}",
                1,
            );
        }

        // Check HTTP status from response headers
        $statusCode = $this->parseStatusCode($http_response_header ?? []);

        if ($statusCode >= 400) {
            $decoded = json_decode($response, true);
            $error = $decoded['error'] ?? "HTTP {$statusCode}";

            throw TaskExecutionException::processError($taskName, $error, $statusCode);
        }

        if ($async) {
            return '{"status":"dispatched"}';
        }

        return $response;
    }

    protected function parseStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/[\d.]+ (\d{3})/', $header, $matches)) {
                return (int) $matches[1];
            }
        }

        return 200;
    }

    protected function log(string $message): void
    {
        if (class_exists(\Illuminate\Support\Facades\Log::class)) {
            try {
                \Illuminate\Support\Facades\Log::warning($message);

                return;
            } catch (\Throwable) {
                // Facade not booted
            }
        }

        error_log($message);
    }
}
